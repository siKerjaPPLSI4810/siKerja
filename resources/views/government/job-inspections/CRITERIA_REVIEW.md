# Subtask 3.2.1.2 — Kriteria pemeriksaan lowongan kerja

Diperbarui: 23 November 2026. Branch: `Hasya/Sprint1`. Baseline integrasi: `78c6ca33f54374c288c731ffdcb2419cced7e753`.

Status: rancangan kerja keputusan dipilih pengguna untuk implementasi API 3.2.1.3; **masih memerlukan konfirmasi PM Hasya**. Aturan di bawah merupakan rancangan implementasi, bukan kebijakan final atau bukti persetujuan PM/deployment.

## 1. Tujuan dan pembagian pekerjaan

Menjadi acuan bersama bagi API **3.2.1.3** dan fungsi keputusan **3.2.1.5**, agar pemeriksaan menggunakan dasar yang jelas dan tidak menghasilkan dua implementasi aturan yang berbeda.

- **3.2.1.2:** mendokumentasikan informasi yang diperiksa, dasar kriteria, matriks keputusan, dan keputusan PM yang masih terbuka.
- **3.2.1.3:** mengimplementasikan pengambilan pengajuan dan penyimpanan keputusan melalui API berdasarkan kontrak yang sudah disepakati. GET HTML daftar/detail existing dapat dimanfaatkan, tetapi belum merupakan API penyimpanan keputusan.
- **3.2.1.5:** mengimplementasikan fungsi Setujui, Tolak, dan Minta Perbaikan dengan aturan yang sama; hindari menyalin logika keputusan dari API.
- **3.2.1.6:** menguji keputusan dan perubahan status setelah kontraknya disepakati.

Subtask dokumentasi ini tidak menambah endpoint, field, status, model, tabel, atau migration.

## 2. Kriteria dari PBI

Sumber: `C:/Users/ACER/Documents/siKerja-References/PBI_siKERJA_Kelompok_C_FINAL.xlsx`.

| Sumber | Ketentuan tertulis | Implikasi pemeriksaan |
| --- | --- | --- |
| Product Backlog, baris 25, PBI 3.2.1 | Memeriksa dan menyetujui pengajuan agar hanya lowongan layak dipublikasikan | Pemerintah perlu menilai pengajuan sebelum keputusan publikasi |
| Product Backlog, baris 25, acceptance criteria | “Keputusan setuju, tolak, atau catatan perbaikan tersimpan; status lowongan berubah sesuai keputusan.” | Keputusan/catatan dan pemetaan perubahan status harus memiliki kontrak yang jelas |
| Task per PBI, baris 126 | “Menentukan kriteria pemeriksaan lowongan berdasarkan aturan yang disepakati.” | Kriteria harus memiliki dasar atau persetujuan; usulan bukan aturan final |
| Task per PBI, baris 128, 3.2.1.4 | Membuat tampilan detail lowongan, kualifikasi, dan informasi gaji | Informasi pekerjaan harus dapat diperiksa |
| Story Rinci (Draf Awal), baris 33–35 | Antrean menunggu pemeriksaan; detail posisi, persyaratan, lokasi, dan gaji lengkap; keputusan beserta keterangan | Konteks pendukung, bukan pengganti AC revisi. Pengajuan belum disetujui tidak tampil publik |

PBI tidak merinci syarat perusahaan verified, deadline keputusan, panjang alasan, status perbaikan atau metadata pemeriksa. Rancangan kerja terpilih pada bagian 4 berasal dari instruksi implementasi pengguna dan masih menunggu konfirmasi PM; prototype bukan sumber persetujuan bisnis. Pemeriksaan upah wilayah belum diadopsi.

## 3. Perilaku aplikasi existing

Bagian ini menjelaskan kode yang tersedia, bukan otomatis menetapkan syarat persetujuan pemerintah.

| Bagian | Perilaku terverifikasi | Referensi kode |
| --- | --- | --- |
| Akses baca | Daftar/detail dibatasi `auth` dan `can:access-government`; gate mensyaratkan pemerintah aktif | routes/web.php; AppServiceProvider |
| Antrean dan detail | Hanya pending_approval; detail status lain/ID hilang menghasilkan 404. Ini kontrak baca yang telah ditetapkan pengguna | Government/JobInspectionController::index, show |
| Status lowongan | draft, pending_approval, published, rejected, closed; status berupa string | JobPosting; migration create_sikerja_table; CompanyJobController |
| Publikasi | Daftar/detail publik menggunakan published; API keputusan kini mengikuti rancangan bagian 4 | JobPosting::published; PublicController |
| Perusahaan | verification_status pending/verified/rejected. Pengajuan perusahaan belum mensyaratkan verified; keputusan verifikasi resmi ditangani Feature 3.1 | CompanyProfile; CompanyJobController; layanan pemeriksaan perusahaan |
| Waktu pengajuan | Tidak ada submitted_at/approved_at khusus; created_at/updated_at bukan bukti waktu pengajuan atau keputusan | Migration job_postings |
| Atribut keputusan | approved_by dan rejection_reason nullable tersedia; API keputusan mengikuti rancangan bagian 4 | JobPosting; migration job_postings |

### Informasi yang diperiksa dan validasi form existing

Field berikut sudah dapat dibaca dari detail. Validasi di bawah berlaku pada form buat/edit perusahaan; rancangan kerja memberlakukannya ulang saat Setujui; Tolak/Minta Perbaikan tidak mensyaratkan lowongan sudah lengkap.

| Informasi | Aturan form existing |
| --- | --- |
| Judul | Wajib string, 3–255 karakter; regex huruf ASCII dan whitespace |
| Kategori | Wajib string, maksimal 100 karakter |
| Tipe pekerjaan | Wajib string, maksimal 50 karakter |
| Lokasi | Wajib string, 3–150 karakter; regex huruf ASCII, whitespace, titik, tanda hubung |
| Pendidikan | Wajib string, maksimal 50 karakter |
| Deskripsi dan persyaratan | Keduanya wajib string, minimal 20 karakter |
| Kuota | Wajib integer, minimal 1 |
| Estimasi gaji | Wajib numerik, minimal Rp1.000.000 |
| Deadline | Wajib tanggal setelah hari ini saat buat/edit |

Sumber: `JobPostingValidation::rules/messages`, diekstrak tanpa mengubah isi dari `CompanyJobController::validateJobRequest` dan dipakai bersama form perusahaan serta pemeriksaan persetujuan. Pilihan kategori/tipe/pendidikan di UI belum menjadi whitelist backend. Minimum gaji aplikasi bukan bukti kepatuhan upah minimum wilayah. Salary dan deadline nullable pada skema walaupun form mewajibkannya; data lama perlu dibedakan dari data yang lolos form saat ini.

Deadline hari ini belum dianggap lewat oleh `JobPosting::applicationDeadlinePassed()`; daftar published tidak otomatis menyembunyikan deadline lama. Perbedaan ini tidak menetapkan aturan deadline saat pemeriksaan pemerintah.

### Pengajuan ulang dan perubahan existing

- Buat dengan is_draft: draft; pengajuan langsung: pending_approval. Keduanya melewati validasi form.
- Edit published atau update dengan resubmit: pending_approval dan rejection_reason dibersihkan; approved_by lama tetap tersimpan.
- Edit tanpa resubmit pada status selain published: status tetap.
- Toggle published: closed. Toggle status selain published: pending_approval dan alasan dibersihkan, tanpa validasi form/deadline ulang.

Sumber: `CompanyJobController::store`, `update`, `toggleStatus`. Perubahan terhadap mekanisme perusahaan tersebut membutuhkan koordinasi; tidak diubah pada subtask ini.

## 4. Rancangan implementasi keputusan — menunggu konfirmasi PM Hasya

Instruksi implementasi memilih rancangan kerja berikut. Ini menggantikan sel Belum disepakati untuk kebutuhan coding/testing, bukan mencatat persetujuan final PM.

| Keputusan / action API | Status asal → tujuan | Kriteria | Catatan dan metadata |
| --- | --- | --- | --- |
| Setujui / approve | pending_approval → published | Perusahaan verified; data tersimpan lolos validator Feature 2.2; deadline setelah hari ini | approved_by = ID pemerintah aktif dari sesi; rejection_reason = null |
| Tolak / reject | pending_approval → rejected | Pemerintah aktif; alasan string wajib, di-trim, maksimal 1.000 karakter setelah trim | Simpan rejection_reason; approved_by = null agar persetujuan lama tidak tertinggal |
| Minta Perbaikan / request_changes | pending_approval → rejected | Pemerintah aktif; catatan string wajib dan di-trim | Simpan rejection_reason; approved_by = null; tidak membuat status baru |

Tolak dan Minta Perbaikan **belum dapat dibedakan secara terstruktur** setelah disimpan; action tidak mempunyai kolom tersendiri. Tidak ada identitas pemeriksa penolakan/perbaikan yang dipersistenkan dalam atribut baru. Batas 1.000 karakter yang dipilih pengguna berlaku untuk Tolak; batas panjang khusus catatan Minta Perbaikan belum ditetapkan dan tidak ditambahkan berdasarkan asumsi.

Perusahaan belum verified atau data/deadline tidak valid menghasilkan 422 tanpa otomatis menolak lowongan. Status selain pending_approval menghasilkan 409 tanpa perubahan. ID tidak ditemukan/nonnumerik menghasilkan 404. Metadata/status dari klien tidak dapat menggantikan nilai server; status, approved_by, company_profile_id, job_posting_id dilarang sebagai input keputusan.

## 5. Kontrak API dan penggunaan bersama UI

- POST `/pemerintah/api/pemeriksaan-lowongan/{jobPosting}/keputusan`.
- Route `government.job-inspections.api.decisions.store`, parameter whereNumber, group web dengan auth dan can:access-government; perlindungan CSRF existing tetap aktif.
- Payload: action (`approve`, `reject`, `request_changes`), rejection_reason sesuai matriks, dan token CSRF sesi saat dibutuhkan. Tidak menerima perubahan isi lowongan dalam endpoint keputusan.
- Sukses 200 JSON: message dan data berisi id/status/approved_by/rejection_reason. Tidak mengirim model perusahaan atau catatan internal pemerintah. Respons sukses/401/403/404/409/422 menggunakan kebijakan JSON existing dengan no-store, private; 419 mengikuti CSRF Laravel existing.
- `StoreJobInspectionDecisionRequest` dan `JobInspectionDecisionValidation` berbagi validasi payload dengan `JobInspectionDecisionService::record` agar service aman dipanggil UI 3.2.1.5 tanpa melewati HTTP.
- `JobPostingValidation` berisi aturan/pesan Feature 2.2 existing yang dipakai ulang. Controller perusahaan hanya beralih memanggil komponen ini; alur create/update/toggle/pengajuan ulang tidak diubah.
- UI 3.2.1.5 memakai endpoint keputusan existing; tidak menambah endpoint mutasi lainnya. API baca dan query bersama tetap dipertahankan.

## 6. Transaksi, penguncian, dan batas konsistensi

Service mengotorisasi pemerintah aktif, memvalidasi payload, membuka transaksi (maksimal 3 percobaan), lalu membaca ulang JobPosting dengan lockForUpdate. Bila status bukan pending_approval, keputusan ditolak 409. Saat Setujui, service juga mengunci profil perusahaan dan memeriksa status verified serta data lowongan tersimpan terkini sebelum UPDATE. Kegagalan validasi atau transaksi tidak meninggalkan perubahan data.

Pada database yang mendukung row lock, penguncian dirancang agar keputusan kedua membaca hasil pertama dan tidak menimpanya. **SQLite :memory: tidak membuktikan semantik row lock/konkurensi paralel MySQL.** Tes menguji request berurutan dari dua pemerintah, replay, dan rollback kegagalan UPDATE; bukan dua transaksi MySQL paralel.

Status diperiksa kembali, tetapi belum ada token versi/revisi pengajuan untuk mendeteksi perubahan konten yang tetap pending atau pengajuan ulang setelah siklus keputusan. API memeriksa data tersimpan terbaru; kebutuhan versi/audit tambahan masih memerlukan keputusan PM dan tidak ditambahkan lewat migration/field baru.

## 7. Konfirmasi PM dan koordinasi yang masih diperlukan

| Topik | Status rancangan / tindak lanjut |
| --- | --- |
| Pemetaan keputusan, perusahaan verified, kelengkapan dan deadline | Sudah dipilih sebagai rancangan implementasi di bagian 4; **menunggu konfirmasi PM Hasya** |
| Tolak vs Minta Perbaikan | Sama-sama rejected + rejection_reason; konfirmasi penerimaan keterbatasan pelaporan dan batas panjang catatan perbaikan |
| Pengajuan ulang Feature 2.2 | Existing membersihkan alasan tetapi belum membersihkan approved_by. Koordinasikan reset metadata dengan Rifqi; tidak diubah di sini. Keputusan baru Tolak/Perbaikan sudah membersihkan approved_by |
| Metadata pemeriksa dan sejarah keputusan | Setujui mengisi approved_by; penolakan/perbaikan mengosongkannya. Kebutuhan actor/history tambahan dan versi pengajuan belum ditetapkan |
| Pemeriksaan upah wilayah | Belum diadopsi; Rp1.000.000 adalah minimum aplikasi existing, bukan bukti kepatuhan upah wilayah |
| Konkurensi deployment | SQLite saja tidak memverifikasi perilaku database produksi; perlukan lingkungan database testing terisolasi bila pengujian paralel diperlukan, bukan MySQL Cloud bersama |

Subtask 3.2.1.3 mencakup API baca dan simpan; 3.2.1.5 kini memakai endpoint/service keputusan yang sama untuk UI agar aturan tidak terduplikasi. Implementasi tidak berarti persetujuan PM final atau siap deployment.

## 8. Pengujian

Tes dijalankan hanya untuk keputusan, API baca, daftar HTML, dan detail HTML. Fixture memakai SQLite :memory:, environment kosong dan cache/compiled view TEMP; tidak membaca .env repository atau MySQL Cloud. Dua migration existing diterapkan hanya pada SQLite terisolasi oleh fixture; tidak ada migration baru.

Cakupan keputusan: otorisasi, approve/reject/request_changes, trim/batas alasan, validasi field tersimpan dan deadline, perusahaan belum verified, metadata spoofing, pencegahan replay/keputusan kedua, rollback UPDATE, CSRF 419/token benar, 404, pemanggilan service langsung, dan regresi validasi/form perusahaan setelah ekstraksi validator.

Hasil pengujian tahap ini dicatat di README. Hasil QA visual dan konfirmasi manual OK/Cancel terbaru dicatat pada QA_REVIEW.md; pemeriksaan paralel MySQL belum dijalankan. UI keputusan dibuat pada 3.2.1.5 dengan endpoint/service existing.

## 9. Implementasi antarmuka 3.2.1.5

Tiga form keputusan pada detail pending memakai action approve/reject/request_changes dan rejection_reason sesuai rancangan bagian 4, dengan sesi/CSRF existing. Konfirmasi Setujui, field required, serta batas 1000 khusus Tolak mengikuti backend tanpa menambah kriteria kelayakan di frontend. Backend tetap menentukan hasil keputusan.

UI menampilkan pesan sukses dan status baru, kemudian mengunci/menyembunyikan tindakan. Request berjalan tidak dapat dikirim ulang; 422 membuka form untuk koreksi, sedangkan kegagalan akses/status/sesi dan hasil tidak pasti memerlukan muat ulang. Tidak ada perubahan aturan atau alur Feature 3.1/2.2. Konfirmasi final PM Hasya tetap terbuka.

Validasi tahap ini: 156 PHPUnit PASS, 0 FAIL, 1.414 assertions pada enam file terkait; 17 tes handler JavaScript PASS, 0 FAIL dengan DOM/Fetch sintetis. Ini bukan bukti pengujian visual browser atau transaksi paralel MySQL. Seluruh tes database memakai SQLite :memory: terisolasi tanpa .env repository atau database bersama.
