<?php

namespace Tests\Unit;

use App\Services\RiskPredictionService;
use PHPUnit\Framework\TestCase;

class RiskPredictionServiceTest extends TestCase
{
    public function test_risk_classes_follow_normalized_risk_percentage_thresholds(): void
    {
        $service = new class extends RiskPredictionService
        {
            public function classify(array $features): int
            {
                return $this->riskClass($features);
            }
        };
        $cases = [
            0 => 0,
            59 => 0,
            60 => 1,
            70 => 1,
            71 => 2,
            100 => 2,
        ];

        foreach ($cases as $riskPercentage => $expectedClass) {
            $features = [
                'service_age' => $riskPercentage * 0.2,
                'engine_operating_hours' => $riskPercentage * 100,
                'runtime_cycles' => $riskPercentage * 10,
                'mechanical_failures' => $riskPercentage * 0.1,
                'days_since_last_inspection' => $riskPercentage * 3.65,
                'days_since_last_maintenance' => $riskPercentage * 3.65,
                'defects_count' => $riskPercentage * 0.1,
                'inspection_outcome' => 1 + $riskPercentage * 0.03,
                'maintenance_frequency' => $riskPercentage * 3.65,
            ];

            $this->assertSame($expectedClass, $service->classify($features), $riskPercentage.'%');
        }
    }

    public function test_windows_python_process_environment_preserves_path(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Python process environment is Windows-specific.');
        }

        $originalPath = getenv('PATH');
        $testPath = 'C:\\python-test-path';
        putenv('PATH='.$testPath);

        try {
            $service = new class extends RiskPredictionService
            {
                public function processEnvironment(): array
                {
                    return $this->windowsProcessEnvironment();
                }
            };

            $this->assertSame($testPath, $service->processEnvironment()['PATH']);
        } finally {
            putenv($originalPath === false ? 'PATH' : 'PATH='.$originalPath);
        }
    }
}
