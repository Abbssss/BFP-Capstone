<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 3);
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['prefix', 'sequence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_reference_sequences');
    }
};
