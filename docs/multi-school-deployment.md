# Penerapan multi sekolah dan PBL universal

## Hasil perubahan

- Setiap akun terikat ke satu sekolah. Email tetap unik di seluruh aplikasi: satu email = satu akun = satu sekolah. Login tetap memakai email dan password; sekolah ditentukan dari akun, bukan pilihan di request.
- Pendaftaran baru wajib kode sekolah aktif. Kode sekolah berbeda dari kode kelas/mapel. Guru baru tetap menunggu persetujuan admin sekolahnya.
- Admin mengelola pengguna, persetujuan guru, nama/kode sekolah, dan mata pelajaran hanya di sekolahnya sendiri. Tidak ada superadmin lintas sekolah di aplikasi. Pembuatan sekolah/admin pertama dilakukan operator server lewat Artisan.
- Seluruh data lama masuk ke **Sekolah Utama**, kode awal **SEKOLAH-UTAMA**. Semua relasi, ID, akun, dan nilai lama dipertahankan. Setelah deploy, admin lama perlu mengganti nama sekolah dan merotasi kode awal melalui Pengaturan Sekolah.
- Sekolah akun tidak dapat diubah lewat profil. Pemindahan akun beserta riwayat belajar antar sekolah bukan fitur yang didukung.
- Model data memakai `school_id`, scope query, dan validasi relasi. Konteks sekolah ditentukan middleware dari akun terautentikasi, berlaku pula pada admin dan unduhan. Header/query/body `school_id` bukan otoritas.
- Proses `bkt:fit` melatih parameter tiap sekolah secara terpisah, termasuk fallback lintas mapel **dalam satu sekolah**. Pengingat otomatis juga menjalankan konteks sekolah masing-masing.
- Flutter menampilkan sekolah aktif, meminta kode saat pendaftaran, menyediakan pengaturan sekolah admin, dan mereset tampilan/notifikasi saat berganti akun.

## Rubrik PBL universal

| Kriteria | Bobot | Fokus |
| --- | --- | --- |
| Pemecahan Masalah & Inisiatif | 25% | Solusi, penalaran, inisiatif, orisinalitas pendekatan |
| Ketepatan Metode & Hasil | 35% | Prosedur/teknik sesuai mapel, akurasi, bukti pendukung |
| Pemahaman Materi | 25% | Konsep, argumentasi, penerapan materi |
| Komunikasi & Dokumentasi | 15% | Kejelasan, susunan, rujukan, dokumentasi proses |

Format pengumpulan (teks, gambar, video, audio, dokumen, ZIP) adalah media bukti; siswa tidak wajib memakai multimedia untuk memperoleh nilai tinggi. Kunci JSON rubrik dan bobot lama dipertahankan agar nilai terdahulu tetap konsisten. Nilai akhir memakai dua angka desimal.

## Urutan perintah server

Backup database, `.env`, dan **seluruh storage** terlebih dahulu. Jalankan dari root Laravel. Tidak perlu menjalankan seeder/reset database.

```bash
php artisan down
# Ambil kode backend yang telah di-push.
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan schools:secure-files
php artisan config:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

**Hentikan urutan jika satu perintah gagal. Jangan menjalankan `up` sebelum migrasi dan `schools:secure-files` berhasil.** Pastikan `.env` server tetap benar setelah pembaruan kode, termasuk database, APP_KEY, dan APP_URL. Perintah `schools:secure-files` memverifikasi SHA-256 sebelum menghapus salinan publik, mempertahankan file asli jika isi tujuan berbeda, dan aman dijalankan ulang. Gunakan `optimize:clear` lebih dulu agar disk tidak masih menunjuk lokasi lama.

Dua migrasi baru (yang belum tercatat akan dijalankan otomatis):

- `2026_09_15_000000_add_attachments_to_pbl_projects.php` — beberapa lampiran dan nilai desimal.
- `2026_09_16_000000_add_school_isolation.php` — sekolah, backfill data lama, foreign key/index sekolah, dan kolom foto bila belum ada.

Rollback multi sekolah ditolak jika sudah ada lebih dari satu sekolah, karena menghapus pembatas sekolah akan mencampur data. Gunakan backup dan prosedur pemulihan terencana bila diperlukan.

## Penyimpanan dan akses file

Nama disk Laravel `public` dipertahankan demi kompatibilitas kode, tetapi root-nya sekarang **`storage/app/private/school_files`**. Lampiran PBL baru tetap di `storage/app/private/pbl_attachments`.

- File lama dari `storage/app/public` dipindahkan oleh `schools:secure-files`.
- Jangan membuat symlink web ke `storage/app/private`; konfigurasi `filesystems.links` tidak lagi menerbitkan file sekolah.
- Pastikan direktori private persisten saat redeploy dan ikut backup.
- API file membutuhkan Bearer token; tautan tanpa login, termasuk URL bertanda tangan lama, tidak memberi akses.
- Panel web memakai route file tersendiri dengan sesi login; Flutter mengirim token pada gambar, audio, video, dan unduhan.
- Flutter web mengambil media melalui permintaan terautentikasi sebelum memutarnya, karena elemen media browser tidak dapat mengirim header Bearer. Untuk file besar, pemutaran awal memerlukan waktu unduh dan memori perangkat.
- Apabila hosting sebelumnya menyalin/caching file publik ke CDN atau folder publik lain, hapus salinan/cache tersebut saat maintenance. Perintah ini hanya memindahkan file di storage aplikasi.

Untuk 5 file, maksimum 50 MiB/file dan total 100 MiB, contoh konfigurasi PHP:

```ini
upload_max_filesize = 50M
post_max_size = 110M
max_file_uploads = 20
max_input_time = 600
max_execution_time = 180
```

Sesuaikan pula batas hosting/CDN/web server, misalnya Nginx `client_max_body_size 110m;`, lalu reload layanan. `APP_URL` harus mengarah ke API HTTPS yang benar. Izinkan origin Flutter web yang digunakan dalam CORS, termasuk header Authorization.

## Menambah sekolah baru

```bash
php artisan schools:create "SMA Contoh" --code=SMA-CONTOH
php artisan make:admin --school=SMA-CONTOH
```

`make:admin` meminta nama, email, dan password secara interaktif; hindari menaruh password dalam shell history. Jika `--code` tidak diisi pada `schools:create`, kode acak dibuat otomatis. Login sebagai admin sekolah baru, lalu bagikan kode sekolah kepada guru/siswa. Guru membuat mata pelajaran, kemudian membagikan kode kelas setelah akun disetujui.

Admin lama dapat mengubah **Sekolah Utama** melalui `/admin/school` di web atau tombol Pengaturan Sekolah di dashboard admin Flutter.

## Flutter dan verifikasi

Flutter hanya di-commit lokal sesuai permintaan. Distribusikan build Flutter terbaru bersamaan dengan backend baru: aplikasi lama belum mengirim kode sekolah saat pendaftaran dan belum mengautentikasi pratinjau multimedia.

```bash
cd /home/yaw/smk_animasi_app
flutter pub get
flutter test test/pbl_multimedia_test.dart test/school_test.dart
flutter build apk --release
```

Lakukan uji pada hosting/perangkat sasaran dengan dua sekolah: register dan login, persetujuan guru, pencarian siswa, kode kelas, materi/RPP, tugas campuran, penilaian dan feedback, unduh/putar/seek media, serta logout/login akun sekolah berbeda. Mengetahui ID atau URL sekolah lain tetap tidak boleh memberi akses.

Tes backend memakai database SQLite terisolasi, bukan database server. Konfigurasi unggahan, migrasi produksi, serta dukungan codec perangkat tetap perlu diverifikasi setelah penerapan.
