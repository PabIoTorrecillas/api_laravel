<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Equipos
 *
 * Endpoints para administrar los equipos de trabajo, sus integrantes y su relación con las materias.
 */
class EquipoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show', 'store', 'updateIntegrantes']),
            new Middleware('role:Maestro,Admin', only: ['destroy']),
        ];
    }

    /**
     * Listar equipos.
     * * Obtiene una lista de todos los equipos con su materia e integrantes.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": [
     * {
     * "id_equipo": 1,
     * "id_grupo": 2,
     * "equipo": "Los Dinamita",
     * "active": 1,
     * "created_at": "2026-03-08T10:00:00.000000Z",
     * "updated_at": "2026-03-08T10:00:00.000000Z",
     * "grupo": {
     * "id_grupo": 2,
     * "id_materia": 5,
     * "materia": {
     * "id_materia": 5,
     * "nombre_materia": "Programación Web"
     * }
     * },
     * "integrantes": [
     * {
     * "id_usuario": 10,
     * "nombre": "Juan Pérez",
     * "email": "juan@example.com",
     * "usuario": {
     * "id_usuario": 10,
     * "nombre": "Juan Pérez"
     * }
     * }
     * ]
     * }
     * ],
     * "message": "Equipos recuperados exitosamente."
     * }
     * @response 401 { "message": "Unauthenticated." }
     */
    public function index()
    {
        $equipos = Equipo::with(['grupo.materia', 'integrantes.usuario'])->get();
        return $this->sendResponse($equipos, 'Equipos recuperados exitosamente.');
    }

    /**
     * Crear un equipo.
     * * Crea un nuevo equipo y asigna los alumnos integrantes mediante una transacción.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @bodyParam id_grupo integer required ID del grupo al que pertenece. Example: 1
     * @bodyParam equipo string required Nombre identificador del equipo. Example: Los Dinamita
     * @bodyParam alumnos int[] required Array de IDs de usuarios (alumnos). Example: [10, 11, 12]
     * @response 201 {
     * "success": true,
     * "data": {
     * "id_equipo": 5,
     * "id_grupo": 1,
     * "equipo": "Los Dinamita",
     * "active": true,
     * "created_at": "2026-03-08T12:00:00.000000Z",
     * "updated_at": "2026-03-08T12:00:00.000000Z",
     * "integrantes": [
     * { "id_usuario": 10, "nombre": "Juan Pérez" },
     * { "id_usuario": 11, "nombre": "Maria Lopez" }
     * ]
     * },
     * "message": "Equipo creado e integrantes asignados."
     * }
     * @response 422 {
     * "message": "The alumnos field is required.",
     * "errors": {
     * "alumnos": ["The alumnos field is required."],
     * "id_grupo": ["The selected id grupo is invalid."]
     * }
     * }
     * @response 500 {
     * "success": false,
     * "message": "Error al crear el equipo.",
     * "data": ["SQLSTATE[23000]: Integrity constraint violation..."]
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_grupo' => 'required|exists:grupos,id_grupo',
            'equipo'   => 'required|string|max:100',
            'alumnos'  => 'required|array',
            'alumnos.*'=> 'exists:alumnos,id_usuario'
        ]);

        try {
            $nuevoEquipo = DB::transaction(function () use ($request) {
                $equipo = Equipo::create([
                    'id_grupo' => $request->id_grupo,
                    'equipo'   => $request->equipo,
                    'active'   => true
                ]);
                $equipo->integrantes()->attach($request->alumnos);
                return $equipo->load('integrantes.usuario');
            });
            return $this->sendResponse($nuevoEquipo, 'Equipo creado e integrantes asignados.', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error al crear el equipo.', [$e->getMessage()], 500);
        }
    }

    /**
     * Ver un equipo específico.
     * * Muestra la información detallada de un equipo, incluyendo sus integrantes y exposiciones programadas.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID del equipo. Example: 1
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_equipo": 1,
     * "equipo": "Los Dinamita",
     * "grupo": { "id_grupo": 1, "materia": { "nombre_materia": "Base de Datos" } },
     * "integrantes": [
     * { "id_usuario": 10, "nombre": "Juan Pérez" },
     * { "id_usuario": 11, "nombre": "Maria Lopez" }
     * ],
     * "exposiciones": [
     * { "id_exposicion": 1, "fecha": "2026-04-10", "tema": "Normalización" }
     * ]
     * },
     * "message": "Detalles del equipo recuperados."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Equipo no encontrado.",
     * "data": { "error": "El ID proporcionado no existe en la base de datos." }
     * }
     */
    public function show($id)
    {
        $equipo = Equipo::with(['grupo.materia', 'integrantes.usuario', 'exposiciones'])->find($id);
        if (!$equipo) {
            return $this->sendError('Equipo no encontrado.', ['error' => 'El ID proporcionado no existe en la base de datos.'], 404);
        }
        return $this->sendResponse($equipo, 'Detalles del equipo recuperados.');
    }

    /**
     * Actualizar integrantes.
     * * Reemplaza la lista actual de integrantes del equipo por una nueva lista de IDs de alumnos.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID del equipo a actualizar. Example: 1
     * @bodyParam alumnos int[] required Nuevos IDs de alumnos integrantes. Example: [14, 15]
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_equipo": 1,
     * "equipo": "Los Dinamita",
     * "integrantes": [
     * { "id_usuario": 14, "nombre": "Kevin Flynn" },
     * { "id_usuario": 15, "nombre": "Alan Bradley" }
     * ]
     * },
     * "message": "Integrantes actualizados exitosamente."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Equipo no encontrado.",
     * "data": []
     * }
     * @response 422 {
     * "message": "The alumnos.0 field is invalid.",
     * "errors": {
     * "alumnos.0": ["The selected alumnos.0 is invalid."]
     * }
     * }
     */
    public function updateIntegrantes(Request $request, $id)
    {
        $request->validate([
            'alumnos' => 'required|array', 
            'alumnos.*' => 'exists:alumnos,id_usuario'
        ]);

        $equipo = Equipo::find($id);
        if (!$equipo) {
            return $this->sendError('Equipo no encontrado.', [], 404);
        }

        $equipo->integrantes()->sync($request->alumnos);
        return $this->sendResponse($equipo->load('integrantes.usuario'), 'Integrantes actualizados exitosamente.');
    }

    /**
     * Eliminar equipo.
     * * Elimina permanentemente el equipo del sistema.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID del equipo a eliminar. Example: 1
     * @response 200 {
     * "success": true,
     * "data": [],
     * "message": "Equipo eliminado correctamente."
     * }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     * @response 404 {
     * "success": false,
     * "message": "Equipo no encontrado.",
     * "data": []
     * }
     */
    public function destroy($id)
    {
        $equipo = Equipo::find($id);
        if (!$equipo) {
            return $this->sendError('Equipo no encontrado.', [], 404);
        }
        
        $equipo->delete();
        return $this->sendResponse([], 'Equipo eliminado correctamente.');
    }
}