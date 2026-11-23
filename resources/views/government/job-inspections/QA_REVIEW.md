# QA PBI 3.2.1 — 23 November 2026

Status: pengujian otomatis terkait dan QA manual OK/Cancel lulus. Hasil manual dikonfirmasi oleh pengguna; pemeriksaan tambahan membaca metadata SQLite/log QA untuk mendukung hasil tersebut. **Aturan keputusan masih menunggu konfirmasi final PM Hasya. Konkurensi paralel MySQL belum diuji.** Dokumen ini tidak menyatakan siap deployment atau kebijakan bisnis sudah final.

## Ruang lingkup dan isolasi

- Repository siKerja-Hasya, branch Hasya/Sprint1, baseline 78c6ca33f54374c288c731ffdcb2419cced7e753. Mencakup daftar, detail, API baca, kriteria, API keputusan dan antarmuka keputusan PBI 3.2.1.
- Aplikasi QA HTTP terikat ke 127.0.0.1:8873. Bootstrap, SQLite file, environment kosong, app key sintetis, session, cache, compiled view dan storage berada di direktori TEMP QA khusus, di luar repository. Tidak menggunakan public/index.php yang memuat environment repository.
- Konfigurasi cache repository dilewati; hanya koneksi SQLite QA diizinkan dan path database diperiksa. Dua migration existing diterapkan hanya pada SQLite QA kosong. Semua akun/lowongan QA sintetis; tidak membaca/mengubah .env atau mengakses MySQL Cloud.
- Browser awal memakai APP_ENV=testing dan tidak digunakan sebagai bukti CSRF server. Pemeriksaan HTTP tambahan memakai APP_ENV=local, APP_DEBUG=false dan runningUnitTests=false agar CSRF aktif.
- PHPUnit memakai fixture dengan SQLite :memory:, environment kosong dan cache/storage TEMP terpisah. Tidak memasang dependency tambahan atau Laravel Boost untuk QA; tidak mengubah dependency.
- Fixture browser, database QA, log request, session, key dan helper TEMP tidak menjadi bagian checkpoint. Fixture unit/feature test berbentuk kode memang disertakan agar regresi dapat dijalankan ulang.

## Hasil browser desktop dan mobile

Viewport desktop 1440 × 1000 dan mobile 375 × 812. Mobile adalah emulasi viewport, bukan pengujian perangkat fisik.

| Area | Hasil dan bukti |
| --- | --- |
| Daftar/detail/navigasi | Delapan kolom antrean pending, detail, menu aktif dan navigasi kembali berfungsi. Informasi pekerjaan, teks multiline, gaji, kuota dan deadline terbaca. |
| Pencarian/filter | Judul, kategori exact dan lokasi parsial berfungsi; kombinasi Analis/Teknologi Informasi/Jakarta menghasilkan tujuh fixture. Reset mengembalikan antrean. |
| Pagination | Halaman kedua menampilkan empat dari 19 fixture pending awal; query dipertahankan. Jumlah antrean berkurang setelah keputusan berhasil. |
| Empty state | Pencarian tanpa hasil menampilkan pesan dan reset. Antrean kosong juga tercakup di feature tests. |
| Desktop/mobile | Tidak ditemukan overflow body. Filter/form mobile vertikal; tabel lebar dapat digulir horizontal termasuk dengan keyboard. Tombol dan pesan terbaca. |
| Alasan/catatan | Form kosong diblokir validasi native; whitespace menghasilkan 422, pesan aman tampil dan form dapat diperbaiki. |
| Tolak | Berhasil melalui browser desktop; rejected, alasan tersimpan dan approved_by null; sukses tampil dan tindakan disembunyikan. |
| Minta Perbaikan | Berhasil melalui browser mobile; rejected dan catatan tersimpan; sukses, link kembali dan penguncian tindakan terlihat. |
| Setujui — OK | **LULUS berdasarkan konfirmasi QA manual pengguna.** Metadata SQLite/log lokal mendukung keberhasilan: fixture 24 dan 25 published, approved_by=1, masing-masing POST keputusan tercatat 200. Tidak mengklaim screenshot dialog diamati ulang oleh agen. |
| Setujui — Cancel/Batal | **LULUS berdasarkan konfirmasi QA manual pengguna pada fixture 26:** tidak ada POST dan status tetap pending. Pembacaan ulang SQLite/log memastikan ID 26 pending_approval, approved_by null dan tidak ada POST keputusan untuk ID 26. |
| Keputusan berulang | Dua tab fixture sama: keputusan pertama berhasil, kedua mendapat 409; pesan status berubah dan seluruh aksi terkunci. Hasil pertama tidak tertimpa. Ini uji berurutan, bukan konkurensi database paralel. |
| Sesi berakhir | Logout di tab lain lalu mengirim halaman lama menghasilkan 401; pesan sesi berakhir dan tombol terkunci. Bukan 419. |
| Hak akses | Guest diarahkan ke login. Pemerintah aktif dapat membuka antrean/detail. Pemerintah nonaktif, perusahaan dan pencari kerja mendapat 403. Otorisasi endpoint keputusan juga diuji PHPUnit. |

Kontrol browser agen sebelumnya mengalami koneksi terputus dan kegagalan kontrol dialog native; hasil yang saat itu belum terverifikasi tidak dinyatakan lulus. Pengguna kemudian menyelesaikan QA manual OK/Cancel dan menyampaikan hasilnya pada permintaan checkpoint ini. Catatan terbaru ini menutup keterbatasan pemeriksaan dialog, tanpa mengubah keterbatasan MySQL atau status persetujuan PM.

## Pemeriksaan HTTP dengan CSRF aktif

Klien HTTP lokal memakai sesi dan token HTML; tidak mencatat token/cookie dalam laporan. Ini pemeriksaan HTTP, bukan pengganti QA visual.

| Skenario | Hasil HTTP |
| --- | --- |
| Login pemerintah QA dan antrean | 200 |
| POST keputusan tanpa token CSRF | 419 |
| POST keputusan dengan token salah | 419 |
| Minta Perbaikan dengan sesi/token valid | 200 |
| Setujui dengan sesi/token valid | 200 |
| Tolak dengan sesi/token valid | 200 |

419 nyata diverifikasi pada server. Tampilan 419 diuji JavaScript; tampilan 401/409/422 juga diamati di browser. Error Fetch 403/404, kegagalan jaringan, respons non-JSON dan hasil tidak pasti tercakup tes JavaScript, tidak semuanya direproduksi secara visual.

## Bug yang diperbaiki

Hidden input bernama action menutupi HTMLFormElement.action. fetch(form.action) mengirim URL berisi [object HTMLInputElement] dan memperoleh 404. Penyebab/file terdampak dilaporkan sebelum perbaikan.

- public/js/job-inspection-decision.js kini memakai form.getAttribute('action'); endpoint, payload, otorisasi dan aturan bisnis tetap sama.
- Fixture tests/Frontend/job-inspection-decision.test.cjs meniru benturan properti browser dan memeriksa URL ketiga aksi. Sebelum perbaikan: 14 PASS, 3 FAIL. Sesudah perbaikan: 17 PASS, 0 FAIL.
- Sesudah perbaikan, 422 dan keberhasilan Tolak/Minta Perbaikan diamati di browser; ketiga tindakan berhasil melalui HTTP dengan CSRF aktif. Dialog OK/Cancel dilengkapi oleh QA manual pengguna.

## Regresi akhir sebelum checkpoint

- Enam file PHPUnit terarah dijalankan ulang: **156 PASS, 0 FAIL, 1.414 assertions**, exit 0.
- File: GovernmentJobInspectionDecisionUiTest, CompanyJobSubmissionRegressionTest, GovernmentJobInspectionDecisionTest, GovernmentJobInspectionApiTest, GovernmentJobInspectionIndexTest dan GovernmentJobInspectionShowTest.
- Node built-in test runner dijalankan ulang: **17 PASS, 0 FAIL**, exit 0. Mencakup pembatalan konfirmasi tanpa request, payload/CSRF, sukses, tombol selama request, penguncian setelah hasil final, validasi dan error.
- Regresi mencakup validator/pengajuan lowongan perusahaan Feature 2.2 setelah ekstraksi komponen validasi bersama. Tidak mengklaim seluruh test suite atau regresi penuh Feature 3.1 telah dijalankan.
- Pemeriksaan PHP lint pada 19 file PHP kandidat lulus. Perbandingan route dengan baseline memastikan 60 route existing identik pada metode, URI, nama, controller/action, middleware dan constraint; lima route lowongan menggunakan web/auth/can:access-government.
- Checkpoint memuat source, view, JavaScript, dokumentasi dan tes PBI 3.2.1. Integrasi file bersama dibatasi route lowongan, pola JSON error, menu pemerintah dan ekstraksi validator Feature 2.2. Tidak ada migration/model/status baru atau perubahan aturan verifikasi perusahaan Feature 3.1.

## Keterbatasan dan tindak lanjut

1. PM Hasya masih perlu mengonfirmasi rancangan aturan keputusan. Tolak/Minta Perbaikan sama-sama rejected dan belum dibedakan secara terstruktur. Koordinasi pengajuan ulang/reset metadata Feature 2.2 tetap terbuka.
2. **Konkurensi paralel MySQL/row lock belum diuji.** SQLite :memory: dan dua keputusan berurutan tidak membuktikan semantik row lock database target. Bila diperlukan, gunakan staging database terisolasi; bukan MySQL Cloud bersama.
3. Mobile belum mewakili perangkat fisik, semua browser, pembaca layar atau audit aksesibilitas penuh.
4. Sebagian pesan validasi/pagination berbahasa Inggris; login existing memakai sapaan portal pencari kerja untuk pemerintah. Tidak mengubah alur/login Feature 3.1 pada pekerjaan ini.

Rekomendasi: simpan checkpoint lokal PBI 3.2.1 dengan QA manual OK/Cancel selesai. Acceptance kebijakan bisnis tetap menunggu konfirmasi final PM; jangan menyatakan konkurensi MySQL atau kesiapan deployment sudah tervalidasi.
