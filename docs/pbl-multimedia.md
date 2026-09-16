# Pengumpulan proyek multimedia

Untuk penerapan terbaru, ikuti [panduan multi sekolah](multi-school-deployment.md). Panduan tersebut menggantikan akses file publik/tautan sementara sebelumnya.

## Perilaku

- Siswa mengirim judul, level, serta jawaban teks dan/atau lampiran. Konteks mata pelajaran ditentukan dari topik.
- Lampiran: JPEG/PNG/GIF/WebP, MP4/MOV/WebM, MP3/WAV/OGG/M4A, PDF/TXT/DOC/DOCX/PPT/PPTX, ZIP untuk berkas sumber karya.
- Maksimal 5 lampiran, masing-masing 50 MiB, total 100 MiB. Isi file dan ekstensi divalidasi di server.
- Flutter menampilkan progres unggahan dan pesan kesalahan. Guru dan siswa dapat melihat lampiran, memutar media, serta mengunduh/menyimpan melalui menu berbagi perangkat.
- Pemutaran mengikuti dukungan codec perangkat/browser. MP4 H.264/AAC dan MP3 paling praktis untuk pengujian perangkat; berkas yang tidak dapat diputar tetap dapat diunduh.
- Rubrik universal: pemecahan masalah/inisiatif 25%, ketepatan metode/hasil 35%, pemahaman materi 25%, komunikasi/dokumentasi 15%. Guru memberi catatan setiap kriteria dan feedback umum.
- Nilai akhir disimpan dengan dua angka desimal agar hasil pembobotan tidak terpotong. Rollback migrasi lampiran mempertahankan presisi nilai ini.
- Tugas yang sudah dinilai tidak dapat dihapus atau dinilai ulang, mengikuti aturan sebelumnya.

## Kontrak API

`POST /api/pbl-projects`, Bearer token, multipart:

```
title: Judul karya
level: Dasar | Menengah | Lanjutan
topic_id: ID topik (opsional)
subject_id: ID mata pelajaran jika tidak menggunakan topik
description: Jawaban/penjelasan (opsional jika ada lampiran)
files[]: file pertama
files[]: file kedua
```

Field `file` lama tetap diterima. Respons siswa dan daftar proyek guru menyertakan `attachments` (name, mime_type, size dalam byte, url) serta `rubric`. Field `file_name`/`file_url` tetap tersedia untuk lampiran pertama agar aplikasi lama tetap kompatibel.

Lampiran baru berada di `storage/app/private/pbl_attachments`. Semua URL file memerlukan akun terautentikasi dengan hak akses sekolah/mapel yang sesuai. File lama dipindahkan ke penyimpanan privat melalui `schools:secure-files`; lihat panduan multi sekolah.

## Penerapan server

GitHub menyimpan kode. Server Laravel yang menjalankan API tetap perlu menerima perubahan, migrasi, dan konfigurasi unggahan.

1. Backup database dan storage, lalu terapkan kode backend.
2. Ikuti urutan maintenance, migrasi, dan pemindahan file privat dalam panduan multi sekolah. Jangan hanya menjalankan migrasi database.
3. Pastikan `APP_URL` menggunakan URL HTTPS API yang diakses aplikasi. Konfigurasi proxy HTTPS harus sesuai agar validasi signature URL berhasil.
4. Pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh PHP, dan storage bersifat persisten saat redeploy. Sertakan direktori private dalam backup.
5. Atur PHP untuk payload yang diizinkan aplikasi, misalnya:

   ```ini
   upload_max_filesize = 50M
   post_max_size = 110M
   max_file_uploads = 20
   max_input_time = 600
   max_execution_time = 180
   ```

   Jika memakai Nginx, sesuaikan `client_max_body_size 110m;` dan timeout proxy. Sesuaikan pula batas unggahan layanan hosting/CDN. Muat ulang layanan PHP/web server setelah mengubah konfigurasinya.
6. Build dan distribusikan ulang Flutter dari `/home/yaw/smk_animasi_app`. Tidak ada dependency baru. Backend baru perlu diterapkan sebelum aplikasi baru.
7. Uji dengan akun siswa dan guru pada perangkat sasaran: teks saja, gambar+audio+video, dokumen/ZIP, batas ukuran, unduh, pemutaran/seek, nilai dan feedback siswa. Uji jaringan lambat serta penolakan file terlalu besar.

## Verifikasi otomatis

- `php artisan test`: termasuk pengumpulan campuran, kompatibilitas data lama, validasi, pembatasan akses, akses file terautentikasi, penghapusan file, dan nilai berbobot.
- `flutter test test/pbl_multimedia_test.dart`: validasi form dan daftar lampiran.
- Analisis Dart pada lima berkas Flutter yang berubah.

Pengujian otomatis tidak menggantikan uji codec, menu berbagi, dan batas unggahan di hosting/perangkat sebenarnya.
