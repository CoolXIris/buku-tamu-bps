<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d172d">
    <title>Layar Antrean | BPS Provinsi Sumatera Selatan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="queue-screen-page" data-queue-screen data-data-url="{{ $dataUrl }}">
    <main class="queue-frame">
        <header class="queue-header">
            <div class="queue-brand-lockup">
                <img class="queue-bps-logo" src="{{ asset('images/logo_bps.svg') }}" alt="">
                <div>
                    <h1>LAYAR ANTREAN PELAYANAN TERPADU</h1>
                    <p>Badan Pusat Statistik Provinsi Sumatera Selatan</p>
                </div>
            </div>
            <div class="queue-header-tools">
                <div class="queue-clock"><span class="clock-icon" aria-hidden="true">◷</span>
                    <div><strong data-queue-clock>--:--:-- WIB</strong><small data-queue-date>Memuat waktu</small></div>
                </div>
                <button class="queue-sound-button" type="button" data-queue-sound aria-pressed="false"><span aria-hidden="true">◖</span><span data-sound-label>Aktifkan suara</span></button>
                <span class="queue-live-status"><i data-live-indicator></i><span data-live-label>Menghubungkan</span></span>
            </div>
        </header>

        <section class="queue-board" aria-label="Informasi antrean saat ini">
            <article class="current-call-panel" aria-live="polite" aria-atomic="true">
                <div class="current-call-topline">
                    <span class="calling-badge"><i></i> NOMOR ANTREAN SEDANG DILAYANI</span>
                    <span class="call-event-time" data-called-time></span>
                </div>
                <div class="current-call-content" data-current-call>
                    <span class="current-queue-number is-empty">--</span>
                    <h2>Menunggu panggilan berikutnya</h2>
                    <p>Silakan menunggu, nomor antrean akan tampil di sini.</p>
                </div>
                <div class="current-call-footer"><span>Silakan menuju meja pelayanan saat nomor Anda dipanggil</span><strong>BPS Sumsel Melayani Dengan Sepenuh Hati</strong></div>
            </article>

            <aside class="service-status-panel" aria-labelledby="service-status-heading">
                <div class="service-status-heading"><span id="service-status-heading">STATUS LOKET PELAYANAN</span><small>4 LAYANAN</small></div>
                <div class="queue-service-list" data-service-list>
                    <article class="queue-service-card"><span class="queue-service-code">PST</span>
                        <div><strong>PST</strong><small>PELAYANAN STATISTIK TERPADU</small></div><span class="queue-service-next">Memuat...</span>
                    </article>
                    <article class="queue-service-card"><span class="queue-service-code">LPSE</span>
                        <div><strong>LPSE</strong><small>LAYANAN PENGADAAN ELEKTRONIK</small></div><span class="queue-service-next">Memuat...</span>
                    </article>
                    <article class="queue-service-card"><span class="queue-service-code">PPID</span>
                        <div><strong>PPID</strong><small>INFORMASI DAN DOKUMENTASI</small></div><span class="queue-service-next">Memuat...</span>
                    </article>
                    <article class="queue-service-card"><span class="queue-service-code">LAIN</span>
                        <div><strong>KEGIATAN LAINNYA</strong><small>LAYANAN KEGIATAN KEDINASAN</small></div><span class="queue-service-next">Memuat...</span>
                    </article>
                </div>
            </aside>
        </section>

        <section class="waiting-strip" aria-labelledby="waiting-heading">
            <div class="waiting-strip-title"><span class="waiting-pulse"></span>
                <div>
                    <h2 id="waiting-heading">ANTREAN MENUNGGU</h2>
                    <p data-waiting-count>Memuat antrean...</p>
                </div>
            </div>
            <div class="waiting-queue-list" data-waiting-list><span class="waiting-empty">Belum ada antrean menunggu</span></div>
        </section>

        <footer class="queue-footer"><span class="queue-announcement-label">PENGUMUMAN</span>
            <p data-announcement>Selamat datang di Pelayanan Statistik Terpadu BPS Provinsi Sumatera Selatan. Mohon menjaga ketertiban dan menunggu nomor antrean Anda dipanggil.</p><span class="queue-footer-mark">BPS SUMSEL</span>
        </footer>
    </main>
</body>

</html>