<?php

namespace App\Http\Controllers;

use App\Models\Rubrica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Rúbricas
 *
 * Endpoints para definir las rúbricas de evaluación y sus criterios (porcentajes) correspondientes.
 */
class RubricaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show']),
            new Middleware('role:Maestro,Admin', except: ['index', 'show']),
        ];
    }

    /**
     * Listar rúbricas.
     * * Obtiene el catálogo completo de rúbricas junto con sus criterios.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": [
     * {
     * "id_rubrica": 1,
     * "rubrica": "Rúbrica Final Proyectos",
     * "criterios": [
     * { "id_criterios": 1, "descripcion": "Dominio del tema", "porcentaje": 40 }
     * ]
     * }
     * ],
     * "message": "Rúbricas recuperadas."
     * }
     */
    public function index()
    {
        $rubricas = Rubrica::with('criterios')->get();
        return $this->sendResponse($rubricas, 'Rúbricas recuperadas.');
    }

    /**
     * Crear rúbrica con criterios.
     * * Crea una rúbrica y asocia múltiples criterios de evaluación de forma atómica.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @bodyParam rubrica string required Nombre descriptivo de la rúbrica. Example: Rubrica Final
     * @bodyParam criterios object[] required Lista de criterios que componen la rúbrica.
     * @bodyParam criterios[].descripcion string required Descripción del criterio. Example: Dominio del tema
     * @bodyParam criterios[].porcentaje number required Valor porcentual (1-100). Example: 30
     * @response 201 {
     * "success": true,
     * "data": {
     * "id_rubrica": 3,
     * "rubrica": "Rubrica Final",
     * "criterios": [
     * { "descripcion": "Dominio del tema", "porcentaje": 30 }
     * ]
     * },
     * "message": "Rúbrica y criterios creados con éxito."
     * }
     * @response 422 {
     * "message": "The criterios.0.porcentaje must not be greater than 100.",
     * "errors": { "criterios.0.porcentaje": ["..."] }
     * }
     * @response 403 {
     * "message": "Access denied. You do not have the correct role."
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'rubrica' => 'required|string|max:255',
            'criterios' => 'required|array|min:1',
            'criterios.*.descripcion' => 'required|string',
            'criterios.*.porcentaje' => 'required|numeric|min:1|max:100',
        ]);

        try {
            $nuevaRubrica = DB::transaction(function () use ($request) {
                $rubrica = Rubrica::create(['rubrica' => $request->rubrica]);
                foreach ($request->criterios as $criterio) {
                    $rubrica->criterios()->create($criterio);
                }
                return $rubrica->load('criterios');
            });
            return $this->sendResponse($nuevaRubrica, 'Rúbrica y criterios creados con éxito.', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error al crear la rúbrica.', [$e->getMessage()], 500);
        }
    }

    /**
     * Ver rúbrica.
     * * Muestra los detalles de una rúbrica específica y sus criterios.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID de la rúbrica. Example: 1
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_rubrica": 1,
     * "rubrica": "Rúbrica Final",
     * "criterios": [...]
     * },
     * "message": "Detalles de la rúbrica obtenidos."
     * }
     * @response 404 { "success": false, "message": "Rúbrica no encontrada.", "data": [] }
     */
    public function show($id)
    {
        $rubrica = Rubrica::with('criterios')->find($id);
        if (!$rubrica) return $this->sendError('Rúbrica no encontrada.', [], 404);
        return $this->sendResponse($rubrica, 'Detalles de la rúbrica obtenidos.');
    }

    /**
     * Actualizar rúbrica.
     * * Actualiza el nombre de una rúbrica existente.
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID de la rúbrica. Example: 1
     * @bodyParam rubrica string Nombre descriptivo de la rúbrica. Example: Rúbrica Parcial
     * @response 200 { "success": true, "data": {...}, "message": "Rúbrica actualizada correctamente." }
     * @response 404 { "success": false, "message": "Rúbrica no encontrada.", "data": [] }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function update(Request $request, $id)
    {
        $rubrica = Rubrica::find($id);
        if (!$rubrica) return $this->sendError('Rúbrica no encontrada.', [], 404);

        $request->validate([
            'rubrica' => 'sometimes|required|string|max:255',
        ]);

        $rubrica->update($request->only('rubrica'));
        return $this->sendResponse($rubrica, 'Rúbrica actualizada correctamente.');
    }

    /**
     * Eliminar rúbrica.
     * * Elimina una rúbrica del sistema (sus criterios se eliminarán en cascada si la base de datos está configurada para ello).
     * <aside class="warning"><strong>Roles permitidos:</strong> Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required ID de la rúbrica. Example: 1
     * @response 200 { "success": true, "data": [], "message": "Rúbrica eliminada correctamente." }
     * @response 404 { "success": false, "message": "Rúbrica no encontrada.", "data": [] }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function destroy($id)
    {
        $rubrica = Rubrica::find($id);
        if (!$rubrica) return $this->sendError('Rúbrica no encontrada.', [], 404);
        
        $rubrica->delete();
        return $this->sendResponse([], 'Rúbrica eliminada correctamente.');
    }
}