<?php

namespace App\Helpers;

use App\Models\Akun;
use App\Models\Siswa;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportHelper implements WithMultipleSheets
{
    public $sheetNames = [];
    private $file;

    public function importSiswa($file)
    {
        $this->file = $file;

        // Ambil semua nama sheet dari file Excel
        $spreadsheet = IOFactory::load($file->getRealPath());
        $this->sheetNames = $spreadsheet->getSheetNames();

        // Jalankan import per sheet
        Excel::import($this, $file);

        return $this->sheetNames;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->sheetNames as $name) {
            $sheets[$name] = new SiswaSheetImport($name);
        }

        return $sheets;
    }
}

class SiswaSheetImport implements ToCollection
{
    private $kelas;

    public function __construct($kelas)
    {
        $this->kelas = $kelas;
    }

    public function collection(Collection $rows)
    {
        $startRow = 15; // data siswa mulai dari baris ke-15
        $rowIndex = 1;

        foreach ($rows as $row) {
            // Lewati baris sebelum 15
            if ($rowIndex < $startRow) {
                $rowIndex++;
                continue;
            }

            // Pastikan ada minimal 3 kolom
            if (count($row) < 4) {
                $rowIndex++;
                continue;
            }

            $nomorInduk = trim($row[1]); // kolom B
            $nama = trim($row[2]);       // kolom C
            $jk = trim($row[3]);         // kolom D

            if (!$nomorInduk && !$nama) {
                $rowIndex++;
                continue;
            }

            // 🔹 1. Buat atau ambil akun (username = NIS)
            $akun = Akun::firstOrCreate(
                ['username' => $nomorInduk],
                [
                    'password' => Hash::make($nomorInduk), // default password = NIS
                    'role' => 'siswa'
                ]
            );

            // Buat atau update data siswa
            Siswa::updateOrCreate(
                ['nis' => $nomorInduk],
                [
                    'akun_id' => $akun->akun_id,
                    'nama' => $nama,
                    'jenkel' => $this->normalizeGender($jk),
                    // 'kelas' => $this->kelas, // bisa aktifkan kalau ada kolom kelas
                ]
            );

            $rowIndex++;
        }
    }

    private function normalizeGender($value)
    {
        if (!$value)
            return null;

        $v = strtolower(trim($value));
        if (in_array($v, ['l', 'laki', 'laki-laki', 'male', 'm']))
            return 'L';
        if (in_array($v, ['p', 'perempuan', 'female', 'f']))
            return 'P';
        return strtoupper($v);
    }
}
