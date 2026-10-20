<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('request_number')->unique();
            $table->string('maintenance_type')->default('preventive');
            $table->string('priority')->default('Medium');
            $table->string('status')->default('Pending');
            $table->text('reported_issue')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('parts_replaced')->nullable();
            $table->decimal('maintenance_cost', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('technician_name')->nullable();
            $table->decimal('downtime_hours', 8, 2)->nullable();
            $table->string('maintenance_outcome')->nullable();
            $table->date('next_recommended_maintenance_date')->nullable();
            $table->string('supporting_document')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
