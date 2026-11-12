<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\SimProviderComparison;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SimComparisonController extends Controller
{
    public function index(Request $request)
    {
        $comparisons = SimProviderComparison::with(['admin:id,name', 'operator:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $operators = Operator::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/SimComparison/Index', [
            'comparisons' => $comparisons,
            'operators' => $operators,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'operator_ids' => ['required', 'array', 'min:1'],
            'operator_ids.*' => ['exists:operators,id'],
            'reconciliation_month' => ['required', 'date_format:Y-m'],
            'report_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $operators = Operator::whereIn('id', $request->operator_ids)->get();
        [$year, $month] = explode('-', $request->reconciliation_month);

        $file = $request->file('report_file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->storeAs('temp', uniqid().'_'.$fileName);
        $absolutePath = Storage::path($filePath);

        try {
            $extension = strtolower($file->getClientOriginalExtension());
            $rows = [];

            if (in_array($extension, ['csv', 'txt'])) {
                $rawRows = array_map('str_getcsv', file($absolutePath));
                foreach ($rawRows as $index => $r) {
                    $rowMap = [];
                    foreach ($r as $colIdx => $val) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
                        $rowMap[$colLetter] = trim((string) $val);
                    }
                    $rows[$index + 1] = $rowMap;
                }
            } else {
                $spreadsheet = IOFactory::load($absolutePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);
            }

            if (empty($rows)) {
                throw ValidationException::withMessages(['report_file' => 'The uploaded file is empty.']);
            }

            // Extract header (row 1)
            $headerRow = array_values($rows[1]);
            $normalizedHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);

            $mandatoryHeaders = ['provider', 'mobile', 'serial', 'country'];
            $missingHeaders = [];

            foreach ($mandatoryHeaders as $mandatory) {
                if (! in_array($mandatory, $normalizedHeaders)) {
                    $missingHeaders[] = ucfirst($mandatory);
                }
            }

            if (! empty($missingHeaders)) {
                Storage::delete($filePath);
                throw ValidationException::withMessages([
                    'report_file' => 'The uploaded provider report is missing mandatory header(s): '.implode(', ', $missingHeaders).'. Required headers: Provider, Mobile, Serial, Country.',
                ]);
            }

            // Map header column indices
            $headerMap = [];
            foreach ($rows[1] as $colKey => $colVal) {
                $clean = strtolower(trim((string) $colVal));
                if (in_array($clean, $mandatoryHeaders)) {
                    $headerMap[$clean] = $colKey;
                }
            }

            // Fetch internal connection records (successful transactions for these operators and month)
            $internalTransactions = Transaction::with(['user:id,name,shop_name,email', 'operator:id,name'])
                ->whereIn('operator_id', $request->operator_ids)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('status', 'success')
                ->get();

            // Group internal records by normalized mobile number (last 10 digits, allowing multiple recharges per number)
            $internalByMobile = [];
            foreach ($internalTransactions as $txn) {
                $normMobile = $this->normalizeMobileNumber($txn->mobile_number);
                $internalByMobile[$normMobile][] = [
                    'id' => $txn->id,
                    'mobile' => $txn->mobile_number,
                    'serial' => $txn->receipt_number ?? '-',
                    'provider' => $txn->operator?->name ?? 'Unknown',
                    'country' => $txn->country?->name ?? 'UK',
                    'retailer' => $txn->user ? [
                        'id' => $txn->user->id,
                        'name' => $txn->user->name,
                        'shop_name' => $txn->user->shop_name,
                        'email' => $txn->user->email,
                    ] : null,
                    'created_at' => $txn->created_at->format('d M Y H:i'),
                ];
            }

            // Parse provider data rows and group by normalized mobile number (last 10 digits, allowing multiple recharges per number)
            $providerByMobile = [];
            $totalProviderRowsParsed = 0;

            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i] ?? null;
                if (! $row) {
                    continue;
                }

                $mobile = trim((string) ($row[$headerMap['mobile']] ?? ''));
                if ($mobile === '') {
                    continue;
                }

                $provName = trim((string) ($row[$headerMap['provider']] ?? ''));
                $provNameLower = strtolower($provName);

                $matchesOperator = false;
                foreach ($operators as $op) {
                    if ($this->providersMatch($provNameLower, strtolower(trim($op->name)))) {
                        $matchesOperator = true;
                        break;
                    }
                }

                if (! $matchesOperator) {
                    continue; // Skip rows from other providers in the file
                }

                $totalProviderRowsParsed++;
                $normProvMobile = $this->normalizeMobileNumber($mobile);

                $providerByMobile[$normProvMobile][] = [
                    'provider' => $provName,
                    'mobile' => $mobile,
                    'serial' => trim((string) ($row[$headerMap['serial']] ?? '')),
                    'country' => trim((string) ($row[$headerMap['country']] ?? '')),
                ];
            }

            Storage::delete($filePath);

            if ($totalProviderRowsParsed === 0) {
                throw ValidationException::withMessages(['report_file' => 'No matching provider records found in the uploaded file for the selected operators.']);
            }

            $matched = [];
            $missingFromProvider = [];
            $additionalInProvider = [];

            $allMobiles = array_unique(array_merge(array_keys($internalByMobile), array_keys($providerByMobile)));

            foreach ($allMobiles as $normMobile) {
                $pList = $providerByMobile[$normMobile] ?? [];
                $iList = $internalByMobile[$normMobile] ?? [];

                $pCount = count($pList);
                $iCount = count($iList);

                $minCount = min($pCount, $iCount);

                // Match up to minCount
                for ($idx = 0; $idx < $minCount; $idx++) {
                    $pRec = $pList[$idx];
                    $iRec = $iList[$idx];
                    $matched[] = [
                        'mobile' => $pRec['mobile'],
                        'serial' => $pRec['serial'] !== '' ? $pRec['serial'] : $iRec['serial'],
                        'provider' => $pRec['provider'],
                        'country' => $pRec['country'],
                        'retailer' => $iRec['retailer'],
                        'internal_date' => $iRec['created_at'],
                    ];
                }

                // If provider has more recharges than internal records
                if ($pCount > $iCount) {
                    for ($idx = $minCount; $idx < $pCount; $idx++) {
                        $additionalInProvider[] = $pList[$idx];
                    }
                }

                // If internal records have more recharges than provider report (Provider Missed)
                if ($iCount > $pCount) {
                    for ($idx = $minCount; $idx < $iCount; $idx++) {
                        $missingFromProvider[] = $iList[$idx];
                    }
                }
            }

            $totalInternalCount = 0;
            foreach ($internalByMobile as $iList) {
                $totalInternalCount += count($iList);
            }

            $comparison = SimProviderComparison::create([
                'admin_id' => Auth::id(),
                'operator_id' => $request->operator_ids[0],
                'operator_ids' => $request->operator_ids,
                'reconciliation_month' => $request->reconciliation_month,
                'file_name' => $fileName,
                'provider_name' => $operators->pluck('name')->implode(', '),
                'total_internal' => $totalInternalCount,
                'total_provider' => $totalProviderRowsParsed,
                'matched_count' => count($matched),
                'missing_count' => count($missingFromProvider),
                'additional_count' => count($additionalInProvider),
                'report_data' => [
                    'matched' => $matched,
                    'missing_from_provider' => $missingFromProvider,
                    'additional_in_provider' => $additionalInProvider,
                ],
            ]);

            return redirect()->route('admin.sim-comparisons.show', $comparison)
                ->with('success', 'SIM connection reconciliation completed successfully.');

        } catch (\Throwable $e) {
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            if ($e instanceof ValidationException) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'report_file' => 'Error processing file: '.$e->getMessage(),
            ]);
        }
    }

    private function normalizeMobileNumber(string $mobile): string
    {
        $digits = preg_replace('/[^0-9]/', '', $mobile);

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }

    private function providersMatch(string $fileProv, string $dbProv): bool
    {
        $fileNorm = $this->normalizeProviderString($fileProv);
        $dbNorm = $this->normalizeProviderString($dbProv);

        if ($fileNorm === '' || $dbNorm === '') {
            return false;
        }

        if ($fileNorm === $dbNorm || str_contains($fileNorm, $dbNorm) || str_contains($dbNorm, $fileNorm)) {
            return true;
        }

        // Special check for 3 / Three
        $threeVariants = ['3', 'three', 'threeuk', '3uk', '3unitedkingdom'];
        if (in_array($fileNorm, $threeVariants) && in_array($dbNorm, $threeVariants)) {
            return true;
        }

        return false;
    }

    private function normalizeProviderString(string $name): string
    {
        $lower = strtolower(trim($name));
        $lower = str_replace(['3', 'three'], '3', $lower);

        $cleaned = preg_replace('/[^a-z0-9]/', '', $lower);
        $cleaned = str_replace(['uk', 'unitedkingdom'], '', $cleaned);

        return $cleaned;
    }

    public function show(SimProviderComparison $simComparison)
    {
        $simComparison->load(['admin:id,name', 'operator:id,name']);

        return Inertia::render('Admin/SimComparison/Show', [
            'comparison' => $simComparison,
        ]);
    }
}
