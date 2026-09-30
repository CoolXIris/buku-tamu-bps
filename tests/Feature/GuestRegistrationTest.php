<?php

namespace Tests\Feature;

use App\Models\QueueCounter;
use App\Models\VisitorEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GuestRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_guest_is_saved_and_receives_service_queue_number(): void
    {
        $response = $this->post(route('guests.store'), $this->guestData());

        $entry = VisitorEntry::query()->firstOrFail();

        $response->assertRedirect(route('guests.receipt', $entry));
        $this->assertSame('PST0001', $entry->queue_no);
        $this->assertDatabaseHas('visitor_entries', [
            'full_name' => 'Siti Aminah',
            'service_code' => 'PST',
            'queue_no' => 'PST0001',
            'dtsen_update' => false,
        ]);

        $this->post(route('guests.store'), $this->guestData(['purpose' => 'PST']))
            ->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));
        $this->assertDatabaseHas('visitor_entries', ['queue_no' => 'PST0002']);

        $this->post(route('guests.store'), $this->guestData(['purpose' => 'LPSE']))
            ->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));
        $this->assertDatabaseHas('visitor_entries', ['queue_no' => 'LPSE0001']);

        $this->post(route('guests.store'), $this->guestData([
            'purpose' => 'KEGIATAN',
            'purpose_other' => 'Kegiatan BPS',
        ]))->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));
        $this->assertDatabaseHas('visitor_entries', [
            'service_code' => 'KEGIATAN',
            'queue_no' => 'UMUM0001',
        ]);
    }

    public function test_dtsen_update_is_saved_only_for_pst_and_defaults_to_unchecked(): void
    {
        $this->post(route('guests.store'), $this->guestData([
            'full_name' => 'Dewi',
            'email' => 'dewi@example.test',
            'dtsen_update' => '1',
        ]))->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));

        $this->assertDatabaseHas('visitor_entries', [
            'full_name' => 'Dewi',
            'dtsen_update' => true,
        ]);

        $this->post(route('guests.store'), $this->guestData([
            'purpose' => 'LPSE',
            'full_name' => 'Rina',
            'email' => 'rina@example.test',
            'dtsen_update' => '1',
        ]))->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));

        $this->assertDatabaseHas('visitor_entries', [
            'full_name' => 'Rina',
            'dtsen_update' => false,
        ]);
    }

    public function test_other_occupation_and_activity_require_details(): void
    {
        $response = $this->from(route('guests.index'))->post(route('guests.store'), $this->guestData([
            'occupation' => 'LAINNYA',
            'occupation_other' => '',
            'purpose' => 'KEGIATAN',
            'purpose_other' => '',
        ]));

        $response->assertRedirect(route('guests.index'))
            ->assertSessionHasErrors(['occupation_other', 'purpose_other']);
        $this->assertDatabaseCount('visitor_entries', 0);
    }

    public function test_counter_is_resynced_when_existing_queue_numbers_are_present(): void
    {
        VisitorEntry::query()->create([
            'full_name' => 'Ayu',
            'gender' => 'Perempuan',
            'institution' => 'BPS',
            'phone' => '081111111111',
            'email' => 'ayu@example.test',
            'occupation' => 'ASN',
            'purpose' => 'LPSE',
            'service_code' => 'LPSE',
            'queue_number' => 1,
            'queue_no' => 'LPSE0001',
        ]);

        VisitorEntry::query()->create([
            'full_name' => 'Budi',
            'gender' => 'Laki-laki',
            'institution' => 'BPS',
            'phone' => '082222222222',
            'email' => 'budi@example.test',
            'occupation' => 'ASN',
            'purpose' => 'LPSE',
            'service_code' => 'LPSE',
            'queue_number' => 2,
            'queue_no' => 'LPSE0002',
        ]);

        QueueCounter::query()->create([
            'service_code' => 'LPSE',
            'service_date' => now()->toDateString(),
            'last_number' => 0,
        ]);

        $this->post(route('guests.store'), $this->guestData([
            'purpose' => 'LPSE',
            'full_name' => 'Citra',
            'email' => 'citra@example.test',
        ]))
            ->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));

        $this->assertDatabaseHas('visitor_entries', ['queue_no' => 'LPSE0003']);
    }

    public function test_queue_number_skips_existing_numbers_today_and_resets_for_a_new_day(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        VisitorEntry::query()->create([
            'full_name' => 'Tamu Seeder',
            'gender' => 'Perempuan',
            'institution' => 'BPS',
            'phone' => '081111111111',
            'email' => 'seeder@example.test',
            'occupation' => 'ASN',
            'purpose' => 'PST',
            'service_code' => 'PST',
            'queue_number' => 1,
            'queue_no' => 'PST0001',
        ]);

        $this->post(route('guests.store'), $this->guestData())
            ->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));
        $this->assertDatabaseHas('visitor_entries', ['queue_no' => 'PST0002']);

        Carbon::setTestNow('2026-10-01 10:00:00');

        $this->post(route('guests.store'), $this->guestData([
            'full_name' => 'Tamu Hari Berikutnya',
            'email' => 'besok@example.test',
        ]))->assertRedirect(route('guests.receipt', VisitorEntry::query()->latest('id')->first()));

        $this->assertSame(2, VisitorEntry::query()->where('queue_no', 'PST0001')->count());
        $this->assertDatabaseHas('queue_counters', [
            'service_code' => 'PST',
            'service_date' => '2026-10-01',
            'last_number' => 1,
        ]);

        Carbon::setTestNow();
    }

    private function guestData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Siti Aminah',
            'gender' => 'Perempuan',
            'institution' => 'Universitas Sriwijaya',
            'phone' => '081234567890',
            'email' => 'siti@example.test',
            'occupation' => 'PELAJAR',
            'purpose' => 'PST',
        ], $overrides);
    }
}
