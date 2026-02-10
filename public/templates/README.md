# Template Files untuk Import Siswa

## File yang Diperlukan

### Template Excel (.xls)
File Excel dengan struktur berikut:

| NIS   | Nama            | Jenis Kelamin | Kelas    |
|-------|-----------------|---------------|----------|
| 12345 | Ahmad Rizki     | L             | X TKJ 1  |
| 12346 | Siti Nurhaliza  | P             | X TKJ 1  |
| 12347 | Budi Santoso    | L             | X RPL 1  |

File tersedia di: `template-siswa.xls`

## Preview Image

Untuk menampilkan preview template, tambahkan file berikut ke folder `/public/images/`:

`preview.png` - Screenshot template Excel

Jika file gambar tidak tersedia, sistem akan menampilkan pesan fallback dengan ikon file.

## Format Data

### Kolom Wajib:
- **NIS**: Nomor Induk Siswa (angka, unik)
- **Nama**: Nama lengkap siswa
- **Jenis Kelamin**: L (Laki-laki) atau P (Perempuan)
- **Kelas**: Nama kelas yang sudah terdaftar di sistem

### Aturan:
- Jangan mengubah nama kolom header
- NIS harus unik (tidak boleh duplikat)
- Jenis Kelamin hanya boleh "L" atau "P"
- Kelas harus sesuai dengan data kelas yang ada di sistem
- Tidak boleh ada baris kosong di tengah data
- File harus dalam format Excel (.xls atau .xlsx)