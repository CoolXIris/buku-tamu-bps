<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Antrean {{ $entry->queue_no }} | BPS Sumsel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="receipt-page">
    <main class="receipt-shell">
        <article class="thermal-receipt">
            <div class="receipt-brand"><span class="bps-mark" aria-hidden="true"><i></i><b></b><em></em></span><strong>BPS PROVINSI<br>SUMATERA SELATAN</strong></div>
            <p class="receipt-address">Jl. Kapten Anwar Sastro No. 1694/113<br>Palembang, Sumatera Selatan</p>
            <div class="receipt-rule"></div>
            <p class="receipt-caption">NOMOR ANTREAN</p>
            <h1>{{ $entry->queue_no }}</h1>
            <p class="receipt-service">{{ $serviceName }}@if ($entry->purpose_other)<br>{{ $entry->purpose_other }}@endif</p>
            <div class="receipt-rule receipt-rule--dashed"></div>
            <dl class="receipt-details">
                <div><dt>Nama</dt><dd>{{ $entry->full_name }}</dd></div>
                <div><dt>Instansi</dt><dd>{{ $entry->institution }}</dd></div>
                <div><dt>Tanggal</dt><dd>{{ $entry->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
            </dl>
            <p class="receipt-thanks">Terima kasih telah berkunjung.<br>Mohon menunggu nomor Anda dipanggil.</p>
            <p class="receipt-url">bps.go.id</p>
        </article>
        <div class="receipt-actions no-print">
            <button class="print-button" type="button" onclick="window.print()"><span aria-hidden="true">▣</span> Cetak struk</button>
            <a href="{{ route('guests.index') }}">Kembali ke halaman utama</a>
        </div>
    </main>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>