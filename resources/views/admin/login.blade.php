<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10263d">
    <title>Login Admin | Buku Tamu BPS Sumsel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="admin-login-page">
    <header class="admin-login-topbar">
        <a class="admin-brand" href="{{ route('guests.index') }}" aria-label="Ke halaman tamu">
            <span class="admin-bps-mark" aria-hidden="true"><i></i><b></b><em></em></span>
            <span><strong>Buku Tamu BPS Provinsi Sumsel</strong><small>Badan Pusat Statistik · Sumatera Selatan</small></span>
        </a>
        <a class="back-to-guest" href="{{ route('guests.index') }}">Halaman tamu <span aria-hidden="true">↗</span></a>
    </header>
    <main class="login-stage">
        <section class="login-panel">
            <span class="login-eyebrow">RUANG ADMINISTRATOR</span>
            <h1>Selamat datang<br>kembali.</h1>
            <p>Masuk menggunakan akun Google admin BPS yang telah terdaftar.</p>
            @if (session('auth_error'))
            <div class="login-error" role="alert">{{ session('auth_error') }}</div>
            @endif
            @if ($googleConfigured)
            <a class="google-login-button" href="{{ route('admin.google.redirect') }}">
                <svg aria-hidden="true" viewBox="0 0 48 48" width="20" height="20">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z" />
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.91c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.39 38.05 47 31.92 47 24.55Z" />
                    <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.9.94 7.59 2.56 10.78l7.97-6.19Z" />
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.9-5.8l-7.73-6c-2.14 1.45-4.88 2.3-8.17 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z" />
                </svg>
                <span>Lanjutkan dengan Google</span>
                <span class="google-button-arrow" aria-hidden="true">→</span>
            </a>
            @else
            <div class="login-setup-note" role="status">
                <strong>Login Google belum dikonfigurasi.</strong>
                <span>Isi GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, dan GOOGLE_ADMIN_EMAILS pada file environment.</span>
            </div>
            @endif
            <div class="login-separator"><span></span>AKSES TERBATAS<span></span></div>
            <p class="login-security"><span aria-hidden="true">◈</span> Hanya akun Google yang telah diizinkan administrator BPS dapat mengakses dashboard.</p>
        </section>
        <aside class="login-aside">
            <div class="aside-index">01 <span>/</span> BPS SUMSEL</div>
            <div class="aside-lines" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <p>Data kunjungan,<br>terpantau dengan jelas.</p>
            <span class="aside-foot">STATISTIK · PELAYANAN · INFORMASI</span>
        </aside>
    </main>
    <footer class="login-footer"><span>© {{ now()->year }} Badan Pusat Statistik Provinsi Sumatera Selatan</span><span>Portal internal administrator</span></footer>
</body>

</html>