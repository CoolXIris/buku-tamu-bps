<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login', [
            'googleConfigured' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect'))
                && count(config('services.google.admin_emails', [])) > 0,
        ]);
    }

    public function redirectToGoogle(): RedirectResponse
    {
        abort_unless($this->googleConfigured(), 503, 'Login Google belum dikonfigurasi.');

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        abort_unless($this->googleConfigured(), 503, 'Login Google belum dikonfigurasi.');

        $googleUser = Socialite::driver('google')->user();
        $email = strtolower((string) $googleUser->getEmail());
        $verified = filter_var($googleUser->user['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
        $allowedEmails = config('services.google.admin_emails', []);

        abort_unless($verified && in_array($email, $allowedEmails, true), 403, 'Akun Google ini tidak memiliki akses admin.');

        $admin = User::firstOrNew(['email' => $email]);
        $admin->name = $googleUser->getName() ?: $email;
        $admin->email_verified_at ??= Carbon::now();
        $admin->password ??= Str::random(48);
        $admin->save();

        Auth::login($admin);
        request()->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'))
            && count(config('services.google.admin_emails', [])) > 0;
    }
}
