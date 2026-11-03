# ☕ POS API — Production-Ready Point of Sale Backend

[![PHP Version](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel Framework](https://img.shields.io/badge/Laravel-11%20%2F%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Pest Test Suite](https://img.shields.io/badge/Pest%20Tests-31%20Passed%20(100%25)-brightgreen?logo=pest&logoColor=white)](https://pestphp.com)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Backend RESTful API untuk sistem **Point of Sale (POS)** modern berstandar industri. Dirancang dengan prinsip *Clean Architecture*, *Security-First*, dan penanganan *Concurrency* (pencegahan race condition & database deadlock).

---

## 🌟 Fitur Utama

- **🔐 Autentikasi & RBAC (Role-Based Access Control)**:
  - Token Bearer berbasis Laravel Sanctum.
  - Pembagian hak akses berjenjang: **Admin**, **Manager**, dan **Cashier**.
  - Proteksi *Brute-Force Attack* pada login menggunakan `RateLimiter`.
  - **Financial Privacy**: Harga modal (HPP / *Cost Price*) otomatis disembunyikan dari kasir.

- **📦 Manajemen Katalog & Kategori Produk**:
  - Dukungan pencarian cepat melalui **Barcode Scanner** (`EAN-13`) dan kode unik **SKU**.
  - Optimasi query kasir dengan *Composite Index* `(category_id, is_active)`.
  - Notifikasi / endpoint khusus produk dengan stok menipis (`/api/products/low-stock`).

- **📊 Inventory Audit Trail & Mutasi Stok**:
  - Setiap perubahan stok fisik dicatat ke kartu stok (`stock_movements`) secara terperinci (`Restock`, `Adjustment/Opname`, `Damage`, `Sale`, `Return`).
  - Proteksi stok minus menggunakan **Pessimistic Locking (`lockForUpdate`)** dan transaksi atomik.

- **⚡ Core POS Checkout Engine**:
  - **Deadlock Prevention**: Pengurutan ID produk secara deterministik sebelum penguncian database pada transaksi multi-item.
  - **Price Snapshotting**: Nama barang, harga modal (HPP), dan harga jual dikunci pada saat transaksi terjadi ke tabel `order_items` (kenaikan harga di masa depan tidak akan mengubah riwayat struk lampau).
  - Penomoran faktur unik berurutan per hari (`INV-YYYYMMDD-XXXX`).

- **💳 Pembayaran & Pembatalan Struk (Void Order)**:
  - Pembayaran **Tunai (*Cash*)** dengan kalkulasi uang kembalian (*change due*) yang akurat.
  - Pembayaran **Non-Tunai (*QRIS, Debit, Transfer*)** dengan pencatatan kode referensi/approval.
  - Fitur **Void Order** (khusus Manager/Admin) yang secara otomatis mengembalikan stok barang ke rak toko dan mencatat mutasi `return`.

- **📈 Laporan Finansial & Analisis Penjualan**:
  - Ringkasan Omzet, Total Diskon, Total Pajak, dan **Laba Kotor (*Gross Profit*)** riil.
  - Rekapitulasi per metode pembayaran (uang tunai di laci vs saldo QRIS).
  - Produk terlaris (*Top Selling Products*) dengan agregasi SQL berkinerja tinggi (*Zero Memory Waste*).

---

## 🏛️ Arsitektur Aplikasi

Aplikasi mengadopsi pola arsitektur berlapis (*layered architecture*) yang memisahkan tanggung jawab secara tegas (*Separation of Concerns*):

```
HTTP Request
     │
     ▼
[routes/api.php] ──► [Middleware (Sanctum, Force JSON, Throttle)]
     │
     ▼
[Form Request] ────► Validasi tipe data, aturan array, & otorisasi pengguna
     │
     ▼
[API Controller] ──► Mengatur alur HTTP (tipis/lean)
     │
     ▼
[Action Class] ────► Business Logic murni dalam DB::transaction() + lockForUpdate()
     │
     ▼
[Database / Eloquent Models]
     │
     ▼
[API Resource] ────► Transformasi data JSON bersih (mencegah kebocoran atribut internal)
     │
     ▼
HTTP JSON Response
```

---

## 📋 Prasyarat Sistem

- **PHP**: `>= 8.3` (Disarankan PHP 8.4 atau 8.5)
- **Composer**: `>= 2.8`
- **Database**: MySQL `>= 8.0` atau SQLite `>= 3.35`
- Ekstensi PHP: `pdo`, `mbstring`, `openssl`, `bcmath`, `curl`

---

## 🚀 Panduan Instalasi & Menjalankan

### 1. Clone Repositori
```bash
git clone https://github.com/username/pos-api.git
cd pos-api
```

### 2. Pasang Dependensi
```bash
composer install
```

### 3. Konfigurasi Environment
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Sesuaikan konfigurasi database di file `.env` (misal MySQL atau SQLite):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_api
DB_USERNAME=root
DB_PASSWORD=
```
*(Atau gunakan `DB_CONNECTION=sqlite` dengan membuat file `touch database/database.sqlite`)*.

### 4. Migrasi & Muat Data Demo (Seeder)
Jalankan migrasi beserta seeder master data:
```bash
php artisan migrate --seed
```

### 5. Jalankan Server Lokal
```bash
php artisan serve
```
API sekarang aktif dan dapat diakses di `http://localhost:8000/api`.

---

## 🔑 Akun Default untuk Pengujian (Seeder)

Database seeder telah menyediakan 3 akun untuk mensimulasikan sistem POS:

| Peran (*Role*) | Email | Password | Akses & Wewenang |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@pos.test` | `password` | Akses penuh (CRUD Produk, Kategori, Laporan Finansial, Void). |
| **Manager** | `manager@pos.test` | `password` | CRUD Produk/Kategori, Restock/Opname Stok, Void Struk, Laporan Omzet. |
| **Cashier** | `cashier@pos.test` | `password` | Akses Kasir (Cari Produk, Buat Order Checkout, Terima Pembayaran). |

---

## 🧪 Pengujian Otomatis (*Automated Testing*)

Proyek ini memiliki cakupan pengujian komprehensif menggunakan **Pest PHP**:

```bash
# Menjalankan seluruh test suite
php artisan test

# Menjalankan test secara ringkas
php artisan test --compact

# Menjalankan pengujian per modul spesifik
php artisan test --filter=AuthTest
php artisan test --filter=ProductCatalogTest
php artisan test --filter=StockMovementTest
php artisan test --filter=CheckoutTest
php artisan test --filter=PaymentAndVoidTest
php artisan test --filter=ReportTest
```

---

## 📖 Dokumentasi & Uji Coba Interaktif (`api.http`)

Proyek ini menyertakan file executable [**`api.http`**](api.http) di root folder. Anda dapat langsung menguji seluruh alur transaksi menggunakan ekstensi **REST Client** (VS Code) atau Antigravity IDE:

1. Buka file [`api.http`](api.http).
2. Klik **`Send Request`** pada blok **1.1 (Login Admin)** atau **1.2 (Login Kasir)**.
3. Token Bearer akan tersimpan secara otomatis di variabel `@adminToken` dan `@cashierToken`.
4. Anda dapat langsung mengeksekusi:
   - Manajemen Kategori & Produk
   - Restock barang masuk & mutasi kerusakan (*Damage*)
   - Transaksi Checkout Multi-Item
   - Pembayaran Tunai (hitung kembalian otomatis) & QRIS
   - Pembatalan transaksi (*Void Order*)
   - Ringkasan Laporan Penjualan & Laba Kotor

---

## 📂 Struktur Direktori Utama

```text
app/
├── Actions/                  # Domain Business Logic (Atomik & Terisolasi)
│   ├── Inventory/            # AdjustStockAction
│   ├── Order/                # ProcessCheckoutAction, VoidOrderAction
│   └── Payment/              # ProcessPaymentAction
├── Enums/                    # PHP 8.1+ Backed Enums (UserRole, OrderStatus, dll)
├── Http/
│   ├── Controllers/Api/      # REST API Controllers (Lean HTTP layer)
│   ├── Requests/             # Form Requests (Validasi ketat & otorisasi)
│   └── Resources/            # Eloquent API Resources (Transformasi respon JSON)
├── Models/                   # Eloquent Models & Relasi Database
└── Policies/                 # Authorization Policies (RBAC)
database/
├── factories/                # Model Factories untuk pengujian Pest
├── migrations/               # Skema tabel database berindeks
└── seeders/                  # Seeder akun, produk, dan transaksi realistis
tests/
└── Feature/                  # Automated Feature Tests komprehensif
```

---

## 🎨 Standar Gaya Kode (*Code Style*)

Seluruh kode diformat secara ketat menggunakan **Laravel Pint** mengikuti standar PSR-12:

```bash
vendor/bin/pint
```

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah lisensi terbuka [MIT License](LICENSE).
