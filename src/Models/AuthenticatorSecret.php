<?php

namespace LaravelAuthenticator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class AuthenticatorSecret extends Model
{
    protected $table = 'authenticator_secrets';

    protected $fillable = [
        'client_id',
        'label',
        'issuer',
        'secret',
        'algorithm',
        'digits',
        'period',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'digits' => 'integer',
        'period' => 'integer',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Encrypt/decrypt the secret
     */
    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Crypt::decryptString($value) : null,
            set: fn($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Scope to get active secrets only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get secrets for a specific user
     */
    public function scopeForClient($query, $user)
    {
        return $query->where('client_id', $user->id);
    }

    /**
     * Mark this secret as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Generate current TOTP code for this secret
     */
    public function getCurrentCode(): string
    {
        return app('laravel-authenticator')->getCurrentCode($this->secret);
    }

    /**
     * Verify a TOTP code against this secret
     */
    public function verifyCode(string $code, ?int $window = null): bool
    {
        $isValid = app('laravel-authenticator')->verifyCode($this->secret, $code, $window);

        if ($isValid) {
            $this->markAsUsed();
        }

        return $isValid;
    }

    /**
     * Generate QR code for this secret
     */
    public function generateQrCode(string $format = 'png'): string
    {
        return app('laravel-authenticator')->generateQrCode(
            $this->secret,
            $this->label,
            $this->issuer,
            $format
        );
    }

    /**
     * Get the provisioning URI for this secret
     */
    public function getProvisioningUri(): string
    {
        return app('laravel-authenticator')->getProvisioningUri(
            $this->secret,
            $this->label,
            $this->issuer
        );
    }
}
