<?php

namespace App\Helpers;

use App\Models\Akun;
use App\Models\Siswa;
use App\Models\Kelas;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportHelper
{
    public function importSiswa($file)
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();
        $kelasMap = [];

        // 🔹 Deteksi kelas untuk setiap sheet
        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $kelasCell = trim((string) $sheet->getCell('C7')->getValue());

            $tingkat = null;
            $jurusan = null;
            $paralel = 1;

            if (preg_match('/^(X{1,3})\s+.*\((.*?)\)/', $kelasCell, $matches)) {
                $tingkat = $matches[1] ?? null;
                $jurusan = $matches[2] ?? null;
            } else {
                $tingkat = 'X';
                $jurusan = 'Umum';
            }

            $kelasModel = Kelas::firstOrCreate(
                [
                    'tingkat' => $tingkat,
                    'jurusan' => $jurusan,
                    'paralel' => $paralel,
                ]
            );

            $kelasMap[$sheetName] = $kelasModel->kelas_id;
        }

        // 🔹 Jalankan import multi-sheet dan dapatkan hasil siswa yang disimpan
        $importer = new SiswaMultiSheetImport($sheetNames, $kelasMap);
        Excel::import($importer, $file);

        // 🔹 Return hasil lengkap
        return [
            'status' => 'success',
            'message' => 'Import siswa berhasil',
            'kelas_terdaftar' => $kelasMap,
            'data_siswa' => $importer->getImportedData(), // ✅ tampilkan siswa
        ];
    }
}

/**
 * 🔹 Importer untuk semua sheet
 */
class SiswaMultiSheetImport implements WithMultipleSheets
{
    private $sheetNames;
    private $kelasMap;
    private $importedData = [];

    public function __construct(array $sheetNames, array $kelasMap)
    {
        $this->sheetNames = $sheetNames;
        $this->kelasMap = $kelasMap;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->sheetNames as $name) {
            $sheetImport = new SiswaSheetImport($name, $this->kelasMap[$name]);
            $sheetImport->setParent($this);
            $sheets[$name] = $sheetImport;
        }

        return $sheets;
    }

    public function addImportedData($sheet, $data)
    {
        if (!isset($this->importedData[$sheet])) {
            $this->importedData[$sheet] = [];
        }

        $this->importedData[$sheet][] = $data;
    }

    public function getImportedData()
    {
        return $this->importedData;
    }
}

/**
 * 🔹 Importer untuk setiap sheet
 */
class SiswaSheetImport implements ToCollection
{
    private $kelas;
    private $kelasId;
    private $parent;

    public function __construct($kelas, $kelasId)
    {
        $this->kelas = $kelas;
        $this->kelasId = $kelasId;
    }

    public function setParent(SiswaMultiSheetImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        $startRow = 15;
        $rowIndex = 1;

        foreach ($rows as $row) {
            if ($rowIndex++ < $startRow)
                continue;
            if (count($row) < 4)
                continue;

            $nomorInduk = trim($row[1] ?? '');
            $nama = trim($row[2] ?? '');
            $jk = trim($row[3] ?? '');

            if (!$nomorInduk || !$nama)
                continue;

            // 🔹 1. Buat akun siswa
            $akun = Akun::firstOrCreate(
                ['username' => $nomorInduk],
                [
                    'password' => Hash::make($nomorInduk),
                    'role' => 'siswa',
                ]
            );

            // 🔹 2. Buat / update siswa
            $siswa = Siswa::updateOrCreate(
                ['nis' => $nomorInduk],
                [
                    'akun_id' => $akun->akun_id,
                    'kelas_id' => $this->kelasId,
                    'nama' => $nama,
                    'jenkel' => $this->normalizeGender($jk),
                ]
            );

            // 🔹 3. Simpan ke data hasil import
            $this->parent->addImportedData($this->kelas, [
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
                'jenkel' => $siswa->jenkel,
                'kelas_id' => $siswa->kelas_id,
                'akun_id' => $siswa->akun_id,
            ]);
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
