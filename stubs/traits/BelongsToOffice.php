<?php

namespace App\Traits;

use App\Models\Office;
use App\Scopes\OfficeScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToOffice
 *
 * Automatically applies fail-closed OfficeScope to models and establishes
 * the belongsTo relationship with the LGU Office model.
 */
trait BelongsToOffice
{
    /**
     * Boot the trait and register the fail-closed OfficeScope.
     */
    protected static function bootBelongsToOffice(): void
    {
        static::addGlobalScope(new OfficeScope());

        static::creating(function ($model) {
            if (empty($model->office_id) && session()->has('active_office_id')) {
                $model->office_id = session('active_office_id');
            }
        });
    }

    /**
     * Relationship to the LGU Office.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
