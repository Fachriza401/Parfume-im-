<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureModelStrictness();
        $this->configurePasswordResetUrl();
    }

    /**
     * Rate limits for authentication endpoints, keyed by IP plus email where it
     * makes sense.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip().'|'.strtolower((string) $request->input('email'))));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip()));

        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip()));
    }

    /**
     * Fail loudly in development when a relation is accessed lazily, and prevent
     * a soft-deleted model from being silently used.
     */
    private function configureModelStrictness(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * The reset link is a mobile deep link, so no web password.reset route is
     * required for the notification to be built.
     */
    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $base = rtrim((string) config('app.frontend_url'), '/');

            return $base.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });
    }
}
