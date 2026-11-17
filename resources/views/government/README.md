# Kontrak UI pemeriksaan perusahaan (PBI 3.1.1)

View terhubung ke CompanyInspectionController melalui route web dengan autentikasi
sesi dan Gate access-government. Tidak ada fixture perusahaan atau endpoint preview
publik. Query antrean digunakan bersama API melalui CompanyInspectionQuery.

## Route yang diharapkan

| Nama | Method | Parameter |
| --- | --- | --- |
| `government.company-inspections.index` | GET | `q`, `page` (query) |
| `government.company-inspections.show` | GET | `companyProfile` |
| `government.company-inspections.store` | POST | `companyProfile` |
| `government.company-inspections.document` | GET | `companyProfile` |

## Data antrean

- `$companies`: `LengthAwarePaginator<CompanyProfile>`, difilter `pending` oleh controller.
- `$filters['q']`: string pencarian nama/NPWP yang sudah divalidasi (maksimal 100 karakter).
- Tiap perusahaan mempunyai atribut presentasi `document_available`: `true`, `false`,
  atau `null` jika belum diperiksa. Backend memeriksa storage; path saja bukan bukti berkas ada.
- Pagination menggunakan view Bootstrap 5 secara eksplisit, tanpa konfigurasi global.

## Data detail

- `$company`: `CompanyProfile` dengan relasi `user` dan `inspection.reviewer` dimuat.
- `$inspectionRecord`: null atau array dengan `oss_check_result`, `administrative_result`,
  `checked_on`, `decision`, `notes`, `reviewer_name`, dan `recorded_at`.
  Tanggal/waktu tampilan diformat backend sesuai zona waktu aplikasi.
- `$document`: array `available` (boolean), `display_name` (string|null),
  `download_url` (string|null). URL harus menuju route internal yang terotorisasi,
  bukan URL storage publik atau input pengguna.
- `$canRecordInspection`: boolean hasil otorisasi dan aturan pencatatan backend.
- `$canSubmit`: alias izin yang sama untuk integrasi frontend.
- `$dataVersion`: token versi/snapshot nonkosong yang diverifikasi backend.
- `$ossPortalUrl`: URL HTTPS sumber OSS resmi yang ditetapkan konfigurasi tepercaya.

Formulir hanya aktif jika izin true, token versi tersedia, dan route store terdaftar.
Hasil pemeriksaan final tidak dapat ditimpa. Status legacy tanpa record pemeriksaan
bukan bukti OSS dan dapat diperiksa manual lewat detail. Status tidak dikenal
menonaktifkan formulir. Ini bukan pengganti validasi/otorisasi server.

## Payload POST

| Input | Nilai |
| --- | --- |
| `oss_check_result` | `matched`, `mismatched`, `unconfirmed` |
| `administrative_result` | `complete`, `incomplete`, `unconfirmed` |
| `checked_on` | tanggal `YYYY-MM-DD` pemeriksaan manual |
| `decision` | `verified`, `rejected`, `pending` |
| `notes` | teks wajib, maksimal 5000 karakter |
| `data_version` | token versi data perusahaan |

Server wajib menolak tanggal masa depan, catatan kosong/whitespace, nilai di luar
daftar, serta keputusan verified tanpa OSS matched dan administrasi complete.
Petugas, metode pemeriksaan, dan snapshot ditentukan server. Kontrak tanggal
menggunakan date; jika database memakai checked_at, adaptasi harus mempertahankan
makna tanggal tanpa mengarang jam pemeriksaan.

Validasi gagal memakai redirect dengan errors dan old input. Konflik versi dan
kegagalan penyimpanan memakai pesan error aman dan mempertahankan input bila sesuai.
Flash `status` hanya diisi setelah transaksi database berhasil; flash `error`
untuk kegagalan. Tidak ada pesan sukses yang dihasilkan JavaScript.

Catatan detail `CompanyInspection.notes` bersifat admin-only. Jangan menyalinnya
ke `CompanyProfile.verification_notes`. Kolom profil menyimpan pesan umum sesuai
status; Blade perusahaan memakai `public_verification_message` tanpa membaca
catatan legacy. Notes/snapshot disembunyikan dari serialisasi model umum, sementara
controller pemerintah memetakan notes secara eksplisit setelah auth/Gate.

## Alur HTML dan konflik

GET index/detail merender Blade. Validasi POST gagal melakukan redirect dengan
errors dan old input; POST berhasil melakukan redirect ke detail dengan flash status.
Konflik HTML mengembalikan 409 dengan data terkini, pesan muat ulang, dan form
dinonaktifkan. Isian lama tidak otomatis diterapkan pada versi baru. Request JSON
tetap memakai kontrak API. Respons berisi data memakai Cache-Control no-store/private.

## Dokumen private dan blocker legacy

Upload baru dan unduhan memakai disk company_documents (storage/app/company-legal-documents),
dengan serve=false, terpisah dari local/public. Hanya referensi relatif berbentuk
company_documents/<nama-aman>.(pdf|jpg|jpeg|png). Pemeriksaan realpath membatasi
direktori dan menolak symlink yang keluar dari root. Query path dari pengguna
diabaikan. URL/path storage tidak dikirim ke Blade; tidak ada fallback public.

Uploader registrasi/edit dan downloader pemilik kini memakai service private yang
sama dengan pemerintah. Dokumen hilang atau referensi silang antarperusahaan
ditolak. Penyimpanan memastikan tipe, ukuran, keberhasilan tulis dan ukuran hasil;
kegagalan transaksi mengompensasi hanya berkas baru percobaan tersebut bila belum
direferensikan profil/snapshot. Dokumen lama tidak dihapus pada penggantian.

BLOCKER legacy: tidak ada berkas nyata yang dipindahkan/dihapus. Dokumen public saja tidak bisa
diunduh melalui route pemerintah, dan tidak dijamin rahasia hanya karena route
baru sudah dilindungi. Keberadaan alias/symlink/CDN publik perlu diaudit deployment.

Rencana migrasi terkoordinasi (belum dijalankan): inventaris referensi tanpa membuka
data ke publik; siapkan backup dan checksum; deploy unggah/unduh pemilik private
dengan otorisasi kepemilikan; salin dan verifikasi berkas di private; tutup akses
langsung direktori company_documents pada web server/CDN dan invalidasi cache;
uji akses admin/pemilik serta penolakan pihak lain; baru lakukan cleanup melalui
pekerjaan terpisah yang disetujui. Jangan aktifkan storage:link sebagai solusi.

## Pekerjaan berikutnya

Task 3.1.1.6 perlu memperluas pengujian integrasi, konkurensi pada DB target,
pengujian visual mobile/keyboard di browser, dan penyelesaian blocker dokumen lama.
Perubahan storage perusahaan harus dikoordinasikan dengan pemilik modul tim.
Disk local untuk CV dan disk public untuk aset sah tidak diubah. Jangan membuat
link atau alias web server untuk root disk company_documents. Windows memerlukan
ACL yang sesuai pada deployment. Kegagalan cleanup dicatat untuk penanganan manual;
tidak ada sweep otomatis berkas yang mungkin masih direferensikan.
