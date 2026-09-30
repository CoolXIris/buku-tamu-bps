<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('queue_counters')->delete();

        Schema::table('queue_counters', function (Blueprint $table) {
            $table->dropUnique(['service_code']);
            $table->date('service_date')->after('service_code');
            $table->unique(['service_code', 'service_date']);
        });

        Schema::table('visitor_entries', function (Blueprint $table) {
            $table->dropUnique(['queue_no']);
        });
    }

    public function down(): void
    {
        $hasDuplicateQueueNumbers = DB::table('visitor_entries')
            ->select('queue_no')
            ->groupBy('queue_no')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateQueueNumbers) {
            throw new RuntimeException('Cannot restore unique queue numbers while duplicates exist across dates.');
        }

        DB::table('queue_counters')->delete();

        Schema::table('queue_counters', function (Blueprint $table) {
            $table->dropUnique(['service_code', 'service_date']);
            $table->dropColumn('service_date');
            $table->unique('service_code');
        });

        Schema::table('visitor_entries', function (Blueprint $table) {
            $table->unique('queue_no');
        });
    }
};
