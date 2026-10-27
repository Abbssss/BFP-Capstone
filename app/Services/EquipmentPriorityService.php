<?php

namespace App\Services;

use App\Models\Equipment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EquipmentPriorityService
{
    public function assess(Equipment $equipment): array
    {
        $equipment->loadMissing('latestInspection', 'maintenances');

        $activeMaintenance = $equipment->maintenances
            ->reject(fn ($maintenance) => in_array($maintenance->status, ['Completed', 'Cancelled'], true));
        $scheduledDates = $activeMaintenance
            ->map(fn ($maintenance) => $maintenance->start_date ?? $maintenance->next_recommended_maintenance_date)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        if ($scheduledDates->isEmpty() && $equipment->last_maintenance_date && $equipment->maintenance_interval_days) {
            $scheduledDates = collect([
                Carbon::parse($equipment->last_maintenance_date)
                    ->addDays($equipment->maintenance_interval_days)
                    ->startOfDay(),
            ]);
        }

        $dueDate = $scheduledDates->sort()->first();
        $inspection = $equipment->latestInspection;
        $activityDates = collect([
            $inspection?->inspection_date,
            $equipment->last_inspection_date,
            $equipment->last_maintenance_date,
        ])
            ->merge($equipment->maintenances
                ->filter(fn ($maintenance) => $maintenance->status === 'Completed')
                ->map(fn ($maintenance) => $maintenance->completion_date ?? $maintenance->start_date))
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());
        $hasActivity = $activityDates->isNotEmpty();
        $lastActivityDate = $activityDates->isNotEmpty()
            ? $activityDates->max()
            : ($equipment->commission_date
                ? Carbon::parse($equipment->commission_date)->startOfDay()
                : null);
        $oneMonthThreshold = $lastActivityDate?->copy()->addMonthNoOverflow();
        $highPriorityThreshold = $oneMonthThreshold?->copy()->addDays(15);
        $findings = collect([
            $inspection?->safety_concerns,
            $inspection?->functional_test_result,
            $inspection?->condition_observed,
            $inspection?->defects_identified,
            $inspection?->outcome,
            $equipment->last_inspection_outcome,
        ])->filter()->implode(' ');
        $unsafeFinding = $this->containsCriticalFinding($findings);
        $unserviceable = in_array(strtolower((string) $equipment->status), ['out of service', 'retired'], true);

        if ($unserviceable || $unsafeFinding) {
            return $this->result('High', $unserviceable
                ? 'Equipment is marked '.$equipment->status.'.'
                : 'Inspection findings identify an unsafe, unfit, critical, or failed condition.', $dueDate, true);
        }

        $priority = $lastActivityDate === null || today()->lt($oneMonthThreshold)
            ? 'Low'
            : (today()->lt($highPriorityThreshold) ? 'Medium' : 'High');
        $reason = $lastActivityDate === null
            ? 'No inspection, maintenance, repair, or acquisition date is recorded.'
            : ($hasActivity
                ? 'Priority is based on the most recent inspection, maintenance, or repair.'
                : 'No inspection, maintenance, or repair recorded; priority is based on the acquisition date.');

        $inspectionFindings = collect([
            $inspection?->defects_identified,
            $inspection?->safety_concerns,
        ])->filter()->implode(' ');

        if ($inspectionFindings !== '' && ! $this->containsNegatedFinding($inspectionFindings)) {
            if ($priority === 'Low') {
                $priority = 'Medium';
                $reason = 'Inspection recorded findings that require attention.';
            }
        }

        return $this->result($priority, $reason, $dueDate, false);
    }

    public function summarize(Collection $equipment): array
    {
        $priorities = $equipment->map(fn (Equipment $item) => [
            'equipment' => $item,
            ...$this->assess($item),
        ]);

        return [
            'records' => $priorities,
            'counts' => [
                'High' => $priorities->where('priority', 'High')->count(),
                'Medium' => $priorities->where('priority', 'Medium')->count(),
                'Low' => $priorities->where('priority', 'Low')->count(),
            ],
        ];
    }

    public function nextInspectionDate(Equipment $equipment): ?Carbon
    {
        $inspectionDate = $equipment->next_inspection_date?->copy()->startOfDay();

        if ($this->assess($equipment)['priority'] !== 'High') {
            return $inspectionDate;
        }

        $automaticDate = today()->addWeek();

        return ! $inspectionDate || $inspectionDate->gt($automaticDate)
            ? $automaticDate
            : $inspectionDate;
    }

    private function result(string $priority, string $reason, ?Carbon $dueDate, bool $critical): array
    {
        return [
            'priority' => $priority,
            'reason' => $reason,
            'due_date' => $dueDate,
            'days_until_due' => $dueDate ? (int) today()->diffInDays($dueDate, false) : null,
            'critical' => $critical,
        ];
    }

    private function containsCriticalFinding(string $findings): bool
    {
        $hasCriticalTerm = preg_match('/\b(unsafe|unfit|critical|fail(?:ed|ure)?)\b/i', $findings) === 1;
        $negatedCriticalTerm = preg_match(
            '/\b(no|not|without|none)\b(?:\W+\w+){0,3}\W*\b(unsafe|unfit|critical|fail(?:ed|ure)?)\b/i',
            $findings
        ) === 1;

        return $hasCriticalTerm && ! $negatedCriticalTerm;
    }

    private function containsNegatedFinding(string $findings): bool
    {
        return preg_match(
            '/\b(no|not|without|none)\b(?:\W+\w+){0,4}\W*\b(defects?|findings?|concerns?|issues?|unsafe|unfit|critical|fail(?:ed|ure)?|damage)\b/i',
            $findings
        ) === 1;
    }
}
