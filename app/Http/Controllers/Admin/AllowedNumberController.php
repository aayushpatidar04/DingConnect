<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllowedNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AllowedNumberController extends Controller
{
    public function index(Request $request)
    {
        $query = AllowedNumber::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where('number', 'like', "%{$search}%")
                ->orWhere('operator_name', 'like', "%{$search}%");
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($request->boolean('inactive')) {
            $query->where('active', false);
        }

        $numbers = $query->paginate(50);

        return Inertia::render('Admin/AllowedNumbers/Index', [
            'numbers' => $numbers,
            'filters' => $request->only('search', 'type', 'inactive'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'type' => 'required|in:mobile,serial',
            'operator_name' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:10',
            'note' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = Auth::id();

        $allowed = AllowedNumber::create($validated);

        return back()->with('success', 'Number added successfully.');
    }

    public function update(Request $request, AllowedNumber $allowedNumber)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:50',
            'type' => 'required|in:mobile,serial',
            'operator_name' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:10',
            'note' => 'nullable|string|max:255',
            'active' => 'required|boolean',
        ]);

        $allowedNumber->update($validated);

        return back()->with('success', 'Updated successfully.');
    }

    public function destroy(AllowedNumber $allowedNumber)
    {
        $allowedNumber->delete();

        return back()->with('success', 'Removed successfully.');
    }

    /**
     * Import numbers via textarea (one per line).
     */
    public function import(Request $request)
    {
        $request->validate([
            'numbers' => ['required', 'string'],
            'type' => ['required', 'in:mobile,serial'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
        ]);

        $lines = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', $request->numbers)
        )));

        // Textarea flow: same type/operator/country for every line
        $rows = array_map(fn($line) => [
            'number' => $line,
            'type' => $request->type,
            'operator_name' => $request->operator_name,
            'country' => $request->country,
        ], $lines);

        return $this->processImport($rows, $request->type);
    }

    /**
     * Import numbers via CSV/Excel file upload.
     */
    public function importFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'type' => ['nullable', 'in:mobile,serial'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:10'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $content),
            fn($l) => trim($l) !== ''
        ));

        if (empty($lines)) {
            return back()->with('error', 'The uploaded file is empty.');
        }

        // First row = header
        $header = array_map(fn($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $col = array_flip($header);

        $numberIdx = $col['number'] ?? $col['serial'] ?? null;
        $typeIdx = $col['type'] ?? null;
        $operatorIdx = $col['operator_name'] ?? $col['operator'] ?? null;
        $countryIdx = $col['country'] ?? null;

        if ($numberIdx === null) {
            return back()->with('error', 'CSV must have a "number" column header.');
        }

        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            $rows[] = [
                'number' => trim($cells[$numberIdx] ?? ''),
                'type' => $typeIdx !== null ? strtolower(trim($cells[$typeIdx] ?? '')) : '',
                'operator_name' => $operatorIdx !== null ? trim($cells[$operatorIdx] ?? '') : '',
                'country' => $countryIdx !== null ? strtoupper(trim($cells[$countryIdx] ?? '')) : '',
            ];
        }

        // $request->type / operator_name / country act as defaults for rows with empty cells
        return $this->processImport($rows, $request->type ?? 'mobile');
    }

    /**
     * Download sample CSV template.
     */
    public function downloadSample()
    {
        $csv = "number,type,operator_name,country\n";
        $csv .= "447700900123,mobile,giffgaff,GB\n";
        $csv .= "447700900124,mobile,giffgaff,GB\n";
        $csv .= "829953289034771924,serial,giffgaff,GB\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="allowed_numbers_sample.csv"',
        ]);
    }

    /**
     * Shared import logic — inserts numbers, skips duplicates & invalid.
     */
    private function processImport(array $rows, string $defaultType)
    {
        $imported = 0;
        $skipped = 0;
        $results = [];

        foreach ($rows as $row) {
            $number = preg_replace('/[^A-Za-z0-9+\-]/', '', $row['number'] ?? '');

            if (strlen($number) < 5) {
                $skipped++;
                $results[] = ['number' => $row['number'] ?? '', 'status' => 'invalid'];
                continue;
            }

            // Per-row type wins; fall back to the flow's default (form value for textarea, request/first for CSV)
            $type = in_array($row['type'] ?? '', ['mobile', 'serial'], true)
                ? $row['type']
                : $defaultType;

            $exists = AllowedNumber::where('user_id', Auth::id())
                ->where('number', $number)
                ->where('type', $type)
                ->exists();

            if ($exists) {
                $skipped++;
                $results[] = ['number' => $number, 'status' => 'duplicate'];
                continue;
            }

            AllowedNumber::create([
                'user_id' => Auth::id(),
                'number' => $number,
                'type' => $type,
                'operator_name' => $row['operator_name'] ?: null,
                'country' => $row['country'] ?: null,
                'active' => true,
            ]);

            $imported++;
            $results[] = ['number' => $number, 'status' => 'imported'];
        }

        return back()->with('success', "Imported {$imported} numbers. Skipped {$skipped} duplicates/invalid.")
            ->with('import_results', $results);
    }
}
