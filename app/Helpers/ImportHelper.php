<?php

namespace App\Helpers;

use App\Models\Akun;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\RiwayatKelas;
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

        foreach ($sheetNames as $sheetName) {
            try {
                // Contoh nama sheet: "X TKJ", "X MP", "X BR"
                $sheetParts = preg_split('/\s+/', trim($sheetName));
                $tingkat = strtoupper($sheetParts[0] ?? 'X');
                $jurusanKode = strtoupper($sheetParts[1] ?? 'UMUM');

                // Coba cari jurusan berdasarkan kode
                $jurusan = Jurusan::where('nama_jurusan', $jurusanKode)->first();

                // Jika jurusan tidak ditemukan, buat otomatis
                if (!$jurusan) {
                    $jurusan = Jurusan::create([
                        'nama_jurusan' => $jurusanKode,
                    ]);
                }

                // Coba cari kelas dengan tingkat + jurusan
                $kelas = Kelas::where('tingkat', $tingkat)
                    ->where('jurusan_id', $jurusan->jurusan_id)
                    ->first();

                // Jika kelas tidak ditemukan, buat otomatis
                if (!$kelas) {
                    $kelas = Kelas::create([
                        'tingkat' => $tingkat,
                        'jurusan_id' => $jurusan->jurusan_id,
                        'paralel' => 1,
                        'walas_id' => 1,
                    ]);
                }

                $kelasMap[$sheetName] = $kelas->kelas_id;
            } catch (\Throwable $e) {
                $kelasMap[$sheetName] = null;
                continue;
            }
        }

        // Import semua sheet
        $importer = new SiswaMultiSheetImport($sheetNames, $kelasMap);
        Excel::import($importer, $file);

        return [
            'status' => 'success',
            'kelas_terdaftar' => $kelasMap,
            'data_siswa' => $importer->getImportedData(),
        ];
    }
}

/**
 * Importer untuk semua sheet
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
 * Importer untuk setiap sheet
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
        if (!$this->kelasId) return;

        $startRow = 15;
        $rowIndex = 1;

        foreach ($rows as $row) {
            if ($rowIndex++ < $startRow) continue;
            if (count($row) < 4) continue;

            $nomorInduk = trim($row[1] ?? '');
            $nama = trim($row[2] ?? '');
            $jk = trim($row[3] ?? '');

            if (!$nomorInduk || !$nama) continue;

            // Buat akun siswa
            $akun = Akun::firstOrCreate(
                ['username' => $nomorInduk],
                [
                    'password' => Hash::make($nomorInduk),
                    'role' => 'siswa',
                ]
            );

            $siswa = Siswa::updateOrCreate(
                ['nis' => $nomorInduk],
                [
                    'akun_id' => $akun->akun_id,
                    'nama' => $nama,
                    'jenkel' => $this->normalizeGender($jk),
                ]
            );

            // Create or update riwayat kelas with status "aktif" for imported student
            RiwayatKelas::updateOrCreate(
                [
                    'siswa_id' => $siswa->siswa_id,
                    'kelas_id' => $this->kelasId
                ],
                [
                    'status' => 'aktif'
                ]
            );

            $this->parent->addImportedData($this->kelas, [
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
                'jenkel' => $siswa->jenkel,
                'kelas_id' => $this->kelasId,
                'akun_id' => $siswa->akun_id,
            ]);
        }
    }

    private function normalizeGender($value)
    {
        if (!$value) return null;
        $v = strtolower(trim($value));
        if (in_array($v, ['l', 'laki', 'laki-laki', 'male', 'm'])) return 'L';
        if (in_array($v, ['p', 'perempuan', 'female', 'f'])) return 'P';
        return strtoupper($v);
    }
}
