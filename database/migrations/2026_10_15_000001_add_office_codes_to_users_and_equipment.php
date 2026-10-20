<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('office_code', 50)->nullable()->index();
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->string('office_code', 50)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex(['office_code']);
            $table->dropColumn('office_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['office_code']);
            $table->dropColumn('office_code');
        });
    }
};
