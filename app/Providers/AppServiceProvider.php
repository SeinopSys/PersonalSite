<?php

namespace App\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment() !== 'production') {
            $this->app->register(IdeHelperServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Shared uploads folders. A plain "throttle:N,M" keys every route on the client alone, so they would all share
        // one counter and the many file requests of a gallery page would use up the page's allowance; named limiters
        // are counted separately. The files get a high allowance since one gallery loads dozens of them (thumbnails,
        // the viewer's pictures, a video's range requests).
        RateLimiter::for('shared-page', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('shared-zip', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('shared-files', fn (Request $request) => Limit::perMinute(1200)->by($request->ip()));

        Validator::extend('uuid4', function ($attribute, $value, $parameters, $validator): bool {
            return preg_match('~^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$~i', $value);
        });
        Validator::extend('domain', function ($attribute, $value, $parameters, $validator): bool {
            return preg_match('~^[\da-z.-]{1,253}$~i', $value);
        });
        Validator::extend('subdomains', function ($attribute, $value, $parameters, $validator): bool {
            return preg_match('~^([\da-z-]+|[\da-z.-]+\.)$~mi', $value);
        });

        Paginator::useBootstrap();

        Gate::define('viewApiDocs', fn () => true);
    }
}
