<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllowedNumber;
use App\Models\AllowedNumberFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;

class AllowedNumberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only('search', 'folder', 'inactive');
        $search = trim((string) ($filters['search'] ?? ''));
        $folder = $filters['folder'] ?? null;

        $currentFolder = null;
        if ($folder === 'manual') {
            $currentFolder = ['id' => 'manual', 'name' => 'Manual entries', 'is_manual' => true];
        } elseif ($folder) {
            $file = AllowedNumberFile::findOrFail($folder);
            $currentFolder = ['id' => $file->id, 'name' => $file->name, 'is_manual' => false];
        }

        $numbers = null;
        $folders = null;

        if ($currentFolder || $search !== '') {
            $numbers = AllowedNumber::query()
                ->when($currentFolder, fn ($q) => $currentFolder['is_manual']
                    ? $q->whereNull('file_name')
                    : $q->where('file_name', $currentFolder['name']))
                ->when($search !== '', function ($q) use ($search) {
                    // grouped so it can't bypass the folder / inactive filters
                    $q->where(function ($q) use ($search) {
                        $q->where('mobile', 'like', "%{$search}%")
                            ->orWhere('serial', 'like', "%{$search}%")
                            ->orWhere('provider', 'like', "%{$search}%");
                    });
                })
                ->when($request->boolean('inactive'), fn ($q) => $q->where('active', false))
                ->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString();
        } else {
            $folders = AllowedNumberFile::withCount('numbers')
                ->orderByDesc('created_at')
                ->get();
        }

        $data = [
            'folders' => $folders,
            'manualCount' => AllowedNumber::whereNull('file_name')->count(),
            'numbers' => $numbers,
            'currentFolder' => $currentFolder,
            'filters' => $filters,
        ];

        if ($request->is('api/*')) {
            return response()->json(['success' => true, 'data' => $data]);
        }

        return Inertia::render('Admin/AllowedNumbers/Index', $data);
    }

    // ---------- Manual entry ----------

    public function store(Request $request)
    {
        $this->normalizeInput($request);
        $validated = $request->validate($this->rules(null, $request->input('serial')), $this->messages());

        unset($validated['active']);
        $allowedNumber = AllowedNumber::create($validated + ['user_id' => Auth::id(), 'active' => true]);

        if ($request->is('api/*')) {
            return response()->json(['success' => true, 'data' => $allowedNumber], 201);
        }

        return back()->with('success', 'Number added successfully.');
    }

    public function update(Request $request, AllowedNumber $allowedNumber)
    {
        $this->normalizeInput($request);
        $validated = $request->validate(
            $this->rules($allowedNumber->id, $request->input('serial')) + ['active' => 'required|boolean'],
            $this->messages()
        );

        $allowedNumber->update($validated);

        if ($request->is('api/*')) {
            return response()->json(['success' => true, 'data' => $allowedNumber->fresh()]);
        }

        return back()->with('success', 'Updated successfully.');
    }

    public function destroy(AllowedNumber $allowedNumber)
    {
        $allowedNumber->delete();

        if (request()->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'Number removed.']);
        }

        return back()->with('success', 'Removed successfully.');
    }

    // ---------- File upload ----------

    public function importFile(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'extensions:csv,txt,xlsx', 'max:2048']]);

        $upload = $request->file('file');
        $name = basename($upload->getClientOriginalName());
        $isXlsx = strtolower($upload->getClientOriginalExtension()) === 'xlsx';

        // same file name can't be uploaded twice (case-insensitive under utf8mb4_unicode_ci)
        if (AllowedNumberFile::where('name', $name)->exists()) {
            if ($request->is('api/*')) {
                throw ValidationException::withMessages([
                    'file' => "A file named \"{$name}\" was already uploaded. Rename it or delete the existing one first.",
                ]);
            }

            return back()->withErrors([
                'file' => "A file named \"{$name}\" was already uploaded. Rename it or delete the existing one first.",
            ]);
        }

        $spreadsheet = null;
        $worksheet = null;
        $handle = null;

        if ($isXlsx) {
            try {
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($upload->getRealPath());
            } catch (SpreadsheetException) {
                throw ValidationException::withMessages(['file' => 'The uploaded XLSX file could not be read.']);
            }

            $worksheet = $spreadsheet->getActiveSheet();
            $lastColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
            $header = [];

            foreach ($worksheet->getRowIterator(1, 1) as $headerRow) {
                $header = $this->spreadsheetRow($headerRow, $lastColumn);
            }
        } else {
            $handle = fopen($upload->getRealPath(), 'r');
            $header = fgetcsv($handle, 0, ',', '"', '\\');
        }

        if (! $header || $header === [null]) {
            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            } elseif (is_resource($handle)) {
                fclose($handle);
            }

            throw ValidationException::withMessages(['file' => 'The uploaded file is empty.']);
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $col = array_flip(array_map(fn ($h) => strtolower(trim((string) $h)), $header));

        $requiredColumns = ['mobile', 'serial', 'provider', 'country'];
        $missingColumns = [];

        foreach ($requiredColumns as $required) {
            if (! isset($col[$required])) {
                $missingColumns[] = "\"{$required}\"";
            }
        }

        if (! empty($missingColumns)) {
            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            } elseif (is_resource($handle)) {
                fclose($handle);
            }

            $missingList = implode(', ', $missingColumns);
            throw ValidationException::withMessages([
                'file' => "The CSV/XLSX file is missing mandatory column headers: {$missingList}. Please ensure your file includes mobile, serial, provider and country columns.",
            ]);
        }

        $pi = $col['provider'] ?? null;
        $ci = $col['country'] ?? null;

        $rows = [];
        $skipped = [];
        $seen = [];
        $processRow = function (array $cells, int $line) use (&$rows, &$skipped, &$seen, $col, $pi, $ci): void {
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                return;
            }

            $rawMobile = trim((string) ($cells[$col['mobile']] ?? ''));
            $rawSerial = trim((string) ($cells[$col['serial']] ?? ''));
            $mobile = $this->mobileOrZero($rawMobile);
            $serial = $this->serialOrZero($rawSerial);

            // skip only when BOTH are missing/invalid
            if ($mobile === '0' && $serial === '0') {
                $skipped[] = ['line' => $line, 'mobile' => $rawMobile, 'serial' => $rawSerial, 'reason' => 'invalid'];

                return;
            }

            $key = $mobile.'|'.strtolower($serial);
            if (isset($seen[$key])) {
                $skipped[] = ['line' => $line, 'mobile' => $mobile, 'serial' => $serial, 'reason' => 'duplicate_in_file'];

                return;
            }
            $seen[$key] = true;

            $rows[] = [
                'mobile' => $mobile,
                'serial' => $serial,
                'provider' => $pi !== null ? (mb_substr(trim((string) ($cells[$pi] ?? '')), 0, 100) ?: null) : null,
                'country' => $ci !== null ? (substr(strtoupper(trim((string) ($cells[$ci] ?? ''))), 0, 10) ?: null) : null,
            ];
        };

        if ($worksheet !== null) {
            $lastRow = $worksheet->getHighestDataRow();

            if ($lastRow > 1) {
                foreach ($worksheet->getRowIterator(2, $lastRow) as $row) {
                    $processRow($this->spreadsheetRow($row, $lastColumn), $row->getRowIndex());
                }
            }

            $spreadsheet->disconnectWorksheets();
        } elseif (is_resource($handle)) {
            $line = 1;

            try {
                while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                    $processRow($cells, ++$line);
                }
            } finally {
                fclose($handle);
            }
        }

        // pairs that already exist in the table
        $existing = [];
        $remember = function ($r) use (&$existing) {
            $existing[$r->mobile.'|'.strtolower($r->serial)] = true;
        };

        $mobiles = array_values(array_unique(array_filter(array_column($rows, 'mobile'), fn ($m) => $m !== '0')));
        $serials = array_values(array_unique(array_filter(array_column($rows, 'serial'), fn ($s) => $s !== '0')));

        foreach (array_chunk($mobiles, 1000) as $chunk) {
            AllowedNumber::whereIn('mobile', $chunk)->get(['mobile', 'serial'])->each($remember);
        }
        foreach (array_chunk($serials, 1000) as $chunk) {
            AllowedNumber::whereIn('serial', $chunk)->get(['mobile', 'serial'])->each($remember);
        }

        $toInsert = [];
        foreach ($rows as $r) {
            if (isset($existing[$r['mobile'].'|'.strtolower($r['serial'])])) {
                $skipped[] = ['line' => null, 'mobile' => $r['mobile'], 'serial' => $r['serial'], 'reason' => 'duplicate_existing'];

                continue;
            }
            $toInsert[] = $r;
        }

        $imported = count($toInsert);
        $skippedCount = count($skipped);
        $path = $upload->store('allowed-numbers');

        try {
            DB::transaction(function () use ($name, $path, $toInsert, $imported, $skippedCount) {
                $now = now();

                AllowedNumberFile::create([
                    'user_id' => Auth::id(),
                    'name' => $name,
                    'path' => $path,
                    'total_rows' => $imported + $skippedCount,
                    'imported_rows' => $imported,
                    'skipped_rows' => $skippedCount,
                ]);

                foreach (array_chunk($toInsert, 500) as $chunk) {
                    AllowedNumber::insert(array_map(fn ($r) => $r + [
                        'user_id' => Auth::id(),
                        'file_name' => $name,
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }
            });
        } catch (\Throwable $e) {
            Storage::delete($path); // don't leave an orphan file behind
            throw $e;
        }

        if ($request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "Uploaded {$name}: {$imported} imported, {$skippedCount} skipped.",
                'data' => [
                    'file' => AllowedNumberFile::where('name', $name)->first(),
                    'imported' => $imported,
                    'skipped' => $skippedCount,
                    'import_results' => array_slice($skipped, 0, 200),
                ],
            ], 201);
        }

        return back()
            ->with('success', "Uploaded {$name}: {$imported} imported, {$skippedCount} skipped.")
            ->with('import_results', array_slice($skipped, 0, 200));
    }

    public function downloadFile(AllowedNumberFile $allowedNumberFile)
    {
        if (! Storage::exists($allowedNumberFile->path)) {
            if (request()->is('api/*')) {
                abort(404, 'The stored file could not be found on the server.');
            }

            return back()->with('error', 'The stored file could not be found on the server.');
        }

        return Storage::download($allowedNumberFile->path, $allowedNumberFile->name);
    }

    public function destroyFile(AllowedNumberFile $allowedNumberFile)
    {
        DB::transaction(function () use ($allowedNumberFile) {
            AllowedNumber::where('file_name', $allowedNumberFile->name)->delete();
            $allowedNumberFile->delete();
        });

        Storage::delete($allowedNumberFile->path);

        if (request()->is('api/*')) {
            return response()->json(['success' => true, 'message' => "Deleted \"{$allowedNumberFile->name}\" and its numbers."]);
        }

        return redirect('/admin/allowed-numbers')
            ->with('success', "Deleted \"{$allowedNumberFile->name}\" and its numbers.");
    }

    public function downloadSample()
    {
        $csv = "mobile,serial,provider,country\n";
        $csv .= "447700900123,829953289034771924,giffgaff,GB\n";
        $csv .= "447700900124,829953289034771925,giffgaff,GB\n";
        $csv .= "447700900125,829953289034771926,EE United Kingdom,GB\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="allowed_numbers_sample.csv"',
        ]);
    }

    // ---------- Helpers ----------

    private function cleanMobile(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v); // digits only
    }

    private function cleanSerial(?string $v): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', (string) $v);
    }

    private function mobileOrZero(?string $v): string
    {
        $c = $this->cleanMobile($v);

        // UK trunk-prefix form 0XXXXXXXXXX -> 44XXXXXXXXXX
        if (strlen($c) === 11 && str_starts_with($c, '0')) {
            $c = '44'.substr($c, 1);
        }

        return strlen($c) >= 5 ? $c : '0';
    }

    private function serialOrZero(?string $v): string
    {
        $c = $this->cleanSerial($v);

        return strlen($c) >= 5 ? $c : '0';
    }

    private function spreadsheetRow(Row $row, int $lastColumn): array
    {
        $cells = $row->getCellIterator('A', Coordinate::stringFromColumnIndex($lastColumn));
        $cells->setIterateOnlyExistingCells(false);

        $values = [];

        foreach ($cells as $cell) {
            $values[] = $cell->getValueString();
        }

        return $values;
    }

    private function normalizeInput(Request $request): void
    {
        $request->merge([
            'mobile' => $this->mobileOrZero($request->input('mobile')),
            'serial' => $this->serialOrZero($request->input('serial')),
            'country' => strtoupper(trim((string) $request->input('country'))) ?: null,
        ]);

        if ($request->input('mobile') === '0' && $request->input('serial') === '0') {
            throw ValidationException::withMessages([
                'mobile' => 'Enter at least a mobile number or a serial number (5+ characters).',
            ]);
        }
    }

    private function rules(?int $ignoreId, ?string $serial): array
    {
        return [
            'mobile' => [
                'required',
                'string',
                'max:50',
                Rule::unique('allowed_numbers', 'mobile')
                    ->where(fn ($q) => $q->where('serial', $serial))
                    ->ignore($ignoreId),
            ],
            'serial' => ['required', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return ['mobile.unique' => 'This mobile + serial combination already exists.'];
    }
}
