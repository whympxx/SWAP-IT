# SWAP-IT: Platform Barter Barang Bekas Antar Mahasiswa

Platform web modern untuk memfasilitasi mahasiswa saling menukar barang bekas yang masih layak pakai. Mendukung gaya hidup hemat dan ramah lingkungan.

## 🚀 Fitur Utama

- ✅ **Login/Registrasi** - Sistem autentikasi pengguna yang aman
- ✅ **Posting Barang** - Upload foto dan deskripsi barang yang ingin ditukar
- ✅ **Browse & Search** - Cari dan filter barang berdasarkan kategori
- ✅ **Sistem Penawaran** - Tawarkan barang Anda untuk barter
- ✅ **Chat Real-time** - AJAX chat tanpa reload halaman, notifikasi sound, typing indicator
- ✅ **Desain Modern** - UI/UX profesional dan responsif

## 📋 Persyaratan Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- XAMPP / WAMP / MAMP
- Web Browser modern (Chrome, Firefox, Edge)

## 🛠️ Instalasi

### 1. Clone atau Download Project

Tempatkan folder `SWAP-IT` di dalam folder `htdocs` XAMPP Anda:
```
C:\xampp\htdocs\SWAP-IT\
```

### 2. Buat Database

1. Buka phpMyAdmin (http://localhost/phpmyadmin)
2. Buat database baru dengan nama `swapit_db`
3. Import file `database.sql` yang ada di root folder project
   - Klik tab "Import"
   - Pilih file `database.sql`
   - Klik "Go"

### 3. Konfigurasi Database

File konfigurasi sudah siap di `config/database.php`. Jika perlu, sesuaikan kredensial database:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'swapit_db');
```

### 4. Sesuaikan Base URL (Opsional)

Jika Anda menggunakan port berbeda atau nama folder berbeda, edit file `config/database.php`:

```php
define('BASE_URL', 'http://localhost/SWAP-IT/');
```

### 5. Set Permissions (Untuk Linux/Mac)

```bash
chmod 777 uploads/
chmod 777 uploads/items/
chmod 777 uploads/profiles/
```

### 6. Akses Aplikasi

Buka browser dan akses:
```
http://localhost/SWAP-IT/
```

## 👤 Akun Testing

Gunakan akun berikut untuk testing:

**User 1:**
- Email: john@example.com
- Password: password123

**User 2:**
- Email: jane@example.com
- Password: password123

## 📁 Struktur Folder

```
SWAP-IT/
│
├── assets/
│   ├── css/
│   │   └── style.css          # Stylesheet utama
│   └── js/
│       └── main.js            # JavaScript utama
│
├── auth/
│   ├── login.php              # Halaman login
│   ├── register.php           # Halaman registrasi
│   └── logout.php             # Logout handler
│
├── config/
│   ├── database.php           # Konfigurasi database
│   └── functions.php          # Helper functions
│
├── includes/
│   ├── navbar.php             # Navigation bar
│   └── footer.php             # Footer
│
├── pages/
│   ├── post-item.php          # Form posting barang
│   ├── my-items.php           # Barang milik user
│   ├── item-detail.php        # Detail barang
│   ├── edit-item.php          # Edit barang
│   ├── delete-item.php        # Hapus barang
│   ├── make-offer.php         # Buat penawaran
│   ├── my-offers.php          # Daftar penawaran
│   ├── accept-offer.php       # Terima penawaran
│   ├── reject-offer.php       # Tolak penawaran
│   └── chat.php               # Halaman chat
│
├── uploads/
│   ├── items/                 # Foto barang
│   └── profiles/              # Foto profil
│
├── database.sql               # SQL schema & data
├── index.php                  # Homepage
└── README.md                  # Dokumentasi
```

## 🎨 Teknologi yang Digunakan

- **Backend:** PHP 7.4+ with MySQLi
- **Database:** MySQL
- **Frontend:** HTML5, CSS3 (Custom Design System)
- **JavaScript:** Vanilla JS
- **Icons:** Font Awesome 6.4
- **Design:** Modern, Clean, Responsive

## 🌟 Fitur Desain

- ✨ Gradient color scheme modern
- 🎨 Card-based layout
- 📱 Fully responsive (Mobile, Tablet, Desktop)
- 🔔 Real-time notifications badge
- 💬 Chat interface yang intuitif
- 🎯 Smooth animations & transitions
- ⚡ Fast loading dengan optimized assets

## 📝 Cara Penggunaan

### Untuk Pengguna Baru:

1. **Registrasi** - Buat akun dengan mengisi form registrasi
2. **Login** - Masuk dengan akun yang telah dibuat
3. **Posting Barang** - Upload barang yang ingin ditukar
4. **Browse Barang** - Lihat barang yang tersedia dari pengguna lain
5. **Buat Penawaran** - Pilih barang Anda untuk ditukar
6. **Chat** - Komunikasi dengan pemilik barang setelah penawaran diterima

### Untuk Penerima Penawaran:

1. Cek **"Penawaran Masuk"** di menu Penawaran
2. Lihat detail barang yang ditawarkan
3. **Terima** atau **Tolak** penawaran
4. Jika terima, mulai **Chat** untuk atur pertemuan

## 🔒 Keamanan

- Password di-hash menggunakan `password_hash()` PHP
- Prepared statements untuk mencegah SQL Injection
- Input sanitization dengan `htmlspecialchars()`
- Session management yang aman
- File upload validation (type, size)

## 🐛 Troubleshooting

### Error: "Connection failed"
- Pastikan MySQL sudah running
- Cek kredensial database di `config/database.php`
- Pastikan database `swapit_db` sudah dibuat

### Error: Upload failed
- Pastikan folder `uploads/` memiliki permission yang benar
- Cek max upload size di `php.ini` (default 5MB)

### Styling tidak muncul
- Cek BASE_URL di `config/database.php`
- Clear browser cache

## 📞 Support

Untuk pertanyaan atau masalah, silakan buat issue di repository ini.

## 📜 License

© 2025 SWAP-IT. All rights reserved.

## 🎯 Future Improvements

- [ ] Rating & review system
- [ ] Advanced search filters
- [ ] Email notifications
- [ ] User profile customization
- [ ] Transaction history
- [ ] Admin dashboard
- [ ] Real-time chat dengan WebSocket
- [ ] Mobile app version

---

**Selamat menggunakan SWAP-IT! 🎉**

Mulai tukar barang bekas Anda sekarang dan berkontribusi untuk lingkungan yang lebih baik! 🌱
