<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VisitorSearchLoadTestSeeder extends Seeder
{
    private const RECORD_COUNT = 5000;

    private const SERVICES = ['PST', 'LPSE', 'PPID', 'KEGIATAN'];

    private const INSTITUTIONS = [
        'BPS Sumatera Selatan',
        'BPS Kota Palembang',
        'BPS Kabupaten Banyuasin',
        'Universitas Sriwijaya',
        'UIN Raden Fatah Palembang',
        'Universitas Muhammadiyah Palembang',
        'Pemerintah Provinsi Sumatera Selatan',
        'Dinas Komunikasi dan Informatika Sumatera Selatan',
        'Dinas Pendidikan Kota Palembang',
        'Politeknik Negeri Sriwijaya',
        'Bank Sumsel Babel',
        'Kantor Wilayah Kementerian Agama Sumatera Selatan',
    ];

    private const OCCUPATIONS = ['ASN', 'SWASTA', 'WIRASWASTA', 'PENELITI', 'PELAJAR', 'LAINNYA'];

    private const PURPOSE_DETAILS = [
        'Konsultasi data inflasi dan harga kebutuhan pokok',
        'Permintaan data statistik ketenagakerjaan',
        'Koordinasi publikasi statistik daerah',
        'Konsultasi pengadaan barang dan jasa',
        'Permintaan informasi publikasi BPS',
        'Rapat koordinasi statistik sektoral',
        'Pendampingan pengisian metadata statistik',
        'Diskusi pemanfaatan data sensus penduduk',
        'Konsultasi data pertumbuhan ekonomi',
        'Koordinasi kegiatan penelitian kampus',
    ];

    public function run(): void
    {
        $faker = Factory::create('id_ID');
        $faker->seed(20260929);
        $startDate = CarbonImmutable::now()->subYears(3);
        $endDate = CarbonImmutable::now();
        $emails = [];

        for ($sequence = 1; $sequence <= self::RECORD_COUNT; $sequence++) {
            $emails[] = $this->email($sequence);
        }

        $existingEmails = collect($emails)
            ->chunk(500)
            ->flatMap(fn ($emailChunk) => DB::table('visitor_entries')->whereIn('email', $emailChunk)->pluck('email'))
            ->flip();

        $rows = [];
        $now = now();

        for ($sequence = 1; $sequence <= self::RECORD_COUNT; $sequence++) {
            $email = $this->email($sequence);
            if ($existingEmails->has($email)) {
                continue;
            }

            $service = self::SERVICES[($sequence - 1) % count(self::SERVICES)];
            $occupation = self::OCCUPATIONS[$faker->numberBetween(0, count(self::OCCUPATIONS) - 1)];
            $createdAt = CarbonImmutable::instance($faker->dateTimeBetween($startDate, $endDate));
            $purposeOther = $service === 'KEGIATAN'
                ? self::PURPOSE_DETAILS[$faker->numberBetween(0, count(self::PURPOSE_DETAILS) - 1)]
                : null;
            $occupationOther = $occupation === 'LAINNYA' ? $faker->jobTitle() : null;

            $rows[] = [
                'queue_no' => $service.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'service_code' => $service,
                'queue_number' => $faker->numberBetween(1, 9999),
                'full_name' => match ($sequence) {
                    1 => 'Sabit Huraira',
                    2 => 'Muhammad Kharis',
                    default => $faker->name(),
                },
                'gender' => $faker->randomElement(['Laki-laki', 'Perempuan']),
                'institution' => $sequence === 1
                    ? 'BPS Sumatera Selatan'
                    : $faker->randomElement(self::INSTITUTIONS),
                'phone' => '08'.str_pad((string) $faker->numberBetween(0, 9999999999), 10, '0', STR_PAD_LEFT),
                'email' => $email,
                'occupation' => $occupation,
                'occupation_other' => $occupationOther,
                'purpose' => $service,
                'purpose_other' => $purposeOther,
                'service_status' => $faker->randomElement(['waiting', 'serving', 'completed']),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $createdAt->format('Y-m-d H:i:s'),
            ];

            if (count($rows) === 500) {
                DB::table('visitor_entries')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('visitor_entries')->insert($rows);
        }

        $added = self::RECORD_COUNT - $existingEmails->count();
        $dummyTotal = $existingEmails->count() + $added;
        $this->command?->info("Data uji pencarian selesai: {$added} entri baru, target total {$dummyTotal} entri dummy.");
        $this->command?->info('Selanjutnya jalankan: php artisan guests:reindex-search');
    }

    private function email(int $sequence): string
    {
        return 'visitor.loadtest+'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT).'@example.test';
    }
}
