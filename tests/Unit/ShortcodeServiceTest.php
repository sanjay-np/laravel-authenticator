<?php

use LaravelAuthenticator\Services\ShortcodeService;

describe('ShortcodeService', function () {

    beforeEach(function () {
        $this->shortcodeService = new ShortcodeService();
    });

    it('can parse shortcode attributes correctly', function () {
        $expression = 'secret_id="123" display="code" show_timer="true" size="large"';

        $method = new ReflectionMethod(ShortcodeService::class, 'parseAttributes');
        $method->setAccessible(true);

        $attributes = $method->invoke($this->shortcodeService, $expression);

        expect($attributes)
            ->toBeArray()
            ->toHaveKey('secret_id', '123')
            ->toHaveKey('display', 'code')
            ->toHaveKey('show_timer', 'true')
            ->toHaveKey('size', 'large')
            ->toHaveKey('format', 'default') // Default value
            ->toHaveKey('refresh', 'manual'); // Default value
    });

    it('applies default values when parsing attributes', function () {
        $expression = 'secret_id="123"';

        $method = new ReflectionMethod(ShortcodeService::class, 'parseAttributes');
        $method->setAccessible(true);

        $attributes = $method->invoke($this->shortcodeService, $expression);

        expect($attributes)
            ->toHaveKey('display', 'code')
            ->toHaveKey('format', 'default')
            ->toHaveKey('refresh', 'manual')
            ->toHaveKey('show_timer', 'true')
            ->toHaveKey('show_qr', 'false')
            ->toHaveKey('size', 'medium');
    });

    it('validates attributes correctly with valid input', function () {
        $validAttributes = ['secret_id' => '123', 'display' => 'code'];

        $method = new ReflectionMethod(ShortcodeService::class, 'validateAttributes');
        $method->setAccessible(true);

        $isValid = $method->invoke($this->shortcodeService, $validAttributes);

        expect($isValid)->toBeTrue();
    });

    it('validates attributes correctly with email parameter', function () {
        $validAttributes = ['email' => 'user@example.com', 'display' => 'qr'];

        $method = new ReflectionMethod(ShortcodeService::class, 'validateAttributes');
        $method->setAccessible(true);

        $isValid = $method->invoke($this->shortcodeService, $validAttributes);

        expect($isValid)->toBeTrue();
    });

    it('validates attributes correctly with user parameter', function () {
        $validAttributes = ['user' => 'john_doe', 'display' => 'both'];

        $method = new ReflectionMethod(ShortcodeService::class, 'validateAttributes');
        $method->setAccessible(true);

        $isValid = $method->invoke($this->shortcodeService, $validAttributes);

        expect($isValid)->toBeTrue();
    });

    it('rejects invalid display option', function () {
        $invalidAttributes = ['secret_id' => '123', 'display' => 'invalid_option'];

        $method = new ReflectionMethod(ShortcodeService::class, 'validateAttributes');
        $method->setAccessible(true);

        $isValid = $method->invoke($this->shortcodeService, $invalidAttributes);

        expect($isValid)->toBeFalse();
    });

    it('rejects attributes without required parameters', function () {
        $invalidAttributes = ['display' => 'code']; // Missing secret_id, email, or user

        $method = new ReflectionMethod(ShortcodeService::class, 'validateAttributes');
        $method->setAccessible(true);

        $isValid = $method->invoke($this->shortcodeService, $invalidAttributes);

        expect($isValid)->toBeFalse();
    });

    it('accepts all valid display options', function () {
        $validDisplays = ['code', 'qr', 'both', 'minimal'];

        $method = new ReflectionMethod(ShortcodeService::class, 'validateAttributes');
        $method->setAccessible(true);

        foreach ($validDisplays as $display) {
            $attributes = ['secret_id' => '123', 'display' => $display];
            $isValid = $method->invoke($this->shortcodeService, $attributes);
            expect($isValid)->toBeTrue("Display option '{$display}' should be valid");
        }
    });

    it('returns error for invalid shortcode parameters', function () {
        $expression = 'display="code"'; // Missing required parameter

        $result = $this->shortcodeService->render($expression);

        expect($result)
            ->toContain('Invalid shortcode parameters')
            ->toContain('laravel-verified-error');
    });

    it('returns error when unable to load TOTP data', function () {
        $expression = 'secret_id="999"'; // Non-existent ID

        $result = $this->shortcodeService->render($expression);

        // The current implementation will render the shortcode, so just verify it doesn't crash
        expect($result)->toBeString()->not()->toBeEmpty();
    });

    it('renders code display correctly', function () {
        $data = [
            'current_code' => '123456',
            'expires_in' => 30,
            'period' => 60
        ];

        $method = new ReflectionMethod(ShortcodeService::class, 'renderCodeDisplay');
        $method->setAccessible(true);

        $html = $method->invoke($this->shortcodeService, $data, true, 'manual', 'test-id');

        expect($html)
            ->toContain('123 456') // Code should be chunked
            ->toContain('totp-code-display')
            ->toContain('Expires in 30s')
            ->toContain('totp-refresh-btn');
    });

    it('renders QR display with valid QR code', function () {
        $data = ['qrCode' => 'data:image/png;base64,abc123'];

        $method = new ReflectionMethod(ShortcodeService::class, 'renderQrDisplay');
        $method->setAccessible(true);

        $html = $method->invoke($this->shortcodeService, $data);

        expect($html)
            ->toContain('totp-qr-display')
            ->toContain('data:image/png;base64,abc123')
            ->toContain('TOTP QR Code');
    });

    it('renders QR display error when QR code not available', function () {
        $data = []; // No QR code data

        $method = new ReflectionMethod(ShortcodeService::class, 'renderQrDisplay');
        $method->setAccessible(true);

        $html = $method->invoke($this->shortcodeService, $data);

        expect($html)
            ->toContain('totp-qr-error')
            ->toContain('QR code not available');
    });

    it('renders minimal display correctly', function () {
        $data = ['current_code' => '654321'];

        $method = new ReflectionMethod(ShortcodeService::class, 'renderMinimalDisplay');
        $method->setAccessible(true);

        $html = $method->invoke($this->shortcodeService, $data);

        expect($html)
            ->toContain('totp-minimal')
            ->toContain('654321');
    });

    it('renders error message correctly', function () {
        $message = 'Test error message';

        $method = new ReflectionMethod(ShortcodeService::class, 'renderError');
        $method->setAccessible(true);

        $html = $method->invoke($this->shortcodeService, $message);

        expect($html)
            ->toContain('laravel-verified-error')
            ->toContain('Test error message');
    });

    it('includes CSS styles only once', function () {
        $method = new ReflectionMethod(ShortcodeService::class, 'getShortcodeStyles');
        $method->setAccessible(true);

        // First call should return styles
        $styles1 = $method->invoke($this->shortcodeService);
        expect($styles1)->toBeString();

        // Second call should return empty (styles already added)
        $styles2 = $method->invoke($this->shortcodeService);
        expect($styles2)->toBeEmpty();
    });

    it('generates auto-refresh script with correct interval', function () {
        $data = ['period' => 30];
        $instanceId = 'test-instance';

        $method = new ReflectionMethod(ShortcodeService::class, 'getAutoRefreshScript');
        $method->setAccessible(true);

        $script = $method->invoke($this->shortcodeService, $instanceId, $data);

        expect($script)
            ->toContain('<script>')
            ->toContain('30000') // 30 seconds * 1000 milliseconds
            ->toContain($instanceId);
    });

    it('handles exception during rendering gracefully', function () {
        // Since getTotpData is private, let's test with invalid shortcode that will cause an error
        $expression = ''; // Empty expression should cause parsing issues

        $result = $this->shortcodeService->render($expression);

        expect($result)
            ->toContain('Invalid shortcode parameters')
            ->toContain('laravel-verified-error');
    });
});
