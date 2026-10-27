<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $categories = collect(['Mechanical', 'Non Mechanical'])
            ->map(fn ($name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()])
            ->values()
            ->all();

        DB::table('equipment_categories')->insertOrIgnore($categories);
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_categories');
    }
};
