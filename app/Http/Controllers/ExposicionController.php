<?php

namespace App\Http\Controllers;

use App\Models\Exposicion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Exposiciones
 *
 * Endpoints para programar, actualizar y consultar las exposiciones de los equipos.
 */
class ExposicionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show']),
            new Middleware('role:Maestro,Admin', except: ['index', 'show']),
        ];
    }

    /**
     * Listar exposiciones.
     * * Obtiene el listado completo de exposiciones programadas.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": [
     * {
     * "id_expo": 1,
     * "tema": "Seguridad en Redes",
     * "fecha": "2026-05-20 09:00:00",
     * "equipo": {
     * "id_equipo": 3,
     * "equipo": "CyberSec Team",
     * "grupo": { "materia": { "nombre_materia": "Seguridad Informática" } }
     * },
     * "rubrica": { "id_rubrica": 1, "nombre": "Rúbrica General" }
     * }
     * ],
     * "message": "Exposiciones recuperadas."
     * }
     */
    public function index()
    {
        $exposiciones = Exposicion::with(['equipo.grupo.materia', 'rubrica'])->get();
        return $this->sendResponse($exposiciones, 'Exposiciones recuperadas.');
    }

    /**
     * Programar exposición.
     * * Asigna un tema, fecha y rúbrica a un equipo para su presentación.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @bodyParam id_equipo integer required ID del equipo asignado. Example: 1
     * @bodyParam id_rubrica integer required ID de la rúbrica a aplicar. Example: 1
     * @bodyParam tema string required Título de la exposición. Example: Inteligencia Artificial
     * @bodyParam fecha string required Formato Y-m-d H:i:s. Example: 2026-06-15 10:00:00
     * @response 201 {
     * "success": true,
     * "data": {
     * "id_expo": 12,
     * "tema": "Inteligencia Artificial",
     * "equipo": { "equipo": "Los Dinamita" },
     * "rubrica": { "nombre": "Rúbrica Proyectos" }
     * },
     * "message": "Exposición programada correctamente."
     * }
     * @response 422 { "message": "The fecha field is required.", "errors": { "fecha": ["..."] } }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_equipo'  => 'required|exists:equipos,id_equipo',
            'id_rubrica' => 'required|exists:rubricas,id_rubrica',
            'tema'       => 'required|string|max:255',
            'fecha'      => 'required|date_format:Y-m-d H:i:s'
        ]);

        $exposicion = Exposicion::create($request->all());
        return $this->sendResponse($exposicion->load(['equipo', 'rubrica']), 'Exposición programada correctamente.', 201);
    }

    /**
     * Ver exposición.
     * * Obtiene información exhaustiva: integrantes del equipo, criterios de evaluación y evaluaciones recibidas.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID de la exposición.
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_expo": 1,
     * "tema": "IA",
     * "equipo": { "integrantes": [...] },
     * "rubrica": { "criterios": [...] },
     * "evaluaciones": [ { "usuario": { "nombre": "Juan" }, "observaciones": "..." } ]
     * },
     * "message": "Detalles de la exposición cargados."
     * }
     * @response 404 { "success": false, "message": "Exposición no encontrada.", "data": [] }
     */
    public function show($id)
    {
        $exposicion = Exposicion::with(['equipo.integrantes.usuario', 'rubrica.criterios', 'evaluaciones.usuario'])->find($id);
        if (!$exposicion) return $this->sendError('Exposición no encontrada.', [], 404);
        return $this->sendResponse($exposicion, 'Detalles de la exposición cargados.');
    }

    /**
     * Actualizar exposición.
     * * Modifica el tema, la fecha o la rúbrica de una exposición ya programada.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID de la exposición.
     * @bodyParam tema string Nuevo tema. Example: IA Generativa
     * @bodyParam fecha string Nueva fecha. Example: 2026-07-01 11:00:00
     * @response 200 { "success": true, "data": { "id_expo": 1, "tema": "IA Generativa" }, "message": "Exposición actualizada." }
     * @response 404 { "success": false, "message": "Exposición no encontrada." }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function update(Request $request, $id)
    {
        $exposicion = Exposicion::find($id);
        if (!$exposicion) return $this->sendError('Exposición no encontrada.', [], 404);

        $exposicion->update($request->only(['tema', 'fecha', 'id_rubrica']));
        return $this->sendResponse($exposicion, 'Exposición actualizada.');
    }

    /**
     * Eliminar exposición.
     * * Cancela y elimina una exposición del sistema.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID de la exposición.
     * @response 200 { "success": true, "data": [], "message": "Exposición eliminada." }
     * @response 404 { "success": false, "message": "Exposición no encontrada." }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function destroy($id)
    {
        $exposicion = Exposicion::find($id);
        if (!$exposicion) return $this->sendError('Exposición no encontrada.', [], 404);
        $exposicion->delete();
        return $this->sendResponse([], 'Exposición eliminada.');
    }
}