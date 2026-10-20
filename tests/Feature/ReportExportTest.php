<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_supports_configuration_and_preview(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        $this->actingAs($user)
            ->get('/reports?report=user-activity&period=all&group_by=role')
            ->assertOk()
            ->assertSee('Date range')
            ->assertSee('All statuses')
            ->assertSee('Group by')
            ->assertSee('Generate preview')
            ->assertSee('Export PDF')
            ->assertSee('Export Excel')
            ->assertSee('1 record');
    }

    public function test_csv_export_contains_filtered_records_and_is_excel_compatible(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        Equipment::query()->create([
            'asset_code' => 'EQ-EXPORT-1',
            'equipment_name' => 'Export Test Pump',
            'category' => 'Fire Pump',
            'status' => 'Operational',
            'location' => 'Station',
            'office_code' => 'STA-ANA',
        ]);

        $response = $this->actingAs($user)
            ->get('/reports/export/csv?report=equipment-registry&period=all&status=Operational&group_by=category');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $header = str_getcsv(explode("\n", ltrim($content, "\xEF\xBB\xBF"))[0]);
        $this->assertSame(['Asset code', 'Equipment', 'Office Code', 'Category', 'Status', 'Location', 'Registered'], $header);
        $this->assertStringContainsString('EQ-EXPORT-1', $content);
        $this->assertStringContainsString('Export Test Pump', $content);
    }

    public function test_pdf_export_returns_a_pdf_download(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);

        $response = $this->actingAs($user)
            ->get('/reports/export/pdf?report=user-activity&period=all&group_by=role');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_excel_export_returns_a_valid_xlsx_workbook(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'office_code' => 'STA-ANA']);
        Equipment::query()->create([
            'asset_code' => 'EQ-XLSX-1',
            'equipment_name' => 'Workbook Test Pump',
            'category' => 'Fire Pump',
            'status' => 'Operational',
            'office_code' => 'STA-ANA',
        ]);

        $response = $this->actingAs($user)
            ->get('/reports/export/excel?report=equipment-registry&period=all&group_by=category');

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new \ZipArchive();
        try {
            $this->assertSame(true, $archive->open($path));
            $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $archive->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->assertNotFalse($worksheet);
        $this->assertStringContainsString('Workbook Test Pump', $worksheet);
    }
}
