<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Criterio extends Model
{
    use HasFactory;

    protected $table      = 'criterios';
    protected $primaryKey = 'id_criterios';

    protected $fillable = [
        'id_rubrica',
        'descripcion',
        'porcentaje',
    ];

    /**
     * Accessor: el contrato OpenAPI expone el campo como "nombre_criterio".
     * La tabla lo guarda como "descripcion". Este accessor permite usar ambos.
     */
    public function getNombreCriterioAttribute(): string
    {
        return $this->descripcion ?? '';
    }

    protected $appends = ['nombre_criterio'];

    public function rubrica()
    {
        return $this->belongsTo(Rubrica::class, 'id_rubrica', 'id_rubrica');
    }

    public function detalles()
    {
        return $this->hasMany(EvaluacionDetalle::class, 'id_criterios', 'id_criterios');
    }
}
