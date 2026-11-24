# Reset metadata pengajuan ulang — review Feature 2.2 untuk Rifqi

Tanggal: 24 November 2026. Branch Hasya/Sprint1, HEAD awal 9441f2ba85044ef9ed7f4daeebc28bcb22341f67. Perubahan lokal Tahap 2 dibuat atas instruksi pengguna setelah pemilihan aturan Feature 3.2 diserahkan PM kepada tim. Dokumen ini untuk review Rifqi, bukan klaim bahwa Rifqi telah memberikan review atau persetujuan merge.

## Perubahan minimal

CompanyJobController hanya mendapat dua penambahan approved_by = null. rejection_reason = null sudah tersedia pada cabang pengajuan ulang. Kedua metadata lowongan kini dibersihkan dalam operasi update yang sama dengan status pending_approval.

| Jalur existing | Perilaku metadata |
| --- | --- |
| update(): edit published | Tetap mengajukan ulang menjadi pending_approval; approved_by dan rejection_reason null |
| update(): resubmit=true pada lowongan perusahaan sendiri | Tetap mengikuti cabang pengajuan ulang existing; kedua metadata null |
| update(): edit biasa tanpa resubmit pada draft/pending_approval/rejected/closed | Status dan kedua metadata dipertahankan |
| toggleStatus(): published ke closed | Hanya menutup; approved_by dan rejection_reason dipertahankan |
| toggleStatus(): cabang non-published ke pending_approval | Kedua metadata null; tidak mengubah pemilihan cabang existing |
| update(): validasi gagal | Tidak mengubah data lowongan maupun perusahaan |

Kondisi cabang dan aturan validator tidak diubah. Toggle existing memperlakukan semua status non-published sebagai pengajuan, termasuk pemanggilan ulang ketika sudah pending_approval; perilaku tersebut tidak ditambahkan oleh pekerjaan ini. Tidak menambah validasi deadline pada toggle, status baru, route, migration, atau backfill data lama.

## Batas kepemilikan dan integrasi

- Perubahan produksi hanya app/Http/Controllers/Company/CompanyJobController.php; scope kepemilikan company_profile_id tetap dipertahankan.
- Tidak menulis verification_status, verification_notes, metadata verifikasi atau atribut CompanyProfile lainnya. Tes membandingkan snapshot seluruh record perusahaan sebelum/sesudah setiap operasi terkait.
- Tidak mengubah aturan approval/rejection PBI 3.2.1, service penandaan, tabel penandaan atau endpoint Fadlan. Reset hanya menyangkut approved_by dan rejection_reason pada JobPosting; bukan verifikasi perusahaan atau penghapusan tanda.
- Seluruh 11 file perubahan monitoring Tahap 1 dipertahankan identik berdasarkan hash SHA-256 sebelum/sesudah Tahap 2. README/QA Tahap 1 tetap menjadi bukti versi sebelumnya; hasil Tahap 2 dicatat terpisah di dokumen ini.
- Tidak melakukan commit, push, operasi PR atau merge. PR #7 tidak dimodifikasi; tidak mengirim pesan kepada Rifqi secara otomatis.

## Cakupan regresi

CompanyJobSubmissionRegressionTest diperbarui untuk published edit serta resubmit rejected/draft/closed, preservasi metadata saat menutup, pengajuan kembali melalui toggle, edit biasa empat status, dan validasi pengajuan ulang yang gagal. Pembatasan kepemilikan serta validasi Feature 2.2 tetap diuji. Assertion lama yang mempertahankan approved_by saat resubmit diganti mengikuti aturan reset terbaru.

PHPUnit memakai SQLite :memory: terisolasi, environment kosong dan cache/storage TEMP. Suite penuh memakai bootstrap TEMP terpisah seperti Tahap 1; tidak mengubah tests/TestCase.php atau tes Fadlan dan tidak membaca .env/MySQL Cloud. Seluruh tes JavaScript serta lint/whitespace juga diperiksa. Hasil akhir eksekusi dicatat di bawah.

## Tindak lanjut review Rifqi

Periksa dua tambahan assignment di controller, kesesuaian seluruh jalur pengajuan ulang, dan assertion preservasi pada edit biasa/penutupan. Tidak ada perubahan aturan pengajuan, validator atau metadata perusahaan yang diminta pada Tahap 2. QA browser baru dan konkurensi paralel MySQL tidak diklaim sudah selesai.

## Hasil pengujian Tahap 2

| Pemeriksaan | Hasil |
| --- | --- |
| Regresi Feature 3.2 dan pengajuan perusahaan | **287 PASS, 0 FAIL; 2.907 assertions**, exit 0 |
| Seluruh PHPUnit, 530 tes | **529 PASS, 0 FAIL, 1 SKIPPED; 4.795 assertions**, exit 0 |
| Seluruh JavaScript | **17 PASS, 0 FAIL**, exit 0 |
| PHP lint controller dan tes perusahaan | PASS |
| Sintaks JavaScript dan git diff --check | PASS |

Satu SKIPPED adalah tes symlink sintetis keamanan dokumen pada Windows, sama seperti Tahap 1. Tidak mengklaim pengujian Linux/symlink atau konkurensi paralel MySQL berhasil. Tidak ada kegagalan tes/regresi Tahap 2. Suite penuh mencakup regresi Fadlan tanpa mengubah source atau tesnya.

Hash SHA-256 seluruh 11 file Tahap 1 diverifikasi tetap identik setelah Tahap 2. Bootstrap, environment dan data pengujian hanya berada di TEMP/SQLite :memory:, tidak ditambahkan ke repository. Perubahan Tahap 2 hanya controller perusahaan (dua baris), tes regresinya, dan dokumen review ini; seluruh perubahan tetap lokal dan belum di-commit.

## Finalisasi review sebelum merge — 24 November 2026

Review Rifqi atas dua tambahan assignment controller dan tes regresi **masih diperlukan sebelum merge**. Tidak menganggap instruksi implementasi sebagai bukti Rifqi sudah mereview. Aturan pemilihan cabang, validator dan metadata verifikasi perusahaan tidak diubah.

QA browser agen serta snapshot SQLite mengonfirmasi pengajuan ulang/reset metadata, edit biasa dan penutupan/preservasi metadata; seluruh record perusahaan identik. Pengguna mengonfirmasi desktop, mobile 375px, detail Closed dan Hapus Tanda Cancel/OK PASS. Bukti manual pengguna dipisahkan dari hasil otomatis pada ../job-monitoring/QA_REVIEW.md. Konkurensi paralel MySQL belum diuji.
