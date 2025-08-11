<?php

use LaravelAuthenticator\Facades\LaravelAuthenticator;
use OTPHP\TOTP;

describe('Authenticator Integration', function () {

    it('can perform complete TOTP workflow', function () {
        // Step 1: Generate a secret
        $secret = LaravelAuthenticator::generateSecret();
        expect($secret)->toBeString()->not()->toBeEmpty();

        // Step 2: Create TOTP instance with user details
        $label = 'user@example.com';
        $issuer = 'TestApp';
        $totp = LaravelAuthenticator::createTotp($secret, $label, $issuer);

        expect($totp)->toBeInstanceOf(TOTP::class);
        expect($totp->getLabel())->toBe($label);
        expect($totp->getIssuer())->toBe($issuer);

        // Step 3: Generate provisioning URI
        $uri = LaravelAuthenticator::getProvisioningUri($secret, $label, $issuer);
        expect($uri)->toStartWith('otpauth://totp/');

        // Step 4: Generate QR code for user to scan
        $qrCodePng = LaravelAuthenticator::generateQrCode($secret, $label, $issuer, 'png');
        $qrCodeSvg = LaravelAuthenticator::generateQrCode($secret, $label, $issuer, 'svg');

        expect($qrCodePng)->toStartWith('data:image/png;base64,');
        expect($qrCodeSvg)->toStartWith('data:image/svg+xml;base64,');

        // Step 5: Simulate user entering current code
        $currentCode = LaravelAuthenticator::getCurrentCode($secret);
        expect($currentCode)->toMatch('/^\d{6}$/');

        // Step 6: Verify the code
        $isValid = LaravelAuthenticator::verifyCode($secret, $currentCode);
        expect($isValid)->toBeTrue();

        // Step 7: Parse the QR content back to verify parameters
        $parsedParams = LaravelAuthenticator::parseQrCode($uri);
        expect($parsedParams['secret'])->toBe($secret);
        expect($parsedParams['label'])->toBe("$issuer:$label");
        expect($parsedParams['issuer'])->toBe($issuer);
    });

    it('handles TOTP verification with time window correctly', function () {
        $secret = LaravelAuthenticator::generateSecret();
        $currentCode = LaravelAuthenticator::getCurrentCode($secret);

        // Test with different window sizes
        expect(LaravelAuthenticator::verifyCode($secret, $currentCode, 0))->toBeTrue();
        expect(LaravelAuthenticator::verifyCode($secret, $currentCode, 1))->toBeTrue();
        expect(LaravelAuthenticator::verifyCode($secret, $currentCode, 2))->toBeTrue();

        // Invalid code should fail regardless of window
        expect(LaravelAuthenticator::verifyCode($secret, '000000', 2))->toBeFalse();
    });

    it('generates consistent codes for same secret and time', function () {
        $secret = LaravelAuthenticator::generateSecret();

        // Multiple calls should return the same code (within the same time period)
        $code1 = LaravelAuthenticator::getCurrentCode($secret);
        $code2 = LaravelAuthenticator::getCurrentCode($secret);

        expect($code1)->toBe($code2);
    });

    it('generates different QR codes for different formats but same data', function () {
        $secret = LaravelAuthenticator::generateSecret();
        $label = 'user@example.com';
        $issuer = 'TestApp';

        $pngQr = LaravelAuthenticator::generateQrCode($secret, $label, $issuer, 'png');
        $svgQr = LaravelAuthenticator::generateQrCode($secret, $label, $issuer, 'svg');

        expect($pngQr)->toStartWith('data:image/png;base64,');
        expect($svgQr)->toStartWith('data:image/svg+xml;base64,');
        expect($pngQr)->not()->toBe($svgQr);
    });

    it('can roundtrip TOTP parameters through QR code parsing', function () {
        $secret = LaravelAuthenticator::generateSecret();
        $label = 'user@example.com';
        $issuer = 'TestApp';

        // Create provisioning URI
        $uri = LaravelAuthenticator::getProvisioningUri($secret, $label, $issuer);

        // Parse it back
        $parsed = LaravelAuthenticator::parseQrCode($uri);

        // Verify all parameters are preserved
        expect($parsed['secret'])->toBe($secret);
        expect($parsed['label'])->toBe("$issuer:$label");
        expect($parsed['issuer'])->toBe($issuer);
        expect($parsed['digits'])->toBe(6);
        expect($parsed['period'])->toBe(60); // From our config
        expect($parsed['algorithm'])->toBe('SHA1');
    });

    it('uses configuration values correctly', function () {
        // Test that our test configuration is being used
        $secret = LaravelAuthenticator::generateSecret();
        $totp = LaravelAuthenticator::createTotp($secret);

        expect($totp->getPeriod())->toBe(config('authenticator.totp.period'));
        expect($totp->getDigits())->toBe(config('authenticator.totp.digits'));
        expect($totp->getDigest())->toBe(config('authenticator.totp.algorithm'));
    });

    it('handles edge cases in QR code parsing', function () {
        // Test QR code with minimal parameters
        $minimalQr = 'otpauth://totp/simple?secret=JBSWY3DPEHPK3PXP';
        $parsed = LaravelAuthenticator::parseQrCode($minimalQr);

        expect($parsed['secret'])->toBe('JBSWY3DPEHPK3PXP');
        expect($parsed['label'])->toBe('simple');
        expect($parsed['issuer'])->toBeNull();
        expect($parsed['digits'])->toBe(6); // Default
        expect($parsed['period'])->toBe(30); // Default

        // Test QR code with all parameters
        $fullQr = 'otpauth://totp/MyApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=MyApp&period=60&digits=8&algorithm=SHA256';
        $parsed = LaravelAuthenticator::parseQrCode($fullQr);

        expect($parsed['secret'])->toBe('JBSWY3DPEHPK3PXP');
        expect($parsed['label'])->toBe('MyApp:user@example.com');
        expect($parsed['issuer'])->toBe('MyApp');
        expect($parsed['digits'])->toBe(8);
        expect($parsed['period'])->toBe(60);
        expect($parsed['algorithm'])->toBe('SHA256');
    });

    it('generates unique secrets', function () {
        $secrets = [];

        // Generate multiple secrets and ensure they're unique
        for ($i = 0; $i < 10; $i++) {
            $secret = LaravelAuthenticator::generateSecret();
            expect($secrets)->not()->toContain($secret);
            $secrets[] = $secret;
        }

        expect($secrets)->toHaveCount(10);
    });
});
