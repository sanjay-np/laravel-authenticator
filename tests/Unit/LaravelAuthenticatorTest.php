<?php

use LaravelAuthenticator\Facades\LaravelAuthenticator;
use LaravelAuthenticator\LaravelAuthenticator as AuthenticatorService;

describe('LaravelAuthenticator', function () {

    beforeEach(function () {
        $this->authenticator = new AuthenticatorService();
    });

    it('can generate valid TOTP secret', function () {
        $secret = $this->authenticator->generateSecret();

        expect($secret)
            ->toBeString()
            ->not()->toBeEmpty()
            ->toMatch('/^[A-Z2-7]+$/'); // Base32 encoded secret
    });

    it('can create TOTP with secret', function () {
        $secret = $this->authenticator->generateSecret();
        $label = 'user@example.com';
        $issuer = 'MyApp';

        $totp = $this->authenticator->createTotp($secret, $label, $issuer);

        expect($totp)
            ->toBeInstanceOf(OTPHP\TOTP::class);

        expect($totp->getSecret())
            ->toBe($secret);

        expect($totp->getLabel())
            ->toBe($label);

        expect($totp->getIssuer())
            ->toBe($issuer);

        expect($totp->getPeriod())
            ->toBe(60); // From config

        expect($totp->getDigits())
            ->toBe(6); // From config
    });

    it('can generate current TOTP code', function () {
        $secret = $this->authenticator->generateSecret();

        $code = $this->authenticator->getCurrentCode($secret);

        expect($code)
            ->toBeString()
            ->toHaveLength(6)
            ->toMatch('/^\d{6}$/'); // Should be 6 digits
    });

    it('can verify correct TOTP code', function () {
        $secret = $this->authenticator->generateSecret();

        // Get the current code
        $currentCode = $this->authenticator->getCurrentCode($secret);

        // Verify the code
        $isValid = $this->authenticator->verifyCode($secret, $currentCode);

        expect($isValid)->toBeTrue();
    });

    it('can generate QR code PNG', function () {
        $secret = $this->authenticator->generateSecret();
        $label = 'user@example.com';
        $issuer = 'MyApp';

        $qrCode = $this->authenticator->generateQrCode($secret, $label, $issuer, 'png');

        expect($qrCode)
            ->toBeString()
            ->toStartWith('data:image/png;base64,');
    });

    it('can generate QR code SVG', function () {
        $secret = $this->authenticator->generateSecret();
        $label = 'user@example.com';
        $issuer = 'MyApp';

        $qrCode = $this->authenticator->generateQrCode($secret, $label, $issuer, 'svg');

        expect($qrCode)
            ->toBeString()
            ->toStartWith('data:image/svg+xml;base64,');
    });

    it('can parse valid QR content', function () {
        $qrContent = 'otpauth://totp/MyApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=MyApp&period=30&digits=6&algorithm=SHA1';

        $params = $this->authenticator->parseQrCode($qrContent);

        expect($params)
            ->toBeArray()
            ->toHaveKey('secret', 'JBSWY3DPEHPK3PXP')
            ->toHaveKey('label', 'MyApp:user@example.com')
            ->toHaveKey('issuer', 'MyApp')
            ->toHaveKey('algorithm', 'SHA1')
            ->toHaveKey('digits', 6)
            ->toHaveKey('period', 30);
    });

    it('can reject invalid TOTP code', function () {
        $secret = $this->authenticator->generateSecret();
        $invalidCode = '000000'; // Very unlikely to be the current code

        $isValid = $this->authenticator->verifyCode($secret, $invalidCode);

        // Note: There's a small chance this could fail if the actual code is 000000
        // In a real scenario, you'd want to test with a fixed secret and known timestamp
        expect($isValid)->toBeFalse();
    });

    it('throws exception for invalid QR code format', function () {
        expect(fn() => $this->authenticator->parseQrCode('invalid-format'))
            ->toThrow(InvalidArgumentException::class, 'Invalid TOTP QR code format');
    });

    it('can get provisioning URI', function () {
        $secret = $this->authenticator->generateSecret();
        $label = 'user@example.com';
        $issuer = 'MyApp';

        $uri = $this->authenticator->getProvisioningUri($secret, $label, $issuer);

        expect($uri)
            ->toBeString()
            ->toStartWith('otpauth://totp/')
            ->toContain(urlencode($label))
            ->toContain($secret)
            ->toContain("issuer=$issuer");
    });

    it('creates TOTP with default config values', function () {
        $secret = $this->authenticator->generateSecret();

        $totp = $this->authenticator->createTotp($secret);

        expect($totp->getPeriod())->toBe(60);
        expect($totp->getDigits())->toBe(6);
        expect($totp->getDigest())->toBe('sha1');
    });

    it('can verify code with custom window', function () {
        $secret = $this->authenticator->generateSecret();
        $currentCode = $this->authenticator->getCurrentCode($secret);

        // Test with window of 2
        $isValid = $this->authenticator->verifyCode($secret, $currentCode, 2);

        expect($isValid)->toBeTrue();
    });

    it('handles QR code generation with minimal parameters', function () {
        $secret = $this->authenticator->generateSecret();
        $label = 'user@example.com';

        $qrCode = $this->authenticator->generateQrCode($secret, $label);

        expect($qrCode)
            ->toBeString()
            ->toStartWith('data:image/png;base64,'); // Default format is PNG
    });

    it('parses QR code with minimal required parameters', function () {
        $qrContent = 'otpauth://totp/user@example.com?secret=JBSWY3DPEHPK3PXP';

        $params = $this->authenticator->parseQrCode($qrContent);

        expect($params)
            ->toBeArray()
            ->toHaveKey('secret', 'JBSWY3DPEHPK3PXP')
            ->toHaveKey('label', 'user@example.com')
            ->toHaveKey('issuer', null)
            ->toHaveKey('algorithm', 'SHA1') // Default
            ->toHaveKey('digits', 6) // Default
            ->toHaveKey('period', 30); // Default
    });
});

describe('LaravelAuthenticator Facade', function () {

    it('can access methods through facade', function () {
        $secret = LaravelAuthenticator::generateSecret();

        expect($secret)
            ->toBeString()
            ->not()->toBeEmpty();
    });

    it('facade returns same results as direct class usage', function () {
        $directSecret = (new AuthenticatorService())->generateSecret();
        $facadeSecret = LaravelAuthenticator::generateSecret();

        // Both should return valid secrets (though different ones)
        expect($directSecret)
            ->toBeString()
            ->toMatch('/^[A-Z2-7]+$/');

        expect($facadeSecret)
            ->toBeString()
            ->toMatch('/^[A-Z2-7]+$/');
    });
});
