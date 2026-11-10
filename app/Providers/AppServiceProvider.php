<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(fn (User $user, string $token) =>
            app(\App\Domain\Mail\PasswordResetMail::class)->message($user, $token, app()->getLocale()));
        RateLimiter::for('spot-api', function (Request $request) {
            $user = $request->user();
            return Limit::perMinute($user ? 600 : 120)
                ->by($user ? 'user:'.$user->id : 'ip:'.$request->ip());
        });

        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->active) {
                return false;
            }

            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }
}
