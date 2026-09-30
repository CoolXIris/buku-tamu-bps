<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

## Buku Tamu Admin

Panel admin berada di `/adminbps-tamu`. Pengunjung tanpa sesi admin akan diarahkan ke halaman login Google; hanya akun dengan email terverifikasi yang tercantum di `GOOGLE_ADMIN_EMAILS` yang dapat masuk.

Untuk mengaktifkan Google OAuth:

1. Buat OAuth 2.0 Client ID tipe **Web application** pada Google Cloud Console.
2. Tambahkan URL callback yang sama persis dengan `GOOGLE_REDIRECT_URI` pada daftar **Authorized redirect URIs**. Untuk server lokal, contohnya `http://127.0.0.1:8000/adminbps-tamu/google/callback`.
3. Isi `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, dan `GOOGLE_ADMIN_EMAILS` di `.env`. Pisahkan beberapa email yang diizinkan dengan koma.
4. Jalankan `php artisan migrate` untuk membuat tabel kunjungan dan status pelayanan, lalu `php artisan optimize:clear` setelah mengubah konfigurasi environment.

Dashboard menampilkan jumlah kunjungan hari ini, status pelayanan, tren tujuh hari, serta distribusi layanan dan pekerjaan. Status kunjungan baru dimulai sebagai `waiting`; halaman daftar tamu nantinya dapat mengubahnya menjadi `serving` atau `completed`.

### Pencarian Elasticsearch

Pencarian daftar tamu dapat memakai Elasticsearch terkelola dari Elastic Cloud atau Elasticsearch Serverless, tanpa Docker dan tanpa paket PHP tambahan. Buat deployment, siapkan API key dengan hak `read`, `write`, dan `create_index` untuk indeks yang digunakan, lalu isi `ELASTICSEARCH_URL`, `ELASTICSEARCH_API_KEY`, dan opsional `ELASTICSEARCH_GUEST_INDEX` di `.env`. `ELASTICSEARCH_URL` adalah URL endpoint deployment, bukan URL halaman dashboard.

Jalankan `php artisan optimize:clear`, lalu `php artisan guests:reindex-search` untuk membuat indeks dan mengimpor data lama. Data tamu yang dibuat atau diubah selanjutnya disinkronkan setelah transaksi database berhasil. Jika konfigurasi kosong atau Elasticsearch sedang tidak dapat dihubungi, halaman tetap mencari melalui database dengan pencocokan kata lintas kolom; fuzzy matching dan ranking relevansi hanya tersedia ketika Elasticsearch aktif.

Indeks memuat nama, instansi, antrean, keperluan, layanan, pekerjaan, telepon, email, dan status tamu. Pastikan penggunaan layanan cloud, lokasi pemrosesan, dan retensi data sesuai kebijakan perlindungan data organisasi sebelum mengaktifkannya.

Untuk membuat 5.000 data sintetis khusus pengujian pencarian (dengan tanggal masuk acak selama tiga tahun), jalankan hanya pada database lokal/pengujian:

```sh
php artisan db:seed --class=VisitorSearchLoadTestSeeder
php artisan guests:reindex-search
```

Seeder ini tidak menghapus data, aman dijalankan ulang, memakai email `example.test`, dan tidak dijalankan oleh `php artisan db:seed` biasa. Data uji baru masuk ke Elasticsearch setelah perintah reindex selesai.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
