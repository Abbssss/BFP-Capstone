<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('equipment_name');
            $table->string('category');
            $table->string('asset_tag')->nullable()->unique();
            $table->string('serial_number')->nullable()->unique();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->date('commission_date')->nullable();
            $table->integer('service_age_years')->default(0);
            $table->string('location')->nullable();
            $table->unsignedBigInteger('assigned_personnel')->nullable();
            $table->foreign('assigned_personnel')->references('id')->on('users')->nullOnDelete();
            $table->string('status')->default('Operational');
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->integer('engine_operating_hours')->nullable();
            $table->integer('runtime_cycles')->nullable();
            $table->integer('mechanical_failure_count')->default(0);
            $table->date('last_maintenance_date')->nullable();
            $table->integer('maintenance_interval_days')->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('inspection_defects_count')->default(0);
            $table->string('last_inspection_outcome')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
