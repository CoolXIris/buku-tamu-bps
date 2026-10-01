<?php

namespace App\Http\Controllers;

use App\Models\QueueCall;
use App\Models\ServiceCounter;
use App\Models\VisitorEntry;
use App\Services\GuestSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminGuestController extends Controller
{
    private const SERVICES = [
        'PST' => 'PST',
        'LPSE' => 'LPSE',
        'PPID' => 'PPID',
        'KEGIATAN' => 'Lainnya',
    ];

    private const PURPOSES = [
        'PST' => 'Pelayanan Statistik Terpadu',
        'LPSE' => 'Layanan Pengadaan Secara Elektronik',
        'PPID' => 'Pejabat Pengelola Informasi dan Dokumentasi',
        'KEGIATAN' => 'Kegiatan lainnya',
    ];

    private const OCCUPATIONS = [
        'ASN' => 'Aparatur Sipil Negara',
        'SWASTA' => 'Karyawan Swasta',
        'WIRASWASTA' => 'Wiraswasta',
        'PENELITI' => 'Peneliti',
        'PELAJAR' => 'Pelajar/Mahasiswa',
        'LAINNYA' => 'Lainnya',
    ];

    private const STATUSES = [
        'waiting' => 'Menunggu',
        'serving' => 'Dilayani',
        'completed' => 'Selesai',
    ];

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $entries = app(GuestSearch::class)
            ->paginate($filters, $request->integer('page', 1))
            ->withQueryString();
        $entriesById = VisitorEntry::query()
            ->with('latestCall')
            ->whereKey($entries->getCollection()->pluck('id'))
            ->get()
            ->keyBy('id');
        $entries->setCollection($entries->getCollection()->map(
            fn (VisitorEntry $entry): VisitorEntry => $entriesById->get($entry->id, $entry),
        ));

        return view('admin.guests.index', [
            'entries' => $entries,
            'filters' => $filters,
            'services' => self::SERVICES,
            'statuses' => self::STATUSES,
            'totalVisitors' => VisitorEntry::count(),
            'counterOccupants' => ServiceCounter::query()->pluck('visitor_entry_id', 'number')->all(),
            'activeVisitorCount' => VisitorEntry::query()->inProgress()->count(),
            'admin' => Auth::user(),
        ]);
    }

    public function show(VisitorEntry $visitorEntry): JsonResponse
    {
        $counterNumber = $visitorEntry->latestCall?->counter_number;

        return response()->json([
            'queue_no' => $visitorEntry->queue_no,
            'created_at' => $visitorEntry->display_created_at?->format('d/m/Y H:i'),
            'full_name' => $visitorEntry->full_name,
            'institution' => $visitorEntry->institution,
            'purpose' => self::PURPOSES[$visitorEntry->purpose] ?? self::PURPOSES[$visitorEntry->service_code] ?? $visitorEntry->purpose,
            'purpose_other' => $visitorEntry->purpose_other,
            'service_code' => $visitorEntry->service_code,
            'dtsen_update' => $visitorEntry->dtsen_update ? 'Ya' : 'Tidak',
            'dtsen_update_value' => $visitorEntry->dtsen_update,
            'gender' => $visitorEntry->gender,
            'phone' => $visitorEntry->phone,
            'email' => $visitorEntry->email,
            'occupation' => self::OCCUPATIONS[$visitorEntry->occupation] ?? $visitorEntry->occupation,
            'occupation_code' => $visitorEntry->occupation,
            'occupation_other' => $visitorEntry->occupation_other,
            'purpose_code' => $visitorEntry->purpose,
            'status' => $visitorEntry->service_status === 'serving' && $counterNumber
                ? "Dilayani di loket ke-{$counterNumber}"
                : (self::STATUSES[$visitorEntry->service_status] ?? $visitorEntry->service_status),
            'counter_number' => $counterNumber,
        ]);
    }

    public function update(Request $request, VisitorEntry $visitorEntry): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'gender' => ['required', 'in:Laki-laki,Perempuan'],
            'institution' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:180'],
            'occupation' => ['required', 'in:ASN,SWASTA,WIRASWASTA,PENELITI,PELAJAR,LAINNYA'],
            'occupation_other' => ['required_if:occupation,LAINNYA', 'nullable', 'string', 'max:100'],
            'purpose' => ['required', 'in:PST,LPSE,PPID,KEGIATAN'],
            'purpose_other' => ['required_if:purpose,KEGIATAN', 'nullable', 'string', 'max:150'],
            'dtsen_update' => ['sometimes', 'boolean'],
        ]);

        $validated['service_code'] = $validated['purpose'];
        $validated['occupation_other'] = $validated['occupation'] === 'LAINNYA'
            ? ($validated['occupation_other'] ?? null)
            : null;
        $validated['purpose_other'] = $validated['purpose'] === 'KEGIATAN'
            ? ($validated['purpose_other'] ?? null)
            : null;
        $validated['dtsen_update'] = $validated['purpose'] === 'PST' && $request->boolean('dtsen_update');
        $visitorEntry->update($validated);

        return response()->json(['message' => 'Data tamu berhasil diperbarui.']);
    }

    public function destroy(VisitorEntry $visitorEntry): JsonResponse
    {
        $visitorEntry->delete();

        return response()->json(['message' => 'Data tamu berhasil dihapus.']);
    }

    public function updateStatus(Request $request, VisitorEntry $visitorEntry): JsonResponse
    {
        $validated = $request->validate([
            'service_status' => ['required', 'in:waiting,serving,completed'],
            'call_announcement' => ['sometimes', 'boolean', 'prohibited_unless:service_status,serving'],
            'counter_number' => ['required_if:call_announcement,true', 'nullable', 'integer', 'between:1,6', 'prohibited_unless:call_announcement,true'],
        ]);

        $callEvent = DB::transaction(function () use ($visitorEntry, $validated): ?QueueCall {
            $counters = ServiceCounter::query()
                ->orderBy('number')
                ->lockForUpdate()
                ->get();
            $entry = VisitorEntry::query()
                ->whereKey($visitorEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($validated['call_announcement'] ?? false) {
                $counter = $counters->firstWhere('number', (int) $validated['counter_number']);
                $occupantId = (int) ($counter->visitor_entry_id ?? 0);

                if ($occupantId && $occupantId !== $entry->id) {
                    throw ValidationException::withMessages([
                        'counter_number' => "Loket {$counter->number} sedang sibuk. Silakan pilih loket lain.",
                    ]);
                }

                foreach ($counters as $assignedCounter) {
                    if ((int) $assignedCounter->visitor_entry_id === $entry->id && $assignedCounter->number !== $counter->number) {
                        $assignedCounter->update(['visitor_entry_id' => null]);
                    }
                }

                $counter->update(['visitor_entry_id' => $entry->id]);
            } elseif ($validated['service_status'] !== 'serving') {
                foreach ($counters as $assignedCounter) {
                    if ((int) $assignedCounter->visitor_entry_id === $entry->id) {
                        $assignedCounter->update(['visitor_entry_id' => null]);
                    }
                }
            }

            $entry->update(['service_status' => $validated['service_status']]);

            return ($validated['call_announcement'] ?? false)
                ? QueueCall::create([
                    'visitor_entry_id' => $entry->id,
                    'counter_number' => $validated['counter_number'],
                ])
                : null;
        });

        $statusLabel = $callEvent
            ? "Dilayani di loket ke-{$callEvent->counter_number}"
            : self::STATUSES[$visitorEntry->service_status];

        return response()->json([
            'queue_no' => $visitorEntry->queue_no,
            'service_status' => $validated['service_status'],
            'status_label' => $statusLabel,
            'call_event_id' => $callEvent?->id,
            'counter_number' => $callEvent?->counter_number,
            'busy_counters' => ServiceCounter::query()->whereNotNull('visitor_entry_id')->pluck('visitor_entry_id', 'number')->all(),
            'message' => $callEvent
                ? 'Memanggil nomor antrean ' . $visitorEntry->queue_no . ' ke loket ' . $callEvent->counter_number
                : 'Status antrean berhasil diperbarui.',
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);
        $tempPath = tempnam(sys_get_temp_dir(), 'bps-guests-');
        abort_if($tempPath === false, 500, 'Tidak dapat menyiapkan file ekspor.');

        $writer = new Writer;
        $writer->openToFile($tempPath);
        $writer->addRow(Row::fromValues([
            'No Antrean',
            'Tanggal',
            'Waktu Masuk',
            'Nama Lengkap',
            'Asal Instansi',
            'Keperluan',
            'Detail Keperluan',
            'Pengurusan update DTSEN',
            'Jenis Kelamin',
            'No HP',
            'Email',
            'Pekerjaan',
            'Detail Pekerjaan',
            'Status Pelayanan',
        ]));

        foreach ($this->filteredEntries($filters)->orderBy('created_at')->orderBy('id')->cursor() as $entry) {
            $displayCreatedAt = $entry->display_created_at;
            $writer->addRow(Row::fromValues([
                $entry->queue_no,
                $displayCreatedAt?->format('d/m/Y'),
                $displayCreatedAt?->format('H:i:s'),
                $entry->full_name,
                $entry->institution,
                self::PURPOSES[$entry->purpose] ?? self::PURPOSES[$entry->service_code] ?? $entry->purpose,
                $entry->purpose_other ?? '',
                $entry->dtsen_update ? 'Ya' : 'Tidak',
                $entry->gender,
                $entry->phone,
                $entry->email,
                self::OCCUPATIONS[$entry->occupation] ?? $entry->occupation,
                $entry->occupation_other ?? '',
                self::STATUSES[$entry->service_status] ?? $entry->service_status,
            ]));
        }

        $writer->close();

        return response()->download($tempPath, 'daftar-tamu-bps-' . now()->format('Ymd-His') . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function validatedFilters(Request $request): array
    {
        return Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:120'],
            'service' => ['nullable', 'in:PST,LPSE,PPID,KEGIATAN'],
            'status' => ['nullable', 'in:waiting,serving,completed'],
        ])->validate();
    }

    private function filteredEntries(array $filters)
    {
        return app(GuestSearch::class)->databaseQuery($filters)->reorder();
    }
}
