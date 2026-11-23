# QA PBI 3.2.2 — Subtask 3.2.2.6

Tanggal: 23 November 2026. Branch Hasya/Sprint1, checkpoint e931b02e96196e4750a0329dcd4d715449d5e20f.

**Hasil terbaru: regresi otomatis/HTTP lulus. QA mobile serta Hapus Tanda Cancel/OK dikonfirmasi selesai oleh pengguna.** Hasil manual tersebut berasal dari laporan pengguna, bukan pengamatan ulang agen atau kesimpulan helper SQLite. Konkurensi paralel MySQL belum diuji.

## Isolasi lingkungan

- PHPUnit memakai GovernmentJobInspectionTestCase: hanya SQLite :memory:, environment kosong, konfigurasi/cache/storage TEMP. Tiga migration existing/penandaan dijalankan hanya setelah guard isolasi SQLite.
- Aplikasi QA baru terikat 127.0.0.1:8874 dengan bootstrap TEMP, environment kosong, konfigurasi/database/session/storage/cache/key khusus. Hanya satu koneksi SQLite file QA diizinkan dan path diperiksa; APP_ENV=local, APP_DEBUG=false, runningUnitTests=false sehingga CSRF aktif.
- Lokasi QA: C:/Users/ACER/AppData/Local/Temp/hasya-monitoring-qa-f94ba510f0464ec69a057e49d4ac99f5. Berisi data sintetis baru: 18 published dan empat lowongan status lain. Deadline fixture mencakup lewat/null. Fixture dan database QA sebelumnya pada port 8873 tidak diubah.
- Tidak menggunakan public/index.php atau environment repository. Tidak membaca/mengubah .env, memakai MySQL Cloud, menjalankan migration/seeder bersama, atau memasang dependency.
- Akun QA, key/session, database, log request dan helper TEMP tidak disalin ke repository. Log hanya metode/path/status; tidak mencatat payload, token atau cookie.

## Hasil pengujian otomatis

| Kelompok | Hasil |
| --- | --- |
| Sepuluh file PHPUnit terarah, monitoring dan regresi PBI 3.2.1/perusahaan | **267 PASS, 0 FAIL, 2.641 assertions**, exit 0 |
| JavaScript decision handler PBI 3.2.1 | **17 PASS, 0 FAIL**, exit 0 |
| PHP lint seluruh file PHP kandidat baru/berubah | 17 file lulus |
| node --check dan git diff --check | Lulus |

Jumlah formal: 284 tes otomatis PASS, 0 FAIL; angka assertions 2.641 adalah PHPUnit. Tidak menggabungkan pemeriksaan HTTP/browser sebagai jumlah test case tambahan. Tes JavaScript tersebut adalah regresi handler keputusan; monitoring memakai form native/window.confirm, bukan handler Fetch baru.

Cakupan PHPUnit: filter tunggal/kombinasi, q judul atau perusahaan dengan OR terkelompok, reset, pagination, nilai terpilih dan opsi dinamis, HTML/API konsisten, empty state, validasi/otorisasi, published termasuk deadline lama/null, privasi/escaping, penandaan/409/pencabutan/no-op/penandaan ulang, aktor/waktu, satu record, persistensi, perubahan status, atribut lowongan tidak berubah, CSRF aktif, constraint unik dan rollback. Regresi tidak mengubah aturan approval.

## Integrasi HTTP nyata dengan CSRF aktif

Ini klien HTTP lokal dengan cookie sesi, bukan kendali browser atau eksekusi dialog JavaScript.

| Skenario | Hasil |
| --- | --- |
| Default, perusahaan, kategori, lokasi, status published | ID dan total HTML/API sama; total berturut-turut 18, 9, 9, 9, 18 |
| Keempat filter + pencarian + page 2/per_page 5 | HTML/API sama: ID 11, 13, 15, 17; total 9 |
| Pencarian tanpa hasil | HTML/API kosong, total 0 |
| Tanpa token CSRF / token salah | 419 / 419 |
| Alasan whitespace / 1.001 karakter | 422 / 422 |
| Form HTML penandaan melalui HTTP | Redirect diikuti hingga 200; pesan sukses, metadata dan Hapus Tanda tersedia; alasan HTML di-escape |
| Penandaan aktif berulang | 409 |
| Pencabutan / pencabutan ulang / penandaan ulang | 200 / 200 / 200 |
| Reload detail dan daftar | Data tanda/badge tetap tersedia; setelah clear detail menunjukkan tidak aktif dan petugas pencabutan |
| Guest API | 401 |
| Pemerintah nonaktif, perusahaan dan pencari kerja | HTML/API masing-masing 403 |
| Privasi | Catatan/badge tidak bocor ke halaman publik atau daftar lowongan perusahaan |

Seluruh atribut 22 lowongan QA dibandingkan dengan snapshot sebelum tindakan: **identik**, termasuk status, approved_by dan rejection_reason. Record tanda fixture manual ID 16 tetap satu dan aktif setelah siklus HTTP clear/reflag. Keberhasilan DELETE pada tabel ini tidak membuktikan dialog Hapus Tanda OK atau Cancel telah diuji.

## QA browser desktop dan mobile

Browser in-app tidak tersedia karena koneksi alat terputus. Fallback skill computer-use berhasil membuka Chrome biasa ke port 8874 dan login pemerintah QA.

| Area browser | Status yang benar-benar diamati |
| --- | --- |
| Desktop | Terverifikasi sebagian pada jendela Chrome sekitar 969 x 1129 piksel, bukan ukuran viewport CSS yang diukur |
| Daftar/filter | Header pemerintah, empat filter, published sebagai satu pilihan status, tabel tujuh kolom dan fallback deadline terlihat |
| Pencarian/empty state | Mengisi TidakAdaQA dan menerapkan form menghasilkan 0 lowongan serta pesan Tidak ada lowongan yang sesuai |
| Reset | Klik Reset filter mengembalikan 18 lowongan |
| Pagination | Klik halaman 2 menampilkan 16–18 dari 18 lowongan; query tetap pada URL |
| Navigasi/detail | Lihat Detail pada fixture 16 membuka detail monitoring; deskripsi multiline, kualifikasi dan form alasan terlihat |
| Alasan wajib | Klik Tandai tanpa alasan memunculkan validasi native Please fill out this field; belum mengirim penandaan browser |
| Mark/badge aktif melalui browser | Belum terverifikasi secara visual; keberhasilannya diuji melalui HTTP dan PHPUnit |
| Hapus Tanda Cancel | **LULUS menurut konfirmasi pengguna:** tidak ada DELETE, tanda tetap aktif. Agen tidak mengamati ulang dialog |
| Hapus Tanda OK | **LULUS menurut konfirmasi pengguna:** tanda berhasil dicabut. Agen tidak mengamati ulang dialog |
| Mobile/responsivitas viewport kecil | **Sesuai menurut konfirmasi pengguna.** Ukuran viewport/perangkat tidak disampaikan; tidak mengarang rinciannya |

Pengisian textarea melalui kontrol aksesibilitas mengalami timeout. Observasi berikutnya menunjukkan jendela aktif berpindah ke aplikasi lain; interaksi UI langsung dihentikan. Tidak mengetik ke aplikasi lain, memaksa fokus kembali, atau mengklaim dialog telah berhasil. Tidak mengubah aplikasi untuk melewati konfirmasi.

Bukti tampilan desktop yang berhasil disimpan: screenshot desktop-pagination.png pada folder artefak monitoring-qa di luar repository. Screenshot tersebut menunjukkan tampilan daftar/filter; bukti jumlah halaman juga berasal dari accessibility tree. Tidak ada screenshot bukti mobile atau dialog Cancel/OK yang berhasil.

## Prosedur manual awal — arsip sebelum konfirmasi pengguna

Prosedur berikut adalah arsip handoff sebelumnya, bukan pekerjaan manual baru yang diminta. Pengguna kemudian mengonfirmasi hasil mobile/Cancel/OK. Server QA sesi ini pada port 8874 dan server QA lama port 8873 telah dihentikan; kedua port diverifikasi tidak menerima koneksi. Tidak mengubah server/proses repository lain.

1. Buka http://127.0.0.1:8874/pemerintah/monitoring-lowongan/16. Gunakan akun dummy monitor.gov@example.test dengan kata sandi fixture QA yang diberikan saat handoff. Reload agar tanda aktif yang disiapkan melalui HTTP terlihat.
2. Pastikan badge Perlu Ditinjau, alasan, petugas dan waktu terlihat. Buka DevTools Network, aktifkan Preserve log dan filter penandaan.
3. Klik Hapus Tanda lalu Cancel. Pastikan tidak ada request pencabutan: form native memakai POST dengan _method=DELETE, sehingga periksa POST maupun DELETE di Network. Jalankan helper mode cancel; tanda dan seluruh atribut lowongan harus tetap sama.
4. Klik Hapus Tanda lalu OK. Harus ada satu submission, redirect kembali ke detail, pesan sukses, tanda tidak aktif dan form Tandai tersedia. Helper mode clear harus menunjukkan satu DELETE logis baru, cleared_by=1 dan cleared_at terisi, dengan atribut lowongan tetap identik.
5. Ulangi pemeriksaan daftar, empat filter, pencarian, reset, pagination, detail/form/badge pada viewport mobile (misalnya 375 x 812). Periksa tabel dapat digulir horizontal, label/tombol terbaca, dan body tidak melebar. Ini langkah yang harus dilakukan, bukan hasil PASS.
6. Simpan screenshot/Network dan hasil helper tiap tahap. Jangan menjalankan Cancel/OK melalui klien HTTP sebagai pengganti interaksi dialog. Setelah pengujian manual selesai, hentikan server QA.

Helper baca-saja, tidak memuat Laravel/.env dan hanya membaca SQLite serta log QA:

```powershell
# Setelah benar-benar memilih Cancel:
& 'C:\php84\php.exe' 'C:/Users/ACER/AppData/Local/Temp/hasya-monitoring-qa-f94ba510f0464ec69a057e49d4ac99f5/verify-manual.php' cancel

# Setelah benar-benar memilih OK dan melihat hasil:
& 'C:\php84\php.exe' 'C:/Users/ACER/AppData/Local/Temp/hasya-monitoring-qa-f94ba510f0464ec69a057e49d4ac99f5/verify-manual.php' clear
```

Baseline helper disimpan setelah pengujian HTTP: ID 16 published, approved_by null, tanda aktif oleh akun pemerintah ID 1, cleared_at null. Hitungan DELETE dihitung relatif baseline agar DELETE dari pengujian HTTP sebelumnya tidak dianggap sebagai hasil manual. Menjalankan helper sebelum interaksi bukan bukti telah menekan Cancel.

## Bug, batas dan keputusan PM

- **Tidak ditemukan bug aplikasi baru yang terbukti pada QA ini.** Tidak ada perubahan kode produksi atau aturan bisnis. Timeout/kendala fokus merupakan keterbatasan kendali UI, bukan bukti cacat aplikasi.
- Persetujuan PM penandaan manual berlaku sebagaimana dicatat di REVIEW_FLAGS.md. Definisi aktif final, filter multi-status dan konfirmasi final rancangan approval PBI 3.2.1 tetap terbuka.
- SQLite/permintaan berurutan/constraint unik tidak membuktikan konkurensi paralel atau row lock MySQL. Tidak mengklaim suite penuh, aksesibilitas penuh atau semua browser sudah selesai. Hasil mobile hanya berdasarkan konfirmasi pengguna.
- Hash 156 file source sebelum dan sesudah pemeriksaan sama, sebelum penambahan laporan QA dan tautan README. File lokal hasil pekerjaan sebelumnya dipertahankan. Tidak ada commit, push atau PR.

Rekomendasi: siapkan staging lokal PBI 3.2.2 untuk cakupan published yang ada. Mobile/Cancel/OK dicatat sebagai hasil manual yang dikonfirmasi pengguna, terpisah dari hasil otomatis. Definisi aktif final dan multi-status tetap memerlukan keputusan PM; jangan menyatakan seluruh acceptance criteria filter status sudah terpenuhi.

## Konfirmasi pengguna dan pemeriksaan sebelum staging

Pengguna menyatakan QA manual PBI 3.2.2 selesai: tampilan mobile sesuai, Hapus Tanda Cancel tidak mengirim DELETE dan mempertahankan tanda, Hapus Tanda OK mencabut tanda. Pengguna juga mengonfirmasi 267 PHPUnit PASS/2.641 assertions serta 17 JavaScript PASS. Tidak ada screenshot/rekaman Network baru yang disampaikan pada konfirmasi ini; hasil manual diatribusikan kepada pengguna.

Pemeriksaan helper lokal untuk fixture 16 pada database TEMP yang tersedia menghasilkan tanda masih aktif, cleared_at null, dan nol DELETE baru sejak baseline; expected_state_matches untuk mode clear adalah false. Jadi artefak tersebut **belum mengonfirmasi pencabutan pada fixture 16**. Ini tidak dipakai sebagai bukti otomatis untuk laporan manual OK/Cancel, dan tidak diasumsikan bahwa pengguna menguji fixture atau lingkungan yang sama. Tidak mengubah data untuk membuat bukti sesuai laporan. Identitas fixture/lingkungan manual belum disebutkan pada konfirmasi pengguna.

Server QA milik sesi ini dihentikan melalui handle proses yang dibuat sesi, tanpa menebak PID atau menghentikan proses lain. Port 8873 dan 8874 kemudian diperiksa tertutup.

Manifest staging: 23 file (18 baru, 5 berubah), termasuk migration penandaan, source monitoring, Blade, empat file tes monitoring, fixture SQLite bersama dan tiga dokumen monitoring. Perubahan pada file bersama dibatasi kebutuhan integrasi PBI 3.2.2. Tidak mengubah aturan approval, gate atau controller/service pemeriksaan perusahaan. Tidak memasukkan .env, dependency, database/fixture browser, key, session, cache, log atau helper TEMP. Tidak membuat commit, push, PR atau merge.
