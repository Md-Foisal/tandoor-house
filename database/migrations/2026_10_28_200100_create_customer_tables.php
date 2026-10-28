<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 80);
            $table->string('postcode', 10);
            $table->string('instructions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('description');
            // Either a percentage (basis points) or a fixed amount in pence.
            $table->unsignedSmallInteger('percent_off')->nullable();
            $table->unsignedInteger('amount_off')->nullable();
            $table->unsignedInteger('minimum_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->unique();
            $table->json('lines');
            $table->string('channel', 20)->nullable();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('promo_code', 30)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('addresses');
    }
};
