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
            'operator_id' => ['required', 'exists:operators,id'],
            'reconciliation_month' => ['required', 'date_format:Y-m'],
            'report_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $operator = Operator::findOrFail($request->operator_id);
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

            // Parse provider data rows
            $providerRecords = [];
            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i] ?? null;
                if (! $row) {
                    continue;
                }

                $mobile = trim((string) ($row[$headerMap['mobile']] ?? ''));
                if ($mobile === '') {
                    continue;
                }

                $providerRecords[] = [
                    'provider' => trim((string) ($row[$headerMap['provider']] ?? '')),
                    'mobile' => $mobile,
                    'serial' => trim((string) ($row[$headerMap['serial']] ?? '')),
                    'country' => trim((string) ($row[$headerMap['country']] ?? '')),
                ];
            }

            Storage::delete($filePath);

            if (empty($providerRecords)) {
                throw ValidationException::withMessages(['report_file' => 'No valid data rows found in the uploaded file.']);
            }

            // Fetch internal connection records (successful transactions for this specific operator and month)
            $internalTransactions = Transaction::with(['user:id,name,shop_name,email', 'operator:id,name'])
                ->where('operator_id', $operator->id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('status', 'success')
                ->get();

            // Index internal records by mobile number
            $internalByMobile = [];
            foreach ($internalTransactions as $txn) {
                $normMobile = preg_replace('/[^0-9]/', '', $txn->mobile_number);
                $internalByMobile[$normMobile] = [
                    'id' => $txn->id,
                    'mobile' => $txn->mobile_number,
                    'serial' => $txn->receipt_number ?? '-',
                    'provider' => $txn->operator?->name ?? $operator->name,
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

            $matched = [];
            $missingFromProvider = []; // Present internally, missing in provider report (Provider missed)
            $additionalInProvider = []; // Present in provider report, missing internally

            $checkedInternalMobiles = [];

            foreach ($providerRecords as $pRec) {
                $normProvMobile = preg_replace('/[^0-9]/', '', $pRec['mobile']);

                if (isset($internalByMobile[$normProvMobile])) {
                    $internalMatch = $internalByMobile[$normProvMobile];
                    $checkedInternalMobiles[$normProvMobile] = true;
                    $matched[] = [
                        'mobile' => $pRec['mobile'],
                        'serial' => $pRec['serial'] !== '' ? $pRec['serial'] : $internalMatch['serial'],
                        'provider' => $pRec['provider'],
                        'country' => $pRec['country'],
                        'retailer' => $internalMatch['retailer'],
                        'internal_date' => $internalMatch['created_at'],
                    ];
                } else {
                    $additionalInProvider[] = $pRec;
                }
            }

            foreach ($internalByMobile as $normMobile => $internalRec) {
                if (! isset($checkedInternalMobiles[$normMobile])) {
                    $missingFromProvider[] = $internalRec;
                }
            }

            $comparison = SimProviderComparison::create([
                'admin_id' => Auth::id(),
                'operator_id' => $operator->id,
                'reconciliation_month' => $request->reconciliation_month,
                'file_name' => $fileName,
                'provider_name' => $operator->name,
                'total_internal' => count($internalByMobile),
                'total_provider' => count($providerRecords),
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

    public function show(SimProviderComparison $simComparison)
    {
        $simComparison->load(['admin:id,name', 'operator:id,name']);

        return Inertia::render('Admin/SimComparison/Show', [
            'comparison' => $simComparison,
        ]);
    }
}
