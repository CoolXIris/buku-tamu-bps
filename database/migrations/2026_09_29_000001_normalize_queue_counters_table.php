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
            $table->dropUnique(['service_code', 'service_date']);
            $table->dropColumn('service_date');
            $table->unique('service_code');
        });
    }

    public function down(): void
    {
        Schema::table('queue_counters', function (Blueprint $table) {
            $table->dropUnique(['service_code']);
            $table->date('service_date')->nullable();
            $table->unique(['service_code', 'service_date']);
        });
    }
};
