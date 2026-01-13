<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Siswa;
use App\Models\RiwayatKelas;

class EnsureRiwayatKelas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'siswa:ensure-riwayat-kelas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure all students have riwayat_kelas records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking students without riwayat_kelas records...');

        $this->info('Command deprecated: kelas_id tidak lagi disimpan di tabel siswa.');
    }
}
