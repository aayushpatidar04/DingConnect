<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;


class SettingController extends Controller
{
    public function index()
    {
        $groups = Setting::select('group')->distinct()->pluck('group');

        $settings = Setting::all()->groupBy('group');

        return Inertia::render('Admin/Settings/Index', compact('settings', 'groups'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.value' => 'required',
        ]);

        foreach ($validated['settings'] as $setting) {
            Setting::where('id', $setting['id'])->update(['value' => $setting['value']]);
        }

        return back()->with('success', 'Settings updated successfully!');
    }
}
