# Storage S3 untuk Shelf

Gunakan `.env.production.example` sebagai template. Isi kredensial di `.env`
server atau `.env.production` yang diabaikan Git. Pertahankan `APP_KEY` production
yang sudah dipakai. Jangan menambahkan file ENV berisi kredensial ke Git.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shelf.completeselular.com
LOG_STACK=daily
LOG_LEVEL=info
SESSION_SECURE_COOKIE=true
FILESYSTEM_DISK=s3
PUBLIC_FILESYSTEM_DRIVER=s3
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=shelf
AWS_ENDPOINT=https://storage.completeselular.com
AWS_URL=https://storage.completeselular.com/shelf
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Isi `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `WAG_TOKEN`, dan kredensial
database di server. `MAIL_MAILER=log` tetap tidak mengirim email; ubah ke SMTP
hanya setelah konfigurasi email tersedia.

## Lokasi file

- Disk `public` adalah nama disk upload aplikasi. Dengan
  `PUBLIC_FILESYSTEM_DRIVER=s3`, foto, lampiran, kop surat, dan dokumen upload
  disimpan di S3 dengan visibility **private**. Nama disk ini dipertahankan agar
  alur upload/delete yang sudah ada tetap konsisten.
- PDF riwayat QR, workbook rekonsiliasi, dan ekspor Filament baru menggunakan
  `FILESYSTEM_DISK=s3`. Riwayat QR menyimpan `file_disk`; workbook menyimpan
  `stored_disk`. Data lama diberi nilai `local` oleh migrasi, sehingga masih
  dapat dibaca sebelum file dipindahkan.
- Preview menggunakan URL S3 bertanda tangan selama satu jam. Muat ulang halaman
  jika tautan kedaluwarsa. Tautan di spreadsheet menggunakan URL aplikasi yang
  tidak kedaluwarsa dan membutuhkan login serta signature yang valid.
- PDF menyematkan kop surat/lampiran dari isi object. Parser Excel menggunakan
  salinan sementara yang dihapus setelah pemrosesan.
- Session, cache, log, font PDF, dan upload sementara Livewire tetap lokal.
  Folder `storage` dan `bootstrap/cache` harus dapat ditulis oleh PHP.

Bucket tidak perlu dibuka ke publik. Izinkan CORS `GET` dan `HEAD` dari origin
`https://shelf.completeselular.com` untuk preview browser. Expose `ETag`,
`Content-Length`, dan `Content-Type`. Kredensial aplikasi perlu izin read,
write, delete, list, dan pengaturan ACL object pada bucket `shelf`.

## Deployment dan pemindahan file lama

Jalankan dari checkout **server yang memiliki file dan database lama**. Pakai
PHP yang sesuai `composer.lock` (minimal 8.4.1). Ambil backup database dan
storage sebelum deployment. Siapkan ENV baru, lalu lakukan perpindahan saat
maintenance dan worker tidak sedang menulis file.

1. Aktifkan maintenance (`php artisan down`) dan hentikan service Horizon yang
   digunakan server. Tarik commit PR yang sudah disetujui.
2. Pasang dependensi dengan `composer install --no-dev --prefer-dist --optimize-autoloader`.
   Salin konfigurasi production ke `.env` server dengan permission terbatas.
3. Jalankan:

   ```bash
   php artisan config:clear
   php artisan migrate --force
   php artisan storage:check-s3
   php artisan storage:migrate-to-s3 --dry-run
   php artisan storage:migrate-to-s3
   php artisan config:cache
   php artisan view:clear
   ```

4. Jika semua berhasil, hidupkan kembali service Horizon dan jalankan
   `php artisan up`. Periksa upload foto, preview, cetak PDF, download riwayat,
   serta compare workbook audit.

`storage:check-s3` membuat satu object privat untuk menguji write/ACL/read/delete,
lalu menghapusnya. Perintah ini tidak mengakses database production.

`storage:migrate-to-s3` menyalin `storage/app/public`, direktori upload aplikasi
lama di `storage/app` (seperti `assets` dan `asset-documents`), file QR/audit yang dirujuk
database, dan ekspor Filament yang sudah selesai. Hash SHA-256 diverifikasi;
object yang sudah ada dengan isi berbeda tidak ditimpa. Lokasi disk pada record
hanya diperbarui setelah file berhasil diverifikasi. Perintah bisa diulang dan
**tidak menghapus file sumber**. Jika ada error, perbaiki penyebabnya dan ulangi
sebelum membuka maintenance.

Migrasi hanya menyalin file yang tersedia; file lama yang sebelumnya sudah
tertimpa tidak dapat dipulihkan oleh proses ini. Jangan rollback kode/database
setelah record menunjuk S3 tanpa rencana pengembalian lokasi file.
