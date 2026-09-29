<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102d63">
    <title>Dashboard Admin | Buku Tamu BPS Sumsel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="admin-dashboard-page">
    <header class="admin-topbar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <img class="admin-bps-logo" src="{{ asset('images/logo_bps.svg') }}" alt="">
            <span><strong>Buku Tamu</strong><small>BPS Provinsi Sumatera Selatan</small></span>
        </a>
        <nav class="admin-navigation" aria-label="Navigasi administrator">
            <a class="admin-nav-link is-current" href="{{ route('admin.dashboard') }}"><span class="nav-glyph" aria-hidden="true">▥</span> Dashboard</a>
            <a class="admin-nav-link" href="{{ route('admin.guests.index') }}"><span class="nav-glyph" aria-hidden="true">♧</span> Daftar Buku Tamu <span class="nav-count">{{ $activeVisitorCount }}</span></a>
            <a class="admin-nav-link" href="{{ route('queue.screen') }}" target="_blank" rel="noopener"><span class="nav-glyph" aria-hidden="true">◉</span> Layar Antrean</a>
        </nav>
        <div class="admin-account">
            <span class="account-avatar">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
            <span class="account-name">{{ $admin->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="logout-button" type="submit" title="Keluar" aria-label="Keluar">↗</button></form>
        </div>
    </header>

    <main class="admin-main">
        <div class="dashboard-heading">
            <div>
                <span class="dashboard-eyebrow">RINGKASAN OPERASIONAL</span>
                <h1>Dashboard</h1>
                <p>Pemantauan kunjungan dan pelayanan publik BPS Provinsi Sumatera Selatan.</p>
            </div>
            <form class="month-filter" method="GET" action="{{ route('admin.dashboard') }}">
                <label class="date-chip" for="dashboard-month"><span class="date-chip-dot"></span>
                    <select id="dashboard-month" name="month" aria-label="Pilih bulan" onchange="this.form.submit()">
                        @foreach ($availableMonths as $monthValue => $monthLabel)
                        <option value="{{ $monthValue }}" @selected($selectedMonth === $monthValue)>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        </div>

        <section class="metric-grid" aria-label="Ringkasan pengunjung hari ini">
            <article class="metric-card metric-card--blue">
                <div class="metric-copy"><span class="metric-label">TOTAL PENGUNJUNG HARI INI</span><strong>{{ number_format($todayTotal) }}</strong><small>Seluruh layanan BPS Sumsel</small></div>
                <span class="metric-icon" aria-hidden="true">♙</span>
                <span class="metric-index">01</span>
            </article>
            <article class="metric-card metric-card--amber">
                <div class="metric-copy"><span class="metric-label">SEDANG DILAYANI</span><strong>{{ number_format($servingCount) }}</strong><small>Pengunjung dalam pelayanan</small></div>
                <span class="metric-icon" aria-hidden="true">◷</span>
                <span class="metric-index">02</span>
            </article>
            <article class="metric-card metric-card--green">
                <div class="metric-copy"><span class="metric-label">SUDAH DILAYANI</span><strong>{{ number_format($completedCount) }}</strong><small>Pelayanan selesai hari ini</small></div>
                <span class="metric-icon" aria-hidden="true">✓</span>
                <span class="metric-index">03</span>
            </article>
        </section>

        <section class="chart-panel" aria-labelledby="visits-chart-title">
            <div class="panel-heading chart-heading">
                <div><span class="panel-kicker">AKTIVITAS BULANAN</span>
                    <h2 id="visits-chart-title">Jumlah pengunjung</h2>
                    <p>Pengunjung per hari · {{ $selectedMonthLabel }}</p>
                </div>
                <span class="chart-legend"><i></i> Pengunjung</span>
            </div>
            <div class="chart-wrap"><canvas data-visits-chart aria-label="Grafik jumlah pengunjung per hari untuk {{ $selectedMonthLabel }}" role="img"></canvas></div>
        </section>

        <div class="distribution-grid">
            <section class="distribution-panel" aria-labelledby="service-distribution-title">
                <div class="panel-heading distribution-heading">
                    <div><span class="panel-kicker">LAYANAN BULANAN</span>
                        <h2 id="service-distribution-title">Distribusi pengunjung</h2>
                    </div><span class="panel-total">{{ number_format($selectedTotal) }} <small>tamu</small></span>
                </div>
                <div class="service-distribution-list">
                    @foreach ($services as $service)
                    <div class="service-stat-row">
                        <div class="service-stat-copy"><strong>{{ $service['name'] }}</strong><small>{{ $service['detail'] }}</small></div>
                        <div class="service-stat-meta"><strong>{{ $service['count'] }}</strong><small>{{ $service['percentage'] }}%</small></div>
                        <div class="stat-track"><span class="stat-fill stat-fill--{{ $service['color'] }}" style="width: {{ $service['percentage'] }}%"></span></div>
                    </div>
                    @endforeach
                </div>
            </section>

            <section class="distribution-panel" aria-labelledby="occupation-distribution-title">
                <div class="panel-heading distribution-heading">
                    <div><span class="panel-kicker">PROFIL PENGUNJUNG</span>
                        <h2 id="occupation-distribution-title">Asal kategori pengunjung</h2>
                    </div><span class="category-symbol" aria-hidden="true">⌂</span>
                </div>
                <div class="occupation-distribution-list">
                    @foreach ($occupations as $occupation)
                    <div class="occupation-stat-row"><span class="occupation-icon" aria-hidden="true">⌂</span><span class="occupation-name">{{ $occupation['name'] }}</span><span class="occupation-track"><i style="width: {{ $occupation['percentage'] }}%"></i></span><strong>{{ $occupation['count'] }}</strong></div>
                    @endforeach
                </div>
            </section>
        </div>
        <footer class="dashboard-footer"><span><i></i> Data diperbarui secara langsung dari buku tamu</span><span>BPS PROVINSI SUMATERA SELATAN</span></footer>
    </main>
    <script>
        window.adminVisitsChart = {
            labels: @json($chartLabels),
            values: @json($chartValues),
        };
    </script>
</body>

</html>