<?php

namespace App\Http\Controllers;

use App\Models\VisitorEntry;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    private const OCCUPATION_VALUES = [
        'ASN' => ['ASN', 'Aparatur Sipil Negara'],
        'SWASTA' => ['SWASTA', 'Karyawan Swasta'],
        'WIRASWASTA' => ['WIRASWASTA', 'Wiraswasta'],
        'PENELITI' => ['PENELITI', 'Peneliti'],
        'PELAJAR' => ['PELAJAR', 'Pelajar / Mahasiswa', 'Pelajar/Mahasiswa'],
        'LAINNYA' => ['LAINNYA', 'Lainnya'],
    ];

    public function home(): View|RedirectResponse
    {
        return Auth::check()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.login');
    }

    public function index(Request $request): View
    {
        $today = Carbon::today();
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $selectedMonth = Carbon::createFromFormat('!Y-m', $validated['month'] ?? $today->format('Y-m'));
        $currentMonth = $today->copy()->startOfMonth();

        if ($selectedMonth->greaterThan($currentMonth)) {
            $selectedMonth = $currentMonth->copy();
        }

        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $monthQuery = VisitorEntry::query()->whereBetween('created_at', [$monthStart, $monthEnd]);
        $selectedTotal = (clone $monthQuery)->count();

        $todayQuery = VisitorEntry::query()->whereDate('created_at', $today);
        $todayTotal = (clone $todayQuery)->count();
        $servingCount = (clone $todayQuery)->where('service_status', 'serving')->count();
        $completedCount = (clone $todayQuery)->where('service_status', 'completed')->count();
        $activeVisitorCount = VisitorEntry::query()->inProgress()->count();

        $dailyCounts = (clone $monthQuery)
            ->selectRaw('DATE(created_at) as visit_date, COUNT(*) as visitor_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('visitor_count', 'visit_date');

        $days = collect(CarbonPeriod::create($monthStart, $monthEnd));
        $chartLabels = $days->map(fn(Carbon $day): string => $day->format('d'))->all();
        $chartValues = $days->map(fn(Carbon $day): int => (int) ($dailyCounts[$day->toDateString()] ?? 0))->all();

        $serviceCounts = (clone $monthQuery)
            ->select('service_code', DB::raw('COUNT(*) as visitor_count'))
            ->groupBy('service_code')
            ->pluck('visitor_count', 'service_code');
        $services = collect(self::SERVICES)->map(function (array $service, string $code) use ($serviceCounts, $selectedTotal): array {
            $service['count'] = (int) ($serviceCounts[$code] ?? 0);
            $service['percentage'] = $selectedTotal > 0 ? (int) round($service['count'] / $selectedTotal * 100) : 0;

            return $service;
        });

        $occupationCounts = (clone $monthQuery)
            ->select('occupation', DB::raw('COUNT(*) as visitor_count'))
            ->groupBy('occupation')
            ->pluck('visitor_count', 'occupation');
        $occupations = collect(self::OCCUPATIONS)->map(function (string $label, string $code) use ($occupationCounts, $selectedTotal): array {
            $count = array_sum(array_map(
                fn(string $value): int => (int) ($occupationCounts[$value] ?? 0),
                self::OCCUPATION_VALUES[$code],
            ));

            return [
                'name' => $label,
                'count' => $count,
                'percentage' => $selectedTotal > 0 ? (int) round($count / $selectedTotal * 100) : 0,
            ];
        });

        $earliestVisit = VisitorEntry::query()->min('created_at');
        $firstMonth = $earliestVisit ? Carbon::parse($earliestVisit)->startOfMonth() : $currentMonth->copy();
        if ($firstMonth->greaterThan($currentMonth)) {
            $firstMonth = $currentMonth->copy();
        }
        $availableMonths = collect(CarbonPeriod::create($firstMonth, '1 month', $currentMonth))
            ->reverse()
            ->mapWithKeys(fn(Carbon $month): array => [$month->format('Y-m') => $month->translatedFormat('F Y')]);

        return view('admin.dashboard', [
            'todayTotal' => $todayTotal,
            'activeVisitorCount' => $activeVisitorCount,
            'servingCount' => $servingCount,
            'completedCount' => $completedCount,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'selectedMonthLabel' => $selectedMonth->translatedFormat('F Y'),
            'availableMonths' => $availableMonths,
            'selectedTotal' => $selectedTotal,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'services' => $services,
            'occupations' => $occupations,
            'admin' => Auth::user(),
        ]);
    }
}
