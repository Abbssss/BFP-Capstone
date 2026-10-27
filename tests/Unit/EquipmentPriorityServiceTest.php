<?php

namespace Tests\Unit;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Maintenance;
use App\Services\EquipmentPriorityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EquipmentPriorityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 12:00:00');
        config([
            'maintenance.priority_warning_days' => 30,
            'maintenance.priority_critical_overdue_days' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_priority_uses_one_month_and_one_month_fifteen_day_acquisition_thresholds(): void
    {
        $service = new EquipmentPriorityService;

        $low = $this->equipment(['commission_date' => today()->subMonthNoOverflow()->addDay()]);
        $medium = $this->equipment(['commission_date' => today()->subMonthNoOverflow()]);
        $mediumAtOneMonthAndFourteenDays = $this->equipment([
            'commission_date' => today()->subMonthNoOverflow()->subDays(14),
        ]);
        $high = $this->equipment(['commission_date' => today()->subMonthNoOverflow()->subDays(15)]);

        $this->assertSame('Low', $service->assess($low)['priority']);
        $this->assertSame('Medium', $service->assess($medium)['priority']);
        $this->assertSame('Medium', $service->assess($mediumAtOneMonthAndFourteenDays)['priority']);
        $this->assertSame('High', $service->assess($high)['priority']);
    }

    public function test_latest_inspection_maintenance_or_repair_resets_age_based_on_acquisition(): void
    {
        $service = new EquipmentPriorityService;
        $equipment = $this->equipment(['commission_date' => today()->subDays(90)]);
        $equipment->setRelation('latestInspection', new Inspection([
            'inspection_date' => today()->subDays(20),
        ]));
        $this->assertSame('Low', $service->assess($equipment)['priority']);

        $equipment->setRelation('latestInspection', null);
        $equipment->setRelation('maintenances', new Collection([
            new Maintenance([
                'status' => 'Completed',
                'completion_date' => today()->subDays(31),
            ]),
            new Maintenance([
                'status' => 'Completed',
                'completion_date' => today()->subDays(10),
            ]),
        ]));
        $this->assertSame('Low', $service->assess($equipment)['priority']);
    }

    public function test_unsafe_inspection_and_out_of_service_status_take_precedence_over_dates(): void
    {
        $service = new EquipmentPriorityService;
        $unsafe = $this->equipment(['status' => 'Operational']);
        $unsafe->setRelation('latestInspection', new Inspection([
            'safety_concerns' => 'Unsafe for operation',
        ]));
        $outOfService = $this->equipment(['status' => 'Out of Service']);

        $this->assertTrue($service->assess($unsafe)['critical']);
        $this->assertSame('High', $service->assess($unsafe)['priority']);
        $this->assertSame('High', $service->assess($outOfService)['priority']);
    }

    public function test_negated_safety_findings_do_not_raise_high_priority(): void
    {
        $equipment = $this->equipment(['status' => 'Operational']);
        $equipment->setRelation('latestInspection', new Inspection([
            'safety_concerns' => 'No unsafe condition found',
            'functional_test_result' => 'No failure detected',
        ]));

        $this->assertSame('Low', (new EquipmentPriorityService)->assess($equipment)['priority']);
    }

    private function equipment(array $attributes = []): Equipment
    {
        $equipment = new Equipment($attributes);
        $equipment->setRelation('latestInspection', null);
        $equipment->setRelation('maintenances', new Collection);

        return $equipment;
    }
}
