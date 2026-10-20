<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('model_versions')) {
            Schema::create('model_versions', function (Blueprint $table) {
                $table->id();
                $table->string('version_name')->unique();
                $table->decimal('accuracy', 6, 4)->default(0.0);
                $table->decimal('precision', 6, 4)->default(0.0);
                $table->decimal('recall', 6, 4)->default(0.0);
                $table->decimal('f1_score', 6, 4)->default(0.0);
                $table->json('confusion_matrix')->nullable();
                $table->dateTime('training_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
