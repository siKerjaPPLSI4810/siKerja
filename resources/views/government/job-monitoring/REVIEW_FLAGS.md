# Penandaan tinjauan lanjutan — Subtask 3.2.2.5

## Persetujuan PM

Pada instruksi implementasi 23 November 2026, pengguna menyampaikan persetujuan PM Amirza Hasya atas penandaan manual, alasan wajib di-trim maksimal 1.000 karakter, penyimpanan terpisah, larangan menimpa tanda aktif, pencatatan petugas/waktu pencabutan, satu siklus terakhir, dan persistensi tanda saat status lowongan berubah/diterbitkan ulang.

Persetujuan ini **tidak menetapkan definisi final lowongan aktif, filter multi-status, atau persetujuan final aturan approval PBI 3.2.1**. Monitoring GET tetap published termasuk deadline lewat/null. Tidak ada penandaan otomatis.

## Data dan relasi

Migration 2026_11_23_000001_create_job_posting_review_flags_table membuat id, job_posting_id unik/FK, reason, flagged_by/FK, flagged_at, cleared_by/FK nullable, cleared_at nullable. Model JobPostingReviewFlag tanpa timestamps tambahan; aktif iff cleared_at IS NULL. JobPosting memiliki hasOne reviewFlag; flag belongsTo jobPosting, flagger dan clearer.

Unik job_posting_id membatasi satu row per lowongan. Penandaan ulang setelah pencabutan memperbarui row yang sama: alasan/petugas/waktu penandaan baru dan metadata pencabutan dikosongkan. Tidak menyimpan seluruh riwayat siklus. FK lowongan cascade saat record lowongan dihapus; FK petugas restrict untuk mempertahankan referensi identitas yang sudah tercatat. Tidak ada perubahan status/metadata lowongan atau field flag pada fillable lowongan.

Migration hanya dijalankan oleh fixture tes setelah memastikan SQLite :memory:. Tidak menjalankan artisan migrate/seeder, membaca .env repository, atau mengakses database bersama. Lingkungan aplikasi/QA browser perlu menyediakan migration baru pada database QA terisolasi sebelum pengujian browser; database QA file lama tidak dimigrasikan pada tahap ini.

## Endpoint dan aturan

- POST /pemerintah/api/monitoring-lowongan/{jobPosting}/penandaan, government.job-monitoring.api.flags.store. Input reason; hanya lowongan published. Status lain 409, ID tidak ditemukan/nonnumerik 404.
- DELETE pada URL sama, government.job-monitoring.api.flags.destroy. Pencabutan manual oleh pemerintah aktif, termasuk jika tanda tersimpan pada lowongan yang kemudian berganti status. Ini tidak memperluas GET monitoring ke status lain. Tanpa tanda/tanda sudah dicabut: 200 active=false dan tidak menulis data atau membuat row.
- Middleware web/auth/can:access-government, sesi dan CSRF existing; gate juga ditegakkan service agar pemanggilan langsung tetap aman. Validasi payload dilakukan terpusat di JobPostingReviewFlagService, bukan mempercayai request->all untuk mass assignment.
- Alasan kosong/whitespace/non-string/>1000: 422. Identitas/waktu petugas, job_posting_id dan atribut keputusan tidak dapat diisi klien. Identitas berasal dari sesi dan waktu server.
- Tanda aktif yang ditandai lagi: 409 tanpa menimpa alasan atau petugas. Penandaan dan pencabutan memakai transaksi, mengunci parent JobPosting terlebih dahulu lalu row flag; constraint unik melengkapi perlindungan saat row flag belum ada.
- JSON sukses hanya message dan data job_posting_id/active, dengan no-store, private. Error JSON mengikuti pola pemerintah existing, termasuk tanpa Accept. Tidak menambahkan catatan/identitas flag pada resource daftar API.
- status, approved_by, rejection_reason dan updated_at lowongan tidak ditulis. Approval, penutupan dan pengajuan ulang tidak otomatis menghapus tanda. Setelah published kembali, tanda lama tetap terlihat sampai pencabutan manual.

## UI dan privasi

Badge Perlu Ditinjau di daftar hanya tampil untuk row tanda aktif sebenarnya. Query daftar eager load id/job_posting_id/cleared_at flag; tidak membutuhkan alasan/petugas untuk setiap baris. Detail pemerintah eager load metadata serta nama petugas.

Detail tanpa tanda aktif menyediakan form alasan required/maxlength 1000. Detail aktif menampilkan alasan, petugas, waktu dan Hapus Tanda dengan konfirmasi native window.confirm. Form Laravel POST/CSRF dan method spoof DELETE memakai marker _flag_form=1 serta Accept HTML untuk redirect kembali ke detail dengan flash sukses/error. Klien API biasa tetap JSON; Accept JSON selalu mendapat JSON. Marker hanya memilih bentuk respons dan tidak memberi otorisasi.

Setelah sukses, redirect/GET menampilkan data tersimpan terbaru tanpa mengubah published. Refresh tidak mengirim ulang POST. Permintaan ganda tetap dilindungi service/constraint. Tidak memakai JavaScript Fetch atau membuat endpoint UI mutasi duplikat.

Semua nilai dinamis di-escape. ReviewFlag disembunyikan dari serialisasi generik JobPosting; atribut catatan/petugas flag juga hidden pada model flag. Tidak mengubah endpoint publik/perusahaan atau approval PBI 3.2.1. Catatan tidak tampil di daftar API monitoring/publik/perusahaan.

## Batas verifikasi

Tes SQLite menguji request berurutan, constraint unik dan rollback; **tidak membuktikan konkurensi paralel/row lock MySQL**. Tidak ada token versi per siklus: tindakan pencabutan bekerja pada tanda aktif terkini saat transaksi. Tidak menambah mekanisme versi/riwayat di luar satu siklus terakhir yang disetujui.

Konfirmasi Hapus Tanda dirender dan diuji sebagai atribut form, tetapi interaksi dialog browser OK/Cancel belum diuji manual pada tahap ini. Tidak mengklaim QA browser, pengujian MySQL atau suite aplikasi penuh sudah selesai.

## Hasil pengujian — 23 November 2026

- GovernmentJobMonitoringFlagTest: 31 kasus baru. Gabungan dengan tes filter/HTML/API monitoring dan enam file regresi PBI 3.2.1/validator perusahaan: **267 PASS, 0 FAIL, 2.641 assertions**, exit 0.
- Mencakup otorisasi HTTP dan service langsung, alasan wajib/trim/batas, metadata spoofing, mark/409, clear/no-op, penggunaan ulang satu record, petugas/waktu, persistensi/reload, perubahan status dan publikasi ulang, HTML escaping, privasi publik/perusahaan/API, atribut lowongan tetap utuh, CSRF nyata, constraint unik dan rollback gagal UPDATE.
- Run awal menemukan pemilihan bentuk respons yang salah: permintaan API tanpa Accept mendapat redirect. Controller/form diperbaiki dengan marker form eksplisit; run akhir tanpa kegagalan. Tidak mengubah aturan bisnis untuk mengatasi kegagalan tersebut.
- PHP lint sembilan file PHP terkait dan git diff --check lulus. Semua migration tes berjalan hanya pada SQLite :memory: setelah guard isolasi fixture. Tidak menjalankan migration pada SQLite QA file lama, MySQL Cloud atau database bersama.
- Pemeriksaan browser dialog Hapus Tanda dan konkurensi paralel MySQL belum dilakukan. Tidak menjalankan seluruh test suite aplikasi.


## Pembaruan QA manual pengguna

Pengguna mengonfirmasi mobile sesuai, Hapus Tanda Cancel tidak mengirim DELETE dan mempertahankan tanda, serta OK berhasil mencabut tanda. Ini konfirmasi pengguna, bukan eksekusi ulang dialog oleh agen. Lihat QA_REVIEW.md untuk atribusi bukti dan ketidaksesuaian snapshot helper fixture 16 yang tersedia. Konkurensi paralel MySQL tetap belum diuji; definisi aktif final dan multi-status belum disetujui. Server QA sesi telah dihentikan.
