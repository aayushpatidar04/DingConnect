<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;


class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('Retailer/Profile/Index', [
            'user' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone,' . $user->id],
            'shop_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:10',
            'vat_number' => 'nullable|string|max:20',
            'company_reg_number' => 'nullable|string|max:20',
            'utr_number' => 'nullable|string|max:20',
            'current_password' => 'nullable|required_with:new_password|string',
            'new_password' => 'nullable|string|min:8|confirmed',
        ]);

        if (!empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $validated['password'] = $validated['new_password']; // User model casts password as 'hashed'
        }

        unset($validated['current_password'], $validated['new_password']);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully!');
    }
}
