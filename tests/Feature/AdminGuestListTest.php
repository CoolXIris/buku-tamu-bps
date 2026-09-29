<?php

namespace Tests\Feature;

use App\Models\QueueCall;
use App\Models\User;
use App\Models\VisitorEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class AdminGuestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_list_supports_search_and_service_and_status_filters(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData());
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'LPSE0001',
            'service_code' => 'LPSE',
            'purpose' => 'LPSE',
            'full_name' => 'Budi Pengadaan',
            'service_status' => 'serving',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.guests.index', ['q' => 'Siti', 'service' => 'PST', 'status' => 'waiting']))
            ->assertOk()
            ->assertSee('PST0001')
            ->assertDontSee('LPSE0001')
            ->assertSee('Siti Aminah');
    }

    public function test_admin_can_read_guest_details_and_change_service_status(): void
    {
        $admin = User::factory()->create();
        $entry = VisitorEntry::create($this->visitorData([
            'queue_no' => 'KGT0001',
            'service_code' => 'KEGIATAN',
            'queue_number' => 1,
            'occupation' => 'LAINNYA',
            'occupation_other' => 'Konsultan',
            'purpose' => 'KEGIATAN',
            'service_code' => 'KEGIATAN',
            'purpose_other' => 'Rapat koordinasi',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.guests.show', $entry))
            ->assertOk()
            ->assertJsonPath('queue_no', 'KGT0001')
            ->assertJsonPath('purpose_other', 'Rapat koordinasi')
            ->assertJsonPath('occupation_other', 'Konsultan');

        $this->patchJson(route('admin.guests.status', $entry), [
            'service_status' => 'serving',
            'call_announcement' => true,
        ])
            ->assertOk()
            ->assertJsonPath('service_status', 'serving')
            ->assertJsonPath('status_label', 'Dilayani');

        $this->assertDatabaseHas('visitor_entries', ['id' => $entry->id, 'service_status' => 'serving']);
        $this->assertDatabaseHas('queue_calls', ['visitor_entry_id' => $entry->id]);

        $this->patchJson(route('admin.guests.status', $entry), ['service_status' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_status');
    }

    public function test_public_queue_screen_shows_waiting_and_called_visitors_without_admin_login(): void
    {
        $waiting = VisitorEntry::create($this->visitorData());
        $serving = VisitorEntry::create($this->visitorData([
            'queue_no' => 'PPID0001',
            'service_code' => 'PPID',
            'purpose' => 'PPID',
            'full_name' => 'Budi Dipanggil',
            'service_status' => 'serving',
        ]));
        QueueCall::create(['visitor_entry_id' => $serving->id]);

        $this->get(route('queue.screen'))
            ->assertOk()
            ->assertSee('layar-antrean');

        $this->getJson(route('queue.data'))
            ->assertOk()
            ->assertJsonPath('waiting.0.queue_no', $waiting->queue_no)
            ->assertJsonPath('serving.0.queue_no', $serving->queue_no)
            ->assertJsonPath('latest_call.queue_no', $serving->queue_no)
            ->assertJsonPath('latest_call.event_id', 1)
            ->assertJsonPath('services.2.serving.queue_no', $serving->queue_no);
    }

    public function test_guest_list_export_downloads_an_xlsx_file(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData());

        $response = $this->actingAs($admin)
            ->get(route('admin.guests.export', ['service' => 'PST']));

        $response->assertDownload()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $workbook = new \ZipArchive;
        /** @var BinaryFileResponse $download */
        $download = $response->baseResponse;
        $filePath = $download->getFile()->getPathname();
        $this->assertSame(true, $workbook->open($filePath));
        $this->assertNotFalse($workbook->locateName('xl/workbook.xml'));
        $workbook->close();
    }

    public function test_guest_list_requires_admin_authentication(): void
    {
        $this->get(route('admin.guests.index'))
            ->assertRedirect(route('admin.login'));
    }

    private function visitorData(array $overrides = []): array
    {
        return array_merge([
            'queue_no' => 'PST0001',
            'service_code' => 'PST',
            'queue_number' => 1,
            'full_name' => 'Siti Aminah',
            'gender' => 'Perempuan',
            'institution' => 'Universitas Sriwijaya',
            'phone' => '081234567890',
            'email' => uniqid('tamu', true) . '@example.test',
            'occupation' => 'PELAJAR',
            'purpose' => 'PST',
            'service_status' => 'waiting',
        ], $overrides);
    }
}
