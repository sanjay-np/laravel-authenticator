<?php

namespace LaravelAuthenticator;

use Illuminate\Support\Facades\Blade;
use LaravelAuthenticator\Services\ShortcodeService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAuthenticatorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-authenticator')
            ->hasconfigfile('authenticator')
            ->hasviews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-authenticator', function () {
            return new \LaravelAuthenticator\LaravelAuthenticator();
        });

        $this->app->singleton(ShortcodeService::class);
    }

    public function packageBooted(): void
    {
        // Register Blade directives
        $this->registerBladeDirectives();
    }

    protected function registerBladeDirectives(): void
    {
        if (!config('authenticator.shortcodes.enabled', true)) {
            return;
        }

        // Register @totp directive
        Blade::directive('totp', function ($expression) {
            return "<?php echo app('" . ShortcodeService::class . "')->render($expression); ?>";
        });
    }
}
