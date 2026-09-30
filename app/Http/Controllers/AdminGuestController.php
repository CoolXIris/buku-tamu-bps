<?php

namespace App\Http\Controllers;

use App\Models\QueueCall;
use App\Models\VisitorEntry;
use App\Services\GuestSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $entries = app(GuestSearch::class)->paginate($filters, $request->integer('page', 1))->withQueryString();

        return view('admin.guests.index', [
            'entries' => $entries,
            'filters' => $filters,
            'services' => self::SERVICES,
            'statuses' => self::STATUSES,
            'totalVisitors' => VisitorEntry::count(),
            'admin' => Auth::user(),
        ]);
    }

    public function show(VisitorEntry $visitorEntry): JsonResponse
    {
        return response()->json([
            'queue_no' => $visitorEntry->queue_no,
            'created_at' => $visitorEntry->created_at?->format('d/m/Y H:i'),
            'full_name' => $visitorEntry->full_name,
            'institution' => $visitorEntry->institution,
            'purpose' => self::PURPOSES[$visitorEntry->purpose] ?? self::PURPOSES[$visitorEntry->service_code] ?? $visitorEntry->purpose,
            'purpose_other' => $visitorEntry->purpose_other,
            'gender' => $visitorEntry->gender,
            'phone' => $visitorEntry->phone,
            'email' => $visitorEntry->email,
            'occupation' => self::OCCUPATIONS[$visitorEntry->occupation] ?? $visitorEntry->occupation,
            'occupation_other' => $visitorEntry->occupation_other,
            'status' => self::STATUSES[$visitorEntry->service_status] ?? $visitorEntry->service_status,
        ]);
    }

    public function updateStatus(Request $request, VisitorEntry $visitorEntry): JsonResponse
    {
        $validated = $request->validate([
            'service_status' => ['required', 'in:waiting,serving,completed'],
            'call_announcement' => ['sometimes', 'boolean', 'prohibited_unless:service_status,serving'],
        ]);

        $callEvent = DB::transaction(function () use ($visitorEntry, $validated): ?QueueCall {
            $visitorEntry->update(['service_status' => $validated['service_status']]);

            return ($validated['call_announcement'] ?? false)
                ? QueueCall::create(['visitor_entry_id' => $visitorEntry->id])
                : null;
        });

        return response()->json([
            'queue_no' => $visitorEntry->queue_no,
            'service_status' => $visitorEntry->service_status,
            'status_label' => self::STATUSES[$visitorEntry->service_status],
            'call_event_id' => $callEvent?->id,
            'message' => $callEvent
                ? 'Memanggil nomor antrean ' . $visitorEntry->queue_no
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
            'Jenis Kelamin',
            'No HP',
            'Email',
            'Pekerjaan',
            'Detail Pekerjaan',
            'Status Pelayanan',
        ]));

        foreach ($this->filteredEntries($filters)->orderBy('created_at')->orderBy('id')->cursor() as $entry) {
            $writer->addRow(Row::fromValues([
                $entry->queue_no,
                $entry->created_at?->format('d/m/Y'),
                $entry->created_at?->format('H:i:s'),
                $entry->full_name,
                $entry->institution,
                self::PURPOSES[$entry->purpose] ?? self::PURPOSES[$entry->service_code] ?? $entry->purpose,
                $entry->purpose_other ?? '',
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
