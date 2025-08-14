<?php

namespace LaravelAuthenticator;

use Illuminate\Support\Facades\Auth;
use LaravelAuthenticator\Models\AuthenticatorSecret;
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
     * Get the secretId for a given client ID
     *
     * @param int $clientId
     * @return int
     *
     * */
    public function clientSecret(int $clientId): int
    {
        $clientSecret = AuthenticatorSecret::where('client_id', $clientId)->first();
        if ($clientSecret) {
            return $clientSecret->id;
        }
        /**
         *Generate a new one
         *
         * */
        $newClientSecret = $this->generateClientSecret($clientId);
        return $newClientSecret->id;
    }

    /**
     * Generate a new Client Secret
     *
     * @param int $clientId
     * @param string $label
     * @return AuthenticatorSecret
     *
     * */
    public function generateClientSecret(int $clientId, ?string $label = ''): AuthenticatorSecret
    {
        $secret = $this->generateSecret();
        return AuthenticatorSecret::create([
            'client_id' => $clientId,
            'label' => $label ?? 'Authenticator Secret - ' . $clientId,
            'issuer' => Auth::user()?->email,
            'secret' => $secret,
            'algorithm' => config('authenticator.totp.algorithm', 'sha1'),
            'digits' => config('authenticator.totp.digits', 6),
            'period' => config('authenticator.totp.period', 60),
            'is_active' => true,
        ]);
    }

    /**
     * Get TOTP display data for a user's secret
     */
    public function getClientTotpDisplayData(int $secretId, array $options = []): array
    {
        $clientVerifiedSecret = AuthenticatorSecret::where('id', $secretId)->where('is_active', true)->first();

        if (!$clientVerifiedSecret) {
            throw new \InvalidArgumentException('TOTP secret not found for user');
        }

        $currentTime = now();
        $period = $clientVerifiedSecret->period;
        $expiresIn = $period - ($currentTime->timestamp % $period);

        $data = [
            'secretId' => $clientVerifiedSecret->id,
            'secret' => $options['includeSecret'] ?? false ? $clientVerifiedSecret->secret : null,
            'currentCode' => $this->getCurrentCode($clientVerifiedSecret->secret),
            'period' => $period,
            'expiresIn' => $expiresIn,
            'progressPercentage' => round((($period - $expiresIn) / $period) * 100, 2),
            'label' => $clientVerifiedSecret->label,
            'issuer' => $clientVerifiedSecret->issuer,
            'showCurrentCode' => $options['showCurrentCode'] ?? true,
            'showVerification' => $options['showVerification'] ?? false,
        ];

        return $data;
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

    public function verifyTotpCode(int $clientId, string $code)
    {
        if (!isset($clientId) || !isset($code)) {
            return false;
        }

        $clientSecret = AuthenticatorSecret::where('client_id', $clientId)->active()->first();
        if (!$clientSecret) {
            return false;
        }
        $isValid = $clientSecret->verifyCode($code);
        return $isValid;
    }

    /**
     * Verify TOTP code
     */
    public function verifyCode(string $secret, string $code, ?int $window = null): bool
    {
        $totp = $this->createTotp($secret);
        $window = $window ?? config('authenticator.totp.window', 1);
        return $totp->verify($code, null, $window);
    }
}
