<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Pharmacy;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Settings. The owner sees the organization, its branches and their own
 * account. A staff member sees only their own account. Branch details are
 * not edited here: each branch has its own page, so a detail lives in one
 * place only.
 */
class SettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($redirect = $this->turnAway($user)) {
            return $redirect;
        }

        $isOwner = $user->role === 'vendor';
        $organization = $isOwner ? Organization::where('owner_id', $user->id)->first() : null;

        if ($isOwner && ! $organization) {
            return redirect()->route('vendor.onboarding');
        }

        return view('vendor-dashboard.profile', [
            'user' => $user,
            'isOwner' => $isOwner,
            'organization' => $organization,
            'branches' => $isOwner ? Pharmacy::branchesOf($user)->get() : collect(),
            'branch' => Pharmacy::resolveForUser($user),
            'roleLabel' => $isOwner ? 'Owner' : StaffRoles::label($user->staff_role),
        ]);
    }

    /** The business itself: its name and contact details. Owner only. */
    public function updateOrganization(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->role === 'vendor', 403);

        $organization = Organization::where('owner_id', $user->id)->firstOrFail();
        $mainBranch = Pharmacy::branchesOf($user)->first();

        $data = $request->validateWithBag('organization', [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'name.required' => 'Enter the name of your organization.',
            'phone.required' => 'Enter a phone number customers can call.',
            'email.required' => 'Enter a contact email for the business.',
        ]);

        $data = array_map(fn ($value) => trim((string) $value), $data);
        $before = $organization->only(array_keys($data));

        $organization->update($data);

        $labels = ['name' => 'name', 'phone' => 'phone', 'email' => 'email'];
        $changed = array_keys(array_filter($data, fn ($value, $key) => (string) ($before[$key] ?? '') !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed) {
            Audit::log(
                'organization.updated',
                ($user->full_name ?: $user->email) . ' changed the organization ' . implode(', ', array_map(fn ($key) => $labels[$key], $changed)),
                $mainBranch?->id,
                $organization,
                ['before' => array_intersect_key($before, array_flip($changed)), 'after' => array_intersect_key($data, array_flip($changed))],
            );
        }

        return redirect()->to(route('vendor.profile') . '#organization')->with('success', 'Organization details saved.');
    }

    /** The signed-in person's own name, email and phone. Staff cannot change their sign-in email. */
    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        if ($redirect = $this->turnAway($user)) {
            return $redirect;
        }

        $isOwner = $user->role === 'vendor';

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ];

        if ($isOwner) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        $data = $request->validateWithBag('account', $rules, [
            'name.required' => 'Enter your name.',
            'email.unique' => 'Another account already uses this email.',
            'phone.unique' => 'Another account already uses this phone number.',
        ]);

        $oldEmail = $user->email;

        $user->full_name = trim($data['name']);
        $user->phone = filled($data['phone'] ?? null) ? trim($data['phone']) : null;

        if ($isOwner) {
            $user->email = Str::lower(trim($data['email']));
        }

        $user->save();

        if ($isOwner && $oldEmail !== $user->email) {
            Audit::account('account.email_changed', ($user->full_name ?: $user->email) . " changed their sign-in email from {$oldEmail} to {$user->email}", $user);
        }

        return redirect()->to(route('vendor.profile') . '#account')->with('success', 'Your details were saved.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        if ($redirect = $this->turnAway($user)) {
            return $redirect;
        }

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

        return redirect()->to(route('vendor.profile') . '#password')->with('success', 'Your password was changed.');
    }

    /** Settings is for the pharmacy side. A customer has their own profile page. */
    private function turnAway(User $user)
    {
        return in_array($user->role, ['vendor', 'sales_person'], true) ? null : redirect()->route('user.profile');
    }
}
