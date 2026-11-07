<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service WorkingDaysService
 *
 * Implements ARTA (Anti-Red Tape Authority) RA 11032 SLA compliance calculations:
 * - Simple Transactions: Maximum 3 working days.
 * - Complex Transactions: Maximum 7 working days.
 * - Highly Technical Transactions: Maximum 20 working days.
 *
 * Business Rules:
 * - Working days strictly exclude Saturdays, Sundays, and official non-working holidays.
 * - Supports emergency work suspensions (e.g., typhoon signals, disaster declarations)
 *   by automatically extending active SLA target deadlines with an audit trail entry.
 */
class WorkingDaysService
{
    /**
     * Add working days to a start datetime, skipping weekends and declared holidays.
     */
    public function addWorkingDays(CarbonInterface $startDate, int $workingDays, ?int $officeId = null): Carbon
    {
        $current = Carbon::parse($startDate);

        if ($workingDays <= 0) {
            return $current;
        }

        $daysAdded = 0;

        while ($daysAdded < $workingDays) {
            $current->addDay();

            if ($this->isWorkingDay($current, $officeId)) {
                $daysAdded++;
            }
        }

        return $current;
    }

    /**
     * Add working hours to a start datetime (standard 8-hour government workday).
     */
    public function addWorkingHours(CarbonInterface $startDate, float|int $workingHours, ?int $officeId = null): Carbon
    {
        $workingDays = (int) ceil($workingHours / 8);

        return $this->addWorkingDays($startDate, $workingDays, $officeId);
    }

    /**
     * Check if a specific calendar date is an active working day for an LGU office.
     */
    public function isWorkingDay(CarbonInterface $date, ?int $officeId = null): bool
    {
        $dt = Carbon::parse($date);

        // Weekends (Saturday = 6, Sunday = 0) are non-working days
        if ($dt->isWeekend()) {
            return false;
        }

        // Check against statutory database holidays if table exists
        if (Schema::hasTable('office_holidays')) {
            $isHoliday = DB::table('office_holidays')
                ->where('holiday_date', $dt->toDateString())
                ->where(function ($query) use ($officeId) {
                    $query->whereNull('office_id'); // National or LGU-wide holiday
                    if ($officeId !== null) {
                        $query->orWhere('office_id', $officeId); // Office-specific holiday
                    }
                })
                ->exists();

            if ($isHoliday) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count working days elapsed between two dates.
     */
    public function countWorkingDays(CarbonInterface $startDate, CarbonInterface $endDate, ?int $officeId = null): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($start->greaterThan($end)) {
            return 0;
        }

        $workingDays = 0;
        $current = $start->copy();

        while ($current->lessThanOrEqualTo($end)) {
            if ($this->isWorkingDay($current, $officeId)) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Extend target SLA due date upon declaration of an emergency work suspension.
     */
    public function applyEmergencySuspension(
        string $documentModelClass,
        int|string $documentId,
        CarbonInterface $suspensionDate,
        string $suspensionReason,
        int $suspensionDays = 1
    ): void {
        $document = $documentModelClass::findOrFail($documentId);

        if (empty($document->sla_due_at)) {
            return;
        }

        $currentDue = Carbon::parse($document->sla_due_at);
        $newDue = $this->addWorkingDays($currentDue, $suspensionDays, $document->office_id ?? null);

        $document->update([
            'sla_due_at' => $newDue,
            'sla_notes'  => trim(($document->sla_notes ?? '') . " | Extended by {$suspensionDays} day(s) due to: {$suspensionReason}"),
        ]);
    }
}
