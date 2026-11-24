# QA PBI 3.2.2 — Subtask 3.2.2.6

## Finalisasi QA Feature 3.2 — 24 November 2026

Baseline Git 9441f2ba85044ef9ed7f4daeebc28bcb22341f67, Hasya/Sprint1. Ringkasan ini menggantikan status belum diuji/menunggu keputusan pada arsip Tahap 1 dan QA awal di bawah. Referensi agen: FINAL_QA_REVIEW.md di TEMP. Hanya temuan yang diperlukan diringkas; password dummy, token, cookie, key, database dan helper QA tidak disalin.

### Bukti pengujian otomatis dan pengamatan agen

| Pemeriksaan | Hasil dan asal bukti |
| --- | --- |
| Suite penuh setelah Tahap 2, 530 tes | **529 PASS, 0 FAIL, 1 SKIPPED; 4.795 assertions**, SQLite terisolasi |
| Regresi Feature 3.2 dan perusahaan | **287 PASS, 0 FAIL; 2.907 assertions**, SQLite :memory: |
| Seluruh JavaScript | **17 PASS, 0 FAIL** |
| Integrasi HTTP/SQLite lokal | **26 PASS**, hasil individual pada laporan TEMP; bukan 26 tes browser |
| Browser agen | Login pemerintah/perusahaan, default Published, filter Closed/kombinasi, detail Closed, pencarian kosong, reset, pagination dan pencabutan tanda berhasil |
| Browser + snapshot SQLite agen | Edit biasa/penutupan mempertahankan metadata; edit Published/pengajuan ulang membersihkan approved_by dan rejection_reason; record perusahaan tetap identik; approval PBI 3.2.1 berhasil |

Satu SKIPPED adalah CompanyDocumentSecurityTest::test_symlink_outside_root_is_rejected_when_platform_supports_links: Windows tidak mengizinkan symlink sintetis. Bukan PASS dan bukan bukti verifikasi Linux. Suite penuh di atas dijalankan sebelum finalisasi satu kalimat teks Blade; rerun tes yang terdampak finalisasi dicatat terpisah di bawah, tanpa mengubah angka suite penuh sebelumnya.

Lingkungan HTTP/browser memakai server loopback port 8875, bootstrap/environment/storage/cache di TEMP dan hanya koneksi SQLite QA yang path-nya diperiksa. Tidak membaca .env repository atau mengakses MySQL Cloud. Agen memverifikasi pencabutan fixture 5: satu DELETE logis, cleared_by dan cleared_at tersimpan, status tetap Closed serta metadata lowongan tidak berubah. Agen tidak berhasil mengendalikan dialog Cancel/OK secara eksplisit. Screenshot desktop/mobile agen terpotong/terskala; keterbatasan alat ini tidak disamakan dengan bug UI.

### QA manual terakhir — konfirmasi pengguna

| Skenario | Hasil yang dilaporkan pengguna |
| --- | --- |
| Desktop | PASS |
| Mobile 375px | PASS |
| Detail Closed dan badge Ditutup | PASS |
| Hapus Tanda → Cancel | PASS; tanda tetap aktif |
| Hapus Tanda → OK | PASS; tanda berhasil dicabut, status tetap Closed |

Lima hasil ini diatribusikan kepada pengguna, bukan eksekusi otomatis atau pengamatan ulang agen. Tidak mengarang screenshot, rekaman Network, dimensi desktop atau identitas fixture manual yang tidak disampaikan. Konfirmasi terbaru melengkapi bagian visual/dialog yang belum terverifikasi agen pada laporan TEMP; tidak menghapus batas bukti historis.

### Aturan akhir dan review sebelum merge

PM Amirza Hasya telah mendelegasikan pemilihan aturan sesuai PBI kepada tim. Tim mempertahankan approval/rejection existing; Tolak dan Minta Perbaikan tetap rejected/rejection_reason dan **belum dibedakan secara terstruktur**. Published adalah default monitoring, termasuk deadline lewat/null; closed dapat dipilih, status lain ditolak. Tanda baru hanya Published; tanda lama Closed dapat dicabut.

Reset approved_by/rejection_reason sudah diimplementasikan secara minimal pada dua cabang pengajuan ulang existing. Penutupan dan edit biasa mempertahankan metadata; record verifikasi perusahaan tetap. **Review Rifqi atas perubahan Feature 2.2 masih diperlukan sebelum merge**; lihat ../job-inspections/COMPANY_RESUBMISSION_REVIEW.md. Tidak mengklaim review Rifqi telah diberikan. Konkurensi paralel MySQL belum diuji. Tidak ada migration baru atau perubahan logika keputusan pada finalisasi ini.

### Pemeriksaan akhir sebelum staging

Perubahan source pada finalisasi hanya satu kalimat penjelasan Blade keputusan. Tidak ada perubahan logika approval, payload, service, route, middleware atau schema. GovernmentJobInspectionDecisionUiTest dan GovernmentJobInspectionShowTest dijalankan ulang: **31 PASS, 0 FAIL; 310 assertions**, exit 0, dengan SQLite :memory: dan environment kosong. Seluruh JavaScript dijalankan ulang: **17 PASS, 0 FAIL**. Lint 9 file PHP kandidat, sintaks JavaScript dan git diff --check lulus. Angka suite penuh 529 PASS/1 SKIPPED/4.795 assertions tetap hasil Tahap 2 sebelumnya, bukan klaim rerun suite penuh setelah perubahan teks.

Listener 127.0.0.1:8875 dicocokkan dengan executable C:/php84/php.exe serta router TEMP QA sesi ini. Server dihentikan melalui handle proses sesi. Probe socket sesudah penghentian timeout dan tidak dipakai sebagai bukti port tertutup; pemeriksaan Windows kemudian memastikan tidak ada listener port 8875 dan proses QA 11676 sudah tidak ada. Tidak menghentikan proses lain. Fixture, SQLite, session, key, log, helper dan laporan TEMP tidak dimasukkan staging. Tidak membuat commit, push, PR atau merge; PR #7 tidak dimodifikasi.

## Arsip QA Tahap 1 dan QA awal — sebelum finalisasi

Hasil dan batas bukti berikut dipertahankan sebagai riwayat, bukan status terbaru.

## QA perubahan Published/Closed — 24 November 2026

Baseline kode: 9441f2ba85044ef9ed7f4daeebc28bcb22341f67, branch Hasya/Sprint1. Hasil pada bagian ini berasal dari eksekusi baru untuk penyempurnaan Tahap 1, bukan pemakaian ulang hasil QA browser sebelumnya.

### Bukti otomatis baru

| Pemeriksaan | Hasil |
| --- | --- |
| Seluruh PHPUnit, 522 tes | **521 PASS, 0 FAIL, 1 SKIPPED; 4.706 assertions**, exit 0 |
| Regresi terarah akhir Feature 3.2 dan pengajuan perusahaan | **279 PASS, 0 FAIL; 2.818 assertions**, exit 0 |
| Tes status integrasi baru | **13 PASS, 0 FAIL; 180 assertions**, exit 0 setelah perbaikan snapshot fixture |
| Seluruh JavaScript, node --test tests/Frontend/*.test.cjs | **17 PASS, 0 FAIL**, exit 0 |
| Lint PHP yang baru/berubah | 7 file lulus |
| node --check public/js/job-inspection-decision.js; git diff --check | Lulus |

Satu tes SKIPPED berasal dari CompanyDocumentSecurityTest::test_symlink_outside_root_is_rejected_when_platform_supports_links. Tes menjalankan markTestSkipped ketika OS tidak mengizinkan symlink sintetis. Kasus ini dikonfirmasi lagi secara terpisah (1 SKIPPED, 1 assertion), bukan dianggap PASS atau bukti pengujian Linux.

Cakupan baru: status default/tidak dikirim/kosong/whitespace, Published/Closed, deadline lama/null, OR pencarian tetap terkelompok, kombinasi filter, pagination dan Reset, katalog perusahaan/kategori lintas status serta halaman, konsistensi HTML/API dan allowlist JSON. Closed mempunyai detail/badge sesuai, tanpa form tanda baru; request penandaan langsung tetap 409. Tanda aktif lama, alasan escaped, petugas/waktu dan tombol pencabutan tetap tersedia pada Closed. Form pencabutan tanpa CSRF menghasilkan 419; token benar berhasil, metadata pencabutan tersimpan dan atribut lowongan tetap identik. Regresi mencakup status lain ditolak, otorisasi, privasi, PBI 3.2.1 serta pengajuan perusahaan existing.

Regresi terarah pertama: 279 tes, 278 PASS/1 FAIL, 2.818 assertions. Satu kegagalan berasal dari fixture baru yang mengambil snapshot model sebelum nilai default database di-refresh (quota/approved_by dan urutan atribut). Diperbaiki hanya dengan fresh()->getRawOriginal() pada snapshot awal. Tes baru kemudian 13 PASS/180 assertions dan suite penuh di atas PASS; bukan perubahan aturan bisnis atau mutasi data aplikasi.

### Isolasi dan prosedur

- Tes Feature 3.2 memakai GovernmentJobInspectionTestCase existing: environment kosong di TEMP, cache/storage TEMP, hanya SQLite :memory: dengan foreign key aktif sebelum migration tes.
- Suite penuh memakai bootstrap PHP sementara di TEMP, tanpa mengubah tests/TestCase.php atau tes anggota lain. Bootstrap memuat autoloader repository lalu mendefinisikan Tests\TestCase dengan metode createApplication isolasi existing. Path bootstrap aplikasi diarahkan absolut ke clone Hasya; environment dialihkan ke folder kosong sebelum bootstrap Laravel; konfigurasi koneksi setelah LoadConfiguration hanya SQLite :memory:. Kelas tes kemudian memakai koneksi SQLite :memory: sendiri atau RefreshDatabase pada SQLite tersebut.
- Perintah suite penuh: PHP C:/php84/php.exe, vendor/phpunit/phpunit/phpunit --bootstrap <folder-TEMP>/bootstrap.php --do-not-cache-result. Bootstrap sementara tidak dimasukkan ke repository. Migration hanya berjalan pada SQLite pengujian terisolasi.
- Tidak membaca/mengubah .env, mengakses MySQL Cloud, memasang dependency atau memakai database QA browser lama. Percobaan runner yang tidak menghasilkan keluaran dihentikan melalui handle proses sesi sebelum eksekusi ulang; tidak menghentikan proses pengguna lain.
- Diff controller/tes Feature 2.2, service approval/penandaan, scope JobPosting, route/bootstrap aplikasi dan migration tetap kosong.

### QA manual dan batas hasil

Tidak membuka browser atau mengulang QA desktop/mobile, dialog Hapus Tanda Cancel/OK, maupun interaksi form Closed pada tahap ini. Assertion Blade/HTTP dalam PHPUnit bukan bukti manual browser. Hasil mobile/Cancel/OK tanggal 23 November di bawah tetap konfirmasi pengguna untuk versi sebelumnya; tidak dipindahkan menjadi bukti versi Published/Closed. Perlu QA visual ulang pada Published/Closed, terutama filter, badge Closed, form tersembunyi dan pencabutan tanda lama.

Konkurensi paralel/row lock MySQL belum diuji. Reset approved_by/rejection_reason pada pengajuan ulang belum diimplementasikan; menunggu koordinasi Rifqi (Feature 2.2). Aturan approval/rejection existing dipertahankan sesuai keputusan tim terbaru, dengan keterbatasan Tolak/Minta Perbaikan sama-sama rejected. Tidak ada migration baru, commit, push atau perubahan PR #7; tidak meminta perubahan status Draft atau merge.

## Arsip QA 23 November 2026 — sebelum Published/Closed

Seluruh hasil dan kendala berikut dipertahankan dengan konteks versi awal, termasuk perbedaan konfirmasi pengguna dan snapshot helper fixture 16.

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
