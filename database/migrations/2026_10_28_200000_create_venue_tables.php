<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('label', 20)->unique();
            $table->unsignedTinyInteger('seats');
            $table->string('area', 40);
            // Floor plan position and size in grid units.
            $table->unsignedSmallInteger('x');
            $table->unsignedSmallInteger('y');
            $table->unsignedSmallInteger('width')->default(2);
            $table->unsignedSmallInteger('height')->default(2);
            $table->string('shape', 10)->default('square');
            $table->string('status', 20)->default('free')->index();
            $table->string('qr_token', 40)->unique();
            $table->foreignId('merged_into_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('postcode_prefixes');
            $table->unsignedInteger('fee');
            $table->unsignedInteger('minimum_order');
            $table->unsignedSmallInteger('eta_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('dining_tables');
    }
};
