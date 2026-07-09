# Stack Cleanup — Laravel 12 + Filament 4 + Tailwind v4 + Vite (web-shelf)

Dokumen ini mencatat langkah-langkah pembersihan yang telah dilakukan pada proyek **web-shelf** untuk memastikan basis kode sepenuhnya bersih, efisien, dan selaras dengan stack modern:

| Komponen | Versi Aktif (`web-shelf`) | Keterangan |
|---|---|---|
| **Laravel Framework** | **12.63.0** | Framework modern standar (tanpa artefak legacy Laravel 9/10/11) |
| **PHP Engine** | **8.4.23** | Mendukung fitur modern PHP 8.4 |
| **Filament** | **v4.11.8** | Panel admin modern dengan `schemas` dan `infolists` |
| **Tailwind CSS** | **v4.3.2** | Setup CSS-first menggunakan plugin `@tailwindcss/vite` |
| **Vite & Frontend** | **v8.1.0** | Bundler super cepat dengan `laravel-vite-plugin` |

Dokumen ini berfungsi sebagai **catatan standar & checklist verifikasi** bahwa proyek **web-shelf** benar-benar berjalan di atas stack Laravel 12 & Filament 4 yang bersih, tanpa menyisakan artefak boilerplate, konfigurasi mati, atau API deprecated.

---

## 0. Verifikasi Versi Stack

Anda dapat memverifikasi kondisi stack saat ini melalui perintah terminal berikut:

```bash
php artisan --version          # => Laravel Framework 12.63.0
php artisan about              # => Memperlihatkan versi PHP 8.4.23, Filament v4.11.8, dll.
npm list tailwindcss @tailwindcss/vite vite   # => Memperlihatkan versi Tailwind v4.3.2 & Vite v8.1.0
```

Pada kondisi yang telah dibersihkan, output `php artisan about` untuk Filament memuat:
`Packages: filament, forms, notifications, support, tables, actions, infolists, schemas, widgets`
(Kehadiran `schemas` dan `infolists` adalah penanda resmi arsitektur **Filament v4**).

---

## 1. Pembersihan Tailwind v4 + Vite (`package.json`, `postcss.config.js`)

> **Konteks:** Tailwind CSS v4 beralih ke arsitektur **CSS-first** melalui plugin `@tailwindcss/vite` yang langsung terintegrasi dengan Vite (`vite.config.js`). Akibatnya, penggunaan PostCSS (`postcss.config.js` dan paket `@tailwindcss/postcss`) menjadi redundan dan meremehkan performa instalasi serta build frontend.

### Yang Dilakukan
1. **Menghapus Konfigurasi Redundan:**
   - Menghapus file `postcss.config.js` karena seluruh pemrosesan CSS sudah ditangani langsung oleh `@tailwindcss/vite` di `vite.config.js`.
2. **Membersihkan `devDependencies` di `package.json`:**
   - Menghapus `@tailwindcss/postcss`
   - Menghapus `postcss`
   - Menghapus `postcss-nesting`
3. **Memastikan Plugin `@tailwindcss/vite` Aktif:**
   - Memastikan `vite.config.js` dan `resources/css/app.css` (`@import "tailwindcss";`) terkonfigurasi dengan benar tanpa dependensi PostCSS tambahan.

### Verifikasi Frontend Build
```bash
npm install && npm run build
```
Output `npm run build` dengan Vite v8.1.0 sukses tanpa peringatan PostCSS:
```text
public/build/manifest.json                0.77 kB │ gzip:  0.26 kB
public/build/assets/app-B-7B9L68.css     28.15 kB │ gzip:  5.98 kB
public/build/assets/theme-j4CIweLz.css  684.60 kB │ gzip: 73.38 kB
public/build/assets/mekaya-Drf7ba5Z.js    3.46 kB │ gzip:  1.08 kB
✓ built in 1.36s
```

---

## 2. Pembersihan Broadcasting & JS Bootstrap (`routes/channels.php`, `config/broadcasting.php`, `resources/js/`)

> **Konteks:** Proyek **web-shelf** adalah aplikasi manajemen aset yang tidak menggunakan fitur real-time broadcasting (Pusher/Laravel Echo). Di `bootstrap/app.php`, metode `->withRouting()` tidak mendefinisikan parameter `channels:`, sehingga `routes/channels.php` tidak pernah dimuat oleh kernel Laravel (`dead code`).

### Yang Dilakukan
1. **Menghapus File Konfigurasi & Rute Redundan:**
   - Menghapus `routes/channels.php` (karena tidak didaftarkan di `bootstrap/app.php`).
   - Menghapus `config/broadcasting.php` (Laravel 12 memiliki default konfigurasi internal; file ini bisa dihapus dengan aman bila tidak diubah dari standar).
2. **Membersihkan Boilerplate JS (`resources/js/`):**
   - Menghapus file `resources/js/bootstrap.js` (berisi boilerplate Axios dan konfigurasi Laravel Echo/Pusher yang tidak terpakai).
   - Menghapus baris `import './bootstrap';` dari `resources/js/app.js` agar build modul JS bersih dan ringan.
3. **Menyesuaikan Environment (`.env.example`):**
   - Mengubah `BROADCAST_DRIVER=log` menjadi `BROADCAST_CONNECTION=null` agar selaras dengan standar penamaan Laravel 12 serta menonaktifkan driver broadcasting sejak awal instalasi.

---

## 3. Pembersihan Middleware Redundan & Deprecated (`app/Http/Middleware/`)

> **Konteks:** Laravel 11/12 telah memindahkan handling middleware dasar seperti `TrimStrings`, `PreventRequestsDuringMaintenance`, dan `TrustProxies` ke core framework. File wrapper custom di `app/Http/Middleware/` yang hanya mewarisi class framework tanpa modifikasi tambahan adalah redundan. Selain itu, `AuthenticateSession` (untuk penanganan session SPA/Multi-device lama) sudah didepresiasi dan tidak diperlukan lagi dalam konfigurasi panel Filament modern.

### Yang Dilakukan
1. **Menghapus File Middleware Wrapper Redundan:**
   - Menghapus `app/Http/Middleware/TrimStrings.php`
   - Menghapus `app/Http/Middleware/PreventRequestsDuringMaintenance.php`
   - Menghapus `app/Http/Middleware/TrustProxies.php`
2. **Membersihkan Penggunaan `AuthenticateSession` di `AdminPanelProvider.php`:**
   - Menghapus `use Illuminate\Session\Middleware\AuthenticateSession;` dari bagian import.
   - Menghapus `AuthenticateSession::class,` dari daftar `->middleware([...])` di `app/Providers/Filament/AdminPanelProvider.php`.
   - Mempertahankan `config/sanctum.php` yang menggunakan class milik Sanctum (`Laravel\Sanctum\Http\Middleware\AuthenticateSession`) untuk kompatibilitas API jika diperlukan.

---

## 4. Migrasi API Filament v4 (`->reactive()` ke `->live()`)

> **Konteks:** Pada Filament v3, metode `->reactive()` digunakan untuk membuat komponen form bereaksi dan memicu re-render saat nilainya berubah. Di Filament v4, `->reactive()` telah didepresiasi dan hanya bertindak sebagai alias internal untuk `->live()`. Untuk menjaga konsistensi codebase dengan standar v4 murni, seluruh pemanggilan `->reactive()` dimigrasikan ke `->live()`.

### Yang Dilakukan
Mengganti total **15 pemanggilan** `->reactive()` menjadi `->live()` di 3 file resource utama `web-shelf`:

1. **`app/Filament/Resources/AssetTransferResource.php`** (3 tempat)
   - Field `Select::make('business_entity_id')`
   - Field `Select::make('from_user_id')`
   - Field `Select::make('asset_id')` di dalam `Repeater::make('details')`
2. **`app/Filament/Resources/CustomAssetAttributeResource.php`** (3 tempat)
   - Field `Select::make('input_type')`
   - Field `Toggle::make('is_notifiable')`
   - Field `Select::make('notification_type')`
3. **`app/Filament/Resources/AssetResource.php`** (9 tempat)
   - Field `Select::make('category_id')`
   - Field `Select::make('custom_attribute_id')` / repeater custom attributes
   - Field `TextInput::make('attribute_value')` (Text)
   - Field `TextInput::make('attribute_value')` (Number)
   - Field `Textarea::make('attribute_value')` (Textarea)
   - Field `DatePicker::make('attribute_value')` (Date)
   - Field `DatePicker::make('document_expires_at')`
   - Field `Select::make('condition_status')`
   - Field `Select::make('nbh_status')`

### Verifikasi Migrasi
```bash
grep -rn "reactive()" app/Filament/   # => Hasil: Kosong (0 matches)
```

---

## 5. Ringkasan Perubahan File (Diff Summary)

| Jenis Perubahan | Jumlah | Daftar File |
|---|:---:|---|
| **Dihapus (Deleted)** | **7 file** | `postcss.config.js`<br>`routes/channels.php`<br>`config/broadcasting.php`<br>`resources/js/bootstrap.js`<br>`app/Http/Middleware/TrimStrings.php`<br>`app/Http/Middleware/PreventRequestsDuringMaintenance.php`<br>`app/Http/Middleware/TrustProxies.php` |
| **Dimodifikasi (Modified)** | **8 file** | `package.json` & `package-lock.json`<br>`resources/js/app.js`<br>`.env.example`<br>`app/Providers/Filament/AdminPanelProvider.php`<br>`app/Filament/Resources/AssetTransferResource.php`<br>`app/Filament/Resources/CustomAssetAttributeResource.php`<br>`app/Filament/Resources/AssetResource.php` |

---

## 6. Verifikasi Akhir Codebase (`php artisan test` & Build)

Seluruh perubahan di atas telah diverifikasi secara ketat melalui rangkaian pengujian build frontend, pembersihan cache, dan pengujian unit/feature test:

```bash
# 1. Bersihkan semua cache framework
php artisan optimize:clear
# => config, cache, compiled, events, routes, views, blade-icons, filament cleared successfully.

# 2. Cache konfigurasi dan periksa status framework
php artisan config:cache && php artisan about
# => Configuration cached successfully. Laravel 12.63.0, PHP 8.4.23, Broadcasting: null, Filament v4.11.8.

# 3. Verifikasi daftar rute aplikasi
php artisan route:list
# => Seluruh rute web, API, dan halaman admin Filament terdaftar dan dapat dimuat tanpa error.

# 4. Jalankan seluruh test suite web-shelf
php artisan test
```

### Hasil `php artisan test` (100% Lulus):
```text
  PASS  Tests\Feature\AssetRequestResourceInfolistLayoutTest
  PASS  Tests\Feature\AssetRequestResourceTableActionTest
  PASS  Tests\Feature\AssetRequestTest
  PASS  Tests\Feature\AssetResourceInfolistLayoutTest
  PASS  Tests\Feature\AssetResourceTableActionTest
  PASS  Tests\Feature\AssetTransferDocumentLifecycleTest
  PASS  Tests\Feature\ExampleTest
  PASS  Tests\Feature\FonnteWhatsappNotificationTest
  PASS  Tests\Feature\PdfAuthorizationTest
  PASS  Tests\Feature\PublicAssetRequestTest
  PASS  Tests\Feature\WhatsappAssetIntegrationTest

  Tests:    117 passed (478 assertions)
  Duration: 6.95s
```

**Kesimpulan:** Basis kode **web-shelf** kini berada dalam kondisi sangat bersih, modern, dan lulus uji (`117 passed`), siap untuk pengembangan fitur baru maupun deployment ke production.