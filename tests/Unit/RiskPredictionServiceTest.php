<?php

namespace Tests\Unit;

use App\Services\RiskPredictionService;
use PHPUnit\Framework\TestCase;

class RiskPredictionServiceTest extends TestCase
{
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
