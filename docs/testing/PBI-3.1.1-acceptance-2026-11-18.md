# Laporan Task 3.1.1.6 — 18 November 2026 (Asia/Jakarta)

> Laporan historis sebelum perbaikan catatan internal. FAIL di bawah sudah ditutup
> oleh pengujian lanjutan; lihat [laporan perbaikan dan remediasi](../security/PBI-3.1.1-internal-notes-remediation.md).
> Angka dan reproduksi awal dipertahankan sebagai bukti audit, bukan hasil terbaru.

## Keputusan

Belum siap merge. Layak sebagai draft PR untuk review/koordinasi dengan satu test
penerimaan gagal yang sengaja dipertahankan. Commit lokal checkpoint dapat dilakukan
oleh pengguna dengan catatan hasil ini, tetapi bukan tanda acceptance selesai.
Tidak ada commit/push/merge yang dilakukan oleh agent.

Branch: `feature/pbi-3.1.1-memeriksa-legalitas-dan-status-administrasi-perusahaan`.

## Eksekusi dan isolasi

- PHP: `C:\php84\php.exe`, PHP 8.4.27.
- Suite utama: 137 kasus; 135 PASS, 1 FAIL, 1 SKIPPED; 953 assertion.
- Regresi CV terarah: 1 kasus PASS; 17 assertion.
- Total eksekusi final (tanpa menghitung ulang percobaan diagnosis):
  **138 kasus; 136 PASS, 1 FAIL, 1 SKIPPED; 970 assertion**.
- SQLite `:memory:` dengan FK aktif pada suite khusus. Regresi perusahaan/CV memakai
  environment proses `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`,
  `DB_URL=`. Tidak ada cached config saat mulai pemeriksaan.
- Storage pengujian menggunakan Storage::fake dan dokumen sintetis. Tidak membuka,
  memindahkan, menghapus, menyalin, atau melakukan checksum dokumen nyata.
- `php -l`: 28 file native PHP berubah/untracked, seluruhnya PASS.
- `php artisan view:cache`: PASS dengan PHP 8.4; hanya generated view cache diperbarui.
- `git diff --check`: PASS.
- MySQL compatibility/concurrency: **NOT TESTED**. Tidak ditemukan koneksi khusus
  testing yang eksplisit; tidak mencoba koneksi/default database utama.
- Symlink keluar root: **SKIPPED** karena OS tidak mengizinkan symlink sintetis.
  Jalankan test yang sama pada CI Linux tanpa mengubah privilege Windows.
- Visual browser/mobile dan screen reader: **NOT TESTED**. Test DOM memeriksa label,
  target aria-describedby, landmark main, heading, required fields, caption/scope
  tabel, viewport dan kelas responsif. Ini bukan bukti tampilan visual browser.

Perintah suite utama:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
& C:/php84/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/CompanyDocumentSecurityTest.php tests/Feature/CompanySprint1Test.php tests/Feature/CompanyInspectionApiTest.php tests/Feature/CompanyInspectionDatabaseTest.php tests/Feature/CompanyInspectionStoreTest.php tests/Feature/CompanyInspectionWebTest.php --do-not-cache-result
& C:/php84/php.exe vendor/phpunit/phpunit/phpunit tests/Feature/JobseekerSprint1Test.php --filter automatic_cv --do-not-cache-result
```

## Temuan P1: hasil catatan pemerintah dapat dibaca pemilik perusahaan

Test gagal:
`CompanyInspectionStoreTest::test_company_cannot_read_government_notes_under_admin_only_acceptance_requirement`.

Reproduksi sintetis:

1. Pemerintah aktif POST hasil verified (OSS matched, administrasi complete) dengan
   notes `SYNTHETIC_GOVERNMENT_INTERNAL_FINDINGS_3116`; respons 200.
2. Login sebagai pemilik perusahaan itu, GET `/perusahaan/profil`; respons 200.
3. Marker notes muncul dalam HTML. assertDontSee gagal.

Penyebab:

- `app/Services/CompanyInspectionService.php:62` menyalin notes ke verification_notes.
- `resources/views/company/profile/index.blade.php:47` (juga 50 dan 53) menampilkan
  verification_notes kepada pemilik.
- Route pemerintah sendiri tidak bypass; kebocoran terjadi lewat salinan data di
  view perusahaan. Tidak ditemukan bukti akses catatan perusahaan lain dari jalur ini.

Temuan dinilai terhadap tujuan task sekarang: hanya Admin Pemerintah aktif dapat
mengakses hasil pemeriksaan. Rencana sebelumnya memang menyalin catatan sebagai
ringkasan status; perlu keputusan produk/tim apakah catatan penuh bersifat internal
atau sengaja dibagikan. Sampai ada keputusan, tidak menganggap akses ini PASS.

Usulan perbaikan sebelum merge: tetap simpan notes penuh di company_inspections,
gunakan pesan status netral/terkurasi untuk verification_notes, dan koordinasikan
tampilan perusahaan. Data lama perlu audit terpisah; jangan menghapus catatan atau
mengubah schema secara otomatis. Tidak ada perbaikan fitur yang dilakukan pada task ini.

## Audit route dan Gate

Enam route diperiksa lewat `artisan route:list --path=pemerintah -v`:

| Route | Method | Middleware |
| --- | --- | --- |
| government.company-inspections.index | GET | web, auth, can:access-government |
| government.company-inspections.show | GET | web, auth, can:access-government |
| government.company-inspections.document | GET | web, auth, can:access-government |
| government.company-inspections.store | POST | web, auth, can:access-government |
| government.company-inspections.api.index | GET | web, auth, can:access-government |
| government.company-inspections.api.show | GET | web, auth, can:access-government |

Gate di AppServiceProvider: role pemerintah DAN is_active. POST juga memiliki
FormRequest authorize dan service Gate. Semua akun pemerintah aktif memiliki ruang
akses sama; tidak ada asumsi pembatasan wilayah yang belum menjadi requirement.

## Matriks akses

| Aktor | HTML pemerintah | API | POST JSON | Unduh pemerintah | Unduh pemilik |
| --- | --- | --- | --- | --- | --- |
| Tamu | redirect login | 401 | 401 | redirect login | redirect login perusahaan |
| Pemerintah aktif | 200 | 200 | 200/302 sesuai negosiasi | 200 untuk dokumen valid | 403 |
| Pemerintah nonaktif | 403 | 403 | 403 | 403 | 403 |
| Perusahaan | 403 | 403 | 403 | 403 | hanya profil sendiri |
| Pencari kerja | 403 | 403 | 403 | 403 | 403 |

Referensi profil ganda untuk berkas yang sama ditolak resolver; input companyProfile
atau path pada route pemilik tidak memilih dokumen perusahaan lain. Untuk catatan
hasil melalui profil pemilik, lihat FAIL P1 di atas. CSRF aktif diuji dengan bypass
test framework dihilangkan hanya pada test: token hilang 419, token valid tersimpan.

## Matriks keputusan dan persistensi

27 kombinasi OSS x administrasi x keputusan diuji seluruhnya PASS:

| Keputusan | Syarat | Hasil |
| --- | --- | --- |
| pending | semua kombinasi hasil | diterima dengan catatan valid |
| verified | hanya matched + complete | diterima; kombinasi lain 422 |
| rejected | kedua hasil sudah pasti (bukan unconfirmed) | diterima |
| verified/rejected | salah satu hasil unconfirmed | 422 |

Untuk setiap kombinasi diterima, test membaca ulang DB dan memeriksa hasil OSS,
administrasi, decision, reviewed_by dari Auth, checked_on, trim notes, snapshot
identitas, verification_status, verification_notes, dan verified_by.

Test lain membuktikan: unique perusahaan, FK/nullability/enum, date/JSON cast,
factory states, perubahan pending ke rejected tetap satu record, rollback saat
update profil gagal, token silang perusahaan ditolak, token stale ditolak, dan
overwrite final oleh admin lain ditolak 409 tanpa mengubah pemeriksa awal.

Race yang diuji adalah dua request berurutan dengan versi lama. SQLite tidak
membuktikan row lock MySQL, deadlock/retry atau transaksi paralel. Uji dua koneksi
MySQL testing tetap diperlukan; tidak dinyatakan PASS.

## Audit seluruh penulisan status

| Penulis | Perilaku |
| --- | --- |
| CompanyInspectionService | keputusan resmi, transaksi, reviewer dari server |
| CompanyAuthController | pendaftaran selalu pending |
| CompanyProfileController | profil baru pending; verifyIdentity tidak menulis status |
| CompanyJobController | profil baru pending |
| CompanyVerificationService | requestedScenario dinetralkan sebelum penentuan hasil |
| CompanyJobSprint1Seeder:38 | contoh data verified TANPA pemeriksaan; risiko provenance |

Tidak ditemukan endpoint simulasi perusahaan yang dapat menulis verified resmi.
Seeder lama bukan endpoint dan tidak dijalankan, tetapi tidak boleh dipakai sebagai
bukti verifikasi resmi atau dijalankan sembarang pada produksi. Model fillable
bukan lapisan otorisasi; code path backend baru tetap harus memakai service.

## Dokumen dan UI

- Upload registrasi/edit baru hanya disk company_documents, serve=false, root
  terpisah dari local/public. Validasi MIME/ukuran dan konfirmasi tulis diuji.
- Resolver membatasi pola path, ekstensi, realpath, keberadaan, dan referensi silang.
- Dokumen public-only ditolak tanpa fallback. Path storage/snapshot tidak dikirim
  API atau dirender sebagai data dokumen pada halaman pemerintah.
- Kegagalan storage, partial write, DB insert/update, serta larangan penggantian
  final diuji; kompensasi hanya berkas baru, dokumen lama/snapshot dipertahankan.
- Pencarian nama/NPWP, literal wildcard, SQL binding, batas query/page, urutan stabil,
  data kosong, status pending diperiksa, PRG, old input/error, final disabled dan
  halaman konflik 409 diuji.
- Blocker legacy: file public lama belum dimigrasikan atau ditutup URL-nya.
  Tidak ada klaim seluruh dokumen sudah rahasia. Audit server/CDN, backup/checksum,
  migrasi terkontrol, dan invalidasi cache perlu koordinasi/deployment terpisah.

## Acceptance dan traceability

Definisi formal dokumen UC-11/KF-11 tidak tersedia di repository yang ditemukan.
Pemetaan berikut bersifat sementara berdasarkan user story/PBI yang diberikan,
bukan klaim kesesuaian penuh dokumen requirement yang belum diberikan.

| Kebutuhan / task | Status | Bukti / batas |
| --- | --- | --- |
| Data perusahaan dan pemeriksaan manual tersimpan | PASS | matriks persistensi dan rollback |
| Hasil hanya diakses pemerintah aktif | BLOCKED | test P1 catatan melalui profil pemilik gagal |
| Kerahasiaan semua dokumen | BLOCKED | legacy public belum ditutup; upload baru PASS |
| UC-11 — alur pemeriksaan (pemetaan sementara) | PARTIAL | alur nominal PASS, boundary catatan/legacy belum selesai |
| KF-11 — fungsi pemeriksaan (pemetaan sementara) | PARTIAL | persistensi PASS, pembatasan catatan belum lulus |
| 3.1.1.1 UI/UX | PASS | render/markup/state otomatis; visual browser NOT TESTED |
| 3.1.1.2 Database | PASS | SQLite constraint/relasi/cast; MySQL NOT TESTED |
| 3.1.1.3 API baca | PASS | auth, query, pagination, allowlist respons |
| 3.1.1.4 Pemeriksaan manual | PARTIAL | keputusan/transaksi PASS; salinan notes bocor ke pemilik |
| 3.1.1.5 Integrasi tampilan | PARTIAL | PRG/409/dokumen baru PASS; blocker di atas |
| 3.1.1.6 Pengujian penerimaan | PARTIAL | eksekusi selesai, satu FAIL dan verifikasi tersisa |

## Perubahan pada task audit ini

- tests/Feature/CompanyInspectionStoreTest.php: matriks 27 kombinasi, persistensi
  lengkap, regresi pembatasan catatan (gagal), token perusahaan lain.
- tests/Feature/CompanyInspectionWebTest.php: pemeriksaan DOM aksesibilitas/markup.
- docs/testing/PBI-3.1.1-acceptance-2026-11-18.md: laporan ini.
- Generated view cache berubah melalui perintah yang diminta. Tidak ada source fitur
  diperbaiki, schema/dependency/env/server diubah, atau dokumen nyata disentuh.

## Seluruh perubahan Git pada workspace (termasuk pekerjaan sebelumnya)

Modified tracked:

```text
app/Http/Controllers/Company/CompanyAuthController.php
app/Http/Controllers/Company/CompanyProfileController.php
app/Models/CompanyProfile.php
app/Models/User.php
app/Providers/AppServiceProvider.php
app/Services/CompanyVerificationService.php
bootstrap/app.php
config/filesystems.php
routes/web.php
tests/Feature/CompanySprint1Test.php
```

Untracked relevan PBI:

```text
app/Http/Controllers/Government/CompanyInspectionController.php
app/Http/Controllers/Government/CompanyInspectionDataController.php
app/Http/Requests/Government/ListCompanyInspectionsRequest.php
app/Http/Requests/Government/StoreCompanyInspectionRequest.php
app/Http/Resources/CompanyInspectionResource.php
app/Models/CompanyInspection.php
app/Services/CompanyDocumentUpload.php
app/Services/CompanyInspectionDocument.php
app/Services/CompanyInspectionQuery.php
app/Services/CompanyInspectionService.php
config/company_inspections.php
database/factories/CompanyInspectionFactory.php
database/migrations/2026_11_17_000001_create_company_inspections_table.php
docs/testing/PBI-3.1.1-acceptance-2026-11-18.md
resources/views/government/README.md
resources/views/government/company-inspections/index.blade.php
resources/views/government/company-inspections/partials/company-information.blade.php
resources/views/government/company-inspections/partials/inspection-form.blade.php
resources/views/government/company-inspections/partials/inspection-result.blade.php
resources/views/government/company-inspections/partials/legal-document.blade.php
resources/views/government/company-inspections/partials/status.blade.php
resources/views/government/company-inspections/show.blade.php
resources/views/government/layouts/app.blade.php
tests/Feature/CompanyDocumentSecurityTest.php
tests/Feature/CompanyInspectionApiTest.php
tests/Feature/CompanyInspectionDatabaseTest.php
tests/Feature/CompanyInspectionStoreTest.php
tests/Feature/CompanyInspectionWebTest.php
```

Untracked lainnya: `package-lock.json` sudah ada sebelum audit ini; tidak disentuh,
tidak diklaim sebagai bagian PBI dan perlu diperiksa pemilik sebelum staging.
