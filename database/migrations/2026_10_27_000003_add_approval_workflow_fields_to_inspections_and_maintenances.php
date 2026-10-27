<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('approval_status')->default('Not Required');
            $table->text('approval_notes')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('approval_status')->default('Not Required');
            $table->text('approval_notes')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        DB::table('inspections')->where('status', 'Completed')->update(['approval_status' => 'Pending']);
        DB::table('inspections')->where('status', 'finalized')->where('outcome', 'Requires Further Assessment')->update(['approval_status' => 'Pending']);
        DB::table('maintenances')->where('status', 'Completed')->update(['approval_status' => 'Pending']);
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropColumn(['approval_status', 'approval_notes', 'reviewed_at']);
        });

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropColumn(['approval_status', 'approval_notes', 'reviewed_at']);
        });
    }
};
