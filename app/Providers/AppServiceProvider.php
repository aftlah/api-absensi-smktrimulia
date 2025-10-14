<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use App\Models\{Siswa, GuruPiket, Kelas, Akun, Admin, WaliKelas, JadwalPiket, Absensi, Pengaturan};
use App\Observers\GlobalActivityObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */


    public function boot(): void
    {
        // Daftarkan observer untuk setiap model yang ingin diawasi
        Akun::observe(GlobalActivityObserver::class);
        Siswa::observe(GlobalActivityObserver::class);
        GuruPiket::observe(GlobalActivityObserver::class);
        Kelas::observe(GlobalActivityObserver::class);
        Admin::observe(GlobalActivityObserver::class);
        WaliKelas::observe(GlobalActivityObserver::class);
        JadwalPiket::observe(GlobalActivityObserver::class);
        Absensi::observe(GlobalActivityObserver::class);
        Pengaturan::observe(GlobalActivityObserver::class);
    }

}
