<?php

namespace App\Http\Controllers;

use App\Services\PdfReportExporter;
use App\Services\ReportDataService;
use App\Services\ExcelReportExporter;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request, ReportDataService $reports): View
    {
        $filters = $this->filters($request);
        $data = $reports->report($filters['report'], $filters['period'], $filters['status'], $filters['group_by'], $filters['office_code']);

        return view('reports.index', [
            ...$data,
            ...$filters,
            'reportOptions' => $this->availableReports($request),
            'groupOptions' => $this->groupOptions($filters['report']),
            'officeCodes' => $this->officeCodes(),
        ]);
    }

    public function export(
        Request $request,
        string $format,
        ReportDataService $reports,
        PdfReportExporter $pdf,
        ExcelReportExporter $excel
    ): Response
    {
        abort_unless(in_array($format, ['pdf', 'excel', 'csv'], true), 404);
        $filters = $this->filters($request);
        $data = $reports->report($filters['report'], $filters['period'], $filters['status'], $filters['group_by'], $filters['office_code']);
        $filename = 'fire-pmis-'.$filters['report'].'-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            return response($pdf->make($data, $filters), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        if ($format === 'excel') {
            $filename .= '.xlsx';

            return response()->download($excel->make($data), $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($data): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open the report export stream.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $data['headers']);
            foreach ($data['records'] as $record) {
                fputcsv($output, array_map([$this, 'spreadsheetSafe'], array_values($record['values'])));
            }
            fclose($output);
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'report' => ['nullable', 'in:user-activity,equipment-registry,maintenance-summary,inspection-summary,audit-activity'],
            'period' => ['nullable', 'in:quarter,30-days,year,all'],
            'status' => ['nullable', 'string', 'max:100'],
            'group_by' => ['nullable', 'in:role,status,category,month'],
            'office_code' => ['nullable', 'string', 'max:50'],
        ]);

        $availableReports = $this->availableReports($request);
        $defaultReport = $request->user()->role === 'super_admin' ? 'user-activity' : 'equipment-registry';
        $report = $validated['report'] ?? $defaultReport;
        abort_unless(array_key_exists($report, $availableReports), 403);
        $groupOptions = $this->groupOptions($report);
        $groupBy = $validated['group_by'] ?? array_key_first($groupOptions);
        if (! array_key_exists($groupBy, $groupOptions)) {
            $groupBy = array_key_first($groupOptions);
        }

        $effectiveOfficeCode = $request->user()->role === 'super_admin'
            ? ($validated['office_code'] ?? '')
            : ($request->user()->office_code ?: '');

        return [
            'report' => $report,
            'period' => $validated['period'] ?? 'quarter',
            'status' => $validated['status'] ?? '',
            'group_by' => $groupBy,
            'office_code' => $effectiveOfficeCode !== '' ? $effectiveOfficeCode : null,
        ];
    }

    private function availableReports(Request $request): array
    {
        if ($request->user()->role === 'super_admin') {
            return ReportDataService::REPORTS;
        }

        return array_diff_key(ReportDataService::REPORTS, [
            'user-activity' => true,
            'audit-activity' => true,
        ]);
    }

    private function officeCodes(): array
    {
        return Equipment::query()->whereNotNull('office_code')->where('office_code', '<>', '')
            ->distinct()->pluck('office_code')
            ->merge(User::query()->whereNotNull('office_code')->where('office_code', '<>', '')->distinct()->pluck('office_code'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function groupOptions(string $report): array
    {
        return match ($report) {
            'user-activity' => ['role' => 'Role', 'status' => 'Status', 'month' => 'Month'],
            'equipment-registry' => ['category' => 'Category', 'status' => 'Status', 'month' => 'Month'],
            'maintenance-summary' => ['category' => 'Maintenance type', 'status' => 'Status', 'month' => 'Month'],
            'inspection-summary' => ['category' => 'Inspection type', 'status' => 'Outcome', 'month' => 'Month'],
            default => ['month' => 'Month'],
        };
    }

    private function spreadsheetSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
