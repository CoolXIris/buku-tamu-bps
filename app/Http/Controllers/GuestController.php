<?php

namespace App\Http\Controllers;

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
        ]);

        $entry = DB::transaction(function () use ($validated): VisitorEntry {
            $serviceCode = $validated['purpose'];
            $serviceDate = now()->toDateString();

            DB::table('queue_counters')->upsert(
                [[
                    'service_code' => $serviceCode,
                    'service_date' => $serviceDate,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['service_code', 'service_date'],
                ['service_code'],
            );

            $counter = DB::table('queue_counters')
                ->where('service_code', $serviceCode)
                ->where('service_date', $serviceDate)
                ->lockForUpdate()
                ->first();

            $nextNumber = $counter->last_number + 1;
            DB::table('queue_counters')
                ->where('id', $counter->id)
                ->update(['last_number' => $nextNumber, 'updated_at' => now()]);

            return VisitorEntry::create([
                ...$validated,
                'service_code' => $serviceCode,
                'queue_number' => $nextNumber,
                'queue_no' => $serviceCode . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
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