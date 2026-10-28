<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 10)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20);
            $table->unsignedTinyInteger('party_size');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedInteger('deposit')->default(0);
            $table->string('deposit_status', 20)->nullable();
            $table->string('occasion', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 80);
            $table->string('author_area', 80)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->text('reply')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('reservations');
    }
};
