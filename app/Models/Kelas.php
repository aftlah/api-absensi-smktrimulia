<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    /** @use HasFactory<\Database\Factories\KelasFactory> */
    use HasFactory;

    protected $table = 'kelas';
    protected $primaryKey = 'kelas_id';
    protected $fillable = ['tingkat', 'jurusan', 'paralel'];

    public function siswa()
    {
        return $this->hasMany(Siswa::class, 'kelas_id', 'kelas_id');
    }

    public function waliKelas()
    {
        return $this->hasOne(WaliKelas::class, 'kelas_id', 'kelas_id');
    }
}
