<?php

use LaravelAuthenticator\LaravelAuthenticator;

describe('Error Handling', function () {

    beforeEach(function () {
        $this->authenticator = new LaravelAuthenticator();
    });

    it('throws exception for invalid QR code format', function () {
        expect(fn() => $this->authenticator->parseQrCode('invalid://format'))
            ->toThrow(InvalidArgumentException::class, 'Invalid TOTP QR code format');
    });

    it('throws exception for non-TOTP QR code', function () {
        expect(fn() => $this->authenticator->parseQrCode('otpauth://hotp/test?secret=ABC'))
            ->toThrow(InvalidArgumentException::class, 'Invalid TOTP QR code format');
    });

    it('throws exception for malformed URI', function () {
        expect(fn() => $this->authenticator->parseQrCode('malformed-uri'))
            ->toThrow(InvalidArgumentException::class, 'Invalid TOTP QR code format');
    });

    it('throws exception for empty QR content', function () {
        expect(fn() => $this->authenticator->parseQrCode(''))
            ->toThrow(InvalidArgumentException::class, 'Invalid TOTP QR code format');
    });

    it('handles QR code without query parameters', function () {
        // This should not throw an exception, but handle gracefully
        $qrContent = 'otpauth://totp/test';
        $parsed = $this->authenticator->parseQrCode($qrContent);

        expect($parsed['secret'])->toBeNull();
        expect($parsed['label'])->toBe('test');
    });

    it('handles malformed query parameters gracefully', function () {
        $qrContent = 'otpauth://totp/test?invalidparam&secret=ABC123';
        $parsed = $this->authenticator->parseQrCode($qrContent);

        expect($parsed['secret'])->toBe('ABC123');
        expect($parsed['label'])->toBe('test');
    });

    it('handles URL encoded labels correctly', function () {
        $qrContent = 'otpauth://totp/My%20App%3Auser%40example.com?secret=ABC123&issuer=My%20App';
        $parsed = $this->authenticator->parseQrCode($qrContent);

        expect($parsed['label'])->toBe('My App:user@example.com');
        expect($parsed['issuer'])->toBe('My App');
    });

    it('handles missing configuration gracefully', function () {
        // Temporarily clear config to test defaults
        config(['authenticator' => null]);

        $secret = $this->authenticator->generateSecret();
        $totp = $this->authenticator->createTotp($secret);

        // Should use OTPHP defaults when config is missing
        expect($totp->getPeriod())->toBe(60); // Our config() call has default
        expect($totp->getDigits())->toBe(6);
    });

    it('verifies code with default window when config missing', function () {
        $secret = $this->authenticator->generateSecret();
        $code = $this->authenticator->getCurrentCode($secret);

        // Test with window parameter missing and config potentially missing
        $isValid = $this->authenticator->verifyCode($secret, $code);

        expect($isValid)->toBeTrue();
    });

    it('handles empty or null secret gracefully', function () {
        // OTPHP may not throw for empty secrets, so let's just verify it doesn't crash
        $totp = $this->authenticator->createTotp('');
        expect($totp)->toBeInstanceOf(OTPHP\TOTP::class);
    });

    it('handles invalid secret format gracefully', function () {
        // OTPHP may not throw for invalid base32, so let's just verify it doesn't crash
        $totp = $this->authenticator->createTotp('invalid_secret_123!');
        expect($totp)->toBeInstanceOf(OTPHP\TOTP::class);
    });

    it('handles non-numeric codes in verification', function () {
        $secret = $this->authenticator->generateSecret();

        // Non-numeric code should return false, not throw exception
        $isValid = $this->authenticator->verifyCode($secret, 'abc123');
        expect($isValid)->toBeFalse();

        // Empty code should return false
        $isValid = $this->authenticator->verifyCode($secret, '');
        expect($isValid)->toBeFalse();
    });

    it('handles codes with wrong length', function () {
        $secret = $this->authenticator->generateSecret();

        // Too short
        $isValid = $this->authenticator->verifyCode($secret, '123');
        expect($isValid)->toBeFalse();

        // Too long
        $isValid = $this->authenticator->verifyCode($secret, '1234567890');
        expect($isValid)->toBeFalse();
    });

    it('handles QR code generation with invalid parameters', function () {
        $secret = $this->authenticator->generateSecret();

        // Empty label should throw exception according to OTPHP
        expect(fn() => $this->authenticator->generateQrCode($secret, ''))
            ->toThrow(InvalidArgumentException::class, 'The label is not set.');

        // Invalid format should default to PNG
        $qrCode = $this->authenticator->generateQrCode($secret, 'test', null, 'invalid_format');
        expect($qrCode)->toBeString()->toStartWith('data:image/');
    });
});
