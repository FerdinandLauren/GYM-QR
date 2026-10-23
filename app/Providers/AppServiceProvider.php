<?php

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Support\AuditLogger;
use App\Support\JwtService;
use App\Support\LogOtpSender;
use App\Support\OtpSender;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpSender::class, LogOtpSender::class);
        $this->app->singleton(AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerJwtGuard();
        $this->configureRateLimiters();
    }

    private function registerJwtGuard(): void
    {
        Auth::extend('jwt', function (Application $app, string $name, array $config): JwtGuard {
            $provider = Auth::createUserProvider($config['provider'] ?? null);

            if (! $provider instanceof UserProvider) {
                throw new RuntimeException("Provider untuk guard {$name} tidak ditemukan.");
            }

            return new JwtGuard($app['request'], $provider, $app->make(JwtService::class));
        });
    }

    /**
     * Batas mengikuti AGENTS §6 dan PRD §4.2/FR-MEM-01.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('otp-request', fn (Request $request) => [
            Limit::perHour(5)->by('otp-phone:'.$this->phoneKey($request)),
            Limit::perHour(20)->by('otp-ip:'.$request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)
            ->by('otp-verify:'.$this->phoneKey($request).'|'.$request->ip()));

        RateLimiter::for('scan-qr', fn (Request $request) => Limit::perMinute(10)
            ->by('scan-qr:'.$this->actorKey($request)));

        RateLimiter::for('assign-locker', fn (Request $request) => Limit::perMinute(30)
            ->by('assign-locker:'.$this->actorKey($request)));

        RateLimiter::for('manual-checkin', fn (Request $request) => Limit::perHour(60)
            ->by('manual-checkin:'.$this->actorKey($request)));

        RateLimiter::for('staff-login', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by('staff-login-account:'.$this->phoneKey($request)),
            Limit::perMinutes(15, 5)->by('staff-login-ip:'.$request->ip()),
        ]);
    }

    private function phoneKey(Request $request): string
    {
        $phoneNumber = $request->input('phone_number');

        return is_string($phoneNumber) && $phoneNumber !== ''
            ? PhoneNumber::normalize($phoneNumber)
            : (string) $request->ip();
    }

    private function actorKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}
