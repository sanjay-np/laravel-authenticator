<?php

namespace LaravelAuthenticator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class AuthenticatorSecret extends Model
{
    use SoftDeletes;

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
     * Mark this secret as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
