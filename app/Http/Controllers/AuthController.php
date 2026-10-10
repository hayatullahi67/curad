<?php

namespace App\Http\Controllers;

use App\Models\StaffPasswordResetRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Wrong passwords allowed for one email from one device before a pause. */
    private const LOGIN_ATTEMPTS = 5;

    /** Accounts one device may create per hour. */
    private const SIGNUPS_PER_HOUR = 5;

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectUser(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $throttleKey = 'login|' . $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);
            $known = User::where('email', $email)->first();

            if ($known) {
                Audit::account('account.locked', 'Sign-in paused for ' . ($known->full_name ?: $email) . ' after too many wrong passwords', $known);
            }

            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. Please try again in {$minutes} " . Str::plural('minute', $minutes) . '.',
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 15 * 60);

            // The same message whether the email or the password is wrong,
            // so the form cannot be used to find out who has an account.
            throw ValidationException::withMessages([
                'email' => 'The email or password is not correct.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            Audit::account('account.sign_in_blocked', ($user->full_name ?: $email) . ' tried to sign in to a deactivated account', $user);

            throw ValidationException::withMessages([
                'email' => $user->role === 'sales_person'
                    ? 'This staff account has been deactivated. Contact your pharmacy admin.'
                    : 'This account has been deactivated. Contact CuraD support.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);

        return $this->redirectUser($user, true);
    }

    public function showRegister(Request $request)
    {
        if (Auth::check()) {
            return $this->redirectUser(Auth::user());
        }

        $accountType = $request->query('as') === 'pharmacy' ? 'vendor' : 'patient';

        return view('auth.register', compact('accountType'));
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => preg_replace('/[\s\-()]/', '', (string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'account_type' => ['required', Rule::in(['patient', 'vendor'])],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'regex:/^(\+?234|0)[789][01]\d{8}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
            // A customer's delivery address. A pharmacy gives its address when it registers the pharmacy.
            'address' => ['nullable', 'string', 'max:500'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:address_latitude'],
        ], [
            'email.unique' => 'An account with this email already exists. Sign in instead.',
            'phone.unique' => 'An account with this phone number already exists.',
            'phone.regex' => 'Enter a valid Nigerian phone number, e.g. 08012345678.',
            'password.confirmed' => 'The two passwords do not match.',
            'terms.accepted' => 'Please accept the terms to create an account.',
        ]);

        $throttleKey = 'signup|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::SIGNUPS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'email' => 'Too many accounts were created from this device. Please try again later.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60 * 60);

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        $user = new User();
        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $firstName . ' ' . $lastName,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => Hash::make($data['password']),
            'cura_role' => $data['account_type'],
            'address' => $data['account_type'] === 'patient' && filled($data['address'] ?? null) ? trim($data['address']) : null,
            'address_latitude' => $data['account_type'] === 'patient' && filled($data['address'] ?? null) ? ($data['address_latitude'] ?? null) : null,
            'address_longitude' => $data['account_type'] === 'patient' && filled($data['address'] ?? null) ? ($data['address_longitude'] ?? null) : null,
            'is_active' => 1,
            'module' => 'CuraD',
            'terms_accepted_at' => now(),
            'last_login_at' => now(),
        ])->save();

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectUser($user)->with('success', "Welcome to CuraD, {$firstName}! Your account is ready.");
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $email = Str::lower(trim($request->input('email')));
        $throttleKey = 'reset-link|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many requests. Please wait a few minutes and try again.',
            ]);
        }

        RateLimiter::hit($throttleKey, 10 * 60);

        $user = User::where('email', $email)->where('is_active', 1)->first();
        $status = 'If an account exists for that email, a link to choose a new password has been sent. It works for 60 minutes. For a staff account, the pharmacy owner has been asked to set a new password for you.';

        if (! $user) {
            // Same answer as for a real account, so nobody can use this
            // form to check which emails are registered.
            return back()->with('status', $status);
        }

        // Staff do not get an email link: the request goes to the pharmacy
        // owner, who sets a new password for them from Staff Access.
        if ($user->role === 'sales_person') {
            if (! StaffPasswordResetRequest::pending()->where('staff_id', $user->id)->exists()) {
                StaffPasswordResetRequest::create(['staff_id' => $user->id, 'pharmacy_id' => $user->assigned_pharmacy_id]);
                Audit::account('account.password_requested', ($user->full_name ?: $email) . ' asked for a new password', $user);
            }

            return back()->with('status', $status);
        }

        // On a developer's computer email is only written to a log file, so
        // the link is shown on the page instead. Never on the live site.
        if (app()->environment('local') && config('mail.default') === 'log') {
            $token = Password::broker()->createToken($user);

            return back()->with('status', $status)->with('dev_reset_link', route('password.reset', ['token' => $token, 'email' => $user->email]));
        }

        Password::broker()->sendResetLink(['email' => $email]);

        return back()->with('status', $status);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $result = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password_hash' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This link is not valid any more. Ask for a new one and try again.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password has been changed. Sign in with the new one.');
    }

    public function becomePharmacy(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->role !== 'vendor') {
            $user->role = 'vendor';
            $user->save();
        }

        return redirect()->route('vendor.onboarding');
    }

    public function becomePatient(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->role !== 'patient') {
            $user->role = 'patient';
            $user->save();
        }

        return redirect()->route('user.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Where each kind of account lands after signing in. A customer who was
     * sent to sign in from a page (say, a product they wanted) goes back to it.
     */
    protected function redirectUser($user, bool $allowIntended = false)
    {
        if ($user->role === 'sales_person') {
            return redirect()->route('sales.staff.dashboard');
        }

        if ($user->role === 'vendor') {
            $hasPharmacy = DB::table('pharmacies')->where('user_id', $user->id)->exists();

            return redirect()->route($hasPharmacy ? 'vendor.dashboard' : 'vendor.onboarding');
        }

        return $allowIntended
            ? redirect()->intended(route('user.dashboard'))
            : redirect()->route('user.dashboard');
    }
}
