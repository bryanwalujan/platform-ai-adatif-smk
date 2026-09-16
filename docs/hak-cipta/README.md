# Paket dokumentasi Pijar PBL

**Nama usulan:** Pijar PBL — Platform Pembelajaran Adaptif Berbasis PBL untuk Siswa SMK.
Nama pada antarmuka kode acuan masih Belajar Adaptif; paket ini tidak mengubah aplikasi.

## Isi

1. `01-Panduan-Penggunaan`: panduan akun, admin, guru, siswa, materi multimedia, kuis, PBL, penilaian, dan bantuan.
2. `02-Deskripsi-dan-Artefak`: deskripsi program, inventaris fitur, empat diagram, skenario dokumentasi, dan catatan verifikasi.
3. `03-Template-Tangkapan-Layar`: 12 lembar yang **belum berisi screenshot**. Lengkapi dengan tangkapan layar asli menggunakan akun uji.
4. `04-Cuplikan-Kode`: cuplikan implementasi sekolah, multimedia, rubrik, dan level PBL; bukan seluruh kode sumber.
5. `diagram/`: diagram PNG untuk dokumen, SVG untuk cetak, dan DOT yang dapat diedit.
6. `sumber/`: generator dokumen dan indeks sumber cuplikan.
7. `MANIFEST.json`: daftar berkas dan checksum SHA-256 (manifest tidak menghitung dirinya sendiri).

PDF untuk dibaca/dicetak; DOCX untuk penyuntingan. File ZIP merupakan bundel kerja,
bukan pernyataan bahwa format tersebut adalah format unggahan wajib suatu layanan.

## Sebelum digunakan sebagai lampiran

- Isi nama pencipta, institusi, pemegang hak, tahun dan tanggal berdasarkan data sebenarnya.
- Tetapkan nama final, lalu selaraskan nama pada dokumen dan antarmuka.
- Lengkapi screenshot asli. Jangan menyertakan token, kata sandi, kode OTP, atau data pribadi siswa nyata.
- Ekspor kembali PDF setelah menyunting DOCX; PDF yang diberikan masih memuat kolom template.
- Tinjau kembali sesuai formulir/ketentuan layanan pencatatan yang Anda gunakan. Paket ini tidak memuat klaim persetujuan, nomor permohonan, tanda tangan, atau persyaratan hukum resmi.

## Acuan dan batas bukti

Laravel `5e3356b`, Flutter `c8ec620`; tanggal penyusunan 16 September 2026.
Catatan pengujian merujuk hasil implementasi sebelumnya, tidak dijalankan ulang untuk penyusunan dokumentasi.
Diagram didasarkan pada kode dan merupakan ringkasan; diagram relasi bukan ERD lengkap.
Tidak ada data produksi, kredensial, database, atau dependensi vendor yang dibundel.

## Membangun ulang dokumen

Generator memerlukan Python, python-docx, Graphviz (`dot`), serta repositori Laravel pada struktur ini.
Jalankan dari root repositori:

```bash
python3 docs/hak-cipta/sumber/buat_dokumen.py
libreoffice --headless --convert-to pdf --outdir docs/hak-cipta docs/hak-cipta/*.docx
```

Generator akan menimpa DOCX dengan template, jadi simpan hasil isian manual terpisah.
Untuk hasil yang identik dengan cuplikan awal, gunakan kode Laravel versi acuan.
