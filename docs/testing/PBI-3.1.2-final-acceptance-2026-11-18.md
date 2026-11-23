# Pengujian akhir PBI 3.1.2 — 18 November 2026

## Ringkasan dan batas klaim

Task 3.1.2.6 telah menjalankan regresi otomatis dan pengujian HTTP nyata antarrequest
pada server Laravel terisolasi. Tidak tersedia tool browser automation atau modul
Playwright/Puppeteer. Tidak menginstal dependency/browser tambahan.

**Browser visual, klik/interaksi DOM browser, viewport nyata, keyboard/screen reader,
dan screenshot: NOT TESTED.** HTTP terkontrol bukan pengujian visual browser.
Belum boleh menyatakan acceptance browser selesai.

- Feature suites: 212 kasus; 211 PASS, 0 FAIL, 1 SKIPPED; 1716 assertion.
- Regresi CV terarah: 1 PASS; 17 assertion.
- Total: **213 kasus; 212 PASS, 0 FAIL, 1 SKIPPED; 1733 assertion**.
- HTTP terkontrol: **48 pemeriksaan PASS**, terpisah dari angka PHPUnit.
- php -l: 23 file native PHP baru/berubah PASS; git diff --check PASS.
- Symlink: SKIPPED karena OS Windows tidak mengizinkan link sintetis; perlu CI Linux.
- MySQL compatibility/concurrency paralel: NOT TESTED; tidak tersedia koneksi
  database testing terisolasi yang diketahui. Tidak mencoba database utama.

Tidak ditemukan bug aplikasi baru yang terbukti; tidak ada perbaikan runtime pada
task pengujian ini. Satu-satunya file repository yang ditambahkan task ini adalah
laporan ini. Wrapper/fixture/pengujian HTTP dibuat di TEMP.

## Environment dan isolasi

Branch: feature/pbi-3.1.2-monitoring-verifikasi-perusahaan, fondasi commit f3cbf20.
PHP: C:\php84\php.exe, versi 8.4.27.

Root baru, bukan environment browser lama:

```text
C:\Users\devia\AppData\Local\Temp\pbi312-acceptance-a708f1146c264caaa383fb951064bd8c
```

SQLite persisten HTTP: browser.sqlite di root tersebut. Seluruh disk local/public/
company_documents, session SQLite, log, compiled views dan manifest cache diarahkan
ke TEMP. APP_KEY baru dibuat tanpa dicetak. Guard bersama CLI/HTTP memverifikasi
path sebenarnya (termasuk PRAGMA database_list), session, key equality, cache dan
private disk sebelum operasi. Seluruh tujuh migration diterapkan hanya di DB baru,
termasuk company_verification_status_histories. Tidak ada seed/migrate:fresh.

Feature test memakai SQLite :memory: dan storage fake tersendiri.

Server khusus: http://127.0.0.1:8812, PID saat pengujian 11440. Tidak menghentikan
server lama di 8811. Wrapper TEMP menggunakan bootstrap Windows prefix dan
useStoragePath eksplisit; tidak memodifikasi bootstrap/vendor repository.

Metadata database utama (ukuran dan LastWriteTimeUtc) tetap sama sebelum/sesudah.
Tidak membaca isi/hash database utama atau dokumen nyata. Database/browser TEMP
lama tidak menjadi target migration maupun request pengujian ini.

## Fixture sintetis

Awal setelah extension kasus: 24 pengguna, 20 perusahaan, 2 pemeriksaan,
20 dokumen private sintetis, 0 file public pengujian, 1 event.

- Pemerintah aktif A/B, pemerintah nonaktif, pencari kerja.
- Pemilik perusahaan sintetis dengan akun tersendiri.
- 17 pending: 16 belum diperiksa dan satu pending resmi.
- 1 verified resmi, 1 rejected legacy sintetis, 1 unknown sintetis.
- Legacy/unknown sengaja dibuat untuk uji tampilan; bukan bukti sejarah nyata.
- Event awal milik perusahaan 17 dibuat lewat service pemeriksaan yang sah saat
  fixture ini dibuat. Tidak melakukan backfill pada legacy sintetis.
- File pengujian adalah PNG sintetis; tidak menyalin dokumen perusahaan nyata.
- Hash password diverifikasi secara boolean; password/APP_KEY/cookie tidak dicetak.

Setelah uji: 24 pengguna, 20 perusahaan (15 pending, 2 verified, 2 rejected,
1 unknown), 5 pemeriksaan, 3 event, 20 dokumen private. Dua event baru hanya
untuk perusahaan 1/2 yang diputuskan melalui POST form pemeriksaan resmi.

## HTTP yang benar-benar dieksekusi

Script: TEMP/http-acceptance.ps1; bukti boolean kasus pada
TEMP/http-acceptance-results.json. Tidak menyimpan password, cookie, token atau
APP_KEY pada laporan hasil. Cookie jar hanya di memori proses pengujian.

| Kelompok | Eksekusi dan hasil |
| --- | --- |
| Health/isolasi | /up 200, X-PBI-Isolation verified |
| Login pemerintah aktif | GET monitoring -> login; POST login dengan CSRF/session -> monitoring 200 |
| HTML monitoring | menu tampil, respons private/no-store |
| Filter | API pending/verified/rejected hanya menghasilkan status yang diminta |
| Semua/pagination | 20 perusahaan; per_page=1&page=2 menghasilkan ID 2, urutan stabil |
| Search | nama Browser 01 dan NPWP sintetis masing-masing tepat satu hasil |
| Provenance | ID 1 uninspected, 17 official, 18 legacy, 19 unknown, 20 official pending; HTML detail semuanya 200 |
| Legacy kosong | teks batas riwayat tampil; tidak ada event legacy yang dibuat |
| Empty/error | pencarian HTML tanpa hasil; filter invalid JSON 422 |
| Verified | form resmi ID 1 POST -> 302; GET status verified/official; tepat satu event pending->verified |
| Rejected | form resmi ID 2 POST -> 302; GET status rejected; tepat satu event pending->rejected |
| Event | sequence 1, actor Pemerintah Sintetis A, timestamp UTC aktual dan reason aman; cocok dengan pembacaan DB terisolasi |
| Final replay | request ulang hasil final 409; tidak menambah event |
| Pending | form resmi ID 3 pending->pending -> 302, timeline tetap kosong |
| Stale version | submit token lama ID 3 -> HTML 409 dengan instruksi muat ulang |
| Validation | notes whitespace -> 422 |
| CSRF | POST tanpa token -> 419 pada server HTTP nyata |
| Privacy | admin detail menampilkan marker internal; monitoring JSON tidak membawa notes/path; profil pemilik tidak menampilkan marker |
| Role | pemerintah nonaktif, perusahaan, pencari kerja: HTML/API pemerintah 403; tamu HTML redirect, API 401 |
| Markup | table-responsive dan viewport tersedia; bukan bukti responsivitas visual |

Daftar/filter/pagination HTML, DOM accessibility, transaksi/rollback, sequence,
spoofing dan invalid query juga diuji lewat feature suites. Tabel ini tidak
mengklaim dropdown diklik pada browser. UI menggunakan select status, bukan tab.

## Bukti data akhir

Pembacaan hanya SQLite TEMP baru:

| Perusahaan sintetis | Sequence | Transisi | Actor ID | changed_at UTC |
| --- | --- | --- | --- | --- |
| 17 (fixture resmi) | 1 | pending -> verified | 1 | 2026-11-18T01:31:20+00:00 |
| 1 (HTTP form) | 1 | pending -> verified | 1 | 2026-11-18T01:33:52+00:00 |
| 2 (HTTP form) | 1 | pending -> rejected | 1 | 2026-11-18T01:33:55+00:00 |

Tidak ada event ID 3 pending->pending atau ID 18 legacy. Kegagalan transaksi,
sequence independen, stale token dan final overwrite diuji juga dalam in-memory.
Race yang diuji berbasis stale request berurutan; bukan transaksi paralel MySQL.

## Acceptance dan keputusan

| Acceptance criterion | Hasil | Batas |
| --- | --- | --- |
| Daftar menurut status tampil | PASS fungsional | HTML/feature + filter HTTP API; visual browser belum diuji |
| Riwayat tercatat dan akurat | PASS fungsional | POST resmi, GET timeline, DB TEMP, rollback/constraint tests; MySQL paralel belum diuji |
| Browser acceptance | PARTIAL | tidak ada automation atau pemeriksaan visual langsung |

Layak checkpoint commit/push ke branch fitur setelah review/staging eksplisit;
tidak ada operasi Git mutatif dilakukan. Belum siap merge/acceptance deployment:
selesaikan browser desktop/mobile/keyboard, CI Linux symlink, MySQL concurrency,
dan koordinasi dokumen legacy public. Jangan mengklaim seluruh data legacy aman.

## Risiko dan koordinasi

- Dokumen public legacy belum dimigrasikan/ditutup URL deployment-nya; tidak disentuh.
- Catatan legacy yang dahulu terpapar tetap memerlukan remediasi terkoordinasi.
- Status legacy/unknown tidak otomatis diperbaiki atau menjadi pemeriksaan resmi.
- Login pemerintah memakai intended redirect dari URL terlindungi; bukan login baru.
- Aplikasi menampilkan timezone UTC; perlu evaluasi UX tanggal untuk pengguna WIB.
- Menjalankan ulang script HTTP mutation pada fixture yang sama akan bertemu hasil
  final. Jangan reset hasil final; buat environment TEMP baru bila butuh mengulang.
- Jangan menjalankan POST pada environment lama yang belum mempunyai tabel riwayat.

## Seluruh perubahan Git saat review task ini

M = tracked modified; U = untracked. Perubahan fitur sebelumnya tetap ada.

```text
M app/Models/CompanyInspection.php
M app/Models/CompanyProfile.php
M app/Services/CompanyInspectionService.php
M bootstrap/app.php
M resources/views/government/layouts/app.blade.php
M routes/web.php
M tests/Feature/CompanyDocumentSecurityTest.php
M tests/Feature/CompanyInspectionApiTest.php
M tests/Feature/CompanyInspectionStoreTest.php
M tests/Feature/CompanyInspectionWebTest.php
U app/Http/Controllers/Government/CompanyVerificationMonitoringController.php
U app/Http/Controllers/Government/CompanyVerificationMonitoringDataController.php
U app/Http/Requests/Government/ListCompanyVerificationStatusesRequest.php
U app/Http/Requests/Government/ShowCompanyVerificationStatusRequest.php
U app/Http/Requests/Government/UpdateCompanyVerificationStatusRequest.php
U app/Http/Resources/CompanyVerificationStatusHistoryResource.php
U app/Http/Resources/CompanyVerificationStatusResource.php
U app/Models/CompanyVerificationStatusHistory.php
U app/Services/CompanyVerificationMonitoringQuery.php
U database/factories/CompanyVerificationStatusHistoryFactory.php
U database/migrations/2026_11_18_000001_create_company_verification_status_histories_table.php
U docs/design/PBI-3.1.2-status-transition-contract.md
U docs/testing/PBI-3.1.2-final-acceptance-2026-11-18.md
U package-lock.json
U resources/views/government/company-verification-statuses/README.md
U resources/views/government/company-verification-statuses/index.blade.php
U resources/views/government/company-verification-statuses/partials/status.blade.php
U resources/views/government/company-verification-statuses/show.blade.php
U tests/Feature/CompanyVerificationMonitoringApiTest.php
U tests/Feature/CompanyVerificationMonitoringWebTest.php
U tests/Feature/CompanyVerificationStatusHistoryDatabaseTest.php
```

package-lock.json tidak disentuh dan tetap tidak direkomendasikan untuk staging PBI.
