<?php

namespace Tests\Feature;

use App\Models\QueueCall;
use App\Models\ServiceCounter;
use App\Models\User;
use App\Models\VisitorEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
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

    public function test_guest_list_badge_counts_only_waiting_and_serving_visitors(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData());
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'PST0002',
            'queue_number' => 2,
            'service_status' => 'serving',
        ]));
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'PST0003',
            'queue_number' => 3,
            'service_status' => 'completed',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.guests.index'))
            ->assertOk()
            ->assertSee('class="nav-count">2</span>', false);

        VisitorEntry::query()->update(['service_status' => 'completed']);

        $this->get(route('admin.guests.index'))
            ->assertOk()
            ->assertSee('class="nav-count">0</span>', false);
    }

    public function test_guest_search_matches_terms_across_fields_when_elasticsearch_is_not_configured(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData());
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'LPSE0001',
            'service_code' => 'LPSE',
            'purpose' => 'LPSE',
            'full_name' => 'Budi Pengadaan',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.guests.index', ['q' => 'Aminah 0812']))
            ->assertOk()
            ->assertSee('PST0001')
            ->assertDontSee('LPSE0001');
    }

    public function test_guest_search_uses_elasticsearch_fuzzy_results_and_preserves_relevance_order(): void
    {
        $admin = User::factory()->create();
        $first = VisitorEntry::create($this->visitorData());
        $second = VisitorEntry::create($this->visitorData([
            'queue_no' => 'LPSE0001',
            'service_code' => 'LPSE',
            'purpose' => 'LPSE',
            'full_name' => 'Siti Pengadaan',
        ]));

        config([
            'services.elasticsearch.url' => 'https://elastic.example.test',
            'services.elasticsearch.api_key' => 'test-api-key',
            'services.elasticsearch.index' => 'bps-guests-test',
        ]);

        Http::fake([
            'elastic.example.test/bps-guests-test/_search' => Http::response([
                'hits' => [
                    'total' => ['value' => 2],
                    'hits' => [
                        ['_id' => (string) $second->id],
                        ['_id' => (string) $first->id],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.guests.index', ['q' => 'Sitti']))
            ->assertOk()
            ->assertSeeInOrder(['LPSE0001', 'PST0001']);

        Http::assertSent(static function (ClientRequest $request): bool {
            $searchClauses = data_get($request->data(), 'query.bool.should', []);
            $multiMatch = collect($searchClauses)
                ->first(static fn(array $clause): bool => isset($clause['multi_match']))['multi_match'] ?? [];
            $partialSearch = collect($searchClauses)
                ->first(static fn(array $clause): bool => data_get($clause, 'bool.should') !== null);
            $partialNameQuery = collect(data_get($partialSearch, 'bool.should', []))
                ->first(static fn(array $clause): bool => isset($clause['wildcard']['full_name.keyword']));

            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'ApiKey test-api-key')
                && data_get($multiMatch, 'fuzziness') === 'AUTO'
                && in_array('full_name^5', data_get($multiMatch, 'fields', []), true)
                && data_get($request->data(), 'query.bool.minimum_should_match') === 1
                && ($partialNameQuery['wildcard']['full_name.keyword']['value'] ?? null) === '*Sitti*'
                && ($partialNameQuery['wildcard']['full_name.keyword']['case_insensitive'] ?? false) === true
                && data_get($request->data(), 'sort.0._score') === 'desc'
                && data_get($request->data(), 'sort.2._id') === null;
        });
    }

    public function test_guest_search_expands_sumsel_alias_to_sumatera_selatan(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData());

        config([
            'services.elasticsearch.url' => 'https://elastic.example.test',
            'services.elasticsearch.api_key' => 'test-api-key',
            'services.elasticsearch.index' => 'bps-guests-test',
        ]);

        Http::fake([
            'elastic.example.test/bps-guests-test/_search' => Http::response([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [['_id' => '1', '_source' => ['full_name' => 'Siti Aminah']]],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.guests.index', ['q' => 'sumsel']))
            ->assertOk()
            ->assertSee('Siti Aminah');

        Http::assertSent(static function (ClientRequest $request): bool {
            $clauses = data_get($request->data(), 'query.bool.should', []);

            return collect($clauses)->contains(
                static fn(array $clause): bool =>
                data_get($clause, 'multi_match.query') === 'sumatera selatan'
                    && data_get($clause, 'multi_match.operator') === 'and'
            );
        });
    }

    public function test_guest_reindex_command_creates_the_index_and_bulk_imports_existing_entries(): void
    {
        VisitorEntry::create($this->visitorData());
        config([
            'services.elasticsearch.url' => 'https://elastic.example.test',
            'services.elasticsearch.api_key' => 'test-api-key',
            'services.elasticsearch.index' => 'bps-guests-test',
        ]);

        Http::fake(static function (ClientRequest $request) {
            if ($request->method() === 'HEAD') {
                return Http::response([], 404);
            }

            if (str_ends_with($request->url(), '/_bulk')) {
                return Http::response(['errors' => false]);
            }

            return Http::response(['acknowledged' => true]);
        });

        $this->artisan('guests:reindex-search')
            ->expectsOutput('Indeks Elasticsearch siap. 1 data tamu diproses.')
            ->assertExitCode(0);

        Http::assertSent(static fn(ClientRequest $request): bool => $request->method() === 'PUT'
            && data_get($request->data(), 'mappings.properties.service_code.fields.keyword.type') === 'keyword');
        Http::assertSent(static fn(ClientRequest $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/_bulk')
            && str_contains($request->body(), 'Siti Aminah'));
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
            'counter_number' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('service_status', 'serving')
            ->assertJsonPath('status_label', 'Dilayani di loket ke-3')
            ->assertJsonPath('counter_number', 3)
            ->assertJsonPath('busy_counters.3', $entry->id);

        $this->assertDatabaseHas('visitor_entries', ['id' => $entry->id, 'service_status' => 'serving']);
        $this->assertDatabaseHas('queue_calls', ['visitor_entry_id' => $entry->id, 'counter_number' => 3]);

        $this->patchJson(route('admin.guests.status', $entry), ['service_status' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_status');

        $this->patchJson(route('admin.guests.status', $entry), [
            'service_status' => 'serving',
            'call_announcement' => true,
            'counter_number' => 7,
        ])->assertUnprocessable()->assertJsonValidationErrors('counter_number');
    }

    public function test_guest_list_shows_dtsen_update_value_for_each_visitor(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData(['dtsen_update' => true]));
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'PST0002',
            'queue_number' => 2,
            'dtsen_update' => false,
        ]));
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'LPSE0001',
            'service_code' => 'LPSE',
            'queue_number' => 1,
            'purpose' => 'LPSE',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.guests.index'))
            ->assertOk()
            ->assertSee('Pengurusan Update DTSEN')
            ->assertSee('Ya')
            ->assertSee('Tidak')
            ->assertSee('—');
    }

    public function test_admin_can_update_guest_details_and_dtsen_value(): void
    {
        $admin = User::factory()->create();
        $entry = VisitorEntry::create($this->visitorData());

        $this->actingAs($admin)
            ->patchJson(route('admin.guests.update', $entry), [
                'full_name' => 'Siti Aminah Baru',
                'gender' => 'Perempuan',
                'institution' => 'BPS Sumsel',
                'phone' => '081299887766',
                'email' => 'siti.baru@example.test',
                'occupation' => 'LAINNYA',
                'occupation_other' => 'Analis data',
                'purpose' => 'PST',
                'purpose_other' => '',
                'dtsen_update' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Data tamu berhasil diperbarui.');

        $this->assertDatabaseHas('visitor_entries', [
            'id' => $entry->id,
            'queue_no' => 'PST0001',
            'full_name' => 'Siti Aminah Baru',
            'institution' => 'BPS Sumsel',
            'occupation' => 'LAINNYA',
            'occupation_other' => 'Analis data',
            'purpose' => 'PST',
            'service_code' => 'PST',
            'dtsen_update' => true,
        ]);

        $this->patchJson(route('admin.guests.update', $entry), [
            'full_name' => 'Siti Aminah Baru',
            'gender' => 'Perempuan',
            'institution' => 'BPS Sumsel',
            'phone' => '081299887766',
            'email' => 'siti.baru@example.test',
            'occupation' => 'LAINNYA',
            'occupation_other' => 'Analis data',
            'purpose' => 'KEGIATAN',
            'purpose_other' => 'Rapat koordinasi',
            'dtsen_update' => true,
        ])->assertOk();

        $this->assertDatabaseHas('visitor_entries', [
            'id' => $entry->id,
            'purpose' => 'KEGIATAN',
            'service_code' => 'KEGIATAN',
            'purpose_other' => 'Rapat koordinasi',
            'dtsen_update' => false,
        ]);
    }

    public function test_admin_can_delete_guest_and_related_queue_data(): void
    {
        $admin = User::factory()->create();
        $entry = VisitorEntry::create($this->visitorData());
        $call = QueueCall::create(['visitor_entry_id' => $entry->id, 'counter_number' => 2]);
        ServiceCounter::query()->where('number', 2)->update(['visitor_entry_id' => $entry->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.guests.destroy', $entry))
            ->assertOk()
            ->assertJsonPath('message', 'Data tamu berhasil dihapus.');

        $this->assertDatabaseMissing('visitor_entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('queue_calls', ['id' => $call->id]);
        $this->assertDatabaseHas('service_counters', ['number' => 2, 'visitor_entry_id' => null]);
    }

    public function test_a_busy_counter_cannot_be_assigned_twice_and_is_freed_after_completion(): void
    {
        $admin = User::factory()->create();
        $firstEntry = VisitorEntry::create($this->visitorData());
        $secondEntry = VisitorEntry::create($this->visitorData([
            'queue_no' => 'PST0002',
            'queue_number' => 2,
            'full_name' => 'Budi',
        ]));
        $this->actingAs($admin);

        $this->patchJson(route('admin.guests.status', $firstEntry), [
            'service_status' => 'serving',
            'call_announcement' => true,
            'counter_number' => 3,
        ])->assertOk();

        $this->get(route('admin.guests.index'))
            ->assertOk()
            ->assertSee('Loket 3 (Sibuk)');

        $this->patchJson(route('admin.guests.status', $secondEntry), [
            'service_status' => 'serving',
            'call_announcement' => true,
            'counter_number' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('counter_number');

        $this->patchJson(route('admin.guests.status', $firstEntry), [
            'service_status' => 'completed',
        ])->assertOk()->assertJsonPath('busy_counters', []);

        $this->patchJson(route('admin.guests.status', $secondEntry), [
            'service_status' => 'serving',
            'call_announcement' => true,
            'counter_number' => 3,
        ])->assertOk()->assertJsonPath('busy_counters.3', $secondEntry->id);
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
        QueueCall::create(['visitor_entry_id' => $serving->id, 'counter_number' => 4]);

        $this->get(route('queue.screen'))
            ->assertOk()
            ->assertSee('layar-antrean');

        $this->getJson(route('queue.data'))
            ->assertOk()
            ->assertJsonPath('waiting.0.queue_no', $waiting->queue_no)
            ->assertJsonPath('serving.0.queue_no', $serving->queue_no)
            ->assertJsonPath('latest_call.queue_no', $serving->queue_no)
            ->assertJsonPath('latest_call.event_id', 1)
            ->assertJsonPath('latest_call.counter_number', 4)
            ->assertJsonPath('counters.3.number', 4)
            ->assertJsonPath('counters.3.serving.queue_no', $serving->queue_no)
            ->assertJsonPath('services.2.serving.queue_no', $serving->queue_no);
    }

    public function test_guest_list_export_downloads_an_xlsx_file(): void
    {
        $admin = User::factory()->create();
        VisitorEntry::create($this->visitorData(['dtsen_update' => true]));
        VisitorEntry::create($this->visitorData([
            'queue_no' => 'PST0002',
            'queue_number' => 2,
            'dtsen_update' => false,
        ]));

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

        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($filePath);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertContains('Pengurusan update DTSEN', $rows[0]);
        $dtsenColumn = array_search('Pengurusan update DTSEN', $rows[0], true);
        $this->assertSame('Ya', $rows[1][$dtsenColumn]);
        $this->assertSame('Tidak', $rows[2][$dtsenColumn]);
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
