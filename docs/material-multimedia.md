# Materi multimedia

Manajemen konten Flutter guru menyediakan daftar materi per topik, pratinjau,
serta form tambah/edit. Materi bisa berisi penjelasan, tautan video, dan beberapa
lampiran. PBL tetap digunakan sebagai pendekatan pembelajaran lintas mapel SMK.

## Batas unggahan

- Maksimal 5 lampiran per materi, 50 MB per file, total 100 MB.
- Gambar: JPG, JPEG, PNG, GIF, WebP.
- Video/audio: MP4, MOV, WebM, MP3, WAV, OGG, M4A.
- Dokumen: PDF, TXT, DOC/DOCX, PPT/PPTX, ZIP.
- Dukungan codec pemutar mengikuti perangkat. Tautan video langsung dapat diputar;
  tautan halaman seperti YouTube dapat disalin untuk dibuka melalui browser.
- Lampiran disajikan melalui endpoint autentikasi dan pembatasan sekolah/mapel.
  Gambar ditampilkan, video/audio diputar, dokumen dapat diunduh.
- Edit mempertahankan lampiran lama kecuali dihapus secara eksplisit.

## Deploy Laravel

Setelah mengambil commit terbaru pada server:

```bash
git pull origin main
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

Migration baru: `2026_09_16_100000_add_media_files_to_materials`.
Migration ini menambahkan kolom JSON nullable; materi lama tetap bisa dibaca.
Flutter perlu dibangun/dipasang ulang agar tampilan baru tersedia.

Sesuaikan batas PHP/web server hosting untuk mendukung batas aplikasi:
`upload_max_filesize = 50M`, `post_max_size = 110M`, dan
`max_file_uploads` minimal 5. Batas request proxy/web server juga harus cukup.
Pengaturan ini dilakukan melalui konfigurasi PHP hosting, bukan `.env` Laravel.

## Verifikasi manual setelah deploy

Masuk sebagai guru, pilih mapel dan topik, buat materi dengan gambar, video/audio,
dan PDF. Buka sebagai siswa terdaftar dan coba pemutar/unduhan. Edit judul tanpa
menghapus lampiran, lalu coba hapus satu lampiran. Akun sekolah/mapel lain harus
tidak dapat membuka URL lampiran tersebut.
