<?php

namespace App\Rules;

use App\Casts\EncryptedCredential;
use App\Services\CredentialBlindIndex;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class UniqueCredential implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $hashColumn,
        private readonly string $purpose,
        private readonly ?int $ignoreId = null,
        private readonly ?string $credentialColumn = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hash = app(CredentialBlindIndex::class)->hash(
            $value === null ? null : (string) $value,
            $this->purpose
        );

        if ($hash === null) {
            return;
        }

        $query = DB::table($this->table)->where($this->hashColumn, $hash);

        if ($this->ignoreId !== null) {
            $query->where('id', '<>', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail("The {$attribute} has already been taken.");

            return;
        }

        if ($this->credentialColumn === null) {
            return;
        }

        $normalized = app(CredentialBlindIndex::class)->normalize((string) $value);
        $legacyRows = DB::table($this->table)
            ->select(['id', $this->credentialColumn])
            ->whereNull($this->hashColumn)
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '<>', $this->ignoreId))
            ->cursor();

        foreach ($legacyRows as $row) {
            $stored = $row->{$this->credentialColumn};

            try {
                $plain = EncryptedCredential::looksLikeEncryptedEnvelope($stored)
                    ? Crypt::decryptString($stored)
                    : $stored;
            } catch (Throwable) {
                $fail("The {$attribute} cannot be safely validated until stored credentials are repaired.");

                return;
            }

            $existing = app(CredentialBlindIndex::class)->normalize($plain);
            if ($existing !== null && hash_equals($existing, $normalized)) {
                $fail("The {$attribute} has already been taken.");

                return;
            }
        }
    }
}
