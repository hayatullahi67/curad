<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A customer's own profile: their details, password and wallet PIN.
 * Pharmacy owners and staff use Settings instead (Vendor\SettingsController).
 */
class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();

        if (in_array($user->role, ['vendor', 'sales_person'], true)) {
            return redirect()->route('vendor.profile');
        }

        return view('user-dashboard.profile', ['user' => $user, 'hasPin' => (bool) $user->transaction_pin]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validateWithBag('details', [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ], [
            'name.required' => 'Enter your name.',
            'email.unique' => 'Another account already uses this email.',
            'phone.unique' => 'Another account already uses this phone number.',
        ]);

        $oldEmail = $user->email;

        $user->full_name = trim($data['name']);
        $user->email = Str::lower(trim($data['email']));
        $user->phone = filled($data['phone'] ?? null) ? trim($data['phone']) : null;
        $user->save();

        if ($oldEmail !== $user->email) {
            Audit::account('account.email_changed', ($user->full_name ?: $user->email) . " changed their sign-in email from {$oldEmail} to {$user->email}", $user);
        }

        return redirect()->to(route('user.profile') . '#details')->with('success', 'Your details were saved.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'password.min' => 'Use at least 8 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Choose a password different from your current one.',
        ]);

        // Set directly: "password" is a stand-in name for the real column, so update() would skip it.
        $user->password = Hash::make($data['password']);
        $user->save();

        Audit::account('account.password_changed', ($user->full_name ?: $user->email) . ' changed their password', $user);

        return redirect()->to(route('user.profile') . '#password')->with('success', 'Your password was changed.');
    }

    /** The 4-digit PIN asked for when paying from the wallet at checkout. */
    public function updatePin(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('pin', [
            'current_password' => ['required', 'current_password'],
            'transaction_pin' => ['required', 'digits:4', 'confirmed'],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'transaction_pin.digits' => 'The PIN must be exactly 4 numbers.',
            'transaction_pin.confirmed' => 'The two PINs do not match.',
        ]);

        $hadPin = (bool) $user->transaction_pin;

        // Checkout checks the PIN with Hash::check, so it is stored hashed.
        $user->transaction_pin = Hash::make($data['transaction_pin']);
        $user->save();

        Audit::account('account.pin_changed', ($user->full_name ?: $user->email) . ($hadPin ? ' changed their wallet PIN' : ' set a wallet PIN'), $user);

        return redirect()->to(route('user.profile') . '#pin')->with('success', $hadPin ? 'Your wallet PIN was changed.' : 'Your wallet PIN is set. You can now pay from your wallet.');
    }
}
