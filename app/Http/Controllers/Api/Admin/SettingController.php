<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get()->groupBy('group');

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings, 'groups' => Setting::select('group')->distinct()->orderBy('group')->pluck('group')],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.id' => ['required', 'integer', 'distinct', 'exists:settings,id'],
            'settings.*.value' => ['present'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['settings'] as $setting) {
                Setting::whereKey($setting['id'])->update(['value' => $setting['value']]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Settings updated successfully.']);
    }
}
