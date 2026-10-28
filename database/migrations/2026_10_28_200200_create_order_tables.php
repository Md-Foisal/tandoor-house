<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 12)->unique();
            $table->string('channel', 20)->index();
            $table->string('status', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->json('contact');
            $table->json('address')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedInteger('service_charge')->default(0);
            $table->unsignedInteger('tip')->default(0);
            $table->unsignedInteger('vat_total');
            $table->unsignedInteger('total');
            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_method', 20);
            $table->unsignedInteger('cash_collected')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->boolean('age_confirmed')->default(false);
            $table->timestamp('placed_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('estimated_ready_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots, so history survives menu edits.
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->json('modifiers');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('vat_rate');
            $table->string('station', 20)->index();
            $table->string('status', 20)->default('queued');
            $table->string('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('bumped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from', 20)->nullable();
            $table->string('to', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('payable');
            $table->string('gateway', 20);
            $table->string('reference', 100)->nullable()->index();
            $table->string('method', 20);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('refunded')->default(0);
            $table->string('status', 20);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('table_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
