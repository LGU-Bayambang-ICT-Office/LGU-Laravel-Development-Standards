<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Lgu\GovSuite\Scopes\OfficeScope;

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
        static::addGlobalScope(new OfficeScope);

        static::creating(function ($model) {
            $sessionKey = Config::get('lgu.office.session_key', 'active_office_id');
            if (empty($model->office_id) && Session::has($sessionKey)) {
                $model->office_id = Session::get($sessionKey);
            }
        });
    }

    /**
     * Relationship to the LGU Office.
     */
    public function office(): BelongsTo
    {
        $officeModel = Config::get('lgu.office.model', 'App\\Models\\Office');

        return $this->belongsTo($officeModel);
    }
}
