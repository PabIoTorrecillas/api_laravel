<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    use HasFactory;

    protected $table      = 'materias';
    protected $primaryKey = 'id_materia';

    protected $fillable = [
        'clave_materia',   // Campo del contrato OpenAPI
        'nombre_materia',  // Campo del contrato OpenAPI
        'materia',         // Campo original (compatibilidad)
    ];

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_materia', 'id_materia');
    }
}
