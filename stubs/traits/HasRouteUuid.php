<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait HasRouteUuid
 *
 * Implements the LGU Dual-Identifier Standard:
 * - Internal engine/database performance: Uses auto-incrementing BIGINT id as Primary Key.
 * - External security & privacy: Exposes an indexed UUID v4 for all public route URLs and APIs,
 *   preventing sequential ID enumeration, scrapers, and IDOR vulnerabilities.
 */
trait HasRouteUuid
{
    /**
     * Initialize the trait by ensuring UUID is set on new instances.
     */
    public function initializeHasRouteUuid(): void
    {
        if (empty($this->attributes['uuid'])) {
            $this->attributes['uuid'] = (string) Str::uuid();
        }
    }

    /**
     * Boot the trait to assign a UUID v4 on model creation if absent.
     */
    public static function bootHasRouteUuid(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Set the route key name for implicit route model binding to public UUID.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope query to find record by UUID.
     */
    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where('uuid', $uuid);
    }

    /**
     * Find model instance by UUID or return null.
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Find model instance by UUID or throw 404 ModelNotFoundException.
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }
}
