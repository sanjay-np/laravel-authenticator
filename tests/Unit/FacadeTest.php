<?php

use LaravelAuthenticator\Facades\LaravelAuthenticator as LaravelAuthenticatorFacade;
use LaravelAuthenticator\LaravelAuthenticator;

describe('LaravelAuthenticator Facade', function () {

    it('resolves to the correct service class', function () {
        $facade = LaravelAuthenticatorFacade::getFacadeRoot();

        expect($facade)
            ->toBeInstanceOf(LaravelAuthenticator::class);
    });

    it('has the correct facade accessor', function () {
        $reflection = new ReflectionClass(LaravelAuthenticatorFacade::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        $accessor = $method->invoke(null);

        expect($accessor)
            ->toBe(LaravelAuthenticator::class);
    });

    it('can call methods through the facade', function () {
        $secret = LaravelAuthenticatorFacade::generateSecret();

        expect($secret)
            ->toBeString()
            ->not()->toBeEmpty()
            ->toMatch('/^[A-Z2-7]+$/');
    });

    it('facade methods return same types as direct class methods', function () {
        $directInstance = new LaravelAuthenticator();

        // Test generateSecret
        $directSecret = $directInstance->generateSecret();
        $facadeSecret = LaravelAuthenticatorFacade::generateSecret();

        expect(gettype($directSecret))->toBe(gettype($facadeSecret));
        expect($directSecret)->toMatch('/^[A-Z2-7]+$/');
        expect($facadeSecret)->toMatch('/^[A-Z2-7]+$/');

        // Test getCurrentCode (both should return 6-digit strings)
        $directCode = $directInstance->getCurrentCode($directSecret);
        $facadeCode = LaravelAuthenticatorFacade::getCurrentCode($facadeSecret);

        expect(gettype($directCode))->toBe(gettype($facadeCode));
        expect($directCode)->toHaveLength(6);
        expect($facadeCode)->toHaveLength(6);
    });

    it('maintains state correctly across facade calls', function () {
        $secret = LaravelAuthenticatorFacade::generateSecret();
        $code = LaravelAuthenticatorFacade::getCurrentCode($secret);
        $isValid = LaravelAuthenticatorFacade::verifyCode($secret, $code);

        expect($isValid)->toBeTrue();
    });
});
