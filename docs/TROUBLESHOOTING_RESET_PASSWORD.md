# Troubleshooting - Reset Password

## Error: "Token reset password telah kadaluarsa"

### Penyebab:
Link reset password yang Anda klik sudah **melewati batas waktu 2 jam** sejak dibuat.

### Solusi:
1. **Kembali ke halaman Lupa Password**
   - Klik link "Request Reset Baru" di halaman error
   - Atau buka: `http://localhost/SWAP-IT/auth/forgot-password.php`

2. **Request link reset baru**
   - Masukkan email yang sama
   - Klik "Kirim Link Reset"
   - Link baru akan dibuat dengan masa berlaku 2 jam

3. **Gunakan link segera**
   - Copy link yang baru
   - Paste di browser
   - Segera set password baru
   - Jangan tunggu terlalu lama

### Tips Mencegah Error Ini:

✅ **DO:**
- Gunakan link reset segera setelah menerima
- Set password dalam waktu 2 jam
- Simpan link di clipboard/notepad jika perlu waktu

❌ **DON'T:**
- Menunggu terlalu lama untuk reset password
- Menggunakan link yang sudah lebih dari 2 jam
- Me-request multiple tokens tanpa menggunakan yang terbaru

---

## Error: "Token reset password tidak valid atau sudah digunakan"

### Penyebab:
- Token sudah pernah digunakan untuk reset password
- Token tidak valid (URL tidak lengkap/rusak)
- Token sudah dihapus dari sistem

### Solusi:
1. Pastikan URL lengkap (tidak ada karakter yang hilang)
2. Request link reset password baru
3. Gunakan link yang paling baru

---

## Link Tidak Berfungsi/Error 404

### Penyebab:
- URL tidak lengkap saat di-copy
- Ada spasi atau karakter tambahan di URL

### Solusi:
1. **Copy link dengan benar:**
   - Klik kanan pada link → "Copy Link Address"
   - Atau select semua teks link dengan triple-click
   - Jangan ketik manual

2. **Paste dengan hati-hati:**
   - Paste di address bar browser
   - Hapus spasi di awal/akhir
   - Tekan Enter

3. **Cek format URL:**
   ```
   Format benar:
   http://localhost/SWAP-IT/auth/reset-password.php?token=abc123...
   
   Format salah:
   http://localhost/SWAP-IT/auth/reset-password.php (tanpa token)
   ```

---

## Password Tidak Ter-update

### Penyebab:
- Password terlalu pendek (< 6 karakter)
- Password dan konfirmasi tidak cocok
- Koneksi database error

### Solusi:
1. **Cek password requirements:**
   - Minimal 6 karakter
   - Maksimal 255 karakter
   - Konfirmasi password harus sama persis

2. **Perhatikan indicator:**
   - Lihat password strength indicator
   - Pastikan muncul tanda ✓ "Password cocok"

3. **Jika masih error:**
   - Refresh halaman
   - Request token baru
   - Coba browser lain

---

## Cara Mendapatkan Link Reset Baru

### Langkah-langkah:

1. **Buka halaman Lupa Password**
   ```
   http://localhost/SWAP-IT/auth/forgot-password.php
   ```

2. **Masukkan email yang terdaftar**
   - Email harus sama dengan yang digunakan saat registrasi
   - Cek spelling dan format email

3. **Klik "Kirim Link Reset"**
   - Link baru akan muncul di halaman
   - Link berlaku selama **2 jam**

4. **Copy dan gunakan link segera**
   - Click link atau copy-paste di browser
   - Isi form password baru
   - Submit

---

## FAQ

### Q: Berapa lama link reset berlaku?
**A:** Link reset password berlaku selama **2 jam** sejak dibuat.

### Q: Apakah bisa request multiple kali?
**A:** Ya, tapi hanya link yang terakhir dibuat yang valid. Link lama akan otomatis tidak valid.

### Q: Apakah link bisa digunakan lebih dari sekali?
**A:** Tidak. Link reset password hanya bisa digunakan **satu kali**. Setelah password berhasil direset, link otomatis tidak valid.

### Q: Kenapa email saya tidak menerima link?
**A:** Untuk development, link ditampilkan langsung di halaman. Untuk production, link akan dikirim via email (belum aktif).

### Q: Apa yang harus dilakukan jika lupa email?
**A:** Hubungi administrator sistem untuk bantuan pemulihan akun.

### Q: Apakah aman request reset berkali-kali?
**A:** Ya, aman. Sistem akan menghapus token lama dan hanya menyimpan yang terbaru. Token lama tidak bisa digunakan.

---

## Kontak Support

Jika masalah masih berlanjut setelah mengikuti panduan ini:

1. **Screenshot error message**
2. **Catat waktu kejadian**
3. **Hubungi administrator:**
   - Email: admin@swap-it.com (production)
   - Forum: [link ke forum support]

---

## Technical Notes (Untuk Developer)

### Token Cleanup
Token expired dibersihkan otomatis saat:
- User request reset password baru
- Admin menjalankan cleanup script

Manual cleanup:
```bash
php utils/cleanup_expired_tokens.php
```

### Token Validity
- Duration: 2 hours
- Algorithm: SHA-256 hash
- Storage: Database table `password_resets`
- One-time use: Auto-deleted after successful reset

### Database Schema
```sql
password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  token VARCHAR(255) UNIQUE,
  expires_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```
