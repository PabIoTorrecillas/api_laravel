<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionDetalle extends Model
{
    protected $table      = 'evaluacion_detalles';
    public    $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'id_evaluacion',
        'id_criterios',
        'calificacion',
    ];

    public function criterio()
    {
        return $this->belongsTo(Criterio::class, 'id_criterios', 'id_criterios');
    }

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion', 'id_evaluacion');
    }
}
