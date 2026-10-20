<?php

namespace App\Services;

class PdfReportExporter
{
    public function make(array $data, array $filters): string
    {
        $lines = [
            'FIRE-PMIS - '.$data['reportTitle'],
            'Period: '.$data['periodLabel'],
            'Status: '.($filters['status'] ?: 'All statuses'),
            'Grouped by: '.ucfirst($filters['group_by']),
            'Records: '.$data['count'],
            str_repeat('-', 88),
        ];

        foreach ($data['records'] as $index => $record) {
            $lines[] = 'Record '.($index + 1);
            foreach ($record['values'] as $label => $value) {
                foreach (explode("\n", wordwrap($label.': '.$value, 88, "\n", true)) as $line) {
                    $lines[] = $line;
                }
            }
            $lines[] = str_repeat('-', 88);
        }

        if ($data['count'] === 0) {
            $lines[] = 'No records match the selected report filters.';
        }

        $pages = array_chunk($lines, 55);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $pageRefs = [];

        foreach ($pages as $index => $pageLines) {
            $pageId = 4 + ($index * 2);
            $contentId = $pageId + 1;
            $pageRefs[] = $pageId.' 0 R';
            $stream = "BT\n/F1 9 Tf\n45 750 Td\n12 TL\n";
            foreach ($pageLines as $line) {
                $stream .= '('.$this->escape($line).") Tj\nT*\n";
            }
            $stream .= "ET\n";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
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

    private function escape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $text = $encoded === false ? $value : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
