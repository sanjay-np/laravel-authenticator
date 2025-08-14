<?php

use LaravelAuthenticator\LaravelAuthenticator;
use LaravelAuthenticator\Models\AuthenticatorSecret;
use Tests\TestCase;

it('generates a TOTP secret', function () {
    $service = new LaravelAuthenticator();
    $secret = $service->generateSecret();

    expect($secret)->toBeString()->not->toBe('');
});

it('creates a client secret record with defaults', function () {
    $service = new LaravelAuthenticator();

    $record = $service->generateClientSecret(123);

    expect($record)
        ->toBeInstanceOf(AuthenticatorSecret::class)
        ->and($record->client_id)->toBe(123)
        ->and($record->label)->toBe('Authenticator Secret - 123')
        ->and($record->algorithm)->toBe(config('authenticator.totp.algorithm'))
        ->and($record->digits)->toBe(config('authenticator.totp.digits'))
        ->and($record->period)->toBe(config('authenticator.totp.period'))
        ->and($record->is_active)->toBeTrue();
});

it('returns same secret id for existing client and creates new when missing', function () {
    $service = new LaravelAuthenticator();

    $first = $service->generateClientSecret(999);
    $id1 = $service->clientSecret(999);
    $id2 = $service->clientSecret(999); // should not create new

    expect($id1)->toBe($first->id)
        ->and($id2)->toBe($first->id)
        ->and(AuthenticatorSecret::where('client_id', 999)->count())->toBe(1);

    $newId = $service->clientSecret(1000);
    expect($newId)->toBeInt()->and($newId)->not->toBe($id1);
});

it('creates a TOTP instance with configured options', function () {
    $service = new LaravelAuthenticator();
    $secret = $service->generateSecret();

    $totp = $service->createTotp($secret, 'Label', 'Issuer');

    expect($totp->getLabel())->toBe('Label')
        ->and($totp->getIssuer())->toBe('Issuer')
        ->and($totp->getPeriod())->toBe(config('authenticator.totp.period'))
        ->and($totp->getDigits())->toBe(config('authenticator.totp.digits'));
});

it('verifies a correct code and rejects incorrect code', function () {
    $service = new LaravelAuthenticator();
    $secret = $service->generateSecret();

    $code = $service->getCurrentCode($secret);

    // correct code
    expect($service->verifyCode($secret, $code))->toBeTrue();

    // obviously wrong
    expect($service->verifyCode($secret, '000000'))->toBeFalse();
});

it('verifies code via model and marks secret as used', function () {
    $service = new LaravelAuthenticator();
    $record = $service->generateClientSecret(777, 'Test');

    $code = $service->getCurrentCode($record->secret);

    $before = $record->last_used_at;
    $valid = $record->verifyCode($code);
    $record->refresh();

    expect($valid)->toBeTrue()
        ->and($record->last_used_at)->not->toBeNull();
});

it('getClientTotpDisplayData returns correct structure', function () {
    $service = new LaravelAuthenticator();
    $record = $service->generateClientSecret(321, 'User321');

    $data = $service->getClientTotpDisplayData($record->id);

    expect($data)
        ->toHaveKeys(['secretId','currentCode','period','expiresIn','progressPercentage','label','issuer','showCurrentCode','showVerification'])
        ->and($data['secretId'])->toBe($record->id)
        ->and($data['period'])->toBe(config('authenticator.totp.period'));
});

it('verifyTotpCode uses client id lookup and window', function () {
    $service = new LaravelAuthenticator();
    $record = $service->generateClientSecret(222);

    $code = $service->getCurrentCode($record->secret);

    expect($service->verifyTotpCode(222, $code))->toBeTrue()
        ->and($service->verifyTotpCode(222, '123456'))->toBeFalse();
});