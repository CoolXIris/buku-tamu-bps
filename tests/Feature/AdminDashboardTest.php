<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitorEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_prefix_redirects_guests_to_google_login(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('admin.home'))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Selamat datang')
            ->assertSee('Login Google belum dikonfigurasi.');
    }

    public function test_authenticated_admin_sees_database_backed_daily_metrics(): void
    {
        $admin = User::factory()->create(['name' => 'Admin BPS']);

        VisitorEntry::create($this->visitorData([
            'queue_no' => 'PST0001',
            'service_code' => 'PST',
            'queue_number' => 1,
            'occupation' => 'ASN',
            'service_status' => 'serving',
        ]));
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'LPSE0001',
            'service_code' => 'LPSE',
            'queue_number' => 1,
            'purpose' => 'LPSE',
            'occupation' => 'SWASTA',
            'service_status' => 'completed',
        ]));
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'PPID0001',
            'service_code' => 'PPID',
            'queue_number' => 1,
            'purpose' => 'PPID',
            'occupation' => 'PENELITI',
            'service_status' => 'waiting',
        ]));

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Admin BPS')
            ->assertSee('TOTAL PENGUNJUNG HARI INI')
            ->assertSee('SEDANG DILAYANI')
            ->assertSee('SUDAH DILAYANI')
            ->assertSee('Jumlah pengunjung')
            ->assertSee('Asal kategori pengunjung');

        $response->assertViewHas('todayTotal', 3)
            ->assertViewHas('servingCount', 1)
            ->assertViewHas('completedCount', 1);
    }

    private function visitorData(array $overrides = []): array
    {
        return array_merge([
            'queue_no' => 'PST0001',
            'service_code' => 'PST',
            'queue_number' => 1,
            'full_name' => 'Pengunjung Uji',
            'gender' => 'Perempuan',
            'institution' => 'Instansi Uji',
            'phone' => '081234567890',
            'email' => uniqid('tamu', true) . '@example.test',
            'occupation' => 'PELAJAR',
            'purpose' => 'PST',
            'service_status' => 'waiting',
        ], $overrides);
    }
}
