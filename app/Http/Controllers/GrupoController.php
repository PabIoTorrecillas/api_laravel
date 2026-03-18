<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Grupos
 *
 * Endpoints para administrar Grupos de una materia y la inscripción de alumnos.
 */
class GrupoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show']),
            new Middleware('role:Maestro,Admin', except: ['index', 'show']),
        ];
    }

    /**
     * Listar todos los grupos.
     * * Obtiene una lista de todos los grupos junto con la información de su materia y el maestro asignado.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": [
     * {
     * "id_grupo": 1,
     * "id_materia": 5,
     * "id_maestro": 2,
     * "grupo": "A",
     * "materia": { "id_materia": 5, "materia": "Programación Web" },
     * "maestro": { "id_usuario": 2, "usuario": { "nombre": "Prof. García" } }
     * }
     * ],
     * "message": "Grupos recuperados con éxito."
     * }
     */
    public function index()
    {
        $grupos = Grupo::with(['materia', 'maestro.usuario'])->get();
        return $this->sendResponse($grupos, 'Grupos recuperados con éxito.');
    }

    /**
     * Crear un nuevo grupo.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @bodyParam id_materia integer required El ID de la materia. Example: 1
     * @bodyParam id_maestro integer required El ID del usuario que es maestro. Example: 2
     * @bodyParam grupo string required El identificador del grupo (ej. A, B, 401). Max: 10 caracteres. Example: 601-A
     *
     * @response 201 {
     * "success": true,
     * "data": {
     * "id_grupo": 10,
     * "id_materia": 1,
     * "id_maestro": 2,
     * "grupo": "601-A",
     * "materia": { "id_materia": 1, "materia": "Matemáticas" },
     * "maestro": { "id_usuario": 2, "usuario": { "nombre": "Prof. García" } }
     * },
     * "message": "Grupo creado correctamente."
     * }
     * @response 422 {
     * "message": "The given data was invalid.",
     * "errors": { "id_materia": ["The selected id materia is invalid."] }
     * }
     * @response 403 {
     * "message": "Access denied. You do not have the correct role."
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_materia' => 'required|exists:materias,id_materia',
            'id_maestro' => 'required|exists:maestros,id_usuario',
            'grupo'      => 'required|string|max:10'
        ]);

        $grupo = Grupo::create($request->all());

        return $this->sendResponse(
            $grupo->load(['materia', 'maestro.usuario']),
            'Grupo creado correctamente.',
            201
        );
    }

    /**
     * Mostrar un grupo específico.
     * * Retorna los detalles de un grupo incluyendo la lista de alumnos inscritos.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required El ID del grupo. Example: 1
     *
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_grupo": 1,
     * "grupo": "A",
     * "alumnos": [
     * { "id_usuario": 10, "usuario": { "nombre": "Alumno Ejemplo" } }
     * ]
     * },
     * "message": "Detalles del grupo obtenidos."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Grupo no encontrado.",
     * "data": []
     * }
     */
    public function show($id)
    {
        $grupo = Grupo::with(['materia', 'maestro.usuario', 'alumnos.usuario'])
                      ->find($id);

        if (!$grupo) {
            return $this->sendError('Grupo no encontrado.', [], 404);
        }

        return $this->sendResponse($grupo, 'Detalles del grupo obtenidos.');
    }

    /**
     * Inscribir alumnos al grupo.
     * * Actualiza la lista de alumnos mediante el método sync (reemplaza la lista actual por la nueva).
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required El ID del grupo. Example: 1
     * @bodyParam alumnos int[] required Array con los IDs (id_usuario) de los alumnos a inscribir. Example: [10, 11, 12]
     *
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_grupo": 1,
     * "alumnos": [ { "id_usuario": 10, "usuario": {...} } ]
     * },
     * "message": "Lista de alumnos actualizada correctamente."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Grupo no encontrado.",
     * "data": []
     * }
     * @response 403 {
     * "message": "Access denied. You do not have the correct role."
     * }
     */
    public function inscribirAlumnos(Request $request, $id)
    {
        $request->validate([
            'alumnos' => 'required|array',
            'alumnos.*' => 'exists:alumnos,id_usuario'
        ]);

        $grupo = Grupo::find($id);
        if (!$grupo) return $this->sendError('Grupo no encontrado.', [], 404);

        $grupo->alumnos()->sync($request->alumnos);

        return $this->sendResponse(
            $grupo->load('alumnos.usuario'),
            'Lista de alumnos actualizada correctamente.'
        );
    }

    /**
     * Eliminar un grupo.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required El ID del grupo a eliminar. Example: 1
     *
     * @response 200 {
     * "success": true,
     * "data": [],
     * "message": "Grupo eliminado exitosamente."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Grupo no encontrado.",
     * "data": []
     * }
     * @response 403 {
     * "message": "Access denied. You do not have the correct role."
     * }
     */
    public function destroy($id)
    {
        $grupo = Grupo::find($id);
        if (!$grupo) return $this->sendError('Grupo no encontrado.', [], 404);

        $grupo->delete();
        return $this->sendResponse([], 'Grupo eliminado exitosamente.');
    }
}