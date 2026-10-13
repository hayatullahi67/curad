<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Models\Organization;
use App\Models\Pharmacy;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            // What stands in the way of deleting the account, and what deleting it will switch off.
            'deletion' => $isOwner ? $this->deletionFacts($user) : null,
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

        $user->full_name = trim(preg_replace('/\s+/', ' ', $data['name']));
        // The first word is the first name; the greeting and the top bar use it.
        [$user->first_name, $user->last_name] = array_pad(explode(' ', $user->full_name, 2), 2, '');
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

    /**
     * The owner deletes their account, which closes the whole business on
     * CuraD: every branch leaves the marketplace, every staff account is
     * switched off, and the owner's sign-in and personal details are
     * removed. Sales, orders and the activity log are kept, because they
     * are the business's financial record.
     *
     * It is refused while an online order is still in progress, so no
     * customer is left waiting for an order nobody can hand over.
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->role === 'vendor', 403);

        $organization = Organization::where('owner_id', $user->id)->firstOrFail();
        $back = route('vendor.profile') . '#delete';

        $request->validateWithBag('delete', [
            'confirm_name' => ['required', 'string'],
            'current_password' => ['required', 'current_password'],
        ], [
            'confirm_name.required' => 'Type the name of your organization to confirm.',
            'current_password.required' => 'Enter your password to confirm.',
            'current_password.current_password' => 'That is not your current password.',
        ]);

        if (mb_strtolower(trim($request->input('confirm_name'))) !== mb_strtolower(trim($organization->name))) {
            return redirect()->to($back)->withErrors(['confirm_name' => 'That does not match the name of your organization.'], 'delete');
        }

        $facts = $this->deletionFacts($user);

        if ($facts['open_orders'] || $facts['own_orders']) {
            return redirect()->to($back)->withErrors(['confirm_name' => 'There are orders still in progress. Finish or cancel them first, then delete the account.'], 'delete');
        }

        DB::transaction(function () use ($user, $organization, $facts) {
            $branchIds = Pharmacy::where('user_id', $user->id)->pluck('id');

            Audit::log(
                'account.deleted',
                "{$user->full_name} deleted the account and closed {$organization->name} ({$facts['branches']} " . Str::plural('branch', $facts['branches']) . ", {$facts['staff']} staff)",
                $branchIds->first(),
                $organization,
            );

            // Off the marketplace, and nobody can sign in to work there.
            Pharmacy::whereIn('id', $branchIds)->update(['is_active' => false, 'updated_at' => now()]);
            User::where('cura_role', 'sales_person')->where('created_by_user_id', $user->id)->update(['is_active' => 0, 'remember_token' => null, 'updated_at' => now()]);
            $organization->update(['closed_at' => now()]);

            // The owner's sign-in and personal details go; the row stays so old records still point somewhere.
            // The email is released, so the same address can register again later.
            $user->forceFill([
                'full_name' => 'Deleted account',
                'first_name' => 'Deleted',
                'last_name' => 'account',
                'email' => "deleted-{$user->id}-" . Str::lower(Str::random(8)) . '@deleted.curad',
                'phone' => null,
                'address' => null,
                'address_latitude' => null,
                'address_longitude' => null,
                'password_hash' => Hash::make(Str::random(48)),
                'pin_hash' => null,
                'remember_token' => null,
                'is_active' => 0,
            ])->save();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Your account has been deleted and your pharmacy is no longer on CuraD. Thank you for using it.');
    }

    /** What deleting the account will switch off, and what currently stands in its way. */
    private function deletionFacts(User $owner): array
    {
        $branchIds = Pharmacy::where('user_id', $owner->id)->pluck('id');

        return [
            'branches' => $branchIds->count(),
            'staff' => User::where('cura_role', 'sales_person')->where('created_by_user_id', $owner->id)->where('is_active', 1)->count(),
            'products' => DB::table('medications')->whereIn('pharmacy_id', $branchIds)->count(),
            // Online orders one of the branches still has to accept, hand over, or see delivered.
            'open_orders' => OrderFulfillment::whereIn('pharmacy_id', $branchIds)->whereIn('status', ['awaiting_confirmation', 'confirmed', 'ready', 'dispatched'])->count(),
            // Orders the owner placed as a customer that are still on their way.
            'own_orders' => Order::where('user_id', $owner->id)->whereNotIn('status', ['completed', 'partially_fulfilled', 'cancelled'])->count(),
            'wallet' => (float) $owner->wallet_balance,
        ];
    }

    /** Settings is for the pharmacy side. A customer has their own profile page. */
    private function turnAway(User $user)
    {
        return in_array($user->role, ['vendor', 'sales_person'], true) ? null : redirect()->route('user.profile');
    }
}
