<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('waived')->default(0);
            $table->unsignedInteger('tip')->default(0);
            $table->boolean('service_included')->default(true);
            $table->string('payment_status', 20);
            $table->timestamps();

            $table->index(['dining_table_id', 'payment_status']);
        });

        Schema::create('table_bill_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_bill_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_bill_payment_orders');
        Schema::dropIfExists('table_bill_payments');
    }
};
