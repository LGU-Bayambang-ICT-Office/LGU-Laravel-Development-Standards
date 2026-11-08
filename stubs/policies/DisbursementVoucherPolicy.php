<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\DisbursementVoucher;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Standard Municipal Policy Example: Disbursement Voucher.
 *
 * Demonstrates:
 * 1. Native Policy abilities (viewAny, view, create, update, delete).
 * 2. Domain-specific municipal abilities (approve, certify, disburse, cancel).
 * 3. Multi-office departmental boundaries and COA segregation of duties.
 * 4. Optional integration with spatie/laravel-permission (permission-first, not role-first).
 */
class DisbursementVoucherPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any disbursement vouchers.
     */
    public function viewAny(User $user): bool
    {
        // Must have view permission and an active office session context
        return $this->hasPermission($user, 'vouchers.view-any')
            && $user->active_office_id !== null;
    }

    /**
     * Determine whether the user can view the specific disbursement voucher.
     */
    public function view(User $user, DisbursementVoucher $voucher): bool
    {
        if (! $this->hasPermission($user, 'vouchers.view')) {
            return false;
        }

        return $this->isWithinOfficeScope($user, $voucher);
    }

    /**
     * Determine whether the user can create disbursement vouchers.
     */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'vouchers.create')
            && $user->active_office_id !== null;
    }

    /**
     * Determine whether the user can update the voucher (Draft stage only).
     */
    public function update(User $user, DisbursementVoucher $voucher): bool
    {
        if (! $this->hasPermission($user, 'vouchers.update')) {
            return false;
        }

        // COA rule: Once submitted or certified, draft edits are locked
        if (! in_array($voucher->status, ['DRAFT', 'RETURNED'], true)) {
            return false;
        }

        return $this->isWithinOfficeScope($user, $voucher);
    }

    /**
     * Determine whether the user can approve the disbursement voucher.
     */
    public function approve(User $user, DisbursementVoucher $voucher): bool
    {
        if (! $this->hasPermission($user, 'vouchers.approve')) {
            return false;
        }

        // State Machine: Must be pending approval
        if ($voucher->status !== 'PENDING_APPROVAL') {
            return false;
        }

        // COA Segregation of Duties (SoD): The creator/claimant cannot approve their own voucher
        if ($voucher->created_by_user_id === $user->id) {
            return false;
        }

        // Office Boundary: Approver must be in the originating office or an oversight body
        if (! $this->isWithinOfficeScope($user, $voucher)) {
            return false;
        }

        // Financial Delegation Ceilings (Local Government Code / Municipal Executive Order)
        return $this->isWithinApprovalCeiling($user, (float) $voucher->total_amount);
    }

    /**
     * Determine whether the user can disburse funds (Treasury Office only).
     */
    public function disburse(User $user, DisbursementVoucher $voucher): bool
    {
        if (! $this->hasPermission($user, 'vouchers.disburse')) {
            return false;
        }

        // State Machine: Must be fully approved and certified
        if ($voucher->status !== 'APPROVED') {
            return false;
        }

        // Departmental Boundary: Only Municipal Treasury Office (MTO) can release payments
        return $user->active_office?->code === 'MTO';
    }

    /**
     * Determine whether the user can cancel the voucher.
     */
    public function cancel(User $user, DisbursementVoucher $voucher): bool
    {
        if (! $this->hasPermission($user, 'vouchers.cancel')) {
            return false;
        }

        // Disbursed or liquidated vouchers cannot be cancelled
        if (in_array($voucher->status, ['DISBURSED', 'LIQUIDATED', 'CANCELLED'], true)) {
            return false;
        }

        return $this->isWithinOfficeScope($user, $voucher);
    }

    /**
     * Check if the user belongs to the voucher's office or has municipal oversight clearance.
     */
    protected function isWithinOfficeScope(User $user, DisbursementVoucher $voucher): bool
    {
        // Oversight departments (Accounting, Mayor's Office, Internal Audit) can review cross-office vouchers
        if (in_array($user->active_office?->code, ['MACCO', 'MO', 'IAS'], true)) {
            return true;
        }

        return $user->active_office_id === $voucher->originating_office_id;
    }

    /**
     * Validate monetary approval ceilings per Philippine LGU delegation standards.
     */
    protected function isWithinApprovalCeiling(User $user, float $amount): bool
    {
        $maxCeiling = (float) ($user->approval_limit_amount ?? 0.0);

        // Department Head ceiling (e.g., up to ₱100,000)
        // Municipal Administrator ceiling (e.g., up to ₱1,000,000)
        // Municipal Mayor ceiling (unlimited or > ₱1,000,000)
        return $amount <= $maxCeiling;
    }

    /**
     * Helper to verify permissions, supporting both native checks and Spatie RBAC.
     */
    protected function hasPermission(User $user, string $permission): bool
    {
        // If spatie/laravel-permission is installed, use hasPermissionTo()
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($permission);
        }

        // Native fallback or custom user attribute
        return (bool) ($user->permissions[$permission] ?? false);
    }
}
