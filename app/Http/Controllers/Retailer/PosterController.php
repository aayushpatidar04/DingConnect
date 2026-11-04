<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Poster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PosterController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $levelId = $user->retailerCommissionProfile?->commission_level_id;

        $posters = Poster::live()
            ->where(function ($q) use ($user, $levelId) {
                $q->where('audience', 'all')
                    ->orWhere(function ($q) use ($user) {
                        $q->where('audience', 'retailers')
                            ->whereHas('retailers', fn ($r) => $r->where('users.id', $user->id));
                    })
                    ->orWhere(function ($q) use ($levelId) {
                        if ($levelId) {
                            $q->where('audience', 'levels')
                                ->whereHas('levels', fn ($l) => $l->where('commission_levels.id', $levelId));
                        }
                    });
            })
            ->with(['levels:id,name', 'retailers:id'])
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
                    'expires_at' => $p->expires_at?->toIso8601String(),
                    'status' => $p->status(),
                ];
            });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['posters' => $posters]]);
        }

        return Inertia::render('Retailer/Posters/Index', [
            'posters' => $posters,
        ]);
    }
}
