<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102d63">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daftar Buku Tamu | BPS Sumsel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="admin-dashboard-page guest-list-page">
    <header class="admin-topbar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <img class="admin-bps-logo" src="{{ asset('images/logo_bps.svg') }}" alt="">
            <span><strong>Buku Tamu</strong><small>BPS Provinsi Sumatera Selatan</small></span>
        </a>
        <nav class="admin-navigation" aria-label="Navigasi administrator">
            <a class="admin-nav-link" href="{{ route('admin.dashboard') }}"><span class="nav-glyph" aria-hidden="true">▥</span> Dashboard</a>
            <a class="admin-nav-link is-current" href="{{ route('admin.guests.index') }}"><span class="nav-glyph" aria-hidden="true">♧</span> Daftar Buku Tamu <span class="nav-count">{{ $activeVisitorCount }}</span></a>
            <a class="admin-nav-link" href="{{ route('queue.screen') }}" target="_blank" rel="noopener"><span class="nav-glyph" aria-hidden="true">◉</span> Layar Antrean</a>
        </nav>
        <div class="admin-account">
            <span class="account-avatar">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
            <span class="account-name">{{ $admin->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="logout-button" type="submit" title="Keluar" aria-label="Keluar">↗</button></form>
        </div>
    </header>

    <main class="admin-main guest-list-main">
        <div class="guest-list-heading">
            <div>
                <span class="dashboard-eyebrow">PENGELOLAAN KUNJUNGAN</span>
                <h1>Buku Tamu <span>Semua waktu</span></h1>
                <p>Log daftar kunjungan tamu terpadu BPS Provinsi Sumatera Selatan.</p>
            </div>
            <a class="export-button" href="{{ route('admin.guests.export', request()->query()) }}">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 16v4h14v-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Ekspor Excel
            </a>
        </div>

        <form class="guest-filter-bar" method="GET" action="{{ route('admin.guests.index') }}" data-guest-filters>
            <label class="guest-search-field">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.7" />
                    <path d="m16 16 4.3 4.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama tamu, instansi, nomor antrean, atau keperluan..." aria-label="Cari daftar tamu">
            </label>
            <label class="filter-control"><span>Layanan</span>
                <select name="service" aria-label="Filter layanan">
                    <option value="">Semua Unit Layanan</option>
                    <option value="PST" @selected(($filters['service'] ?? '' )==='PST' )>PST</option>
                    <option value="PPID" @selected(($filters['service'] ?? '' )==='PPID' )>PPID</option>
                    <option value="LPSE" @selected(($filters['service'] ?? '' )==='LPSE' )>LPSE</option>
                    <option value="KEGIATAN" @selected(($filters['service'] ?? '' )==='KEGIATAN' )>Lainnya</option>
                </select>
            </label>
            <label class="filter-control"><span>Status</span>
                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $status => $label)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '' )===$status)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="filter-submit" type="submit">Terapkan</button>
            @if (count($filters))<a class="filter-reset" href="{{ route('admin.guests.index') }}">Reset</a>@endif
        </form>

        <section class="guest-table-panel" aria-label="Tabel daftar tamu">
            <div class="table-scroll">
                <table class="guest-table">
                    <thead>
                        <tr>
                            <th class="queue-column">No. Antrean</th>
                            <th class="name-column">Nama Tamu &amp; Instansi</th>
                            <th class="purpose-column">Layanan &amp; Keperluan</th>
                            <th class="time-column">Waktu Masuk</th>
                            <th class="status-column">Status Pelayanan</th>
                            <th class="actions-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                        @php
                        $serviceName = ['PST' => 'PST', 'PPID' => 'PPID', 'LPSE' => 'LPSE', 'KEGIATAN' => 'Kegiatan lainnya'][$entry->service_code] ?? $entry->service_code;
                        $purposeName = ['PST' => 'Pelayanan Statistik Terpadu', 'PPID' => 'Informasi dan Dokumentasi', 'LPSE' => 'Pengadaan Secara Elektronik', 'KEGIATAN' => 'Kegiatan lainnya'][$entry->purpose] ?? $entry->purpose;
                        $statusName = $statuses[$entry->service_status] ?? $entry->service_status;
                        @endphp
                        <tr data-guest-row data-status-url="{{ route('admin.guests.status', $entry) }}" data-detail-url="{{ route('admin.guests.show', $entry) }}" data-queue-no="{{ $entry->queue_no }}">
                            <td><span class="queue-pill">{{ $entry->queue_no }}</span></td>
                            <td>
                                <div class="guest-name-cell"><strong>{{ $entry->full_name }}</strong><span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4.5 20V5.8A1.8 1.8 0 0 1 6.3 4h11.4a1.8 1.8 0 0 1 1.8 1.8V20M3 20h18M8 8h2m4 0h2m-8 4h2m4 0h2m-5 8v-4h2v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>{{ $entry->institution }}</span></div>
                            </td>
                            <td>
                                <div class="purpose-cell"><span class="service-pill service-pill--{{ strtolower($entry->service_code) }}">{{ $serviceName }}</span><span class="purpose-label">{{ $entry->purpose_other ?: $purposeName }}</span></div>
                            </td>
                            <td>
                                <div class="arrival-time"><strong>{{ $entry->created_at->format('H:i') }} WIB</strong><small>{{ $entry->created_at->format('d/m/Y') }}</small></div>
                            </td>
                            <td><span class="status-pill status-pill--{{ $entry->service_status }}"><i></i>{{ $statusName }}</span></td>
                            <td>
                                <div class="row-actions">
                                    @if ($entry->service_status !== 'completed')
                                    <button class="icon-action call-action" type="button" data-call-guest title="Panggil {{ $entry->queue_no }}" aria-label="Panggil nomor {{ $entry->queue_no }}">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                    @else
                                    <span class="icon-action action-spacer" aria-hidden="true"></span>
                                    @endif
                                    <select class="row-status-select" aria-label="Ubah status {{ $entry->queue_no }}" data-status-select>
                                        @foreach ($statuses as $status => $label)
                                        <option value="{{ $status }}" @selected($entry->service_status === $status)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="icon-action detail-action" type="button" data-open-detail title="Lihat detail {{ $entry->queue_no }}" aria-label="Lihat detail tamu {{ $entry->queue_no }}">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                            <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-table-state"><span aria-hidden="true">⌕</span><strong>Tidak ada data tamu</strong>
                                    <p>Coba ubah kata pencarian atau pilihan filter.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Menampilkan <strong>{{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }}</strong> dari <strong>{{ $entries->total() }}</strong> kunjungan</span>
                {{ $entries->links() }}
            </div>
        </section>
        <footer class="dashboard-footer"><span><i></i> Data kunjungan tersimpan pada sistem buku tamu</span><span>BPS PROVINSI SUMATERA SELATAN</span></footer>
    </main>

    <div class="guest-detail-backdrop hidden" data-guest-detail-modal aria-hidden="true">
        <section class="guest-detail-modal" role="dialog" aria-modal="true" aria-labelledby="detail-title">
            <button class="detail-close" type="button" data-close-detail aria-label="Tutup detail">×</button>
            <div class="detail-heading"><span>DETAIL KUNJUNGAN</span>
                <h2 id="detail-title">Data tamu</h2>
                <p data-detail-queue></p>
            </div>
            <div class="detail-grid">
                <div><span>Nama lengkap</span><strong data-detail="full_name"></strong></div>
                <div><span>Asal instansi</span><strong data-detail="institution"></strong></div>
                <div><span>Keperluan</span><strong data-detail="purpose"></strong></div>
                <div class="hidden" data-detail-other-wrap><span>Detail keperluan</span><strong data-detail="purpose_other"></strong></div>
                <div><span>Jenis kelamin</span><strong data-detail="gender"></strong></div>
                <div><span>Pekerjaan</span><strong data-detail="occupation"></strong></div>
                <div class="hidden" data-detail-occupation-wrap><span>Detail pekerjaan</span><strong data-detail="occupation_other"></strong></div>
                <div><span>No. HP</span><strong data-detail="phone"></strong></div>
                <div><span>Email</span><strong data-detail="email"></strong></div>
                <div><span>Waktu masuk</span><strong data-detail="created_at"></strong></div>
                <div><span>Status pelayanan</span><strong data-detail="status"></strong></div>
            </div>
            <div class="detail-footer"><button type="button" data-close-detail>Tutup</button></div>
        </section>
    </div>
    <div class="admin-toast" data-admin-toast role="status" aria-live="polite"></div>
</body>

</html>