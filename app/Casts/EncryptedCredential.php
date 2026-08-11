<?php

namespace App\Casts;

use App\Services\CredentialBlindIndex;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Laravel-compatible encrypted string cast with temporary plaintext-read
 * support for rolling conversion of existing rows.
 */
class EncryptedCredential implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::looksLikeEncryptedEnvelope((string) $value)) {
            return (string) $value;
        }

        return Crypt::decryptString((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $normalized = app(CredentialBlindIndex::class)->normalize(
            $value === null ? null : (string) $value
        );

        if ($normalized === null) {
            return null;
        }

        if (self::looksLikeEncryptedEnvelope($normalized)) {
            try {
                Crypt::decryptString($normalized);

                return $normalized;
            } catch (DecryptException) {
                // A malformed envelope must not be encrypted again and hidden.
                throw new DecryptException("Invalid encrypted credential for {$key}.");
            }
        }

        return Crypt::encryptString($normalized);
    }

    public static function looksEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac'])
            && is_string($payload['iv'])
            && is_string($payload['value'])
            && is_string($payload['mac']);
    }

    public static function looksLikeEncryptedEnvelope(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && (
                array_key_exists('iv', $payload)
                || array_key_exists('value', $payload)
                || array_key_exists('mac', $payload)
                || array_key_exists('tag', $payload)
            );
    }
}
