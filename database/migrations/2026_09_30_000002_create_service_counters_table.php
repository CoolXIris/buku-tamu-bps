<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_counters', function (Blueprint $table) {
            $table->unsignedTinyInteger('number')->primary();
            $table->foreignId('visitor_entry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('service_counters')->insert(array_map(
            fn(int $number): array => [
                'number' => $number,
                'visitor_entry_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            range(1, 6),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('service_counters');
    }
};