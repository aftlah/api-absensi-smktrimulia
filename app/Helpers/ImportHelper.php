<?php

namespace App\Helpers;

use App\Models\Akun;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\WaliKelas;
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
        $defaultWali = WaliKelas::first();
        if (!$defaultWali) {
            throw new \RuntimeException('Tidak ada wali kelas terdaftar. Silakan buat minimal satu wali kelas terlebih dahulu sebelum import siswa.');
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();
        $kelasMap = [];
        $missingJurusan = [];
        $jurusanLog = [];

        // Log semua jurusan yang ada di database
        $existingJurusan = Jurusan::all();
        foreach ($existingJurusan as $jur) {
            $jurusanLog[] = [
                'id' => $jur->jurusan_id,
                'nama' => $jur->nama_jurusan,
                'nama_lowercase' => strtolower($jur->nama_jurusan)
            ];
        }

        \Log::info('=== IMPORT SISWA DIMULAI ===');
        \Log::info('Jurusan yang tersedia di database:', $jurusanLog);
        \Log::info('Sheet names dari Excel:', $sheetNames);

        foreach ($sheetNames as $sheetName) {
            try {
                $name = trim(preg_replace('/\s+/', ' ', $sheetName));
                \Log::info("Memproses sheet: {$sheetName} (cleaned: {$name})");
                
                if (!preg_match('/^(X|XI|XII)\s+(.+)$/i', $name, $m)) {
                    \Log::warning("Sheet '{$sheetName}' tidak sesuai format (X/XI/XII JURUSAN)");
                    $kelasMap[$sheetName] = null;
                    $missingJurusan[] = $name;
                    continue;
                }
                
                $tingkat = strtoupper($m[1]);
                $jurusanNama = trim($m[2]);
                
                \Log::info("Tingkat: {$tingkat}, Jurusan: {$jurusanNama}");

                $jurusan = Jurusan::whereRaw('LOWER(nama_jurusan) = ?', [strtolower($jurusanNama)])->first();

                if (!$jurusan) {
                    \Log::error("Jurusan '{$jurusanNama}' tidak ditemukan di database");
                    $kelasMap[$sheetName] = null;
                    $missingJurusan[] = $jurusanNama;
                    continue;
                }

                \Log::info("Jurusan ditemukan: {$jurusan->nama_jurusan} (ID: {$jurusan->jurusan_id})");

                $kelas = Kelas::where('tingkat', $tingkat)
                    ->where('jurusan_id', $jurusan->jurusan_id)
                    ->first();

                if (!$kelas) {
                    \Log::info("Kelas tidak ditemukan, membuat kelas baru: {$tingkat} {$jurusan->nama_jurusan}");
                    $kelas = Kelas::create([
                        'tingkat' => $tingkat,
                        'jurusan_id' => $jurusan->jurusan_id,
                        'paralel' => 1,
                        'walas_id' => $defaultWali->walas_id,
                    ]);
                    \Log::info("Kelas berhasil dibuat dengan ID: {$kelas->kelas_id}");
                } else {
                    \Log::info("Kelas sudah ada dengan ID: {$kelas->kelas_id}");
                }

                $kelasMap[$sheetName] = $kelas->kelas_id;
            } catch (\Throwable $e) {
                \Log::error("Error saat memproses sheet '{$sheetName}': " . $e->getMessage());
                \Log::error("Stack trace: " . $e->getTraceAsString());
                $kelasMap[$sheetName] = null;
                continue;
            }
        }

        if (!empty($missingJurusan)) {
            $errorMsg = 'Jurusan tidak ditemukan untuk sheet: ' . implode(', ', $missingJurusan);
            \Log::error($errorMsg);
            \Log::info('Jurusan yang tersedia: ' . implode(', ', array_column($jurusanLog, 'nama')));
            throw new \RuntimeException($errorMsg);
        }

        $existingNis = Siswa::pluck('nis')->toArray();
        \Log::info('Total NIS yang sudah ada di database: ' . count($existingNis));

        // Import semua sheet
        $importer = new SiswaMultiSheetImport($sheetNames, $kelasMap, $existingNis);
        Excel::import($importer, $file);

        \Log::info('=== IMPORT SISWA SELESAI ===');
        \Log::info('Total data berhasil diimport: ' . count($importer->getImportedData()));
        \Log::info('Total NIS duplikat: ' . count($importer->getDuplicateNis()));

        return [
            'status' => 'success',
            'kelas_terdaftar' => $kelasMap,
            'data_siswa' => $importer->getImportedData(),
            'nis_duplikat' => $importer->getDuplicateNis(),
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
    private $existingNis = [];
    private $duplicateNis = [];

    public function __construct(array $sheetNames, array $kelasMap, array $existingNis = [])
    {
        $this->sheetNames = $sheetNames;
        $this->kelasMap = $kelasMap;
        $this->existingNis = $existingNis;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->sheetNames as $name) {
            $sheetImport = new SiswaSheetImport($name, $this->kelasMap[$name], $this->existingNis);
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

    public function addDuplicateNis($nis)
    {
        if (!in_array($nis, $this->duplicateNis, true)) {
            $this->duplicateNis[] = $nis;
        }
    }

    public function getDuplicateNis()
    {
        return $this->duplicateNis;
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
    private $existingNis = [];

    public function __construct($kelas, $kelasId, array $existingNis = [])
    {
        $this->kelas = $kelas;
        $this->kelasId = $kelasId;
        $this->existingNis = $existingNis;
    }

    public function setParent(SiswaMultiSheetImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        if (!$this->kelasId) return;

        $startRow = 2; // Mulai dari baris 2 (setelah header)
        $rowIndex = 1;

        foreach ($rows as $row) {
            if ($rowIndex++ < $startRow) continue;
            if (count($row) < 4) continue;

            $nis = trim($row[0] ?? '');
            $nisn = trim($row[1] ?? '');
            $nama = trim($row[2] ?? '');
            $jk = trim($row[3] ?? '');

            if (!$nis || !$nama) continue;

            // Validasi NISN untuk password
            if (!$nisn) {
                // Skip jika NISN kosong karena akan digunakan sebagai password
                continue;
            }

            if (in_array($nis, $this->existingNis, true)) {
                if ($this->parent) {
                    $this->parent->addDuplicateNis($nis);
                }
                continue;
            }

            // Buat akun siswa dengan username = NIS dan password = NISN
            $akun = Akun::firstOrCreate(
                ['username' => $nis],
                [
                    'password' => Hash::make($nisn),
                    'role' => 'siswa',
                ]
            );

            $siswa = Siswa::updateOrCreate(
                ['nis' => $nis],
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
