<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Jurusan extends Model
{
    use HasFactory;

    protected $table = 'jurusan';
    protected $primaryKey = 'jurusan_id';
    protected $fillable = ['nama_jurusan'];

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'jurusan_id', 'jurusan_id');
    }
}