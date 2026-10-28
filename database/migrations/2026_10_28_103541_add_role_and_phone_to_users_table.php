<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->index()->after('email');
            $table->string('phone', 20)->nullable()->after('role');
            $table->unsignedInteger('loyalty_points')->default(0)->after('phone');
            $table->boolean('marketing_opt_in')->default(false)->after('loyalty_points');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'loyalty_points', 'marketing_opt_in']);
        });
    }
};
