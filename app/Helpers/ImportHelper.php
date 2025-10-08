<?php

namespace App\Helpers;

use App\Models\Siswa;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Support\Collection;

class ImportHelper implements ToCollection, WithHeadingRow, WithEvents
{
    public $sheetNames = [];

    public function importSiswa($file)
    {
        Excel::import($this, $file);
        return $this->sheetNames;
    }

    public function collection(Collection $rows)
    {
        $kelas = end($this->sheetNames) ?: 'Unknown';

        foreach ($rows as $row) {
            $nomorInduk = $row['nomor_induk'] ?? $row['nis'] ?? null;
            $nama = $row['nama'] ?? $row['nama_siswa'] ?? null;
            $jk = $row['jenis_kelamin'] ?? $row['jk'] ?? null;

            if (!$nomorInduk && !$nama)
                continue;

            Siswa::updateOrCreate(
                ['nomor_induk' => $nomorInduk],
                [
                    'nama' => $nama,
                    'jenis_kelamin' => $this->normalizeGender($jk),
                    'kelas' => $kelas,
                ]
            );
        }
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $title = $event->getSheet()->getDelegate()->getTitle();
                $this->sheetNames[] = $title;
            },
        ];
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
        return $value;
    }
}
