<?php

namespace App\Services;

use App\Models\Equipment;
use Illuminate\Support\Facades\DB;

class RecordReferenceNumberService
{
    public function nextFor(Equipment $equipment): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $equipment->equipment_name);
        if ($letters === '') {
            $letters = preg_replace('/[^A-Za-z]/', '', $equipment->asset_code);
        }
        $prefix = strtoupper(substr($letters, 0, 3));
        $prefix = str_pad($prefix, 3, 'X');
        $date = today();
        $dateKey = $date->toDateString();
        $datePart = $date->format('m-d-Y');

        $created = DB::table('record_reference_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'sequence_date' => $dateKey,
            'last_number' => 0,
        ]);

        $sequence = DB::table('record_reference_sequences')
            ->where('prefix', $prefix)
            ->where('sequence_date', $dateKey)
            ->lockForUpdate()
            ->first();

        $lastNumber = (int) $sequence->last_number;
        if ($created === 1) {
            $pattern = $prefix.'-'.$datePart.'-%';
            $existingReferences = DB::table('inspections')
                ->where('reference_number', 'like', $pattern)
                ->pluck('reference_number')
                ->merge(
                    DB::table('maintenances')
                        ->where('request_number', 'like', $pattern)
                        ->pluck('request_number')
                );
            foreach ($existingReferences as $reference) {
                if (preg_match('/-(\d+)$/', $reference, $matches)) {
                    $lastNumber = max($lastNumber, (int) $matches[1]);
                }
            }
        }

        $nextNumber = $lastNumber + 1;
        DB::table('record_reference_sequences')
            ->where('prefix', $prefix)
            ->where('sequence_date', $dateKey)
            ->update(['last_number' => $nextNumber]);

        return $prefix.'-'.$datePart.'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
