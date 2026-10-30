<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per gateway payment, so a repeated or racing start cannot record
     * the same charge twice. Rows without a reference (staff-created venue
     * rows) are unaffected: NULLs never collide in a unique index.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['gateway', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway', 'reference']);
        });
    }
};
