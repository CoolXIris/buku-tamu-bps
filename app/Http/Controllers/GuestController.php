<?php

namespace App\Http\Controllers;

use App\Models\QueueCounter;
use App\Models\VisitorEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GuestController extends Controller
{
    private const SERVICES = [
        'PST' => 'Pelayanan Statistik Terpadu',
        'LPSE' => 'Layanan Pengadaan Secara Elektronik',
        'PPID' => 'Pejabat Pengelola Informasi dan Dokumentasi',
        'KEGIATAN' => 'Kegiatan lainnya',
    ];

    public function index(): View
    {
        return view('guests.index', [
            'pstDigitalUrl' => config('services.pst_digital_url', 'https://pst.bps.go.id/'),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
        $validated['dtsen_update'] = $validated['purpose'] === 'PST' && $request->boolean('dtsen_update');

        $entry = DB::transaction(function () use ($validated): VisitorEntry {
            $serviceCode = $validated['purpose'];
            $queuePrefix = $serviceCode === 'KEGIATAN' ? 'UMUM' : $serviceCode;
            $serviceDayStart = now('Asia/Jakarta')->startOfDay();
            $serviceDate = $serviceDayStart->toDateString();
            $serviceDayStartUtc = $serviceDayStart->copy()->utc();
            $nextServiceDayStartUtc = $serviceDayStart->copy()->addDay()->utc();

            QueueCounter::firstOrCreate(
                ['service_code' => $serviceCode, 'service_date' => $serviceDate],
                ['last_number' => 0],
            );

            $counter = QueueCounter::query()
                ->where('service_code', $serviceCode)
                ->where('service_date', $serviceDate)
                ->lockForUpdate()
                ->first();

            $existingEntries = VisitorEntry::query()
                ->where('service_code', $serviceCode)
                ->where('created_at', '>=', $serviceDayStartUtc)
                ->where('created_at', '<', $nextServiceDayStartUtc)
                ->get(['queue_no', 'queue_number']);
            $existingMax = $existingEntries->max(function (VisitorEntry $entry) use ($queuePrefix): int {
                $numberFromQueueNo = preg_match(
                    '/^' . preg_quote($queuePrefix, '/') . '(\d+)$/',
                    $entry->queue_no,
                    $matches,
                ) ? (int) $matches[1] : 0;

                return max((int) $entry->queue_number, $numberFromQueueNo);
            }) ?? 0;

            $number = max((int) $counter->last_number, $existingMax) + 1;
            $counter->update(['last_number' => $number]);

            return VisitorEntry::create([
                ...$validated,
                'service_code' => $serviceCode,
                'queue_number' => $number,
                'queue_no' => $queuePrefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'created_at_is_utc' => true,
            ]);
        });

        return redirect()->route('guests.receipt', $entry);
    }

    public function receipt(VisitorEntry $visitorEntry): View
    {
        abort_unless(isset(self::SERVICES[$visitorEntry->service_code]), 404);

        return view('guests.receipt', [
            'entry' => $visitorEntry,
            'serviceName' => self::SERVICES[$visitorEntry->service_code],
        ]);
    }
}
