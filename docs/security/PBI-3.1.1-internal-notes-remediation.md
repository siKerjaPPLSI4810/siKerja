# Perbaikan kerahasiaan catatan PBI 3.1.1 — 18 November 2026

## Kebijakan dan akar masalah

Catatan detail pemerintah bersifat admin-only. Perusahaan dapat melihat status
dan pesan umum, bukan notes mentah. Kebijakan ini menggantikan proposal awal yang
menyalin notes sebagai ringkasan profil. Prinsip least privilege diterapkan tanpa
mengubah schema, route, login, dokumen, atau data nyata.

Sumber kebocoran aktif adalah CompanyInspectionService menyalin input notes ke
CompanyProfile.verification_notes, lalu Blade company/profile/index menampilkan
kolom itu. Menutup penulisan saja tidak cukup karena data lama bisa terkontaminasi.
Tidak ditemukan jalur notification/mail pemeriksaan pada source aplikasi saat audit.
API pemerintah sudah memakai allowlist dan tidak mengirim notes/snapshot.

## Inventaris baca/tulis

| Lokasi | Fungsi dan keputusan |
| --- | --- |
| StoreCompanyInspectionRequest | trim/validasi notes; akses pemerintah aktif |
| CompanyInspectionService::record | notes lengkap ke company_inspections; pesan status umum ke profil |
| CompanyInspectionService::dataVersion | getAttributes termasuk notes untuk HMAC; tidak mengirim teks mentah |
| CompanyInspectionController::detailData | mapping notes eksplisit ke Blade pemerintah di balik auth/Gate |
| government/.../inspection-result.blade.php | render notes dan catatan legacy hanya di halaman pemerintah |
| company/profile/index.blade.php | dulu membaca verification_notes; kini hanya accessor pesan aman |
| CompanyProfile | notes legacy dan relasi pemeriksaan/verifier disembunyikan dari serialisasi default |
| CompanyInspection | notes, snapshot, reviewed_by dan reviewer disembunyikan dari serialisasi default |
| CompanyInspectionResource | allowlist, tidak mengirim notes, verification_notes atau snapshot |
| CompanyAuthController | menulis pesan validasi registrasi; bukan notes pemerintah |
| CompanyProfileController | pesan awal profil pending; verifyIdentity tidak menulis hasil resmi |
| CompanyJobController | pesan awal profil pending |
| CompanyJobSprint1Seeder | pesan contoh verified lama; tidak diubah/dijalankan, bukan bukti OSS |
| CompanyInspectionFactory | notes sintetis pengujian, tidak dipakai sebagai hasil resmi |
| Feature tests | marker notes, fixture legacy, persistensi, rollback, dan batas akses |

Tidak ditemukan reader mentah verification_notes pada Blade perusahaan lainnya.
Raw attribute tetap tersedia bagi kode internal yang berwenang: $hidden adalah
pertahanan serialisasi, bukan pengganti Gate/Policy atau larangan akses database.
Penambahan resource/view baru tetap wajib memetakan atribut sesuai kewenangan.

## Perilaku sesudah perbaikan

| Status | Pesan yang boleh dilihat perusahaan |
| --- | --- |
| pending | Status perusahaan menunggu pemeriksaan atau tindak lanjut. |
| verified | Status perusahaan terverifikasi. |
| rejected | Status verifikasi perusahaan ditolak. |
| tidak dikenal | Status verifikasi perusahaan belum tersedia. |

Pesan dihitung hanya dari verification_status; tidak ada fallback ke notes lama.
Status, verified_by, reviewed_by, tanggal pemeriksaan, dan transaksi tetap bekerja.
Notes lengkap tetap disimpan dan ditampilkan kepada pemerintah aktif melalui detail
HTML. API tidak menambah akses catatan. Serialisasi umum tidak mengekspos catatan
atau snapshot, termasuk jika relasi inspection/reviewer dimuat.

## Pengujian final

- PHP C:\php84\php.exe (8.4.27); SQLite in-memory dan storage sintetis terisolasi.
- Suite PBI 3.1.1 + keamanan dokumen + perusahaan: 150 kasus,
  149 PASS, 0 FAIL, 1 SKIPPED, 1111 assertion.
- Regresi CV: 1 PASS, 17 assertion.
- Total: **151 kasus; 150 PASS, 0 FAIL, 1 SKIPPED; 1128 assertion**.
- php -l pada empat file native PHP yang diubah: PASS. Blade view:cache dengan
  PHP 8.4: PASS. git diff --check: PASS.
- Test penerimaan yang sebelumnya gagal tetap memakai assertDontSee marker dan
  sekarang PASS; diperkuat dengan assert notes tetap di DB dan terbaca admin.
- Empat status legacy diuji: marker tidak tampil, nilai lama DB tidak dibersihkan.
- Pending/verified/rejected diuji: admin melihat teks lengkap, pemilik hanya pesan
  umum; API antrean/detail tidak mengirim catatan; role lain mendapat 401/403.
- Matriks 27 keputusan mempertahankan assertion notes lengkap dan menambah pesan
  profil aman/tidak sama dengan notes. Rollback memeriksa seluruh raw attributes
  profil sebelum/sesudah dari pembacaan DB yang setara.
- Serialisasi array/JSON model dan relasi diperiksa tidak membawa marker.
- SKIPPED: symlink sintetis tidak diizinkan OS Windows; perlu CI Linux.
- MySQL concurrency dan visual browser tidak diuji pada perbaikan ini.

## Remediasi data lama — rencana saja, tidak dijalankan

1. Deploy perbaikan reader/writer terlebih dahulu agar nilai lama tidak lagi tampil.
2. Inventaris reader/export/log/cache di deployment bersama pemilik modul; tentukan
   periode paparan dan pihak yang sudah dapat melihat catatan. Perbaikan tidak
   membatalkan paparan historis atau salinan yang sudah diunduh.
3. Siapkan backup database/manifest terbatas berisi ID, versi, status, dan nilai
   verification_notes lama. Jangan mempublikasikan isinya dalam laporan umum.
4. Pada pekerjaan terpisah yang disetujui, klasifikasikan seluruh notes bebas di
   profil sebagai tidak tepercaya. Kecocokan dengan notes pemeriksaan saat ini
   hanya indikator, bukan bukti tidak adanya kontaminasi bila tidak cocok.
5. Gunakan transaksi/lock dan pembandingan nilai/versi lama saat mengganti
   verification_notes dengan pesan umum sesuai status terkini. Jangan mengubah
   status, verifier, CompanyInspection.notes, snapshot, atau dokumen perusahaan.
6. Verifikasi jumlah record, uji semua role dan ekspor; invalidasi cache respons
   lama secara terkoordinasi. Jangan menganggap mengganti kolom membersihkan log,
   backup, flash session atau salinan eksternal secara otomatis.
7. Rollback data hanya melalui manifest dan pemeriksaan konflik, dengan reader aman
   tetap aktif. Jangan mengembalikan writer/Blade rentan atau menghapus notes asli.

## Dampak tim dan blocker tersisa

Modul perusahaan kehilangan tampilan catatan bebas, tetapi status, identitas,
pendaftaran/edit, serta unduhan tidak dihapus. Consumer yang bergantung pada
toArray/toJson verification_notes, inspection atau reviewer perlu beralih ke DTO
aman; akses catatan penuh hanya lewat jalur pemerintah terotorisasi yang eksplisit.
Seluruh kontrak aktif yang ditemukan sudah diuji regresinya.

FAIL catatan internal tertutup. Dokumen legacy public tetap belum dimigrasikan dan
URL publik belum dijamin tertutup. Blocker penerimaan deployment itu tidak berubah.
Koordinasi remediasi data lama, CI Linux symlink, MySQL testing/concurrency dan
pemeriksaan visual tetap diperlukan sebelum menyatakan semua risiko selesai.
