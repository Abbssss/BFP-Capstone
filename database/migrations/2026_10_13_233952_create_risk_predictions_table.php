<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('risk_predictions')) {
            // Kept intentionally empty because the final risk model tables are created in a later migration.
        }
    }

    public function down(): void
    {
        // No-op to avoid destructive reordering during fresh database rebuilds.
    }
};
