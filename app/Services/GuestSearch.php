<?php

namespace App\Services;

use App\Models\VisitorEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GuestSearch
{
    private const SEARCH_FIELDS = [
        'full_name^5',
        'institution^3',
        'purpose_text^3',
        'occupation_text^2',
        'purpose_other^3',
        'occupation_other^2',
        'queue_no^2',
        'service_code',
        'gender',
        'phone',
        'email',
        'search_text',
    ];

    private const PARTIAL_SEARCH_FIELDS = [
        'full_name.keyword' => 5,
        'institution.keyword' => 3,
        'purpose_text.keyword' => 3,
        'purpose_other.keyword' => 3,
        'occupation_text.keyword' => 2,
        'occupation_other.keyword' => 2,
        'queue_no.keyword' => 2,
        'service_code.keyword' => 1,
        'gender.keyword' => 1,
        'phone.keyword' => 1,
        'email.keyword' => 1,
        'search_text.keyword' => 1,
    ];

    private const SEARCH_ALIASES = [
        'sumsel' => 'sumatera selatan',
    ];

    private const PURPOSE_LABELS = [
        'PST' => 'Pelayanan Statistik Terpadu',
        'LPSE' => 'Layanan Pengadaan Secara Elektronik',
        'PPID' => 'Pejabat Pengelola Informasi dan Dokumentasi',
        'KEGIATAN' => 'Kegiatan lainnya',
    ];

    private const OCCUPATION_LABELS = [
        'ASN' => 'Aparatur Sipil Negara',
        'SWASTA' => 'Karyawan Swasta',
        'WIRASWASTA' => 'Wiraswasta',
        'PENELITI' => 'Peneliti',
        'PELAJAR' => 'Pelajar Mahasiswa',
        'LAINNYA' => 'Lainnya',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.elasticsearch.url'))
            && filled(config('services.elasticsearch.api_key'));
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 12): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search === '' || ! $this->isConfigured()) {
            return $this->databaseQuery($filters)->paginate($perPage, ['*'], 'page', $page);
        }

        try {
            return $this->elasticsearchPage($filters, $search, $page, $perPage);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->databaseQuery($filters)->paginate($perPage, ['*'], 'page', $page);
        }
    }

    public function databaseQuery(array $filters): Builder
    {
        $query = VisitorEntry::query()
            ->when($filters['service'] ?? null, fn (Builder $query, string $service) => $query->where('service_code', $service))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('service_status', $status));

        $terms = preg_split('/\s+/u', trim((string) ($filters['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $query->where(function (Builder $matches) use ($term): void {
                foreach ([
                    'queue_no', 'full_name', 'institution', 'phone', 'email',
                    'occupation', 'occupation_other', 'purpose', 'purpose_other', 'service_code', 'gender',
                ] as $column) {
                    $matches->orWhere($column, 'like', '%'.$term.'%');
                }

                foreach (self::PURPOSE_LABELS as $code => $label) {
                    if (str_contains(mb_strtolower($label), mb_strtolower($term))) {
                        $matches->orWhere('purpose', $code);
                    }
                }

                foreach (self::OCCUPATION_LABELS as $code => $label) {
                    if (str_contains(mb_strtolower($label), mb_strtolower($term))) {
                        $matches->orWhere('occupation', $code);
                    }
                }
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function prepareIndex(): void
    {
        $index = $this->indexUrl();
        $response = $this->client()->head($index);

        if ($response->successful()) {
            return;
        }

        if ($response->status() !== 404) {
            $response->throw();
        }

        $this->client()->put($index, [
            'mappings' => [
                'properties' => [
                    'created_at' => ['type' => 'date'],
                    'service_status' => ['type' => 'keyword'],
                    'service_code' => [
                        'type' => 'text',
                        'fields' => ['keyword' => ['type' => 'keyword']],
                    ],
                ],
            ],
        ])->throw();
    }

    public function index(VisitorEntry $entry): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $this->client()->put($this->indexUrl().'/_doc/'.$entry->getKey(), $this->document($entry))->throw();
    }

    public function bulkIndex(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        $body = '';
        foreach ($entries as $entry) {
            $body .= json_encode(['index' => ['_id' => (string) $entry->getKey()]], JSON_THROW_ON_ERROR)."\n";
            $body .= json_encode($this->document($entry), JSON_THROW_ON_ERROR)."\n";
        }

        $response = $this->client()
            ->timeout(60)
            ->connectTimeout(5)
            ->withBody($body, 'application/x-ndjson')
            ->post($this->indexUrl().'/_bulk')
            ->throw();

        if ($response->json('errors')) {
            throw new RuntimeException('Elasticsearch melaporkan kegagalan saat mengindeks data tamu.');
        }
    }

    public function delete(int|string $id): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $response = $this->client()->delete($this->indexUrl().'/_doc/'.$id);
        if (! $response->successful() && $response->status() !== 404) {
            $response->throw();
        }
    }

    private function elasticsearchPage(array $filters, string $search, int $page, int $perPage): LengthAwarePaginator
    {
        $filterClauses = [];
        if (! empty($filters['service'])) {
            $filterClauses[] = ['term' => ['service_code.keyword' => $filters['service']]];
        }
        if (! empty($filters['status'])) {
            $filterClauses[] = ['term' => ['service_status' => $filters['status']]];
        }

        $searchClauses = [[
            'multi_match' => [
                'query' => $search,
                'fields' => self::SEARCH_FIELDS,
                'type' => 'best_fields',
                'operator' => 'or',
                'fuzziness' => 'AUTO',
                'prefix_length' => 1,
                'minimum_should_match' => 1,
            ],
        ]];

        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            if (mb_strlen($term) < 2) {
                continue;
            }

            $alias = self::SEARCH_ALIASES[mb_strtolower($term)] ?? null;
            if ($alias !== null) {
                $searchClauses[] = [
                    'multi_match' => [
                        'query' => $alias,
                        'fields' => ['institution^3', 'search_text'],
                        'operator' => 'and',
                        'boost' => 3,
                    ],
                ];
            }

            $escapedTerm = preg_replace_callback('/[\\\\*?]/u', static fn (array $match): string => '\\'.$match[0], $term);
            $partialFieldQueries = [];

            foreach (self::PARTIAL_SEARCH_FIELDS as $field => $boost) {
                $partialFieldQueries[] = [
                    'wildcard' => [
                        $field => [
                            'value' => '*'.$escapedTerm.'*',
                            'case_insensitive' => true,
                            'boost' => $boost,
                        ],
                    ],
                ];
            }

            $searchClauses[] = [
                'bool' => [
                    'should' => $partialFieldQueries,
                    'minimum_should_match' => 1,
                ],
            ];
        }

        $response = $this->client()->post($this->indexUrl().'/_search', [
            'from' => max(0, ($page - 1) * $perPage),
            'size' => $perPage,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'should' => [
                        ...$searchClauses,
                        ['match_phrase' => [
                            'search_text' => ['query' => $search, 'boost' => 4],
                        ]],
                    ],
                    'minimum_should_match' => 1,
                    'filter' => $filterClauses,
                ],
            ],
            'sort' => [
                ['_score' => 'desc'],
                ['created_at' => 'desc'],
            ],
        ])->throw()->json();

        $hits = $response['hits']['hits'] ?? [];
        $ids = array_map(static fn (array $hit): int => (int) $hit['_id'], $hits);
        $records = VisitorEntry::query()->whereIn('id', $ids)->get()->keyBy('id');
        $orderedRecords = collect($ids)->map(static fn (int $id) => $records->get($id))->filter()->values();
        $total = $response['hits']['total']['value'] ?? $response['hits']['total'] ?? count($ids);

        return new LengthAwarePaginator(
            $orderedRecords,
            (int) $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function document(VisitorEntry $entry): array
    {
        $purposeText = self::PURPOSE_LABELS[$entry->purpose] ?? $entry->purpose;
        $occupationText = self::OCCUPATION_LABELS[$entry->occupation] ?? $entry->occupation;
        $values = [
            $entry->queue_no,
            $entry->full_name,
            $entry->institution,
            $entry->purpose,
            $purposeText,
            $entry->purpose_other,
            $entry->service_code,
            $entry->service_status,
            $entry->gender,
            $entry->phone,
            $entry->email,
            $entry->occupation,
            $occupationText,
            $entry->occupation_other,
        ];

        return [
            'queue_no' => $entry->queue_no,
            'full_name' => $entry->full_name,
            'institution' => $entry->institution,
            'purpose' => $entry->purpose,
            'purpose_text' => $purposeText,
            'purpose_other' => $entry->purpose_other,
            'service_code' => $entry->service_code,
            'service_status' => $entry->service_status,
            'gender' => $entry->gender,
            'phone' => $entry->phone,
            'email' => $entry->email,
            'occupation' => $entry->occupation,
            'occupation_text' => $occupationText,
            'occupation_other' => $entry->occupation_other,
            'created_at' => $entry->created_at?->toIso8601String(),
            'search_text' => implode(' ', array_filter($values)),
        ];
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'ApiKey '.config('services.elasticsearch.api_key'),
        ])->acceptJson()->timeout(3)->connectTimeout(1);
    }

    private function indexUrl(): string
    {
        return rtrim((string) config('services.elasticsearch.url'), '/').'/'.rawurlencode((string) config('services.elasticsearch.index'));
    }
}
