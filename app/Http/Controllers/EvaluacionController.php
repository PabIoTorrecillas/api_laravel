<?php

namespace App\Http\Controllers;

use App\Models\Evaluacion;
use App\Models\EvaluacionDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Evaluaciones
 *
 * Registra y consulta evaluaciones de exposiciones según el contrato OpenAPI 1.1.0.
 */
class EvaluacionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show', 'store']),
            new Middleware('role:Maestro,Admin',         only: ['destroy']),
        ];
    }

    // ── GET /evaluaciones ─────────────────────────────────────────────────
    public function index()
    {
        $evaluaciones = Evaluacion::with(['exposicion', 'usuario', 'detalles.criterio'])->get();
        return $this->sendResponse($evaluaciones, 'Evaluaciones recuperadas.');
    }

    // ── POST /evaluaciones ────────────────────────────────────────────────
    /**
     * Registrar evaluación (contrato OpenAPI §/evaluaciones POST).
     *
     * @authenticated
     * @bodyParam id_exposicion       integer required ID de la exposición. Example: 2
     * @bodyParam id_alumno_evaluador integer required ID del usuario evaluador. Example: 5
     * @bodyParam detalles            object[] required Criterios a evaluar.
     * @bodyParam detalles[].id_criterio  integer required. Example: 1
     * @bodyParam detalles[].calificacion number  required (0–10). Example: 9.0
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_exposicion'           => 'required|exists:exposiciones,id_expo',
            'id_alumno_evaluador'     => 'required|exists:usuarios,id_usuario',
            'detalles'                => 'required|array|min:1',
            'detalles.*.id_criterio'  => 'required|exists:criterios,id_criterios',
            'detalles.*.calificacion' => 'required|numeric|min:0|max:10',
        ]);

        // 409 — evaluación duplicada
        $duplicado = Evaluacion::where('id_expo',    $request->id_exposicion)
                               ->where('id_usuario', $request->id_alumno_evaluador)
                               ->exists();

        if ($duplicado) {
            return response()->json([
                'timestamp' => now()->toIso8601String(),
                'status'    => 409,
                'error'     => 'Conflict',
                'message'   => "El alumno {$request->id_alumno_evaluador} ya registró una evaluación para la exposición {$request->id_exposicion}",
                'path'      => '/api/v1/evaluaciones',
            ], 409);
        }

        try {
            $evaluacion = DB::transaction(function () use ($request) {

                $eval = Evaluacion::create([
                    'id_expo'    => $request->id_exposicion,
                    'id_usuario' => $request->id_alumno_evaluador,
                    'fecha'      => now(),
                ]);

                foreach ($request->detalles as $item) {
                    EvaluacionDetalle::create([
                        'id_evaluacion' => $eval->id_evaluacion,
                        'id_criterios'  => $item['id_criterio'],
                        'calificacion'  => $item['calificacion'],
                    ]);
                }

                return $eval->load('detalles.criterio');
            });

            // Promedio ponderado simple (calificacion_final)
            $calificacionFinal = round(
                $evaluacion->detalles->avg('calificacion') ?? 0,
                2
            );

            // Respuesta exacta del contrato
            $detallesOut = $evaluacion->detalles->map(fn($d) => [
                'id_criterio'     => $d->id_criterios,
                // El campo del contrato es nombre_criterio; la tabla usa "descripcion"
                'nombre_criterio' => optional($d->criterio)->descripcion,
                'calificacion'    => (float) $d->calificacion,
            ]);

            return response()->json([
                'id_evaluacion'       => $evaluacion->id_evaluacion,
                'id_exposicion'       => $evaluacion->id_expo,
                'id_alumno_evaluador' => $evaluacion->id_usuario,
                'calificacion_final'  => $calificacionFinal,
                'fecha_registro'      => $evaluacion->fecha,
                'detalles'            => $detallesOut,
            ], 201);

        } catch (\Exception $e) {
            return $this->sendError('Error al registrar evaluación.', [$e->getMessage()], 500);
        }
    }

    // ── GET /evaluaciones/{id} ────────────────────────────────────────────
    public function show($id)
    {
        $evaluacion = Evaluacion::with(['exposicion.equipo', 'usuario', 'detalles.criterio'])
                                ->find($id);

        if (!$evaluacion) {
            return $this->sendError('Evaluación no encontrada.', [], 404);
        }

        return $this->sendResponse($evaluacion, 'Detalle de evaluación obtenido.');
    }

    // ── DELETE /evaluaciones/{id} ─────────────────────────────────────────
    public function destroy($id)
    {
        $evaluacion = Evaluacion::find($id);

        if (!$evaluacion) {
            return $this->sendError('Evaluación no encontrada.', [], 404);
        }

        try {
            $evaluacion->delete(); // detalles eliminados por ON DELETE CASCADE
            return $this->sendResponse([], 'Evaluación eliminada correctamente.');
        } catch (\Exception $e) {
            return $this->sendError('Error al eliminar la evaluación.', [$e->getMessage()], 500);
        }
    }
}
