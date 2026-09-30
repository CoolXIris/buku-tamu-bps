<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $latestCalls = DB::table('queue_calls as calls')
            ->joinSub(
                DB::table('queue_calls')
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('visitor_entry_id'),
                'latest_calls',
                'latest_calls.id',
                '=',
                'calls.id',
            )
            ->join('visitor_entries', 'visitor_entries.id', '=', 'calls.visitor_entry_id')
            ->where('visitor_entries.service_status', 'serving')
            ->whereNotNull('calls.counter_number')
            ->orderByDesc('calls.id')
            ->get(['calls.counter_number', 'calls.visitor_entry_id']);

        $assignedCounters = [];
        $assignedVisitors = [];

        foreach ($latestCalls as $call) {
            $counterNumber = (int) $call->counter_number;
            $visitorEntryId = (int) $call->visitor_entry_id;

            if (isset($assignedCounters[$counterNumber]) || isset($assignedVisitors[$visitorEntryId])) {
                continue;
            }

            DB::table('service_counters')
                ->where('number', $counterNumber)
                ->update(['visitor_entry_id' => $visitorEntryId, 'updated_at' => now()]);
            $assignedCounters[$counterNumber] = true;
            $assignedVisitors[$visitorEntryId] = true;
        }
    }

    public function down(): void
    {
        DB::table('service_counters')->update(['visitor_entry_id' => null]);
    }
};