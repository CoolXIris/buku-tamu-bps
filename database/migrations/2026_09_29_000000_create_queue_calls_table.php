<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_entry_id')->constrained('visitor_entries')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_calls');
    }
};
