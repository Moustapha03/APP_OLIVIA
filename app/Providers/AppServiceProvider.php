<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Qr\PassSigner;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PassSigner::class, fn () => PassSigner::fromConfig());
        $this->app->singleton(PaymentGatewayManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super-admin holds every permission. This is the ONLY place where a
        // role name is checked: business code always checks permissions.
        // Returning null (not false) lets the normal checks run for others.
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        // Production: every absolute link (password reset, e-mail verification,
        // receipts) uses APP_URL in https, whatever Host header was received.
        if ($this->app->isProduction()) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }

        // 12 characters minimum; known leaked passwords refused
        // (haveibeenpwned range API, skipped in tests: no network).
        Password::defaults(function () {
            $rule = Password::min(12);

            return app()->runningUnitTests() ? $rule : $rule->uncompromised();
        });

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // 5 attempts per minute for one account from one IP, 20 per IP overall.
        RateLimiter::for('login', function (Request $request) {
            $identifier = PhoneNumber::normalize($request->string('login')->toString())
                ?? mb_strtolower($request->string('login')->toString());

            return [
                Limit::perMinute(5)->by('login:'.$identifier.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by('password-reset:'.$request->ip()));

        RateLimiter::for('purchase', fn (Request $request) => Limit::perMinute(10)->by('purchase:'.($request->user()->id ?? $request->ip())));

        RateLimiter::for('scan', fn (Request $request) => Limit::perMinute(60)->by('scan:'.($request->user()->id ?? $request->ip())));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by('api:'.($request->user()->id ?? $request->ip())));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by('webhooks:'.$request->ip()));
    }
}
