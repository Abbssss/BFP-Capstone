<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('risk_predictions') && Schema::hasTable('model_versions')) {
            Schema::table('risk_predictions', function (Blueprint $table) {
                $table->unsignedBigInteger('model_version_id')->nullable()->after('equipment_id');
            });

            Schema::table('risk_predictions', function (Blueprint $table) {
                $table->foreign('model_version_id')->references('id')->on('model_versions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('risk_predictions', function (Blueprint $table) {
            $table->dropForeign(['model_version_id']);
            $table->dropColumn('model_version_id');
        });
    }
};
