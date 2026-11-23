# Monitoring lowongan — Subtask 3.2.2.1–3.2.2.5

Status terbaru: daftar/detail HTML telah terhubung pada 3.2.2.3 menggunakan government.layouts.app dan Bootstrap existing. API baca tersedia sejak 3.2.2.2. Subtask 3.2.2.5 menambah tabel/model tanda terpisah; aturan keputusan dan alur pemeriksaan existing tidak diubah. Catatan rancangan awal di bawah diperbarui dengan kontrak integrasi.

## Struktur

- Judul monitoring, catatan keputusan terbuka, pencarian dan empat filter.
- Tabel tujuh kolom: judul, perusahaan, bidang/kategori, lokasi, status, deadline dan Lihat Detail.
- Grid filter satu kolom pada mobile, dua pada small, empat pada large. Region tabel responsif dapat difokuskan dan digulir horizontal; tombol dapat membungkus.
- Keadaan terpisah: backend belum memasok data, paginator kosong, hasil filter kosong, dan hasil berisi data. Tidak menampilkan data dummy atau angka nol sebagai hasil aplikasi sebelum backend tersedia.
- Pagination Bootstrap 5 mempertahankan pencarian, empat filter dan per_page; perubahan filter tidak mengirim page lama.
- Semua nilai dinamis di-escape dengan {{ }}. Deadline memakai cast date JobPosting dan fallback Belum tersedia, tanpa inferensi aktif/kedaluwarsa.
- Badge Perlu Ditinjau kini memakai flag tersimpan. Form Tandai/Hapus Tanda dan catatan persetujuan PM ada pada [REVIEW_FLAGS.md](REVIEW_FLAGS.md). Tidak ada penandaan otomatis.

## Kontrak presentasi usulan untuk integrasi berikutnya

| Variabel | Bentuk/kegunaan |
| --- | --- |
| jobs | LengthAwarePaginator JobPosting; relasi company sudah eager loaded, deadline date/null. Jika belum diberikan, tampil pesan backend belum tersedia |
| filters | Nilai tervalidasi q, company, category, location, status dan per_page. company adalah usulan parameter ID perusahaan, bukan kontrak API final |
| companies | Iterable perusahaan berisi id dan company_name untuk dropdown |
| categories | Iterable string kategori pekerjaan |
| statusOptions | Array nilai status existing ke label, dipasok backend setelah cakupan disepakati. Tidak ada opsi status bawaan yang menetapkan cakupan |

Peta label status pada view hanya menerjemahkan nilai existing untuk baris, bukan menentukan status yang boleh diambil query. Bidang memakai category; lokasi dan pencarian berupa input teks. Daftar opsi perusahaan/kategori harus disiapkan backend terlepas dari halaman paginator aktif.

Route HTML government.job-monitoring.index/show kini terdaftar; guard Route::has tetap dipertahankan. Pencarian/reset dan Lihat Detail tersedia. Detail monitoring terpisah dari detail PBI 3.2.1 yang khusus pending_approval.

Backend HTML memakai ListJobMonitoringRequest dan JobMonitoringQuery existing, cakupan published serta auth/can:access-government. Menu/judul Monitoring Lowongan ditambahkan pada layout bersama tanpa mengganti menu atau perilaku halaman existing. Pengujian lengkap integrasi empat filter tetap dilanjutkan pada 3.2.2.4.

## Keputusan bisnis

- Definisi aktif (published saja atau juga deadline belum lewat): **Belum disepakati**.
- Cakupan filter status (published, closed atau lainnya): **Belum disepakati**.
- Mekanisme penandaan untuk tinjauan lebih lanjut, pemicu manual/otomatis, penyimpanan, petugas dan penghapusan tanda: **Belum disepakati**.

Untuk API 3.2.2.2, pengguna memilih kontrak baca sementara hanya published, termasuk deadline lama/null. Ini bukan penetapan final definisi aktif atau filter multi-status. Penandaan tetap menunggu kesepakatan; tidak menambah status baru atau memakai rejection_reason sebagai flag monitoring.

## Validasi

Pemeriksaan kompilasi/render Blade dapat dilakukan dalam bootstrap TEMP dengan environment kosong, tanpa route baru atau database. Rendering sintetis hanya untuk verifikasi rancangan, bukan data aplikasi. QA browser desktop/mobile dan integrasi backend belum dilakukan pada subtask rancangan ini.

## API read-only — Subtask 3.2.2.2

GET /pemerintah/api/monitoring-lowongan, named route government.job-monitoring.api.index. Endpoint berada dalam group pemerintah existing, memakai sesi web, auth dan can:access-government (hanya pemerintah aktif). Route HTML government.job-monitoring.index/show kemudian ditambahkan pada 3.2.2.3; kontrak API baca tetap sama.

Komponen terpisah: JobMonitoringDataController, ListJobMonitoringRequest, JobMonitoringQuery dan JobMonitoringResource. JobInspectionQuery, controller/resource antrean pending serta implementasi PBI 3.2.1 tidak diubah. Dua daftar path exception pada bootstrap hanya ditambah prefix API monitoring agar respons error konsisten JSON tanpa Accept; perilaku path API lain dipertahankan.

### Kontrak query

| Parameter | Validasi/perilaku |
| --- | --- |
| q | Opsional string max 255, trim; judul ATAU nama perusahaan dalam grup AND cakupan published |
| company | Opsional integer positif hingga PHP_INT_MAX; exact company_profile_id. ID valid yang tidak ditemukan memberi data kosong, bukan error keberadaan perusahaan |
| category | Opsional string max 255, trim; exact match sesuai collation database, memakai kategori existing |
| location | Opsional string max 255, trim; partial match literal |
| status | Opsional string, trim; hanya published diterima. Nilai lain/array mendapat 422. Kosong memakai cakupan published |
| page | Integer 1–100000, default 1 |
| per_page | Integer 1–50, default 15 |

Pencarian/lokasi memperlakukan %, _ dan ! secara literal; parameter SQL di-bind. Query memakai scope published(), eager loading company:id,company_name, select kolom daftar saja dan urutan ID naik yang stabil, bukan tanggal persetujuan. approved_by tidak menjadi syarat pengambilan. Deadline lewat, hari ini, mendatang dan null tetap dapat muncul. Status verifikasi perusahaan tidak menjadi filter tersembunyi yang menghilangkan published lama.

### Respons

Sukses 200 JSON: data, links, meta, filters. Setiap data hanya memuat id, title, company (id/company_name), category, location, status dan deadline (Y-m-d/null). Tidak ada detail akun, NPWP/alamat, path dokumen, identitas pelamar, approved_by, rejection_reason atau catatan internal. Teks JSON adalah data dan tetap harus di-escape saat UI kelak dirender.

filters berisi nilai normalisasi q/company/category/location/status/per_page; status selalu published. Pagination mempertahankan filter. Halaman di luar hasil mengembalikan 200 data kosong dan metadata, mengikuti API pemerintah existing. Hasil kosong memiliki data=[]; tidak menggunakan data dummy.

401 guest, 403 role tidak berwenang/nonaktif, 422 parameter invalid; respons JSON no-store, private mengikuti pola pemerintah. GET sukses juga no-store, private. Tidak ada endpoint API detail monitoring, mutasi lowongan atau penandaan; halaman HTML detail tersedia pada 3.2.2.3. POST/PUT/PATCH/DELETE pada URL daftar tidak didukung.

### Batas keputusan bisnis

API ini memenuhi pembacaan published sesuai instruksi implementasi, **bukan penyelesaian filter multi-status 3.2.2.4**. Definisi aktif final, cakupan status monitoring lebih luas dan mekanisme penandaan tetap **Belum disepakati**. Aturan keputusan PBI 3.2.1 juga masih menunggu konfirmasi final PM Hasya.

### Pengujian

GovernmentJobMonitoringApiTest memakai fixture GovernmentJobInspectionTestCase existing: SQLite :memory: terisolasi, environment kosong dan cache/storage TEMP. Tidak membaca .env atau mengakses MySQL Cloud. Pengujian meliputi gate/route, published vs approved_by, deadline dan verifikasi perusahaan, kombinasi filter/pagination, wildcard literal, batas input, JSON error, allowlist, GET tanpa mutasi serta tidak tersedianya endpoint tulis/detail.

Hasil validasi 23 November 2026:

- Gabungan GovernmentJobMonitoringApiTest (33 kasus baru) dan enam file regresi PBI 3.2.1/validator perusahaan: **189 PASS, 0 FAIL, 1.808 assertions**, exit 0.
- Run awal memiliki enam kegagalan pada snapshot tes baru: snapshot model sebelum refresh belum menyertakan default kolom dan urutan atribut database. Diperbaiki hanya pada tes monitoring dengan mengambil snapshot fresh dari SQLite; tidak ada perubahan kode produksi untuk mengatasi kegagalan tersebut.
- PHP lint tujuh file PHP baru/berubah dan git diff --check lulus. Perbandingan route dengan checkpoint mempertahankan 65 route aplikasi existing (route health/storage framework di luar routes/web.php dikecualikan); endpoint monitoring memakai GET|HEAD/web/auth/can:access-government.
- Hash index.blade.php rancangan 3.2.2.1 tetap sama. Tidak mengaktifkan route HTML, menu, detail monitoring, mutasi atau penandaan. Tidak menjalankan seluruh suite, QA browser, atau pengujian MySQL.

## Daftar dan detail HTML — Subtask 3.2.2.3

- GET /pemerintah/monitoring-lowongan: government.job-monitoring.index.
- GET /pemerintah/monitoring-lowongan/{jobPosting}: government.job-monitoring.show, whereNumber.
- Keduanya memakai web/auth/can:access-government dalam group pemerintah existing. Respons halaman dan redirect pagination memakai Cache-Control: no-store, private.
- JobMonitoringController memakai ListJobMonitoringRequest dan paginate/filters JobMonitoringQuery yang sama dengan API. Tidak menyalin logika pencarian atau pagination. Query ditambah metode companies/categories untuk opsi dari seluruh cakupan published, serta findPublished untuk membaca detail; paginate API tidak diubah.
- Daftar tujuh kolom, pencarian, pagination Bootstrap, empty state dan Lihat Detail aktif. Default status published tidak dianggap filter tambahan dalam empty state. Semua parameter filter diteruskan ke query existing, tetapi review integrasi lengkap filter tetap ruang lingkup 3.2.2.4; multi-status belum diselesaikan.
- Detail menampilkan judul, perusahaan, kategori, lokasi, status, tipe, deskripsi, persyaratan, pendidikan, gaji, kuota dan deadline. Escaping Blade dan fallback Belum tersedia berlaku; tidak membuat tanggal persetujuan dari created_at.
- Detail hanya published, termasuk deadline lewat/null dan approved_by null. Status lain/ID hilang/nonnumerik/di luar rentang integer menghasilkan 404. Status diperiksa ulang setiap GET. Navigasi kembali menuju daftar default, tanpa mempertahankan filter sebelumnya.
- Tidak ada tombol Setujui/Tolak/Minta Perbaikan, endpoint mutasi atau fungsi penandaan. Contoh badge tinjauan pada daftar tetap berlabel rancangan dan tidak diterapkan ke record.
- Menu pemeriksaan perusahaan (brand), pemeriksaan lowongan dan monitoring verifikasi dipertahankan. Penambahan layout dibatasi judul/menu aktif monitoring; controller, gate, model, service dan view pemeriksaan PBI 3.2.1/Feature 3.1 tidak diubah.

### Validasi 3.2.2.3 — 23 November 2026

- GovernmentJobMonitoringWebTest (26 kasus baru), GovernmentJobMonitoringApiTest serta enam file regresi PBI 3.2.1/validator perusahaan: **215 PASS, 0 FAIL, 2.029 assertions**, exit 0; semua database menggunakan SQLite :memory: terisolasi tanpa .env repository atau MySQL Cloud.
- Dua assertion API yang dahulu menyatakan route HTML belum tersedia disesuaikan menjadi tersedia. Kontrak/payload/query/filter/pagination API existing tetap lulus.
- Tes HTML: otorisasi, route/menu, kesetaraan cakupan HTML/API, published meskipun deadline lewat, detail/404, data dan fallback, escaping/privasi, pencarian judul/perusahaan, pagination/redirect, opsi filter lintas halaman, empty state, validasi input serta GET tanpa mutasi.
- PHP lint file PHP terkait dan git diff --check lulus. Tidak menjalankan suite penuh, QA browser atau pengujian konkurensi MySQL pada tahap ini.

Definisi aktif final, cakupan multi-status dan mekanisme penandaan tetap **Belum disepakati**. Konfirmasi final PM atas rancangan aturan keputusan PBI 3.2.1 juga masih terbuka.

## Integrasi filter — Subtask 3.2.2.4

Status: filter terintegrasi dan teruji dalam cakupan sementara published. **Kemampuan filter multi-status belum selesai dan menunggu keputusan PM Hasya. Seluruh acceptance criteria filter status tidak dinyatakan terpenuhi.**

- Perusahaan: parameter company yang divalidasi sebagai ID integer positif, dibandingkan exact dengan company_profile_id. Nama perusahaan yang mirip tidak memengaruhi filter ID.
- Bidang: category exact sesuai collation database, bukan pencarian substring kategori.
- Lokasi: partial match literal menggunakan parameter SQL terikat.
- Status: hanya published; status lain/array ditolak backend. Deadline lewat/null tetap ditampilkan dalam cakupan published.
- Empat filter digabung dengan AND. Pencarian judul ATAU perusahaan tetap di dalam grup AND, sehingga tidak membocorkan status lain. HTML/API memakai Form Request dan JobMonitoringQuery yang sama; tidak menambah atau menduplikasi backend pada subtask ini.
- Opsi perusahaan/kategori berasal dari seluruh data published, bukan halaman aktif atau hasil filter yang sedang dipilih; perusahaan tanpa lowongan published dan kategori pending saja tidak masuk katalog opsi.
- Dropdown status hanya berisi published, tanpa pilihan Semua status yang memberi kesan cakupan lebih luas. Keterangan batasan multi-status tetap terlihat.
- Nilai company/category valid tetapi di luar katalog opsi tetap ditampilkan sebagai nilai filter terpilih dengan catatan tidak ada dalam opsi monitoring. Ini representasi parameter permintaan, bukan data perusahaan/kategori dummy atau penambahan katalog database. Nilai dinamis tetap di-escape.
- Pagination mempertahankan seluruh nilai normalisasi. Form tidak membawa page lama saat filter diterapkan. Reset menuju URL daftar tanpa query menghapus filter pencarian/perusahaan/kategori/lokasi dan mengembalikan page 1/per_page 15; cakupan status tetap published.
- Grid responsif, region tabel horizontal, empty state, dan escaping dipertahankan. Tidak ada penandaan lowongan atau perubahan layout/fitur lain pada subtask ini.

### Validasi 3.2.2.4 — 23 November 2026

GovernmentJobMonitoringFiltersTest menambah 21 kasus: filter masing-masing, kombinasi empat filter, pencarian judul/perusahaan bersama filter, kesetaraan ID/filters HTML dan API, nilai terpilih di DOM, opsi katalog lintas halaman, parameter pagination, reset nyata melalui URL yang dirender, filter tanpa hasil di luar katalog, escaping dan input invalid tanpa mutasi.

Gabungan tes filter, HTML monitoring, API monitoring serta enam file regresi PBI 3.2.1/validator perusahaan: **236 PASS, 0 FAIL, 2.319 assertions**, exit 0. Fixture memakai SQLite :memory:, environment kosong dan cache/storage TEMP; tidak membaca .env repository atau mengakses MySQL Cloud. PHP lint tes baru dan git diff --check lulus. Tidak menjalankan ulang QA browser desktop/mobile atau seluruh suite aplikasi.

File yang berubah khusus tahap ini: index.blade.php, README.md dan tes baru GovernmentJobMonitoringFiltersTest.php. Query, Form Request, controller HTML/API, resource, route, layout dan PBI 3.2.1 dipertahankan dari tahap sebelumnya.

Keputusan terbuka: definisi final aktif terkait deadline, cakupan status selain published, mekanisme penandaan, serta konfirmasi final PM atas rancangan keputusan PBI 3.2.1. Tidak menetapkan keputusan tersebut melalui implementasi filter ini.


## Penandaan manual — Subtask 3.2.2.5

PM Amirza Hasya telah menyetujui rancangan penandaan sebagaimana disampaikan dalam instruksi implementasi. Kontrak, migration, privasi dan batas pengujian dicatat pada [REVIEW_FLAGS.md](REVIEW_FLAGS.md). Bagian sebelumnya merupakan catatan tahapan sebelum persetujuan ini. Definisi aktif final, multi-status dan persetujuan aturan approval PBI 3.2.1 tetap terbuka.

Hasil regresi penandaan: **267 PASS, 0 FAIL, 2.641 assertions**, seluruh database SQLite :memory: terisolasi. Lihat REVIEW_FLAGS.md untuk rincian dan keterbatasan QA browser/konkurensi MySQL.

## QA integrasi — Subtask 3.2.2.6

Lihat [QA_REVIEW.md](QA_REVIEW.md): regresi 267 PHPUnit PASS, 2.641 assertions dan 17 JavaScript PASS; integrasi HTTP lokal dengan CSRF aktif lulus. QA desktop oleh agen terverifikasi sebagian; pengguna kemudian mengonfirmasi mobile sesuai dan dialog Hapus Tanda Cancel/OK berhasil. Rincian perbedaan konfirmasi manual dan helper lokal dicatat pada QA_REVIEW.md. Tidak ada bug aplikasi baru terbukti atau perubahan aturan bisnis pada QA ini. Filter multi-status dan definisi aktif final tetap menunggu PM.
