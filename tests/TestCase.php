<?php

namespace Tests;

use LaravelAuthenticator\LaravelAuthenticatorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('authenticator', [
            'totp' => [
                'period' => 60,
                'digits' => 6,
                'algorithm' => 'sha1',
                'window' => 1,
            ],
            'qr_code' => [
                'size' => 200,
                'margin' => 10,
                'format' => 'png',
            ],
            'shortcodes' => [
                'enabled' => true,
                'tag' => 'totp',
            ],
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelAuthenticatorServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Configure environment for testing
        $app['config']->set('database.default', 'testing');
    }
}
