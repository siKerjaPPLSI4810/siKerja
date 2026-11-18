# Kontrak status dan riwayat — Task 3.1.2.2

Status dokumen: rancangan normatif berdasarkan instruksi task dan aturan PBI 3.1.1;
bukan implementasi runtime. Keputusan terbuka pada bagian akhir perlu konfirmasi
sebelum backend. Tidak ada migration, backfill, event atau perubahan data dijalankan.

Branch audit: feature/pbi-3.1.2-monitoring-verifikasi-perusahaan, fondasi f3cbf20.

## 1. Dasar dan batas scope

PBI 3.1.2 — Memantau Status Verifikasi Perusahaan, Epic 3, Feature 3.1,
Sprint 1, SP 3, KF-11. Acceptance criteria resmi: daftar menurut status tampil;
riwayat perubahan status tercatat dan akurat.

Hanya tiga status database: pending, verified, rejected. Tidak menambah status
seperti in_review, suspended atau legacy. CompanyProfile adalah kondisi terkini;
CompanyInspection adalah satu hasil pemeriksaan terkini, tetap HasOne/unique FK.
Riwayat transisi adalah tabel terpisah. Monitoring bukan editor status bebas.

## 2. Inventaris penulisan yang ditemukan

Nomor baris merupakan lokasi saat audit, dapat bergeser pada implementasi berikut.

| Jalur | Penulisan / aturan saat ini | Implikasi |
| --- | --- | --- |
| app/Http/Controllers/Company/CompanyAuthController.php:145 | registrasi selalu pending; pesan validasi awal pada verification_notes | inisialisasi, bukan transisi |
| app/Http/Controllers/Company/CompanyProfileController.php:40,74 | firstOrCreate profil pending | hanya pembuatan profil yang belum ada |
| app/Http/Controllers/Company/CompanyProfileController.php:update | ubah identitas, tidak menulis status; hasil final menahan perubahan identitas/dokumen | pending bisa mempunyai snapshot lama |
| app/Http/Controllers/Company/CompanyProfileController.php:verifyIdentity | inspeksi format read-only; tidak lagi menulis status/decision | scenario_override bukan keputusan resmi |
| app/Http/Controllers/Company/CompanyJobController.php:62 | firstOrCreate profil pending | inisialisasi, bukan transisi |
| app/Services/CompanyInspectionService.php:50–63 | updateOrCreate inspection.decision; update profil.status, pesan aman, verified_by dalam transaksi | sumber perubahan resmi runtime yang ditemukan |
| app/Http/Requests/Government/StoreCompanyInspectionRequest.php | validasi keputusan/hasil; menolak spoofing atribut internal | bukan penulis DB |
| app/Services/CompanyVerificationService.php:87 | menetralkan requestedScenario; menghitung hasil format tanpa persistensi | branch simulasi lama tidak menjadi otoritas status |
| database/seeders/CompanyJobSprint1Seeder.php:38 | updateOrCreate contoh verified tanpa pemeriksaan | sumber legacy; bukan event resmi; jangan jalankan di produksi sembarangan |
| database/factories/CompanyInspectionFactory.php:21,40,50 | state pending/verified/rejected sintetis; tidak menyinkronkan profil otomatis | fixture bukan event runtime |
| migration company_profiles / company_inspections | default pending pada pembuatan record | bukan bukti pemeriksaan/transisi |
| CompanyProfile / CompanyInspection fillable | atribut dapat ditulis oleh kode tepercaya | bukan otorisasi; jangan mass-assign input bebas |
| tests/Feature/*Company* | penulisan fixture, trigger kegagalan dan assertion pada DB terisolasi | tidak dianggap jalur produksi |

Tidak ditemukan endpoint produksi lain yang menulis decision atau status resmi.
Accessor badge/label, query, resource dan Blade membaca status saja. Penambahan
writer oleh anggota tim harus mengikuti kontrak transaksi di bawah; observer umum
bukan solusi otomatis untuk mengamankan bulk update atau menetapkan actor/reason.

## 3. Matriks transisi

Syarat umum mutasi: sesi web sah, pemerintah aktif, CSRF, payload pemeriksaan
tervalidasi, data_version cocok, data diperiksa ulang di bawah lock perusahaan.
Petugas/metode/snapshot/waktu event ditentukan server.

| Awal | Tujuan | Syarat | Event |
| --- | --- | --- | --- |
| pending | pending | catatan valid; hasil boleh pasti/belum pasti | tidak ada status_changed |
| pending | verified | OSS matched DAN administrasi complete | satu status_changed |
| pending | rejected | OSS matched/mismatched DAN administrasi complete/incomplete | satu status_changed |
| verified final resmi | pending/verified/rejected | ditolak, termasuk pengiriman ulang dan perubahan notes | tidak ada; 409 |
| rejected final resmi | pending/verified/rejected | ditolak, termasuk pengiriman ulang dan perubahan notes | tidak ada; 409 |
| final legacy tanpa inspection | pembaruan dari monitoring | rekomendasi read-only sampai kebijakan koreksi disetujui | tidak ada saat ditolak |
| status tidak dikenal / keadaan inkonsisten | pembaruan monitoring | fail closed; perlu peninjauan data | tidak ada |

Jika salah satu hasil unconfirmed, keputusan wajib pending. Rejected dengan
matched + complete masih diterima validator PBI 3.1.1: jangan menambahkan larangan
bisnis baru tanpa persetujuan. Pending dengan hasil pasti juga tetap diperbolehkan.
Validasi notes: trim, string tidak kosong, maksimal 5000 karakter; checked_on adalah
tanggal valid, tidak di masa depan menurut timezone aplikasi. checked_on bukan
timestamp kejadian perubahan status.

Final resmi berarti CompanyInspection.decision verified/rejected; guard final
tidak boleh dilemahkan hanya karena profil atau snapshot tampak berbeda. Bila
profil dan inspection berbeda, tampilkan unknown dan jangan melakukan auto-repair.

### Beda kontrak monitoring dengan perilaku legacy saat ini

CompanyInspectionService saat ini tidak memblokir profil final yang tidak memiliki
inspection; endpoint PBI 3.1.1 dapat mencatat pemeriksaan pertama pada data legacy.
Dokumen ini TIDAK mengubahnya. Monitoring direkomendasikan tidak membuka pintu
mutasi legacy tersebut sebelum keputusan tim. Jangan mengklaim seluruh profil
verified/rejected sudah ditolak oleh service sekarang.

Jika proses PBI 3.1.1 yang memang sah masih mengubah status legacy setelah pencatatan
riwayat diaktifkan, riwayat harus memakai status DB nyata sebagai old_status,
bukan menggantinya dengan pending atau mengarang asal-usul pemeriksaan lama.

## 4. Provenance: metadata tampilan, bukan status/kolom baru

Evaluasi status tidak dikenal/ketidaksesuaian terlebih dahulu, baru mapping normal.

| Provenance | Kontrak |
| --- | --- |
| official | status dikenal, record inspection ada dan decision konsisten dengan status terkini |
| uninspected | pending dan tidak ada inspection |
| legacy | verified/rejected dan tidak ada inspection |
| unknown | status asing, decision tidak konsisten, atau integritas/sumber tidak dapat dipastikan |

Pending official ditampilkan "Perlu pemeriksaan lanjutan". Pending uninspected
ditampilkan "Belum diperiksa". Legacy tidak boleh diberi badge bukti pengecekan OSS.
verified_by, verification_notes, timestamps dan event historis saja tidak cukup
untuk menetapkan official. Actor yang telah dihapus tidak otomatis membatalkan
catatan historis yang sebelumnya sah.

Rekomendasi tambahan untuk dikonfirmasi: jika snapshot identitas tidak lagi sesuai
profil, tampilkan unknown/indikasi data berubah, bukan hasil pemeriksaan atas data
terkini. Jangan mengubah status DB atau memicu transisi hanya untuk label provenance.

## 5. Payload event minimal

Usulan model CompanyVerificationStatusHistory dan tabel
company_verification_status_histories; belum dibuat pada task ini.

| Atribut | Aturan |
| --- | --- |
| id | primary key event internal |
| company_profile_id | FK wajib ke perusahaan yang dikunci |
| company_inspection_id | FK wajib ke record pemeriksaan sumber |
| sequence | integer positif, urutan per perusahaan |
| old_status | status dikenal yang dibaca dari DB di bawah lock |
| new_status | keputusan tervalidasi; wajib berbeda dari old_status |
| actor_user_id | Auth user pemerintah aktif saat operasi; FK nullable bila actor kemudian dihapus |
| actor_name_snapshot | nama tampilan saat event; internal, tanpa email/password/token |
| reason_code | manual_inspection_decision untuk alur ini, ditetapkan server |
| reason | ringkasan terstruktur yang aman, bukan salinan notes bebas |
| changed_at | timestamp server UTC saat perubahan dalam transaksi; bukan input client |

Contoh reason: "Pemeriksaan manual: OSS sesuai; administrasi lengkap; keputusan
verified." Untuk pending tidak ada event bila status tetap pending. Nama/code ini
bukan status baru. Tidak perlu event_type tambahan karena tabel khusus transisi;
API dapat menyajikan discriminator konstan status_changed.

Tidak menggandakan company_snapshot, dokumen, atau seluruh hasil pemeriksaan.
FK company_inspection_id menunjuk record terkini yang dapat diperbarui saat pending;
old_status/new_status/actor/reason/changed_at event harus berdiri sendiri dan tidak
dibaca ulang dari record mutable untuk merekonstruksi masa lalu.

Constraint/indeks yang direkomendasikan:

- unique (company_profile_id, sequence); sequence positif.
- indeks (company_profile_id, changed_at, id) untuk timeline.
- validasi old_status != new_status pada domain; CHECK DB hanya bila implementasi
  dan pengujian kompatibilitas target SQLite/MySQL sudah dipastikan.
- FK perusahaan dan pemeriksaan restrictOnDelete untuk menjaga riwayat.
- actor nullable + nullOnDelete; actor_name_snapshot dipertahankan dengan kebijakan
  retensi akses internal. Jangan cascade menghapus event saat akun dihapus.
- Event append-only melalui aplikasi; tidak ada route edit/delete atau update
  factory/runtime otomatis yang menyamar sebagai kejadian. Hak DB/retensi perlu
  koordinasi, Eloquent saja tidak memberi jaminan immutability absolut.

## 6. Atomicity, sequence, retry dan token versi

Satu transaksi, satu urutan lock yang sama pada seluruh writer sah:

1. Otorisasi pemerintah aktif, validasi payload dan larangan spoofing.
2. Lock CompanyProfile lebih dahulu, kemudian inspection (pola PBI 3.1.1).
3. Baca ulang status/inspection dan verifikasi data_version. Jangan memakai relasi
   cache dari form/controller untuk mutasi.
4. Tolak final resmi dan konflik; tentukan old_status dari row terkunci.
5. Simpan inspection dan profil, termasuk verification_notes berupa pesan umum.
6. Jika old_status != decision, hitung MAX(sequence) perusahaan + 1 di dalam
   transaksi SETELAH lock parent; buat satu event dengan actor/waktu dari server.
7. Commit bersama; failure pada event membuat inspection/profil ikut rollback.

Lock parent wajib meski belum ada event. MAX+1 tanpa lock tidak aman. Unique
constraint adalah pertahanan terakhir; pelanggaran tidak boleh ditelan sementara
profil tetap di-commit. Tidak ada transaksi/event terpisah, queued listener atau
observer ganda untuk penulisan yang sama.

Retry deadlock mengulang transaksi utuh, bukan hanya insert event. Percobaan yang
rollback tidak meninggalkan event/sequence yang tampak committed. Retry request
lama memakai token lama harus konflik; keputusan final tetap tidak bisa diulang.
Tidak menganggap UUID acak sebagai jaminan idempotency. Jika nanti diperlukan
idempotency key client, itu kontrak tambahan yang harus disetujui.

Pertahankan token versi PBI 3.1.1 dan seluruh pemeriksaannya. Bila perlu memasukkan
sequence ke hash, invalidasi token lama harus fail closed dan memerlukan reload;
jangan menggantinya dengan pemeriksaan updated_at saja. Pending->pending dapat
memperbarui hasil/notes yang sah, tetapi bukan event status_changed. Tidak membuat
riwayat edit notes atau seluruh profil pada PBI ini.

SQLite in-memory memverifikasi transaksi, constraint dan stale-request berurutan;
tidak membuktikan lockForUpdate MySQL atau transaksi paralel. Pengujian dua koneksi
MySQL testing terpisah wajib sebelum mengklaim concurrency tervalidasi.

## 7. Perusahaan baru dan legacy

Tidak membuat backfill status_changed pada migration, pembacaan daftar/detail,
startup atau pembentukan baseline. Inisialisasi baru ke pending bukan transisi;
event pertama adalah perubahan nyata yang sah setelah fitur aktif, sequence 1.
Jangan merekonstruksi actor/waktu dari created_at, updated_at, atau seeder lama.

UI tetap menampilkan status terkini dan batas data:
"Riwayat sebelum pencatatan diaktifkan tidak tersedia."
Timeline kosong bukan bukti perusahaan belum pernah diperiksa. Simpan daftar
baseline/cutoff hanya bila dibutuhkan dan disetujui, tidak sebagai event fiktif.

## 8. Kerahasiaan dan otorisasi

- Daftar/detail/riwayat dan mutasi: web session + auth + access-government aktif.
- Notes penuh hanya di CompanyInspection dan mapping detail admin yang eksplisit.
  Jangan menyalin ke reason atau verification_notes perusahaan.
- Perusahaan hanya mendapat status/pesan umum; legacy verification_notes dianggap
  tidak tepercaya. Pertahankan accessor aman dan hidden PBI 3.1.1.
- Reviewer/actor dan alasan timeline hanya untuk pemerintah aktif; snapshot nama
  tidak memuat kontak atau seluruh User model.
- Snapshot pemeriksaan tidak masuk resource umum, timeline atau serialisasi bebas.
- Path fisik/relatif storage, token, password/hash, cookie dan APP_KEY tidak masuk
  response. Dokumen hanya lewat route private terotorisasi, bukan URL public.
- $hidden adalah defense-in-depth, bukan pengganti Gate. Gunakan allowlist DTO/API
  Resource; escape Blade. Jangan mempercayai canSubmit dari client.
- Token HMAC opaque boleh dipasok untuk concurrency, bukan alasan membuka snapshot.
- Error 401/403/404/422/409 aman; tidak memuat catatan/SQL/path internal.
- Dokumen legacy public dan paparan notes historis tetap pekerjaan remediasi
  terpisah; desain riwayat tidak menyelesaikannya.

## 9. Test plan untuk implementasi backend berikutnya

| Kelompok | Skenario wajib dan assertion |
| --- | --- |
| Transisi | seluruh 27 kombinasi 3 OSS x 3 administrasi x 3 keputusan; verified hanya matched+complete; rejected tanpa unconfirmed |
| Status tetap | pending->pending: inspection boleh berubah, profil konsisten, jumlah event dan sequence tidak bertambah |
| Perubahan sah | pending->verified/rejected: satu event, old/new tepat, reviewer/actor Auth, tanggal pemeriksaan tetap terpisah dari changed_at |
| Final | semua percobaan overwrite final, termasuk sama status/notes saja: 409, seluruh row/event tetap |
| Validasi | tanggal masa depan/invalid, notes whitespace/panjang, status asing, payload status saja, actor/waktu/reason_code spoofing ditolak |
| Token | stale identity, stale inspection, token perusahaan lain, dua admin: tidak menimpa diam-diam |
| Atomicity | paksa insert event gagal: inspection baru tidak tersisa, hasil pending sebelumnya dan profil dipulihkan, tidak ada flash sukses |
| Sequence | event pertama 1; urutan per perusahaan independen; unique duplicate ditolak; rollback tidak meninggalkan event |
| Retry | request duplikat/stale tidak membuat event kedua; retry transaksi utuh tidak menggandakan event |
| Legacy | GET tidak menulis apa pun; final tanpa inspection berlabel legacy; timestamp lama tidak menjadi event; eventual authorized correction mencatat old_status nyata |
| Unknown | status/decision tidak konsisten dan snapshot berubah ditandai sesuai kebijakan yang disepakati, tanpa auto-repair |
| Akses | tamu, pemerintah nonaktif, pemilik, perusahaan lain, pencari kerja: tidak dapat membaca/mengubah catatan/riwayat internal |
| CSRF | mutasi tanpa token ditolak saat bypass framework testing dinonaktifkan hanya pada test |
| Privacy | notes marker tidak bocor ke profil perusahaan, timeline reason, JSON umum, relasi atau error; admin detail tetap lengkap |
| UI/query | Semua/Pending/Verified/Rejected, q nama/NPWP, pagination stabil dan history_page terpisah, koleksi kosong dan provenance benar |
| Retensi | penghapusan actor mengikuti nullOnDelete dan snapshot nama; penghapusan parent tidak menghilangkan riwayat tanpa kebijakan |
| Regresi | seluruh suite PBI 3.1.1, keamanan dokumen, CompanySprint1Test dan CV relevan tetap berjalan |
| DB target | SQLite in-memory + storage fake; MySQL testing dua koneksi untuk lock/unique/retry; jangan gunakan database utama |

Test ini belum dibuat/dijalankan pada task dokumentasi. Jangan menyatakan
accuracy/concurrency/event storage PASS sebelum implementasi dan eksekusi nyata.

## 10. Keputusan terbuka dan koordinasi sebelum backend

1. Legacy final: opsi A monitoring read-only (REKOMENDASI); opsi B pemeriksaan
   pertama melalui proses khusus yang disetujui. Jangan membuka PATCH status bebas
   atau mengubah perilaku endpoint PBI 3.1.1 tanpa review pemilik modul.
2. Snapshot tidak sesuai profil: rekomendasi unknown + warning dan blok mutasi
   monitoring sampai peninjauan; konfirmasikan field identitas yang dibandingkan.
3. Actor deletion/retensi: rekomendasi nullOnDelete + nama snapshot; konfirmasi
   kebijakan privasi dan akses. Tidak mengambil email atau password.
4. Reason bebas vs terstruktur: rekomendasi ringkasan server dari hasil pasti dan
   keputusan. Jika alasan bebas dibutuhkan, tentukan field khusus, validasi dan
   akses tanpa menggandakan notes internal seluruhnya.
5. Deployment: tabel riwayat harus tersedia sebelum writer menggunakannya. Jangan
   fallback diam-diam ke perubahan status tanpa event ketika tabel tidak tersedia.
6. Jalur perusahaan/seed: inisialisasi pending tidak perlu event; larang writer baru
   menetapkan status resmi di luar proses pemeriksaan. Seeder demo tidak boleh
   dianggap provenance official atau dijalankan sebagai migrasi histori.
7. API pembaruan task 3.1.2.3 harus memakai validasi dan transaksi pemeriksaan yang
   sama. Reuse request/service tidak berarti copy-paste aturan ke dua jalur yang
   dapat menyimpang. Tidak menambah privilege reopening final.

Koordinasi terutama menyentuh CompanyInspectionService (transaksi/token),
CompanyProfile (relasi/serialisasi), CompanyVerificationService (simulasi tetap
netral), controller profil/registrasi/lowongan perusahaan, seeder demo, serta tim
yang menangani retensi akun dan deployment. Tidak ada file tersebut diubah oleh
Task 3.1.2.2. Branch/main dan merge tetap mengikuti koordinasi tim.
