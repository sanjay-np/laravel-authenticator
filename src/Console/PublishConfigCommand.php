<?php

namespace LaravelAuthenticator\Console;

use Illuminate\Console\Command;

class PublishConfigCommand extends Command
{
    protected $signature = 'authenticator:publish-config';

    protected $description = 'Publish the Laravel Authenticator config file only';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--provider' => \LaravelAuthenticator\LaravelAuthenticatorServiceProvider::class,
            '--tag' => 'laravel-authenticator-config',
        ]);

        $this->info('Laravel Authenticator config published.');
        return self::SUCCESS;
    }
}