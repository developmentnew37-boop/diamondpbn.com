<?php

namespace App\Services;

use App\Models\Admin\WebhookRotationSetting;
use App\Models\Admin\WebhookSecret;
use Illuminate\Support\Collection;

class WebhookSecretRotationService
{
    public function rotationHours(): int
    {
        return WebhookRotationSetting::current()->rotation_hours;
    }

    public function isAutoRotationEnabled(): bool
    {
        return $this->rotationHours() > 0;
    }

    /**
     * Active secrets whose token is older than the configured interval.
     *
     * @return Collection<int, WebhookSecret>
     */
    public function secretsDueForRotation(): Collection
    {
        $hours = $this->rotationHours();

        if ($hours <= 0) {
            return collect();
        }

        $cutoff = now()->subHours($hours);

        return WebhookSecret::query()
            ->where('is_active', true)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('secret_rotated_at')
                    ->orWhere('secret_rotated_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{rotated: int, skipped: int, rotation_hours: int}
     */
    public function rotateDueSecrets(): array
    {
        $hours = $this->rotationHours();

        if ($hours <= 0) {
            return ['rotated' => 0, 'skipped' => 0, 'rotation_hours' => 0];
        }

        $due = $this->secretsDueForRotation();
        $rotated = 0;

        foreach ($due as $secret) {
            $secret->rotateSecret();
            $rotated++;
        }

        return [
            'rotated' => $rotated,
            'skipped' => WebhookSecret::query()->where('is_active', true)->count() - $rotated,
            'rotation_hours' => $hours,
        ];
    }
}
