<?php

namespace Tests\Feature;

use App\Models\QueueCall;
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
