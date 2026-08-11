<?php

namespace App\Console\Commands;

use App\Casts\EncryptedCredential;
use App\Services\CredentialBlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncryptStoredCredentials extends Command
{
    protected $signature = 'credentials:encrypt
                            {--dry-run : Inspect and report without writing}
                            {--chunk=200 : Rows processed per database chunk}';

    protected $description = 'Encrypt stored credentials and build blind lookup indexes';

    public function handle(CredentialBlindIndex $blindIndex): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);

        if ($chunkSize === false || $chunkSize < 1 || $chunkSize > 10000) {
            $this->error('--chunk must be an integer between 1 and 10000.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totals = ['rows' => 0, 'encrypted' => 0, 'hashed' => 0, 'unchanged' => 0, 'corrupt' => 0];

        $targets = [
            ['domains', 'api_key', 'api_key_lookup_hash', CredentialBlindIndex::DOMAIN_API_KEY],
            ['pending_domains', 'api_key', null, null],
            ['webhook_secrets', 'secret', 'secret_lookup_hash', CredentialBlindIndex::WEBHOOK_SECRET],
        ];

        foreach ($targets as [$table, $column, $hashColumn, $purpose]) {
            DB::table($table)
                ->select(array_values(array_filter(['id', $column, $hashColumn])))
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (
                    $blindIndex,
                    $column,
                    $dryRun,
                    $hashColumn,
                    $purpose,
                    $table,
                    &$totals
                ): void {
                    foreach ($rows as $row) {
                        $totals['rows']++;
                        $stored = $row->{$column};
                        $plain = $stored;
                        $encrypted = false;

                        if (EncryptedCredential::looksLikeEncryptedEnvelope($stored)) {
                            try {
                                $plain = Crypt::decryptString($stored);
                                $encrypted = true;
                            } catch (Throwable) {
                                $totals['corrupt']++;
                                $this->error("{$table} ID {$row->id}: likely encrypted envelope cannot be decrypted.");

                                continue;
                            }
                        }

                        $plain = $blindIndex->normalize($plain);
                        $updates = [];

                        if (! $encrypted && $plain !== null) {
                            $updates[$column] = Crypt::encryptString($plain);
                            $totals['encrypted']++;
                        } elseif (! $encrypted && $stored !== null && $plain === null) {
                            $updates[$column] = null;
                        }

                        if ($hashColumn !== null) {
                            $hash = $blindIndex->hash($plain, $purpose);

                            if ($row->{$hashColumn} !== $hash) {
                                $updates[$hashColumn] = $hash;
                                $totals['hashed']++;
                            }
                        }

                        if ($updates === []) {
                            $totals['unchanged']++;

                            continue;
                        }

                        if (! $dryRun) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                }, 'id');
        }

        $mode = $dryRun ? 'Dry run complete' : 'Conversion complete';
        $this->info(sprintf(
            '%s: %d rows, %d credential encryptions, %d hash updates, %d unchanged, %d corrupt.',
            $mode,
            $totals['rows'],
            $totals['encrypted'],
            $totals['hashed'],
            $totals['unchanged'],
            $totals['corrupt'],
        ));

        return $totals['corrupt'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
