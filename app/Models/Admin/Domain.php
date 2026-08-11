<?php

namespace App\Models\Admin;

use App\Casts\EncryptedCredential;
use App\Data\AgentStatusResult;
use App\Models\Concerns\HasProtectedCredentials;
use App\Services\CredentialBlindIndex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Domain extends Model
{
    use HasProtectedCredentials;

    protected $table = 'domains';

    protected $guarded = [];

    protected $hidden = [
        'api_key',
        'api_key_lookup_hash',
    ];

    protected $casts = [
        'status' => 'integer',
        'last_seen_at' => 'datetime',
        'api_key' => EncryptedCredential::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Domain $domain) {
            if ($domain->isDirty('name') && $domain->name !== null && $domain->name !== '') {
                $domain->name = normalizeDomainName($domain->name);
            }
        });
    }

    protected function credentialBlindIndexes(): array
    {
        return [
            'api_key' => ['api_key_lookup_hash', CredentialBlindIndex::DOMAIN_API_KEY],
        ];
    }

    public function domainCategory(): BelongsTo
    {
        return $this->belongsTo(DomainCategory::class, 'domain_category_id');
    }

    public function persistAgentHealth(AgentStatusResult $result): bool
    {
        return $this->forceFill(
            $result->healthAttributes($this->last_seen_at)
        )->save();
    }

    /**
     * Case-insensitive hostname lookup (matches pending webhook normalization).
     */
    public static function findByNormalizedName(string $name): ?self
    {
        $normalized = normalizeDomainName($name);

        if ($normalized === '') {
            return null;
        }

        return static::query()->whereNormalizedName($normalized)->first();
    }

    /**
     * @param  Builder<Domain>  $query
     * @return Builder<Domain>
     */
    public function scopeWhereNormalizedName(Builder $query, string $name): Builder
    {
        $normalized = normalizeDomainName($name);

        if ($normalized === '') {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereRaw('LOWER(name) = ?', [$normalized]);
    }

    /**
     * @param  array<int, string>  $names  Hostnames or URLs (normalized before compare)
     * @param  Builder<Domain>  $query
     * @return Builder<Domain>
     */
    public function scopeWhereNormalizedNameIn(Builder $query, array $names): Builder
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(fn ($name) => normalizeDomainName($name), $names)
        )));

        if ($normalized === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn(DB::raw('LOWER(name)'), $normalized);
    }

    /**
     * @param  array<int, string>  $normalizedNames  Already normalized hostnames
     * @return \Illuminate\Support\Collection<int, Domain> keyed by normalized name
     */
    public static function collectionByNormalizedName(array $normalizedNames): \Illuminate\Support\Collection
    {
        $normalizedNames = array_values(array_unique(array_filter($normalizedNames)));

        if ($normalizedNames === []) {
            return collect();
        }

        return static::query()
            ->whereNormalizedNameIn($normalizedNames)
            ->get()
            ->keyBy(fn (Domain $domain) => normalizeDomainName($domain->name));
    }
}
