<?php

namespace Tests\Feature;

use App\Models\VisitorEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
