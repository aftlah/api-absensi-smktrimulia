<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RencanaAbsensi;
use App\Models\Siswa;
use App\Models\Absensi;
use Carbon\Carbon;

class MarkAlfaAbsensi extends Command
{
    protected $signature = 'absensi:mark-alfa';

    protected $description = 'Set absensi alfa untuk siswa yang belum absen di hari ini';

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        $rencanaList = RencanaAbsensi::whereDate('tanggal', $today)
            ->where('status_hari', 'normal')
            ->get();

        foreach ($rencanaList as $rencana) {
            $siswaIds = Siswa::whereHas('riwayatKelas', function ($query) use ($rencana) {
                    $query->where('kelas_id', $rencana->kelas_id)
                        ->where('status', 'aktif');
                })
                ->pluck('siswa_id');

            if ($siswaIds->isEmpty()) {
                continue;
            }

            $sudahAbsenIds = Absensi::where('rensi_id', $rencana->rensi_id)
                ->whereIn('siswa_id', $siswaIds)
                ->pluck('siswa_id');

            $belumAbsenIds = $siswaIds->diff($sudahAbsenIds);

            if ($belumAbsenIds->isEmpty()) {
                continue;
            }

            $now = now();

            $rows = $belumAbsenIds->map(function ($siswaId) use ($rencana, $now) {
                return [
                    'siswa_id' => $siswaId,
                    'rensi_id' => $rencana->rensi_id,
                    'jam_datang' => null,
                    'jam_pulang' => null,
                    'latitude_datang' => null,
                    'longitude_datang' => null,
                    'latitude_pulang' => null,
                    'longitude_pulang' => null,
                    'status' => 'alfa',
                    'is_verif' => false,
                    'bukti' => null,
                    'keterangan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            Absensi::insert($rows);
        }

        return Command::SUCCESS;
    }
}
