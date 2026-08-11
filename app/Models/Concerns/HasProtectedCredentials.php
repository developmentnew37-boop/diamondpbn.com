<?php

namespace App\Models\Concerns;

use App\Services\CredentialBlindIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait HasProtectedCredentials
{
    public static function bootHasProtectedCredentials(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->credentialBlindIndexes() as $credential => [$hashColumn, $purpose]) {
                if (! $model->isDirty($credential) || ! Schema::hasColumn($model->getTable(), $hashColumn)) {
                    continue;
                }

                $model->setAttribute(
                    $hashColumn,
                    app(CredentialBlindIndex::class)->hash($model->getAttribute($credential), $purpose)
                );
            }
        });
    }

    /**
     * @return array<string, array{string, string}>
     */
    protected function credentialBlindIndexes(): array
    {
        return [];
    }
}
