<?php

namespace App\Http\Controllers;

use App\Models\QueueCall;
use App\Models\VisitorEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class QueueScreenController extends Controller
{
    private const SERVICES = [
        'PST' => ['label' => 'PST', 'name' => 'Pelayanan Statistik Terpadu'],
        'LPSE' => ['label' => 'LPSE', 'name' => 'Layanan Pengadaan Secara Elektronik'],
        'PPID' => ['label' => 'PPID', 'name' => 'Informasi dan Dokumentasi'],
        'KEGIATAN' => ['label' => 'LAINNYA', 'name' => 'Kegiatan lainnya'],
    ];

    public function index(): View
    {
        return view('queue.screen', ['dataUrl' => route('queue.data')]);
    }

    public function data(): JsonResponse
    {
        $today = Carbon::today();
        $activeEntries = VisitorEntry::query()
            ->whereDate('created_at', $today)
            ->whereIn('service_status', ['waiting', 'serving'])
            ->orderBy('created_at')
            ->get(['id', 'queue_no', 'service_code', 'full_name', 'institution', 'service_status', 'updated_at']);

        $serving = $activeEntries->where('service_status', 'serving')
            ->sortByDesc('updated_at')
            ->values()
            ->map(fn (VisitorEntry $entry): array => $this->formatEntry($entry));

        $waiting = $activeEntries->where('service_status', 'waiting')
            ->values()
            ->map(fn (VisitorEntry $entry): array => $this->formatEntry($entry));

        $services = collect(self::SERVICES)->map(function (array $service, string $code) use ($waiting, $serving): array {
            $serviceWaiting = $waiting->where('service_code', $code)->values();
            $serviceServing = $serving->where('service_code', $code)->values();

            return [
                'code' => $code,
                ...$service,
                'serving' => $serviceServing->first(),
                'waiting_count' => $serviceWaiting->count(),
                'next_queue' => $serviceWaiting->first()['queue_no'] ?? null,
            ];
        })->values();

        $latestCall = QueueCall::query()
            ->with('visitorEntry:id,queue_no,service_code,full_name,institution,service_status,updated_at')
            ->whereDate('created_at', $today)
            ->latest('id')
            ->first();

        return response()->json([
            'serving' => $serving,
            'waiting' => $waiting,
            'services' => $services,
            'latest_call' => $latestCall?->visitorEntry
                ? [...$this->formatEntry($latestCall->visitorEntry), 'event_id' => $latestCall->id, 'called_at' => $latestCall->created_at->format('H:i:s')]
                : null,
            'updated_at' => now()->timezone(config('app.timezone'))->format('H:i:s'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function formatEntry(VisitorEntry $entry): array
    {
        $service = self::SERVICES[$entry->service_code] ?? ['label' => $entry->service_code, 'name' => $entry->service_code];

        return [
            'id' => $entry->id,
            'queue_no' => $entry->queue_no,
            'service_code' => $entry->service_code,
            'service_label' => $service['label'],
            'service_name' => $service['name'],
            'full_name' => $entry->full_name,
            'institution' => $entry->institution,
            'service_status' => $entry->service_status,
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }
}
