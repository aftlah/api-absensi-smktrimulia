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

        // Get all students who don't have any riwayat_kelas record
        $studentsWithoutRiwayat = Siswa::whereDoesntHave('riwayatKelas')
            ->whereNotNull('kelas_id')
            ->get();

        if ($studentsWithoutRiwayat->isEmpty()) {
            $this->info('All students already have riwayat_kelas records.');
            return;
        }

        $this->info("Found {$studentsWithoutRiwayat->count()} students without riwayat_kelas records.");

        $bar = $this->output->createProgressBar($studentsWithoutRiwayat->count());
        $bar->start();

        $created = 0;
        foreach ($studentsWithoutRiwayat as $student) {
            try {
                RiwayatKelas::create([
                    'siswa_id' => $student->siswa_id,
                    'kelas_id' => $student->kelas_id,
                    'status' => 'aktif'
                ]);
                $created++;
            } catch (\Exception $e) {
                $this->error("Failed to create riwayat_kelas for student {$student->nis}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully created {$created} riwayat_kelas records.");
    }
}