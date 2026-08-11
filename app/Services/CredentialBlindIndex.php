<?php

namespace App\Services;

use RuntimeException;

class CredentialBlindIndex
{
    public const DOMAIN_API_KEY = 'domain-api-key';

    public const WEBHOOK_SECRET = 'webhook-secret';

    public function hash(?string $value, string $purpose): ?string
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return null;
        }

        return hash_hmac('sha256', $purpose."\0".$normalized, $this->key());
    }

    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function key(): string
    {
        $configured = (string) config('credentials.lookup_key', '');

        if ($configured === '') {
            throw new RuntimeException('CREDENTIAL_LOOKUP_KEY or APP_KEY must be configured.');
        }

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('Credential lookup key contains invalid base64.');
            }

            return $decoded;
        }

        return $configured;
    }
}
