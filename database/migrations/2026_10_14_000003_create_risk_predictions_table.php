<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('risk_predictions')) {
            Schema::create('risk_predictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
                $table->unsignedBigInteger('model_version_id')->nullable();
                $table->string('prediction_label');
                $table->decimal('confidence', 5, 4)->default(0);
                $table->timestamp('prediction_timestamp')->useCurrent();
                $table->text('details_json')->nullable();
                $table->timestamps();

                $table->foreign('model_version_id')->references('id')->on('model_versions')->nullOnDelete();
            });
        }

        if (Schema::hasTable('risk_predictions') && Schema::hasTable('model_versions') && ! Schema::hasColumn('risk_predictions', 'model_version_id')) {
            Schema::table('risk_predictions', function (Blueprint $table) {
                $table->unsignedBigInteger('model_version_id')->nullable()->after('equipment_id');
                $table->foreign('model_version_id')->references('id')->on('model_versions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_predictions');
    }
};
