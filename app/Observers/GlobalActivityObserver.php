<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\AktivitasTerbaru;
use Illuminate\Support\Facades\Auth;

class GlobalActivityObserver
{
    public function created(Model $model)
    {
        $this->logActivity($model, 'created');
    }

    public function updated(Model $model)
    {
        $this->logActivity($model, 'updated');
    }

    public function deleted(Model $model)
    {
        $this->logActivity($model, 'deleted');
    }

    protected function logActivity(Model $model, string $aksi)
    {
        // Hindari loop
        if ($model instanceof AktivitasTerbaru) {
            return;
        }

        $user = Auth::user();

        AktivitasTerbaru::create([
            'akun_id' => $user->akun_id ?? null,
            'tabel' => $model->getTable(),
            'aksi' => $aksi,
            'deskripsi' => ucfirst($aksi) . " data pada tabel " . $model->getTable(),
            // 'ikon' => $this->getIcon($aksi),
        ]);
    }

    protected function getIcon($aksi)
    {
        return match ($aksi) {
            'created' => 'plus-circle',
            'updated' => 'edit',
            'deleted' => 'trash',
            default => 'activity',
        };
    }
}