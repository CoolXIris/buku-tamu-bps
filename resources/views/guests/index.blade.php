<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102d63">
    <title>Buku Tamu | BPS Provinsi Sumatera Selatan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-page">
    <header class="topbar">
        <a class="brand" href="{{ route('guests.index') }}" aria-label="BPS Provinsi Sumatera Selatan, beranda">
            <span class="bps-mark bps-mark--small" aria-hidden="true"><i></i><b></b><em></em></span>
            <span class="brand-copy"><strong>Buku Tamu</strong><small>BPS Provinsi Sumatera Selatan</small></span>
        </a>
        <div class="topbar-note"><span class="status-dot"></span> Pelayanan hari ini</div>
    </header>

    <main class="guest-main">
        <section class="welcome-block" aria-labelledby="welcome-title">
            <div class="bps-seal" aria-label="Lambang BPS">
                <span class="bps-mark" aria-hidden="true"><i></i><b></b><em></em></span>
                <span class="seal-caption">BPS</span>
            </div>
            <p class="eyebrow">Badan Pusat Statistik</p>
            <h1 id="welcome-title">Selamat Datang di BPS<br class="mobile-break"> Provinsi Sumatera Selatan</h1>
            <p class="welcome-subtitle">Silakan pilih tipe kunjungan Anda</p>
        </section>

        <div class="visitor-tabs" role="tablist" aria-label="Tipe tamu">
            <button class="visitor-tab is-active" type="button" role="tab" aria-selected="true" data-visitor-tab="external">
                <span class="tab-dot" aria-hidden="true"></span> Tamu External
            </button>
            <button class="visitor-tab" type="button" role="tab" aria-selected="false" data-visitor-tab="employee">
                Pegawai BPS
            </button>
        </div>

        <section class="services-panel" aria-label="Pilihan layanan">
            <div class="service-grid" data-service-panel="external">
                <button class="service-card service-card--pst" type="button" data-open-form data-purpose="PST">
                    <span class="service-art"><span class="service-monogram">PST</span><span class="art-bars"><i></i><i></i><i></i></span></span>
                    <span class="service-title">Pelayanan Statistik Terpadu</span>
                    <span class="service-arrow" aria-hidden="true">↗</span>
                </button>
                <button class="service-card service-card--lpse" type="button" data-open-form data-purpose="LPSE">
                    <span class="service-art"><span class="service-monogram">LPSE</span><span class="art-diamonds"><i></i><i></i><i></i></span></span>
                    <span class="service-title">Pengadaan Secara Elektronik</span>
                    <span class="service-arrow" aria-hidden="true">↗</span>
                </button>
                <button class="service-card service-card--ppid" type="button" data-open-form data-purpose="PPID">
                    <span class="service-art"><span class="service-monogram">PPID</span><span class="art-pages"><i></i><i></i><i></i></span></span>
                    <span class="service-title">Informasi dan Dokumentasi</span>
                    <span class="service-arrow" aria-hidden="true">↗</span>
                </button>
                <button class="service-card service-card--event" type="button" data-open-form data-purpose="KEGIATAN">
                    <span class="service-art"><span class="event-spark">✳</span><span class="event-rings"></span></span>
                    <span class="service-title">Kegiatan Lainnya</span>
                    <span class="service-arrow" aria-hidden="true">↗</span>
                </button>
            </div>
            <div class="employee-panel hidden" data-service-panel="employee">
                <a class="service-card service-card--employee" href="{{ $pstDigitalUrl }}" target="_blank" rel="noopener noreferrer">
                    <span class="employee-icon" aria-hidden="true">PST<span>↗</span></span>
                    <span class="service-title">PST Digital Sumsel</span>
                    <span class="employee-description">Buka layanan statistik digital</span>
                    <span class="service-arrow" aria-hidden="true">↗</span>
                </a>
            </div>
        </section>
        <p class="privacy-note"><span aria-hidden="true">◎</span> Data kunjungan Anda digunakan untuk keperluan pelayanan BPS.</p>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-agency">
                <span class="bps-mark bps-mark--footer" aria-hidden="true"><i></i><b></b><em></em></span>
                <div><strong>BADAN PUSAT STATISTIK</strong><span>PROVINSI SUMATERA SELATAN</span></div>
            </div>
            <div class="footer-contact">
                <strong>Palembang, Sumatera Selatan</strong>
                <span>Jl. Kapten Anwar Sastro No. 1694/113, Sungai Pangeran, Ilir Timur I</span>
                <span>bps1600@bps.go.id <i></i> (0711) 351665</span>
            </div>
            <span class="footer-copy">© BPS Provinsi Sumatera Selatan</span>
        </div>
    </footer>

    <div class="modal-backdrop {{ $errors->any() ? '' : 'hidden' }}" data-guest-modal aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
        <section class="guest-modal" role="dialog" aria-modal="true" aria-labelledby="form-title">
            <button class="modal-close" type="button" data-close-form aria-label="Tutup formulir">×</button>
            <div class="modal-heading">
                <span class="modal-kicker">FORMULIR KUNJUNGAN</span>
                <h2 id="form-title">Data Tamu</h2>
                <p>Lengkapi data berikut untuk mendapatkan nomor antrean.</p>
            </div>
            @if ($errors->any())
                <div class="form-errors" role="alert">Periksa kembali isian Anda. Semua kolom wajib diisi dengan benar.</div>
            @endif
            <form class="guest-form" action="{{ route('guests.store') }}" method="POST">
                @csrf
                <div class="form-grid">
                    <label class="field field--wide">Nama lengkap
                        <input name="full_name" value="{{ old('full_name') }}" autocomplete="name" placeholder="Nama sesuai identitas" required maxlength="150">
                        @error('full_name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <fieldset class="field field--wide gender-field">
                        <legend>Jenis kelamin</legend>
                        <div class="gender-options">
                            <label><input type="radio" name="gender" value="Laki-laki" @checked(old('gender') === 'Laki-laki') required><span>Laki-laki</span></label>
                            <label><input type="radio" name="gender" value="Perempuan" @checked(old('gender') === 'Perempuan')><span>Perempuan</span></label>
                        </div>
                    </fieldset>
                    <label class="field field--wide">Asal instansi
                        <input name="institution" value="{{ old('institution') }}" autocomplete="organization" placeholder="Nama instansi atau organisasi" required maxlength="180">
                        @error('institution')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">Nomor HP
                        <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="08xxxxxxxxxx" required maxlength="30">
                        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">Email
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="nama@email.com" required maxlength="180">
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field field--wide">Pekerjaan
                        <select name="occupation" data-occupation required>
                            <option value="">Pilih pekerjaan</option>
                            <option value="ASN" @selected(old('occupation') === 'ASN')>Aparatur Sipil Negara</option>
                            <option value="SWASTA" @selected(old('occupation') === 'SWASTA')>Karyawan Swasta</option>
                            <option value="WIRASWASTA" @selected(old('occupation') === 'WIRASWASTA')>Wiraswasta</option>
                            <option value="PENELITI" @selected(old('occupation') === 'PENELITI')>Peneliti</option>
                            <option value="PELAJAR" @selected(old('occupation') === 'PELAJAR')>Pelajar/Mahasiswa</option>
                            <option value="LAINNYA" @selected(old('occupation') === 'LAINNYA')>Lainnya</option>
                        </select>
                        @error('occupation')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field field--wide conditional-field {{ old('occupation') === 'LAINNYA' ? '' : 'hidden' }}" data-occupation-other>Nama pekerjaan
                        <input name="occupation_other" value="{{ old('occupation_other') }}" data-occupation-other-input placeholder="Tuliskan pekerjaan Anda" maxlength="100">
                        @error('occupation_other')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field field--wide">Tipe keperluan
                        <select name="purpose" data-purpose-input required>
                            <option value="PST" @selected(old('purpose', request('purpose', 'PST')) === 'PST')>PST</option>
                            <option value="LPSE" @selected(old('purpose', request('purpose')) === 'LPSE')>LPSE</option>
                            <option value="PPID" @selected(old('purpose', request('purpose')) === 'PPID')>PPID</option>
                            <option value="KEGIATAN" @selected(old('purpose', request('purpose')) === 'KEGIATAN')>Kegiatan lainnya</option>
                        </select>
                        @error('purpose')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <label class="field field--wide conditional-field {{ old('purpose', request('purpose', 'PST')) === 'KEGIATAN' ? '' : 'hidden' }}" data-purpose-other>Nama kegiatan
                    <input name="purpose_other" value="{{ old('purpose_other') }}" data-purpose-other-input placeholder="Tuliskan nama kegiatan" maxlength="150">
                    @error('purpose_other')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <button class="submit-button" type="submit">Cetak nomor antrean <span aria-hidden="true">→</span></button>
                <p class="form-footnote">Nomor antrean akan dicetak setelah data berhasil disimpan.</p>
            </form>
        </section>
    </div>
</body>
</html>