# Fitur Lupa Password - SWAP-IT

## Overview
Fitur ini memungkinkan pengguna untuk mereset password mereka jika lupa dengan menggunakan token reset yang aman.

## Alur Kerja

### 1. Request Reset Password
- User mengklik "Lupa password?" di halaman login
- User memasukkan email yang terdaftar
- Sistem generate token unik (64 karakter hex)
- Token di-hash dengan SHA-256 untuk keamanan
- Token disimpan di database dengan masa berlaku 1 jam
- Link reset dikirim ke user (saat ini ditampilkan di halaman untuk development)

### 2. Validasi Token
- User mengklik link reset yang diterima
- Sistem memvalidasi token:
  - Apakah token ada di database?
  - Apakah token belum expired?
  - Apakah user_id valid?
- Jika valid, form reset password ditampilkan
- Jika tidak valid/expired, pesan error ditampilkan

### 3. Reset Password
- User memasukkan password baru (minimal 6 karakter)
- Password divalidasi dengan konfirmasi password
- Password indicator menunjukkan kekuatan password
- Password di-hash dengan bcrypt
- Password lama di-update dengan yang baru
- Token dihapus dari database (one-time use)
- User diarahkan untuk login dengan password baru

## File yang Terlibat

### 1. `auth/login.php`
- Menampilkan link "Lupa password?"

### 2. `auth/forgot-password.php`
- Form untuk request reset password
- Generate dan simpan token reset
- Menampilkan link reset (untuk development)

### 3. `auth/reset-password.php`
- Validasi token reset
- Form untuk set password baru
- Update password dan hapus token
- Password strength indicator
- Real-time password match validation

### 4. `database/password_resets` Table
```sql
CREATE TABLE password_resets (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY expires_at (expires_at)
);
```

## Keamanan

### 1. Token Security
- Token generate dengan `random_bytes(32)` = 64 hex characters
- Token di-hash dengan SHA-256 sebelum disimpan
- Token one-time use (dihapus setelah digunakan)
- Token expire dalam 1 jam

### 2. Password Security
- Password minimal 6 karakter
- Password di-hash dengan bcrypt (PASSWORD_DEFAULT)
- Validasi password match di client dan server
- Password strength indicator

### 3. Privacy
- Tidak mengungkapkan apakah email terdaftar atau tidak
- Pesan generic: "Jika email terdaftar, link telah dikirim"

### 4. Database Security
- Foreign key constraint dengan CASCADE delete
- Unique constraint pada token
- Index pada expires_at untuk performa cleanup

## Penggunaan

### Untuk Development
Link reset ditampilkan langsung di halaman setelah request. Copy link dan paste di browser.

### Untuk Production
Uncomment bagian email di `forgot-password.php` dan konfigurasikan:

```php
$to = $user['email'];
$subject = "Reset Password - SWAP-IT";
$message = "Halo " . $user['username'] . ",\n\n";
$message .= "Klik link berikut untuk reset password:\n\n";
$message .= $reset_link . "\n\n";
$message .= "Link berlaku 1 jam.\n\n";

// Gunakan PHPMailer atau SMTP
mail($to, $subject, $message);
```

## Maintenance

### Cleanup Token Expired
Jalankan query ini secara periodik (cron job):

```sql
DELETE FROM password_resets WHERE expires_at < NOW();
```

Atau buat cron job di server:
```bash
# Setiap 1 jam
0 * * * * mysql -u root swap_it -e "DELETE FROM password_resets WHERE expires_at < NOW();"
```

## Testing

### Test Case 1: Request Reset
1. Buka halaman login
2. Klik "Lupa password?"
3. Masukkan email yang terdaftar
4. Verifikasi link reset muncul

### Test Case 2: Reset Password
1. Copy link reset
2. Paste di browser
3. Masukkan password baru
4. Verifikasi password strength indicator
5. Confirm password
6. Submit
7. Login dengan password baru

### Test Case 3: Token Expired
1. Request reset password
2. Tunggu 1+ jam
3. Coba gunakan link
4. Verifikasi error "Token expired"

### Test Case 4: Invalid Token
1. Buka URL reset dengan token random
2. Verifikasi error "Token tidak valid"

### Test Case 5: Password Validation
1. Buka form reset password
2. Coba password < 6 karakter
3. Coba password tidak match
4. Verifikasi validasi berjalan

## Fitur Tambahan

### Password Strength Indicator
- 🔴 Lemah: < 6 karakter atau hanya huruf/angka
- 🟡 Sedang: 6-8 karakter dengan kombinasi
- 🟢 Kuat: 8+ karakter dengan huruf besar, kecil, angka, simbol

### Real-time Validation
- Password strength update saat user mengetik
- Password match indicator
- Client-side validation sebelum submit

### User Experience
- Icon informatif di setiap step
- Pesan error yang jelas
- Success state dengan tombol login
- Link kembali ke login di setiap halaman

## Troubleshooting

### Token tidak berfungsi
- Cek apakah tabel `password_resets` ada
- Cek apakah token di-hash dengan SHA-256
- Cek timezone server vs database

### Email tidak terkirim
- Konfigurasikan SMTP server
- Gunakan PHPMailer untuk reliability
- Cek spam folder

### Password tidak ter-update
- Cek koneksi database
- Cek query UPDATE berhasil
- Cek password di-hash dengan benar

## Future Improvements

1. **Email Integration**
   - Setup PHPMailer/SMTP
   - Email template HTML
   - Rate limiting untuk prevent spam

2. **Security Enhancement**
   - CAPTCHA di form request reset
   - Rate limiting per IP/user
   - 2FA optional

3. **User Experience**
   - Progress indicator
   - Timer countdown untuk token expiry
   - Password history (prevent reuse)

4. **Admin Features**
   - Dashboard untuk monitor reset requests
   - Force reset password untuk user
   - Bulk email notifications
