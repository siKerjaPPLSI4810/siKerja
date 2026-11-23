# Feature 3.2 — Daftar dan detail pengajuan lowongan kerja

Baseline integrasi: `78c6ca33f54374c288c731ffdcb2419cced7e753` (PR #6, Feature 3.1 Fadlan). Implementasi daftar 3.2.1.1 dan detail 3.2.1.4 menggunakan dependency composer.lock. Pengujian daftar sebelumnya lulus 25 tests/192 assertions; pengujian gabungan detail dan regresi daftar pada 23 November 2026 lulus 47 tests, 342 assertions, 0 kegagalan (exit 0).

## Route dan akses

- GET `/pemerintah/pemeriksaan-lowongan`, nama `government.job-inspections.index`.
- Memakai group pemerintah existing yang diperluas dengan prefix `/pemerintah`, name `government.`, middleware `auth` dan `can:access-government`. URL, name dan middleware route perusahaan tetap sama.
- Gate existing membatasi akses untuk role `pemerintah` dengan `is_active=true`. Form Request memeriksa izin yang sama; guard route pada Blade bukan otorisasi.
- Controller `Government/JobInspectionController::index` memakai query bersama `JobInspectionQuery` dan mengirim respons HTML `Cache-Control: no-store, private`.
- Layout `government.layouts.app` dipakai bersama. Menu pemeriksaan lowongan ditambahkan tanpa menghapus menu/brand pemeriksaan perusahaan dan monitoring verifikasi.
- GET `/pemerintah/pemeriksaan-lowongan/{jobPosting}`, nama `government.job-inspections.show`, parameter whereNumber dan middleware pemerintah yang sama. Tombol Lihat Detail otomatis aktif melalui guard route existing. Halaman detail pending kini mempunyai form Setujui/Tolak/Minta Perbaikan pada 3.2.1.5 dan memakai API keputusan existing.

## Kontrak data dan pencarian

- `$jobs`: LengthAwarePaginator JobPosting, hanya `pending_approval`; eager-load `company:id,company_name`.
- `$filters`: q/category/location tervalidasi dan di-trim; per_page integer (default 15).
- q: judul ATAU nama perusahaan, di dalam satu grup AND terhadap status dan filter lain. q dan lokasi adalah pencarian sebagian literal; %, _ dan ! di-escape dan parameter SQL di-bind.
- Kategori memakai kesamaan nilai, bukan LIKE; mengikuti collation database tanpa memaksakan case-sensitive baru.
- q, category, location: nullable string maksimal 255 sesuai kolom string migration. Form perusahaan memiliki batas input kategori/lokasi lebih ketat; daftar tetap dapat memfilter data tersimpan hingga batas skema.
- page integer 1–100000; per_page integer 1–50. Halaman di luar hasil diarahkan ke halaman terakhir dengan filter tetap dibawa.
- Urutan ID menaik untuk hasil stabil, bukan tanggal pengajuan. Pagination membawa q/category/location/per_page; menerapkan filter baru membuang page lama, Reset kembali ke daftar default.
- `$categories`: seluruh kategori distinct dalam antrean pending, diurutkan, independen dari filter dan halaman aktif.
- Tidak menyaring verifikasi perusahaan/deadline secara tambahan; kelayakan keputusan adalah subtask lain.

## Tampilan dan batas data

Delapan kolom dipertahankan: nama lowongan, perusahaan, kategori, lokasi, estimasi gaji, tanggal pengajuan, status Menunggu Tinjauan dan tindakan. Ada tabel responsif, label input, error validasi, empty state antrean, hasil pencarian kosong dan fallback data belum dimuat. Nilai dinamis memakai escaping Blade.

Tidak ada sumber tanggal pengajuan sah pada skema existing. Controller tidak mengirim `$submissionDates`, sehingga kolom tanggal tetap Belum tersedia; tidak mengambil created_at/updated_at sebagai pengganti. Gaji null tetap Belum tersedia. Tidak ada data contoh dalam halaman aplikasi.

## Dasar dan dokumentasi

- Spreadsheet referensi `PBI_siKERJA_Kelompok_C_FINAL.xlsx`: Task per PBI baris 125 dan Product Backlog baris 25.
- Prototype PPL: resources/views/kemnaker/jobs/approval.blade.php (acuan tabel).
- Feature 2.2 Rifqi: model/relasi JobPosting dan status pengajuan.
- Feature 3.1 Fadlan sudah tersedia pada baseline ini; layout, gate dan pola query/validasi digunakan kembali.
- [CRITERIA_REVIEW.md](CRITERIA_REVIEW.md) menjadi acuan dokumentasi 3.2.1.2 untuk API 3.2.1.3 dan fungsi keputusan 3.2.1.5. Dokumen memisahkan AC PBI, perilaku existing, dan usulan; matriks Setujui/Tolak/Minta Perbaikan belum mengesahkan target status atau atribut yang ditandai **Belum disepakati**. Implementasi sekarang hanya daftar/detail baca.

## Validasi dan pengujian

GovernmentJobInspectionIndexTest memakai koneksi khusus SQLite :memory:, memeriksa environment testing, driver, nama database dan foreign key sebelum menerapkan dua migration existing pada database terisolasi. Tidak memakai RefreshDatabase atau database bersama; tidak ada migration baru.

Cakupan: akses pemerintah aktif/guest/role lain/nonaktif, status pending dan pengelompokan OR, relasi eager loading, kombinasi filter, wildcard literal, kategori seluruh antrean, ID ordering, pagination, batas input 255, invalid query, empty state, escaping HTML, gaji/tanggal tidak tersedia, navigasi Feature 3.1, dan tidak berubahnya record lowongan akibat pembacaan.

Fixture isolasi bersama berada pada tests/GovernmentJobInspectionTestCase.php: environment kosong, hanya koneksi SQLite :memory:, cache dan compiled view TEMP. Pemeriksaan php -l, route:list, suite detail dan regresi daftar dilakukan pada tahap 3.2.1.4. QA visual desktop/mobile/keyboard tetap terpisah dari feature tests.

## Detail pengajuan — Subtask 3.2.1.4

Sesuai instruksi implementasi, hanya pending_approval dapat dibuka. Status lain, ID hilang dan parameter nonnumerik menghasilkan 404; query membaca ulang status setiap request. ID diperlakukan sebagai string numerik agar ID di luar rentang integer juga aman ditolak sebagai tidak ditemukan.

Detail memakai show() dan view government.job-inspections.show, eager loading perusahaan terbatas id/nama, serta header no-store, private. Informasi: judul, perusahaan, status, kategori, tipe, lokasi, deskripsi, pendidikan, persyaratan, estimasi gaji, kuota, deadline. Teks kosong/nilai opsional null memiliki fallback Belum tersedia; HTML selalu di-escape dan deskripsi/persyaratan mempertahankan baris baru. Tidak ada tanggal pengajuan buatan, catatan internal perusahaan atau dokumen legalitas.

Navigasi kembali menuju daftar default. Menu lowongan aktif di halaman daftar maupun detail. Tidak ada syarat tambahan perusahaan verified atau deadline mendatang untuk membaca pengajuan. Pemeriksaan kelayakan dan perubahan status tetap di luar subtask ini.

## Hasil validasi 3.2.1.4 — 23 November 2026

- PHPUnit khusus GovernmentJobInspectionShowTest.php dan GovernmentJobInspectionIndexTest.php: 47 PASS, 0 FAIL, 342 assertions, exit 0. Tidak menjalankan suite lain.
- Route:list: index dan show terdaftar sebagai GET|HEAD dengan web, auth, can:access-government; parameter jobPosting memiliki constraint [0-9]+.
- Detail diuji untuk seluruh field, akses role, status selain pending, ID hilang/nonnumerik/di luar rentang integer, HTML tidak aman, nilai opsional null, menu/navigasi, tidak bocornya catatan/path dokumen perusahaan, dan tidak berubahnya data akibat GET.
- Fixture tidak membaca .env repository, hanya mengizinkan SQLite :memory:, dan menyimpan cache/compiled views pada TEMP. Migration existing diterapkan hanya ke SQLite terisolasi oleh fixture.
- PHP lint dan git diff --check lulus. Render Blade diuji melalui HTTP feature tests; review visual browser desktop/mobile/keyboard belum dilakukan.
- Tidak ada perbaikan kegagalan runtime; suite langsung lulus setelah implementasi dan penyesuaian fixture bersama. Tidak ada approval/rejection, model/migration baru, atau perubahan alur pemeriksaan perusahaan.

## Status kriteria pemeriksaan — Subtask 3.2.1.2

Dokumentasi awal dirapikan pada 23 November 2026. Pengguna kemudian memilih rancangan kerja verifikasi, deadline, dan pemetaan keputusan untuk implementasi API. Konfirmasi final PM Hasya serta koordinasi pengajuan ulang/reset metadata dan batas catatan perbaikan masih terbuka; Rancangan implementasi API keputusan kini tercatat pada bagian berikut dan CRITERIA_REVIEW.md; persetujuan final PM serta UI 3.2.1.5 tetap terpisah. Pembaruan ini hanya dokumentasi dan tidak menjalankan ulang pengujian.

## API baca — Subtask 3.2.1.3 (parsial)

Konvensi mengikuti API pemerintah existing. Belum ada API lowongan sebelum tahap ini; route HTML tetap dipertahankan. Endpoint baru:

| Metode | URL | Nama route | Fungsi |
| --- | --- | --- | --- |
| GET/HEAD | /pemerintah/api/pemeriksaan-lowongan | government.job-inspections.api.index | Daftar pengajuan pending |
| GET/HEAD | /pemerintah/api/pemeriksaan-lowongan/{jobPosting} | government.job-inspections.api.show | Detail pending; parameter whereNumber |

Autentikasi memakai sesi web existing, bukan token atau guard baru. Kedua endpoint dilindungi auth dan can:access-government. Respons sukses dan error 401/403/404/422 memakai JSON dan Cache-Control: no-store, private, termasuk tanpa header Accept. Perluasan bootstrap exception handler hanya menambahkan prefix lowongan; pola dan perilaku endpoint perusahaan Fadlan tidak diubah.

HTML dan API menggunakan JobInspectionQuery yang sama: status pending_approval, eager loading perusahaan id/nama, grup pencarian judul ATAU perusahaan, kategori exact match sesuai collation, lokasi parsial literal, urutan ID, batas query, default 15/maksimal 50 per halaman. ListJobInspectionsRequest dipakai ulang. Detail status lain/ID hilang/nonnumerik menghasilkan 404.

Respons daftar berisi data, links, meta, filters, categories. Opsi kategori berasal dari seluruh antrean pending, independen dari filter/halaman. Links mempertahankan q/category/location/per_page. Sesuai konvensi paginator API pemerintah existing, halaman di luar hasil mengembalikan 200 dengan data kosong dan metadata; HTML tetap redirect ke halaman terakhir. Pembedaan transport ini tidak mengubah query/filter atau batas pagination.

JobInspectionResource memakai allowlist: id, title, status, company (hanya id/company_name), category, job_type, location, description, education_requirement, requirements, salary_around, quota, deadline. Gaji berbentuk string decimal atau null; deadline Y-m-d atau null. Nilai JSON adalah data, bukan HTML untuk di-render mentah. Tidak ada NPWP, alamat/kontak, path dokumen, notes, approved_by, rejection_reason, timestamp pengajuan buatan, atau serialisasi model perusahaan utuh.

Pada tahap read-only sebelumnya belum ada endpoint keputusan. GET tetap tidak mengubah lowongan/perusahaan. Tahap penyimpanan di bawah menambahkan POST khusus; PUT/PATCH/DELETE pada URL baca tetap tidak didukung. Rancangan kerja terbaru berada di CRITERIA_REVIEW.md dan masih menunggu konfirmasi PM Hasya.

### Hasil pengujian 23 November 2026

- PHPUnit GovernmentJobInspectionApiTest.php + GovernmentJobInspectionIndexTest.php + GovernmentJobInspectionShowTest.php: **81 PASS, 0 FAIL, 768 assertions, exit 0**.
- Fixture bersama membatasi koneksi ke SQLite :memory:, memakai environment kosong dan cache/compiled view TEMP. Tidak membaca .env repository atau memakai MySQL Cloud. Migration existing hanya diterapkan pada SQLite terisolasi oleh fixture.
- Pengujian API: role/guest/nonaktif, JSON tanpa Accept, status pending dan OR pencarian, kesetaraan hasil HTML/API, kombinasi filter, wildcard literal, opsi kategori seluruh antrean, pagination/urutan ID, 404, 422, batas panjang 255, allowlist privasi, nilai null/teks JSON, serta metode tulis 405 dan tidak ada mutasi record.
- PHP lint pada file PHP baru/berubah lulus. Tidak menjalankan suite lain atau mengklaim pengujian visual browser.

## API penyimpanan keputusan — Subtask 3.2.1.3

Status: implementasi rancangan kerja selesai; **masih memerlukan konfirmasi PM Hasya**, bukan pernyataan siap deployment. UI 3.2.1.5 kini memakai API/service existing tanpa perubahan aturan.

POST `/pemerintah/api/pemeriksaan-lowongan/{jobPosting}/keputusan`, route `government.job-inspections.api.decisions.store`. Memakai sesi web, auth, can:access-government, whereNumber dan CSRF existing.

Payload action: approve/reject/request_changes. rejection_reason wajib untuk reject/request_changes dan di-trim; reject maksimal 1.000 karakter. Instruksi belum menetapkan batas panjang khusus request_changes, sehingga tidak ditambahkan. Klien tidak boleh menentukan status, approved_by, company_profile_id atau job_posting_id.

- approve: pending_approval → published; perusahaan verified, validasi data tersimpan memakai aturan/pesan Feature 2.2 yang sama dan deadline setelah hari ini; approved_by diambil dari sesi, alasan lama dibersihkan.
- reject/request_changes: pending_approval → rejected; simpan catatan dan kosongkan approved_by. Kedua aksi belum dapat dibedakan secara terstruktur setelah tersimpan.
- 200 JSON: message + data id/status/approved_by/rejection_reason. 422 untuk validasi/kelayakan; 409 jika status bukan pending; 404 jika ID tidak ditemukan; 401/403 untuk akses. 419 mengikuti CSRF Laravel existing.

JobInspectionDecisionService tetap menjadi sumber validasi utama untuk API yang dipakai UI 3.2.1.5. Transaksi mengunci lowongan, serta perusahaan pada persetujuan, sebelum pemeriksaan dan perubahan. JobPostingValidation menampung aturan/pesan yang diekstrak apa adanya dari controller perusahaan; create/update/toggle/pengajuan ulang tetap sama.

Koordinasi Feature 2.2: pengajuan ulang existing belum mereset approved_by; tidak diperbaiki dalam tahap ini. Tidak ada field audit/versi/status baru. Pemeriksaan hanya berdasarkan status dan data tersimpan saat transaksi; perubahan konten yang tetap pending atau siklus pengajuan baru belum memakai token versi.

### Validasi tahap penyimpanan — 23 November 2026

- GovernmentJobInspectionDecisionTest.php + GovernmentJobInspectionApiTest.php + GovernmentJobInspectionIndexTest.php + GovernmentJobInspectionShowTest.php: **131 PASS, 0 FAIL, 1.144 assertions, exit 0**.
- CSRF diuji dengan bypass testing middleware dinonaktifkan: tanpa token 419, token sesi cocok berhasil.
- Rollback kegagalan UPDATE, replay dan keputusan pemerintah kedua diuji. Uji dua pemeriksa dilakukan berurutan; **konkurensi paralel/row lock MySQL belum diuji** dan tidak dibuktikan oleh SQLite :memory:.
- Semua tes menggunakan fixture SQLite terisolasi tanpa membaca .env, migration/seeder bersama atau MySQL Cloud. PHP lint lulus. Tidak menjalankan suite di luar empat file tersebut.

## Antarmuka keputusan — Subtask 3.2.1.5

Detail pending pemerintah aktif menyediakan tiga form Bootstrap: Setujui Lowongan, Tolak Lowongan dan Minta Perbaikan. Masing-masing mengirim POST ke route government.job-inspections.api.decisions.store dengan action approve/reject/request_changes. Form membawa token CSRF Laravel; Fetch memakai credentials same-origin, Accept application/json dan X-CSRF-TOKEN dari token form. Tidak ada endpoint, service atau aturan keputusan baru.

Setujui meminta konfirmasi sebelum mengirim. Tolak memiliki alasan required dan maxlength 1000 sesuai backend. Minta Perbaikan memiliki catatan required tanpa maxlength tambahan, sesuai kontrak backend saat ini. Frontend tidak memutuskan kelayakan perusahaan, kelengkapan data atau deadline; error validasi tetap berasal dari backend.

Form dinonaktifkan sampai JavaScript diinisialisasi, dengan pesan noscript bila JavaScript tidak aktif. Seluruh tindakan dikunci selama request. Sukses memperbarui badge dari status respons, menampilkan pesan, menyembunyikan tindakan dan menawarkan tautan Kembali ke antrean; tidak ada pengiriman ulang dari halaman sukses.

422 menampilkan pesan/daftar error sebagai textContent, mempertahankan isian dan membuka form untuk diperbaiki. 401/403/404/409/419 menampilkan instruksi yang sesuai dan mengunci tindakan sampai muat ulang. Kegagalan jaringan, respons server tidak valid atau hasil yang tidak pasti juga meminta muat ulang, karena keputusan mungkin sudah tersimpan; tidak ada retry otomatis. Pesan server/error tidak dirender sebagai HTML.

Aturan keputusan tetap **rancangan kerja yang menunggu konfirmasi final PM Hasya**. Tolak dan Minta Perbaikan tetap berbagi rejected/rejection_reason; koordinasi metadata pengajuan ulang dan batas catatan perbaikan tidak diubah oleh UI.

### Hasil pengujian UI dan regresi — 23 November 2026

- PHPUnit: **156 PASS, 0 FAIL, 1.414 assertions, exit 0**, untuk GovernmentJobInspectionDecisionUiTest.php, CompanyJobSubmissionRegressionTest.php, GovernmentJobInspectionDecisionTest.php, GovernmentJobInspectionApiTest.php, GovernmentJobInspectionIndexTest.php dan GovernmentJobInspectionShowTest.php.
- Tes form membaca HTML/hidden payload/token yang benar-benar dirender dan mem-post payload itu ke API dengan pemeriksaan CSRF aktif. Constraint alasan/catatan, pesan feedback, akses pemerintah, perubahan status dan pencegahan replay tercakup.
- Regresi perusahaan khusus menguji draf/pengajuan, aturan validator yang diekstrak, edit published, resubmit rejected, toggle penutupan/pengajuan ulang, serta pembatasan kepemilikan. approved_by lama saat pengajuan ulang tetap dipertahankan sesuai perilaku existing; tidak diperbaiki diam-diam.
- Node built-in test runner: **17 PASS, 0 FAIL**, tests/Frontend/job-inspection-decision.test.cjs. Menguji handler JS melalui fixture DOM/Fetch sintetis, termasuk konfirmasi, payload/session CSRF, pencegahan request ganda, sukses, 422/401/403/404/409/419, jaringan, non-JSON dan hasil tidak pasti. Tidak memasang dependency JavaScript tambahan.
- Tes JS tersebut bukan browser nyata; feature tests menguji render Blade/HTTP, bukan layout visual atau klik DOM browser. QA visual desktop/mobile, keyboard/screen reader dan konkurensi paralel MySQL tetap belum diuji pada tahap ini.
- SQLite :memory: terisolasi, environment kosong, cache/compiled view TEMP. Tidak membaca .env atau menggunakan MySQL Cloud. PHP lint, node --check dan git diff --check lulus.

## QA browser PBI 3.2.1 — 23 November 2026

Hasil terbaru dan batas bukti setiap pemeriksaan dicatat pada [QA_REVIEW.md](QA_REVIEW.md). Satu bug benturan input action dengan properti form.action diperbaiki pada JavaScript; regresi akhir 156 PHPUnit PASS, 1.414 assertions, serta 17 JavaScript PASS, tanpa kegagalan. QA desktop/mobile dan HTTP CSRF memakai SQLite terisolasi tanpa membaca .env. QA manual dialog OK/Cancel telah dikonfirmasi pengguna; fixture 26 tetap pending tanpa POST saat Cancel. Konfirmasi final PM serta validasi konkurensi DB target tetap terbuka.
