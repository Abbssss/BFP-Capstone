<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('Medium');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to_id');
            $table->dropColumn('priority');
        });
    }
};
