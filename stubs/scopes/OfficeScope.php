<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Session;

/**
 * Scope OfficeScope
 *
 * Implements strict, fail-closed multi-department isolation for LGU applications.
 *
 * Security Principle:
 * - When an active office context is present in session, queries are strictly constrained to that office.
 * - In non-console web requests, if an active office context is missing for an office-scoped model,
 *   the query evaluates to FALSE (1 = 0) to prevent catastrophic cross-department data exposure.
 */
class OfficeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $activeOfficeId = Session::get('active_office_id');

        if (! empty($activeOfficeId)) {
            $builder->where($model->getTable() . '.office_id', '=', $activeOfficeId);

            return;
        }

        // Allow execution in CLI commands, seeders, queue workers, and test suites
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        // Fail-closed in authenticated web requests to prevent unbounded data leakage
        $builder->whereRaw('1 = 0');
    }
}
