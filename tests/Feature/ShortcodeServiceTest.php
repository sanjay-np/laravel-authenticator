<?php

use LaravelAuthenticator\Services\ShortcodeService;
use LaravelAuthenticator\LaravelAuthenticator;
use Tests\TestCase;

it('renders error when required params missing', function () {
    $html = app(ShortcodeService::class)->render([]);

    expect($html)
        ->toContain('laravel-verified-error')
        ->toContain('Invalid shortcode parameters');
});

it('renders code display for a valid secret_id', function () {
    $service = new LaravelAuthenticator();
    $record = $service->generateClientSecret(1);

    $html = app(ShortcodeService::class)->render([
        'secret_id' => (string) $record->id,
        'display' => 'code',
        'show_timer' => 'true',
    ]);

    expect($html)
        ->toContain('laravel-verified-shortcode')
        ->toContain('totp-code-display')
        ->toContain('timer-text');
});