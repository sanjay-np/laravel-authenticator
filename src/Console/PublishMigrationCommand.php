<?php

namespace LaravelAuthenticator\Console;

use Illuminate\Console\Command;

class PublishMigrationCommand extends Command
{
    protected $signature = 'authenticator:publish-migration';

    protected $description = 'Publish the Laravel Authenticator migration file';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--provider' => \LaravelAuthenticator\LaravelAuthenticatorServiceProvider::class,
            '--tag' => 'laravel-authenticator-migrations',
        ]);

        $this->info('Laravel Authenticator migration published.');
        return self::SUCCESS;
    }
}