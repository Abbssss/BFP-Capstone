<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->dateTime('inspection_date');
            $table->string('inspector_name');
            $table->string('inspection_type');
            $table->text('condition_observed')->nullable();
            $table->text('functional_test_result')->nullable();
            $table->text('defects_identified')->nullable();
            $table->text('safety_concerns')->nullable();
            $table->text('recommended_action')->nullable();
            $table->text('notes')->nullable();
            $table->string('supporting_document')->nullable();
            $table->string('outcome')->default('Passed');
            $table->string('status')->default('finalized');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
