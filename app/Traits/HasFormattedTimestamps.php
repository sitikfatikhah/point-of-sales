<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * Trait HasFormattedTimestamps
 *
 * Provides consistent timezone-aware timestamp formatting across models.
 * Ensures all timestamps are in Asia/Jakarta timezone.
 */
trait HasFormattedTimestamps
{
    /**
     * Get the created_at attribute with proper timezone.
     * Returns ISO 8601 format for frontend parsing.
     *
     * @param mixed $value
     * @return string|null
     */
    public function getCreatedAtAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone', 'Asia/Jakarta'))
            ->toIso8601String();
    }

    /**
     * Get the updated_at attribute with proper timezone.
     * Returns ISO 8601 format for frontend parsing.
     *
     * @param mixed $value
     * @return string|null
     */
    public function getUpdatedAtAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone', 'Asia/Jakarta'))
            ->toIso8601String();
    }

    /**
     * Get the raw created_at value as Carbon instance
     *
     * @return Carbon|null
     */
    public function getCreatedAtCarbonAttribute(): ?Carbon
    {
        $value = $this->attributes['created_at'] ?? null;

        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->timezone(config('app.timezone', 'Asia/Jakarta'));
    }

    /**
     * Get the raw updated_at value as Carbon instance
     *
     * @return Carbon|null
     */
    public function getUpdatedAtCarbonAttribute(): ?Carbon
    {
        $value = $this->attributes['updated_at'] ?? null;

        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->timezone(config('app.timezone', 'Asia/Jakarta'));
    }

    /**
     * Get created_at formatted in Indonesian format
     *
     * @return string
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        if (!isset($this->attributes['created_at']) || !$this->attributes['created_at']) {
            return '-';
        }

        return Carbon::parse($this->attributes['created_at'])
            ->timezone(config('app.timezone', 'Asia/Jakarta'))
            ->translatedFormat('d M Y H:i:s');
    }

    /**
     * Get updated_at formatted in Indonesian format
     *
     * @return string
     */
    public function getFormattedUpdatedAtAttribute(): string
    {
        if (!isset($this->attributes['updated_at']) || !$this->attributes['updated_at']) {
            return '-';
        }

        return Carbon::parse($this->attributes['updated_at'])
            ->timezone(config('app.timezone', 'Asia/Jakarta'))
            ->translatedFormat('d M Y H:i:s');
    }
}
