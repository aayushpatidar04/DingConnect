<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\Poster;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PosterController extends Controller
{
    public function index(Request $request)
    {
        $posters = Poster::with(['levels:id,name', 'retailers:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Poster $p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'description' => $p->description,
                    'image_url' => $p->image_path ? Storage::url($p->image_path) : null,
                    'link_url' => $p->link_url,
                    'poster_type' => $p->poster_type,
                    'audience' => $p->audience,
                    'level_ids' => $p->levels->pluck('id')->all(),
                    'level_names' => $p->levels->pluck('name')->all(),
                    'retailer_ids' => $p->retailers->pluck('id')->all(),
                    'starts_at' => $p->starts_at?->format('Y-m-d\TH:i'),
                    'expires_at' => $p->expires_at?->format('Y-m-d\TH:i'),
                    'is_active' => $p->is_active,
                    'status' => $p->status(),
                    'file_size' => Storage::disk('public')->size($p->image_path),
                ];
            });

        $data = [
            'posters' => $posters,
            'commissionLevels' => CommissionLevel::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (CommissionLevel $l) => [
                    'id' => $l->id,
                    'label' => $l->name,
                ]),
            'retailers' => User::where('role', 'retailer')
                ->orderBy('name')
                ->get(['id', 'name', 'shop_name'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'label' => $u->name.($u->shop_name ? " – {$u->shop_name}" : ''),
                ]),
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $data]);
        }

        return Inertia::render('Admin/Posters/Index', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'poster_type' => ['required', 'in:advertisement,festive_greeting,announcement'],
            'audience' => ['required', 'in:all,levels,retailers'],
            'level_ids' => ['nullable', 'array'],
            'level_ids.*' => ['integer', 'exists:commission_levels,id'],
            'retailer_ids' => ['nullable', 'array'],
            'retailer_ids.*' => ['integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        if ($data['audience'] === 'levels' && empty($data['level_ids'])) {
            throw new ValidationException(
                Validator::make([], []),
                ['level_ids' => 'Choose at least one commission level.']
            );
        }

        if ($data['audience'] === 'retailers' && empty($data['retailer_ids'])) {
            throw new ValidationException(
                Validator::make([], []),
                ['retailer_ids' => 'Choose at least one retailer.']
            );
        }

        $imagePath = $request->file('image')->store('posters', 'public');

        $poster = Poster::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'link_url' => $data['link_url'] ?? null,
            'poster_type' => $data['poster_type'],
            'audience' => $data['audience'],
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        if ($data['audience'] === 'levels') {
            $poster->levels()->sync($data['level_ids']);
        }

        if ($data['audience'] === 'retailers') {
            $poster->retailers()->sync($data['retailer_ids']);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $poster->load(['levels:id,name', 'retailers:id'])], 201);
        }

        return back()->with('success', 'Poster created.');
    }

    public function update(Request $request, Poster $poster)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'poster_type' => ['required', 'in:advertisement,festive_greeting,announcement'],
            'audience' => ['required', 'in:all,levels,retailers'],
            'level_ids' => ['nullable', 'array'],
            'level_ids.*' => ['integer', 'exists:commission_levels,id'],
            'retailer_ids' => ['nullable', 'array'],
            'retailer_ids.*' => ['integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        if ($data['audience'] === 'levels' && empty($data['level_ids'])) {
            throw new ValidationException(
                Validator::make([], []),
                ['level_ids' => 'Choose at least one commission level.']
            );
        }

        if ($data['audience'] === 'retailers' && empty($data['retailer_ids'])) {
            throw new ValidationException(
                Validator::make([], []),
                ['retailer_ids' => 'Choose at least one retailer.']
            );
        }

        $imagePath = $poster->image_path;
        if ($request->hasFile('image')) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('posters', 'public');
        }

        $poster->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'link_url' => $data['link_url'] ?? null,
            'poster_type' => $data['poster_type'],
            'audience' => $data['audience'],
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $poster->levels()->sync($data['audience'] === 'levels' ? ($data['level_ids'] ?? []) : []);
        $poster->retailers()->sync($data['audience'] === 'retailers' ? ($data['retailer_ids'] ?? []) : []);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $poster->fresh()->load(['levels:id,name', 'retailers:id'])]);
        }

        return back()->with('success', 'Poster updated.');
    }

    public function destroy(Request $request, Poster $poster)
    {
        if ($poster->image_path && Storage::disk('public')->exists($poster->image_path)) {
            Storage::disk('public')->delete($poster->image_path);
        }

        $poster->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Poster deleted.']);
        }

        return back()->with('success', 'Poster deleted.');
    }
}
