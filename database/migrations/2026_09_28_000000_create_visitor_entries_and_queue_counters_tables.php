<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_counters', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 20);
            $table->date('service_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['service_code', 'service_date']);
        });

        Schema::create('visitor_entries', function (Blueprint $table) {
            $table->id();
            $table->string('queue_no', 30)->unique();
            $table->string('service_code', 20)->index();
            $table->unsignedInteger('queue_number');
            $table->string('full_name', 150);
            $table->string('gender', 20);
            $table->string('institution', 180);
            $table->string('phone', 30);
            $table->string('email', 180);
            $table->string('occupation', 30);
            $table->string('occupation_other', 100)->nullable();
            $table->string('purpose', 20);
            $table->string('purpose_other', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_entries');
        Schema::dropIfExists('queue_counters');
    }
};