<?php

namespace App\Console\Commands;

use App\Models\VisitorEntry;
use App\Services\GuestSearch;
use Illuminate\Console\Command;

class ReindexGuests extends Command
{
    protected $signature = 'guests:reindex-search';

    protected $description = 'Build the Elasticsearch index for visitor entries';

    public function handle(GuestSearch $search): int
    {
        if (! $search->isConfigured()) {
            $this->error('Isi ELASTICSEARCH_URL dan ELASTICSEARCH_API_KEY terlebih dahulu.');

            return self::FAILURE;
        }

        try {
            $search->prepareIndex();
            $indexed = 0;

            VisitorEntry::query()->chunkById(250, function ($entries) use ($search, &$indexed): void {
                $search->bulkIndex($entries);
                $indexed += $entries->count();
            });
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Gagal membangun indeks Elasticsearch. Periksa endpoint, API key, dan log aplikasi.');

            return self::FAILURE;
        }

        $this->info("Indeks Elasticsearch siap. {$indexed} data tamu diproses.");

        return self::SUCCESS;
    }
}
