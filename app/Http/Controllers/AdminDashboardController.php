<?php

namespace App\Http\Controllers;

use App\Models\VisitorEntry;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private const SERVICES = [
        'PST' => ['name' => 'PST', 'detail' => 'Pelayanan Statistik Terpadu', 'color' => 'blue'],
        'LPSE' => ['name' => 'LPSE', 'detail' => 'Layanan Pengadaan Secara Elektronik', 'color' => 'red'],
        'PPID' => ['name' => 'PPID', 'detail' => 'Pejabat Pengelola Informasi dan Dokumentasi', 'color' => 'orange'],
        'KEGIATAN' => ['name' => 'Kegiatan lainnya', 'detail' => 'Kegiatan kedinasan lainnya', 'color' => 'green'],
    ];

    private const OCCUPATIONS = [
        'ASN' => 'Aparatur Sipil Negara',
        'SWASTA' => 'Karyawan Swasta',
        'WIRASWASTA' => 'Wiraswasta',
        'PENELITI' => 'Peneliti',
        'PELAJAR' => 'Pelajar / Mahasiswa',
        'LAINNYA' => 'Lainnya',
    ];

    public function home(): View|RedirectResponse
    {
        return Auth::check()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.login');
    }

    public function index(): View
    {
        $today = Carbon::today();
        $todayQuery = VisitorEntry::query()->whereDate('created_at', $today);
        $todayTotal = (clone $todayQuery)->count();
        $servingCount = (clone $todayQuery)->where('service_status', 'serving')->count();
        $completedCount = (clone $todayQuery)->where('service_status', 'completed')->count();

        $dailyCounts = VisitorEntry::query()
            ->whereBetween('created_at', [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as visit_date, COUNT(*) as visitor_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('visitor_count', 'visit_date');

        $days = collect(CarbonPeriod::create($today->copy()->subDays(6), $today));
        $chartLabels = $days->map(fn (Carbon $day): string => $day->translatedFormat('D, d M'))->all();
        $chartValues = $days->map(fn (Carbon $day): int => (int) ($dailyCounts[$day->toDateString()] ?? 0))->all();

        $serviceCounts = VisitorEntry::query()
            ->whereDate('created_at', $today)
            ->select('service_code', DB::raw('COUNT(*) as visitor_count'))
            ->groupBy('service_code')
            ->pluck('visitor_count', 'service_code');
        $services = collect(self::SERVICES)->map(function (array $service, string $code) use ($serviceCounts, $todayTotal): array {
            $service['count'] = (int) ($serviceCounts[$code] ?? 0);
            $service['percentage'] = $todayTotal > 0 ? (int) round($service['count'] / $todayTotal * 100) : 0;

            return $service;
        });

        $occupationCounts = VisitorEntry::query()
            ->whereDate('created_at', $today)
            ->select('occupation', DB::raw('COUNT(*) as visitor_count'))
            ->groupBy('occupation')
            ->pluck('visitor_count', 'occupation');
        $occupations = collect(self::OCCUPATIONS)->map(function (string $label, string $code) use ($occupationCounts, $todayTotal): array {
            $count = (int) ($occupationCounts[$code] ?? 0);

            return [
                'name' => $label,
                'count' => $count,
                'percentage' => $todayTotal > 0 ? (int) round($count / $todayTotal * 100) : 0,
            ];
        });

        return view('admin.dashboard', [
            'todayTotal' => $todayTotal,
            'servingCount' => $servingCount,
            'completedCount' => $completedCount,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'services' => $services,
            'occupations' => $occupations,
            'admin' => Auth::user(),
        ]);
    }
}
