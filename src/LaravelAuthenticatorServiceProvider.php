<?php

namespace LaravelAuthenticator;

use Illuminate\Support\Facades\Blade;
use LaravelAuthenticator\Console\PublishConfigCommand;
use LaravelAuthenticator\Console\PublishMigrationCommand;
use LaravelAuthenticator\Services\ShortcodeService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAuthenticatorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-authenticator')
            ->hasConfigFile('authenticator')
            ->hasViews()
            ->hasMigration('create_authenticator_secrets_table')
            ->hasCommands([
                PublishMigrationCommand::class,
                PublishConfigCommand::class,
            ]);

        // Ensure publish tags are available for explicit commands
        $this->publishes([
            __DIR__.'/../config/authenticator.php' => config_path('authenticator.php'),
        ], 'laravel-authenticator-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_authenticator_secrets_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_authenticator_secrets_table.php'),
        ], 'laravel-authenticator-migrations');
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
