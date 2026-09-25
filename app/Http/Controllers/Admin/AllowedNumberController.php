<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllowedNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function import(Request $request)
    {
        $request->validate([
            'numbers' => 'required|string',
            'type' => 'required|in:mobile,serial',
            'operator_name' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:10',
        ]);

        $lines = array_filter(array_map('trim', explode("\n", $request->numbers)));

        $imported = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $number = preg_replace('/[^A-Za-z0-9\+\-]/', '', $line);
            if (strlen($number) < 5) {
                $skipped++;
                continue;
            }

            $exists = AllowedNumber::where('user_id', Auth::id())
                ->where('number', $number)
                ->where('type', $request->type)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            AllowedNumber::create([
                'user_id'       => Auth::id(),
                'number'        => $number,
                'type'          => $request->type,
                'operator_name' => $request->operator_name,
                'country'       => $request->country,
                'active'        => true,
            ]);

            $imported++;
        }

        return back()->with('success', "Imported {$imported} numbers. Skipped {$skipped} duplicates/invalid.");
    }
}
