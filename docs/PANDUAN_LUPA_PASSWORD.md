# Panduan Lupa Password

## Cara Reset Password

### Langkah 1: Request Reset Password
1. Buka halaman **Login** 
2. Klik link **"Lupa password?"** di bawah kolom password
3. Masukkan **email** yang terdaftar di akun Anda
4. Klik tombol **"Kirim Link Reset"**
5. Link reset password akan ditampilkan di halaman (untuk development)

### Langkah 2: Akses Link Reset
1. Copy link yang ditampilkan
2. Paste link di address bar browser
3. Atau klik langsung jika link sudah aktif

### Langkah 3: Buat Password Baru
1. Masukkan **password baru** (minimal 6 karakter)
2. Perhatikan indikator kekuatan password:
   - 🔴 **Lemah**: Tingkatkan keamanan password
   - 🟡 **Sedang**: Password cukup aman
   - 🟢 **Kuat**: Password sangat aman
3. Masukkan **konfirmasi password** yang sama
4. Pastikan muncul tanda ✓ "Password cocok"
5. Klik tombol **"Reset Password"**

### Langkah 4: Login dengan Password Baru
1. Setelah reset berhasil, klik **"Login Sekarang"**
2. Masukkan email dan password baru Anda
3. Klik **"Masuk"**

## Tips Password Aman

### ✅ Password yang Baik:
- Minimal **8 karakter**
- Kombinasi **huruf besar dan kecil** (A-Z, a-z)
- Mengandung **angka** (0-9)
- Mengandung **simbol** (!@#$%^&*)

### ❌ Hindari:
- Password yang terlalu pendek (< 6 karakter)
- Password yang mudah ditebak (123456, password, nama)
- Tanggal lahir atau informasi pribadi
- Kata yang ada di kamus

### Contoh Password Kuat:
- `MyP@ssw0rd2025!`
- `Sw4p-1t_S3cur3`
- `B@rt3r#2025*`

## Catatan Penting

⏰ **Link reset berlaku 1 jam**
- Link hanya bisa digunakan sekali
- Jika expired, request reset baru

🔒 **Keamanan**
- Jangan share link reset dengan siapapun
- Pastikan logout dari perangkat publik
- Ganti password secara berkala

📧 **Email**
- Pastikan email yang dimasukkan sudah terdaftar
- Cek spam/junk folder jika email tidak masuk (untuk production)

## Troubleshooting

### ❓ Link Tidak Berfungsi
**Solusi:**
- Cek apakah link sudah kadaluarsa (> 1 jam)
- Request reset password baru
- Copy paste link lengkap, jangan diketik manual

### ❓ Password Tidak Cocok
**Solusi:**
- Ketik ulang konfirmasi password dengan teliti
- Perhatikan huruf besar/kecil (case sensitive)
- Matikan caps lock

### ❓ Password Terlalu Lemah
**Solusi:**
- Tambahkan huruf besar
- Tambahkan angka
- Tambahkan simbol khusus
- Panjangkan minimal 8 karakter

### ❓ Token Tidak Valid
**Solusi:**
- Link mungkin sudah digunakan
- Request reset password baru dari awal

## Bantuan Lebih Lanjut

Jika masih mengalami masalah:
1. Hubungi administrator sistem
2. Email: admin@swap-it.com (jika production)
3. Pastikan browser Anda up-to-date

---

**SWAP-IT** - Platform Barter Barang Bekas Antar Mahasiswa
