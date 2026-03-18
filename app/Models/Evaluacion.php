<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    use HasFactory;

    protected $table      = 'evaluaciones';
    protected $primaryKey = 'id_evaluacion';

    protected $fillable = [
        'id_expo',
        'id_usuario',
        'observaciones',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function exposicion()
    {
        return $this->belongsTo(Exposicion::class, 'id_expo', 'id_expo');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relación correcta: hasMany hacia EvaluacionDetalle.
     * (La tabla evaluacion_detalles NO tiene timestamps, por eso usamos el modelo propio)
     */
    public function detalles()
    {
        return $this->hasMany(EvaluacionDetalle::class, 'id_evaluacion', 'id_evaluacion');
    }
}
