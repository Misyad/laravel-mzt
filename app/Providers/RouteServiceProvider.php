<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/profil';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('legacy-login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('id_anggota')).'|'.$request->ip());
        });

        // Public "Cek Status KTA" — unauthenticated, so key strictly by IP.
        RateLimiter::for('kta-check', function (Request $request) {
            return Limit::perMinute((int) config('kta.rate_limit.check', 5))->by($request->ip());
        });

        RateLimiter::for('kta-verify', function (Request $request) {
            return Limit::perMinute((int) config('kta.rate_limit.verify', 15))->by($request->ip());
        });

        RateLimiter::for('kta-print-request', function (Request $request) {
            return Limit::perMinute((int) config('kta.rate_limit.print_request', 10))->by($request->ip());
        });

        RateLimiter::for('member-activation-check', function (Request $request) {
            return Limit::perMinute((int) config('member_onboarding.rate_limit.activation_check', 5))->by($request->ip());
        });

        RateLimiter::for('member-activation-verify', function (Request $request) {
            return Limit::perMinute((int) config('member_onboarding.rate_limit.activation_verify', 10))->by($request->ip());
        });

        RateLimiter::for('member-account-setup', function (Request $request) {
            return Limit::perMinute((int) config('member_onboarding.rate_limit.setup', 6))->by(($request->user()?->id ?: 'guest').'|'.$request->ip());
        });

        RateLimiter::for('member-password-forgot', function (Request $request) {
            return Limit::perMinute((int) config('member_onboarding.rate_limit.password_forgot', 5))->by($request->ip());
        });

        RateLimiter::for('member-password-reset', function (Request $request) {
            return Limit::perMinute((int) config('member_onboarding.rate_limit.password_reset', 8))->by($request->ip());
        });

        RateLimiter::for('member-applications', function (Request $request) {
            return Limit::perHour((int) config('member_onboarding.rate_limit.application', 5))->by($request->ip());
        });

        RateLimiter::for('applicant-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinute((int) config('member_onboarding.rate_limit.applicant_login', 5))->by($email.'|'.$request->ip());
        });

        RateLimiter::for('applicant-email', function (Request $request) {
            $applicationId = $request->session()->get('applicant_application_id', 'guest');

            return Limit::perMinute((int) config('member_onboarding.rate_limit.applicant_email', 6))->by($applicationId.'|'.$request->ip());
        });
    }
}
