<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Class BaseGovernmentPolicy
 *
 * Base authorization policy providing reusable statutory and departmental
 * checks for Philippine Local Government Unit applications.
 */
abstract class BaseGovernmentPolicy
{
    use HandlesAuthorization;

    /**
     * Check if user is within the model's office scope or belongs to an oversight department.
     */
    protected function isWithinOfficeScope(object $user, Model $model, string $officeColumn = 'office_id'): bool
    {
        $oversightCodes = Config::get('lgu.office.oversight_offices', ['MACCO', 'MTO', 'MO', 'IAS', 'ICTO']);
        $userOfficeCode = $user->active_office?->code ?? null;

        if ($userOfficeCode && in_array($userOfficeCode, $oversightCodes, true)) {
            return true;
        }

        $sessionKey = Config::get('lgu.office.session_key', 'active_office_id');
        $userOfficeId = $user->{$sessionKey} ?? session($sessionKey);
        $recordOfficeId = $model->{$officeColumn} ?? null;

        return $userOfficeId !== null && (int) $userOfficeId === (int) $recordOfficeId;
    }

    /**
     * Validate monetary approval ceilings per Philippine LGU delegation standards.
     */
    protected function isWithinApprovalCeiling(object $user, float $amount): bool
    {
        $maxCeiling = (float) ($user->approval_limit_amount ?? 0.0);

        return $amount <= $maxCeiling;
    }

    /**
     * Helper to verify permissions, supporting both native checks and Spatie RBAC.
     */
    protected function hasPermission(object $user, string $permission): bool
    {
        if (method_exists($user, 'hasPermissionTo')) {
            return (bool) $user->hasPermissionTo($permission);
        }

        if (isset($user->permissions) && is_array($user->permissions)) {
            return (bool) ($user->permissions[$permission] ?? false);
        }

        return false;
    }
}
