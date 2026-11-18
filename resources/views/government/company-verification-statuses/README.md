# Kontrak UI Task 3.1.2.1

PBI 3.1.2: monitoring status, Sprint 1, SP 3, KF-11. Blade sudah terhubung melalui
CompanyVerificationMonitoringController. API dan HTML memakai query/provenance yang
sama, tanpa request HTTP internal. Timeline membaca tabel riwayat yang sudah tersedia.
Integrasi diuji otomatis; visual browser belum diuji. Tidak ada data contoh yang
dipresentasikan sebagai data resmi.

## Layout dan keamanan

Kedua halaman extends government.layouts.app (Bootstrap 5.3.3). Navbar existing
tetap menuju antrean PBI 3.1.1; link Monitoring verifikasi ditambahkan hanya untuk
pemerintah aktif ketika route tersedia. Layout besar tidak diduplikasi.
Partial status monitoring terpisah agar status legacy/provenance tidak mengubah
kontrak status antrean lama. Isi halaman dilindungi @can access-government sebagai
defense-in-depth; backend tetap wajib auth + can:access-government. Jangan render
model mentah atau dump JSON model. Semua data di bawah adalah array terpilih,
bukan CompanyProfile/CompanyInspection yang diserialisasikan.

## Route HTML tersedia

| Nama | Method/URL usulan | Parameter |
| --- | --- | --- |
| government.company-verification-statuses.index | GET /pemerintah/status-verifikasi-perusahaan | status, q, page, per_page |
| government.company-verification-statuses.show | GET /pemerintah/status-verifikasi-perusahaan/{companyProfile} | companyProfile, filter kembali, history_page, history_per_page |

Pencarian GET tanpa CSRF karena read-only. Belum ada form perubahan status.
Jika route belum terdaftar, filter dinonaktifkan dan tautan detail tidak dibuat.

## Index

- $companies: LengthAwarePaginator<array>, nullable bila backend belum tersedia.
  Controller mengambil seluruh perusahaan (bukan query pending antrean lama),
  memfilter status tervalidasi, mencari nama/NPWP dengan binding/escape wildcard,
  mengurutkan stabil, dan membatasi per_page (usulan 1–50, default 15).
- Setiap row: id (integer), company_name (string), npwp (string|null),
  verification_status (string), provenance (enum presentasi di bawah).
- $filters: ['status' => ''|'pending'|'verified'|'rejected', 'q' => string].
  '' berarti Semua. Backend menolak array, status asing dan q >100 karakter.
- $pageError: string|null, pesan umum aman dari controller, bukan exception mentah.
- Pagination mempertahankan status, q, per_page. Pencarian baru tidak membawa page.
  Backend dianjurkan redirect halaman di luar rentang ke halaman valid; view juga
  punya state defensif, tanpa teks 0–0 atau klaim antrean kosong yang menyesatkan.

## Provenance (bukan status DB baru)

| Nilai | Arti yang harus ditetapkan backend |
| --- | --- |
| official | Record CompanyInspection tersedia dan decision konsisten dengan verification_status |
| uninspected | Pending dan belum ada record pemeriksaan |
| legacy | Verified/rejected tanpa record pemeriksaan resmi |
| unknown | Status asing, ketidaksesuaian record/status, atau sumber belum dipastikan |

Pending official berarti perlu pemeriksaan lanjutan. Jangan menilai official
hanya dari verified_by, notes, updated_at, atau label status. Provenance adalah
metadata view yang dihitung, bukan penambahan status atau kolom saat task UI.

## Show

- $companyData: array|null, berisi id, company_name, npwp, address, city, province,
  verification_status, provenance. Identitas ditampilkan read-only.
- $inspectionData: null atau array oss_check_result, administrative_result,
  checked_on (tanggal tampilan berformat), reviewer_name. Opsional notes hanya
  dipasok setelah otorisasi catatan, tidak diambil dari verification_notes legacy.
- $canViewInspectionNotes: boolean strict, default false. Hanya pemerintah aktif
  boleh true. View juga memakai Gate; jangan menyuplai notes ke client yang tidak
  berwenang. Tidak ada snapshot, storage path, token akun, password atau hash.
- $returnFilters: array query tervalidasi status, q, page, per_page untuk kembali
  ke daftar. Jangan memakai seluruh request query tanpa allowlist.
- $canInspect: boolean server, true hanya pending dengan provenance official atau
  uninspected. Tautan menuju government.company-inspections.show yang sudah ada;
  tidak ada editor/PATCH baru. Legacy, unknown dan hasil final tidak actionable.
- $historyAvailable: boolean strict; controller saat ini memasok true.
- $historyEntries: LengthAwarePaginator<array> bila layanan aktif. Gunakan pageName
  history_page, terpisah dari page daftar yang dibawa sebagai konteks kembali.
- Tiap entry: old_status_label, new_status_label, changed_at_label, actor_label,
  reason_label (semuanya string tampilan terpilih dan di-escape). Alasan bukan
  salinan notes/snapshot; backend harus mengikuti kebijakan internal pemerintah.
- Urutan timeline harus deterministik dari event nyata, bukan rekonstruksi
  created_at/updated_at perusahaan. Timestamp disimpan UTC dan ditampilkan dengan
  zona waktu yang disepakati; jangan mengarang waktu atau aktor.
- history_page: integer 1–100000; history_per_page: integer 1–50, default 15.
  Keduanya divalidasi terpisah dari konteks pagination daftar. Urutan sequence
  terbaru dahulu; label changed_at menyertakan timezone aplikasi secara eksplisit.
- Controller tidak memasok model CompanyProfile, CompanyInspection, User atau
  history mentah ke view. Paginator berisi array allowlist; reviewer hanya nama.
- Response data sensitif memakai Cache-Control: no-store, private. Tidak ada
  pencatatan/backfill pada GET. Halaman daftar out-of-range diarahkan ke halaman
  valid; timeline out-of-range menyediakan tautan halaman pertama.

Timeline selalu menjelaskan batas data lama: "Riwayat sebelum pencatatan diaktifkan
tidak tersedia." Ketika historyAvailable=false, jangan memasok histori buatan.
Placeholder bukan bukti acceptance criterion riwayat telah terpenuhi.

## State dan validasi berikutnya

Tersedia state backend belum terhubung, koleksi kosong, pencarian tanpa hasil,
filter invalid, halaman di luar rentang, pesan error umum, pemeriksaan belum ada,
provenance unknown, serta layanan riwayat belum ada/kosong/gagal dimuat.
Render server-side tidak memerlukan skeleton loading. Tabel focusable dengan
caption dan scope; label input, aria error, heading dan grid mobile disediakan.
Integrasi controller diuji pada SQLite in-memory. Uji visual browser, keyboard
nyata dan screen reader tetap pekerjaan acceptance terpisah.

Kontrak Task 3.1.2.2 menjadi acuan aturan transisi dan provenance terpusat.
Tidak ada tombol pembaruan status/final, notifikasi, scheduler atau OSS otomatis.
PBI 3.1.1 dan blocker dokumen legacy public tidak diubah oleh task ini.
