<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_entries', function (Blueprint $table) {
            $table->string('service_status', 20)->default('waiting')->index();
        });
    }

    public function down(): void
    {
        Schema::table('visitor_entries', function (Blueprint $table) {
            $table->dropIndex(['service_status']);
            $table->dropColumn('service_status');
        });
    }
};
