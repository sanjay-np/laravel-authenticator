<?php

namespace LaravelAuthenticator;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use OTPHP\TOTP;

class LaravelAuthenticator
{
    /**
     * Generate a new TOTP secret
     */
    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    /**
     * Create TOTP instance from secret
     */
    public function createTotp(string $secret, ?string $label = null, ?string $issuer = null): TOTP
    {
        $totp = TOTP::createFromSecret($secret);

        if ($label) {
            $totp->setLabel($label);
        }

        if ($issuer) {
            $totp->setIssuer($issuer);
        }

        // Configure TOTP with settings from config
        $totp->setPeriod(config('authenticator.totp.period', 60));
        $totp->setDigits(config('authenticator.totp.digits', 6));
        $totp->setDigest(config('authenticator.totp.algorithm', 'sha1'));

        return $totp;
    }

    /**
     * Generate current TOTP code
     */
    public function getCurrentCode(string $secret): string
    {
        $totp = $this->createTotp($secret);
        return $totp->now();
    }

    /**
     * Verify TOTP code
     */
    public function verifyCode(string $secret, string $code, ?int $window = null): bool
    {
        $totp = $this->createTotp($secret);
        $window = $window ?? config('verified.totp.window', 1);

        return $totp->verify($code, null, $window);
    }

    /**
     * Generate QR code for TOTP
     */
    public function generateQrCode(string $secret, string $label, ?string $issuer = null, string $format = 'png'): string
    {
        $totp = $this->createTotp($secret, $label, $issuer);
        $provisioningUri = $totp->getProvisioningUri();

        $qrCode = QrCode::create($provisioningUri)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->setSize(config('authenticator.qr_code.size', 200))
            ->setMargin(config('authenticator.qr_code.margin', 10))
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        if ($format === 'svg') {
            $writer = new SvgWriter();
        } else {
            $writer = new PngWriter();
        }

        $result = $writer->write($qrCode);
        return $result->getDataUri();
    }

    /**
     * Get TOTP provisioning URI
     */
    public function getProvisioningUri(string $secret, string $label, ?string $issuer = null): string
    {
        $totp = $this->createTotp($secret, $label, $issuer);
        return $totp->getProvisioningUri();
    }

    /**
     * Parse QR code content and extract TOTP parameters
     */
    public function parseQrCode(string $content): array
    {
        // Parse otpauth:// URI
        if (!str_starts_with($content, 'otpauth://totp/')) {
            throw new \InvalidArgumentException('Invalid TOTP QR code format');
        }

        $parsed = parse_url($content);
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $label = urldecode(ltrim($parsed['path'], '/'));

        return [
            'secret' => $query['secret'] ?? null,
            'label' => $label,
            'issuer' => $query['issuer'] ?? null,
            'algorithm' => $query['algorithm'] ?? 'SHA1',
            'digits' => (int) ($query['digits'] ?? 6),
            'period' => (int) ($query['period'] ?? 30),
        ];
    }

    /**
     * Get TOTP display data for a user's secret
     */
    public function getTotpDisplayData($user, int $secretId, array $options = []): array
    {
        //todo
        $verifiedSecret = collect();

        $currentTime = now();
        $period = $verifiedSecret->period;
        $currentPeriod = floor($currentTime->timestamp / $period);
        $periodStartTime = $currentPeriod * $period;
        $expiresIn = $period - ($currentTime->timestamp % $period);

        $data = [
            'secretId' => $verifiedSecret->id,
            'secret' => $options['includeSecret'] ?? false ? $verifiedSecret->secret : null,
            'currentCode' => $this->getCurrentCode($verifiedSecret->secret),
            'period' => $period,
            'expiresIn' => $expiresIn,
            'progressPercentage' => round((($period - $expiresIn) / $period) * 100, 2),
            'label' => $verifiedSecret->label,
            'issuer' => $verifiedSecret->issuer,
            'showCurrentCode' => $options['showCurrentCode'] ?? true,
            'showVerification' => $options['showVerification'] ?? false,
        ];

        if ($options['includeQrCode'] ?? false) {
            $data['qrCode'] = $this->generateQrCode(
                $verifiedSecret->secret,
                $verifiedSecret->label,
                $verifiedSecret->issuer,
                $options['qrFormat'] ?? 'png'
            );
            $data['provisioningUri'] = $this->getProvisioningUri(
                $verifiedSecret->secret,
                $verifiedSecret->label,
                $verifiedSecret->issuer
            );
        }

        return $data;
    }
}
