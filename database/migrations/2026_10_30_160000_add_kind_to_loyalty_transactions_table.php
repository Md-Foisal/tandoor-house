<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->string('kind')->default('manual')->after('points');
        });

        // The ledger used to tell its rows apart by wording; the sign and
        // the wording of existing rows give the kind they were written as.
        DB::table('loyalty_transactions')->whereNotNull('order_id')->where('reason', 'like', '%refunded')->update(['kind' => 'reversed']);
        DB::table('loyalty_transactions')->whereNotNull('order_id')->where('reason', 'like', 'Reward returned%')->update(['kind' => 'returned']);
        DB::table('loyalty_transactions')->whereNotNull('order_id')->where('reason', 'like', 'Reward used%')->update(['kind' => 'redeemed']);
        DB::table('loyalty_transactions')->whereNotNull('order_id')->where('kind', 'manual')->where('points', '>', 0)->where('reason', 'like', 'Order %')->update(['kind' => 'earned']);

        // Anything still ambiguous would break the unique index: keep the first of each pair.
        $duplicates = DB::table('loyalty_transactions')
            ->select('order_id', 'kind', DB::raw('min(id) as keep'))
            ->whereNotNull('order_id')
            ->groupBy('order_id', 'kind')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('loyalty_transactions')
                ->where('order_id', $row->order_id)->where('kind', $row->kind)->where('id', '!=', $row->keep)
                ->update(['kind' => 'manual']);
        }

        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->unique(['order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
