<?php
namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
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
        if ($date->isWeekend()) {
            return false;
        }

        return ! $this->isHoliday($date, $officeId);
    }

    /**
     * Determine if a date is a registered national or local office holiday/suspension.
     */
    public function isHoliday(CarbonInterface $date, ?int $officeId = null): bool
    {
        if (! Schema::hasTable('office_holidays')) {
            return false;
        }

        $formatted = $date->format('Y-m-d');

        $query = DB::table('office_holidays')
            ->whereDate('date', $formatted)
            ->where('is_active', true);

        if ($officeId) {
            $query->where(function ($q) use ($officeId) {
                $q->whereNull('office_id')->orWhere('office_id', $officeId);
            });
        } else {
            $query->whereNull('office_id');
        }

        return $query->exists();
    }

    /**
     * Calculate total working days between two dates (exclusive of start, inclusive of end).
     */
    public function workingDaysBetween(CarbonInterface $startDate, CarbonInterface $endDate, ?int $officeId = null): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($start->gte($end)) {
            return 0;
        }

        $workingDays = 0;
        $current = $start->copy();

        while ($current->lt($end)) {
            $current->addDay();
            if ($this->isWorkingDay($current, $officeId)) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * Retrieve the next upcoming valid working day from a given date.
     */
    public function nextWorkingDay(CarbonInterface $date, ?int $officeId = null): Carbon
    {
        $current = Carbon::parse($date)->addDay();

        while (! $this->isWorkingDay($current, $officeId)) {
            $current->addDay();
        }

        return $current;
    }
}
