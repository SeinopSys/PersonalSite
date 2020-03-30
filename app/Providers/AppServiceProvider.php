<?php

namespace App\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('uuid4', function ($attribute, $value, $parameters, $validator): bool {
            return preg_match('~^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$~i', $value);
        });
        Validator::extend('domain', function ($attribute, $value, $parameters, $validator): bool {
            return preg_match('~^[\da-z.-]{1,253}$~i', $value);
        });
        Validator::extend('subdomains', function ($attribute, $value, $parameters, $validator): bool {
            return preg_match('~^([\da-z-]+|[\da-z-.]+\.)$~m', $value);
        });
    }

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
}
