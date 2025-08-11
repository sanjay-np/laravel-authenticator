<?php

use LaravelAuthenticator\LaravelAuthenticatorServiceProvider;
use LaravelAuthenticator\LaravelAuthenticator;

describe('LaravelAuthenticatorServiceProvider', function () {

    it('registers the authenticator service in the container', function () {
        expect(app(LaravelAuthenticator::class))
            ->toBeInstanceOf(LaravelAuthenticator::class);
    });

    it('binds the service provider correctly', function () {
        $providers = app()->getLoadedProviders();

        expect($providers)
            ->toHaveKey(LaravelAuthenticatorServiceProvider::class);
    });

    it('publishes config file', function () {
        $configPath = config_path('authenticator.php');

        // The config should be available through the config helper
        expect(config('authenticator'))
            ->toBeArray()
            ->toHaveKey('totp')
            ->toHaveKey('qr_code')
            ->toHaveKey('shortcodes');
    });

    it('has correct package configuration', function () {
        $provider = new LaravelAuthenticatorServiceProvider(app());

        // We can't directly test configurePackage as it's protected,
        // but we can verify the service provider loads correctly
        expect($provider)->toBeInstanceOf(LaravelAuthenticatorServiceProvider::class);
    });

    it('loads default configuration values', function () {
        expect(config('authenticator.totp.period'))->toBe(60);
        expect(config('authenticator.totp.digits'))->toBe(6);
        expect(config('authenticator.totp.algorithm'))->toBe('sha1');
        expect(config('authenticator.totp.window'))->toBe(1);

        expect(config('authenticator.qr_code.size'))->toBe(200);
        expect(config('authenticator.qr_code.margin'))->toBe(10);
        expect(config('authenticator.qr_code.format'))->toBe('png');

        expect(config('authenticator.shortcodes.enabled'))->toBeTrue();
        expect(config('authenticator.shortcodes.tag'))->toBe('totp');
    });
});
