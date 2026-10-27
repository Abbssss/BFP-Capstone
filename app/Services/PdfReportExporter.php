<?php

namespace App\Services;

class PdfReportExporter
{
    public function make(array $data, array $filters): string
    {
        $pages = [];
        $records = $data['records']->all();
        $recordChunks = array_chunk($records, 16);
        if ($recordChunks === []) {
            $recordChunks = [[]];
        }

        foreach ($recordChunks as $chunkIndex => $chunk) {
            $pages[] = $chunkIndex === 0
                ? $this->firstPage($data, $filters, $chunk, count($recordChunks))
                : $this->tablePage($data, $filters, $chunk, $chunkIndex + 1, count($recordChunks));
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageRefs = [];

        foreach ($pages as $index => $stream) {
            $pageId = 5 + ($index * 2);
            $contentId = $pageId + 1;
            $pageRefs[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.count($pages).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function firstPage(array $data, array $filters, array $records, int $totalPages): string
    {
        $stream = "0 0 0 rg\n";
        $this->textCommand($stream, 28, 574, 18, 'F2', $data['reportTitle']);
        $this->textCommand($stream, 28, 554, 9, 'F1', 'FIRE-PMIS | '.$data['periodLabel'].' | Status: '.($filters['status'] ?: 'All statuses').' | Grouped by: '.ucfirst($filters['group_by']));
        $this->textCommand($stream, 28, 540, 8, 'F1', 'Generated '.now()->format('M j, Y').' | '.$data['count'].' total records');

        $groups = $data['grouped']->take(6);
        $metricWidth = 736 / max(1, count($groups));
        $metricIndex = 0;
        foreach ($groups as $group => $total) {
            $x = 28 + ($metricIndex * $metricWidth);
            $metricCardWidth = $metricWidth - 8;
            $stream .= "1 1 1 rg\n{$x} 476 {$metricCardWidth} 48 re f\n0.82 0.85 0.89 RG\n{$x} 476 {$metricCardWidth} 48 re S\n0 0 0 rg\n";
            $characterCount = max(8, (int) floor(($metricWidth - 16) / 5));
            $this->textCommand($stream, $x + 8, 505, 7, 'F1', $this->truncate((string) $group, $characterCount));
            $this->textCommand($stream, $x + 8, 486, 14, 'F2', (string) $total);
            $metricIndex++;
        }

        return $this->appendTableToPage($stream, $data, $records, $totalPages, 452, 18, 1);
    }

    private function summaryPage(array $data, array $filters): string
    {
        $stream = $this->pageHeader($data, 'Report summary');

        $stream .= "0.96 0.97 0.99 rg\n28 450 736 118 re f\n";
        $stream .= "0.76 0.80 0.84 RG\n28 450 736 118 re S\n";
        $this->textCommand($stream, 48, 532, 9, 'F2', 'FIRE-PMIS REPORT');
        $this->textCommand($stream, 48, 501, 24, 'F2', $data['reportTitle']);
        $this->textCommand($stream, 48, 476, 10, 'F1', $data['description'] ?: '');
        $this->textCommand($stream, 48, 460, 8, 'F1', 'Period: '.$data['periodLabel'].'   |   Status: '.($filters['status'] ?: 'All statuses').'   |   Grouped by: '.ucfirst($filters['group_by']));

        $cards = [
            ['TOTAL RECORDS', (string) $data['count'], '0.09 0.18 0.31'],
            ['PERIOD', $data['periodLabel'], '0.30 0.47 0.70'],
            ['CRITERIA', ucfirst($filters['group_by']), '0.55 0.29 0.78'],
        ];
        foreach ($cards as $index => $card) {
            $x = 28 + ($index * 248);
            $stream .= $card[2]." rg\n{$x} 402 232 38 re f\n";
            $stream .= "1 1 1 rg\n";
            $this->textCommand($stream, $x + 12, 426, 8, 'F2', $card[0]);
            $this->textCommand($stream, $x + 12, 410, 11, 'F2', $card[1]);
        }

        $stream .= "0.05 0.17 0.31 rg\n";
        $this->textCommand($stream, 28, 376, 12, 'F2', 'Metric count by '.ucfirst($filters['group_by']));

        $groups = collect($data['grouped'])->take(12);
        $groupIndex = 0;
        foreach ($groups as $group => $total) {
            $column = $groupIndex % 3;
            $row = intdiv($groupIndex, 3);
            $x = 28 + ($column * 248);
            $y = 318 - ($row * 76);
            $stream .= "1 1 1 rg\n{$x} {$y} 232 58 re f\n";
            $stream .= "0.82 0.85 0.89 RG\n{$x} {$y} 232 58 re S\n";
            $stream .= "0.74 0.12 0.14 rg\n".($x + 232 - 52)." ".($y + 12)." 40 34 re f\n";
            $stream .= "0.05 0.17 0.31 rg\n";
            $this->textCommand($stream, $x + 12, $y + 39, 8, 'F2', $this->truncate((string) $group, 24));
            $this->textCommand($stream, $x + 12, $y + 22, 8, 'F1', 'Record count');
            $stream .= "1 1 1 rg\n";
            $this->textCommand($stream, $x + 232 - 43, $y + 25, 15, 'F2', (string) $total);
            $groupIndex++;
        }

        if ($groups->isEmpty()) {
            $this->textCommand($stream, 28, 318, 10, 'F1', 'No matching records for the selected criteria.');
        }

        $stream .= $this->pageFooter(1, 'Summary');

        return $stream;
    }

    private function tablePage(array $data, array $filters, array $records, int $pageNumber, int $tablePageCount): string
    {
        $stream = "0 0 0 rg\n";
        $this->textCommand($stream, 28, 574, 16, 'F2', $data['reportTitle'].' - Record Details');
        $this->textCommand($stream, 28, 554, 8, 'F1', 'Period: '.$data['periodLabel'].' | Status: '.($filters['status'] ?: 'All statuses').' | Page '.$pageNumber.' of '.$tablePageCount);
        return $this->appendTableToPage($stream, $data, $records, $tablePageCount, 530, 25, $pageNumber);
    }

    private function appendTableToPage(string $stream, array $data, array $records, int $totalPages, float $topY, float $rowHeight, ?int $pageNumber = null): string
    {
        $this->textCommand($stream, 28, $topY + 10, 11, 'F2', 'Record details');

        $headers = $data['headers'];
        $columnCount = max(1, count($headers));
        $tableWidth = 736;
        $cellWidth = $tableWidth / $columnCount;
        $startX = 28;
        $headerY = $topY - 30;

        $stream .= "0.92 0.94 0.96 rg\n28 {$headerY} {$tableWidth} 32 re f\n0 0 0 rg\n";
        foreach ($headers as $index => $header) {
            $x = $startX + ($index * $cellWidth);
            $this->textCommand($stream, $x + 4, $headerY + 10, 7, 'F2', $this->truncate($header, $this->charactersPerCell($cellWidth)));
        }

        $y = $headerY - $rowHeight;
        foreach ($records as $recordIndex => $record) {
            $fill = $recordIndex % 2 === 0 ? '1 1 1 rg' : '0.96 0.98 1 rg';
            $stream .= $fill."\n28 {$y} {$tableWidth} {$rowHeight} re f\n";
            $stream .= "0.82 0.85 0.89 RG\n28 {$y} {$tableWidth} {$rowHeight} re S\n";

            foreach ($headers as $index => $header) {
                $value = (string) ($record['values'][$header] ?? '—');
                $x = $startX + ($index * $cellWidth);
                $stream .= "0.82 0.85 0.89 RG\n".($x + $cellWidth)." {$y} 0 {$rowHeight} re S\n0 0 0 rg\n";
                $this->textCommand($stream, $x + 4, $y + max(5, $rowHeight / 3), 7, 'F1', $this->truncate($value, $this->charactersPerCell($cellWidth)));
            }
            $y -= $rowHeight;
        }

        if ($records === []) {
            $stream .= "1 1 1 rg\n28 ".($headerY - $rowHeight)." {$tableWidth} 52 re f\n";
            $this->textCommand($stream, 40, $headerY - 30, 9, 'F1', 'No records match the selected report filters.');
        }

        $stream .= "0 0 0 rg\n";
        $stream .= $this->pageFooter(($pageNumber ?? 1).' / '.$totalPages, 'Record details');

        return $stream;
    }

    private function pageFooter(string $pageNumber, string $section): string
    {
        $stream = "0.82 0.85 0.89 RG\n28 32 736 0 re S\n";
        $stream = $this->appendText($stream, 28, 17, 7, 'F1', 'FIRE-PMIS | '.$section.' | Generated '.now()->format('M j, Y'));
        $stream = $this->appendText($stream, 704, 17, 7, 'F1', 'Page '.$pageNumber);

        return $stream;
    }

    private function appendText(string $stream, float $x, float $y, float $size, string $font, string $text): string
    {
        $stream .= "BT\n/{$font} {$size} Tf\n{$x} {$y} Td\n(".$this->escape($text).") Tj\nET\n";

        return $stream;
    }

    private function textCommand(string &$stream, float $x, float $y, float $size, string $font, string $text): void
    {
        $stream .= "BT\n/{$font} {$size} Tf\n{$x} {$y} Td\n(".$this->escape($text).") Tj\nET\n";
    }

    private function charactersPerCell(float $cellWidth): int
    {
        return max(8, (int) floor($cellWidth / 5));
    }

    private function truncate(string $value, int $length): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?: '';

        return mb_strlen($value) > $length
            ? mb_substr($value, 0, max(0, $length - 3)).'...'
            : $value;
    }

    private function escape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $text = $encoded === false ? $value : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
