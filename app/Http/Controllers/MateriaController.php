<?php

namespace App\Http\Controllers;

use App\Models\Materia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Materias
 *
 * Endpoints para la gestión del catálogo de materias de la institución.
 */
class MateriaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // Lectura: cualquier usuario autenticado
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show']),
            // Escritura sobre catálogos generales: Maestro o Admin
            new Middleware('role:Maestro,Admin', except: ['index', 'show']),
        ];
    }

    /**
     * Listar materias (paginado).
     * GET /api/v1/materias
     *
     * @authenticated
     * @queryParam page integer Página base-0. Default: 0. Example: 0
     * @queryParam size integer Elementos por página (1-100). Default: 10. Example: 10
     * @queryParam nombre string Filtrar por nombre parcial. Example: Programación
     */
    public function index(Request $request)
    {
        $query = Materia::query();

        if ($request->filled('nombre')) {
            $query->where('nombre_materia', 'like', '%' . $request->nombre . '%');
        }

        $size = max(1, min((int) $request->input('size', 10), 100));
        $page = max(0, (int) $request->input('page', 0));

        // Laravel usa base-1 para paginar; el contrato usa base-0
        $paginator = $query->paginate($size, ['*'], 'page', $page + 1);

        $content = collect($paginator->items())->map(fn($m) => [
            'id_materia'     => $m->id_materia,
            'clave_materia'  => $m->clave_materia,
            'nombre_materia' => $m->nombre_materia,
        ]);

        return response()->json([
            'page'          => $page,
            'size'          => $size,
            'totalElements' => $paginator->total(),
            'totalPages'    => $paginator->lastPage(),
            'content'       => $content,
        ], 200);
    }

    /**
     * Crear una nueva materia.
     * POST /api/v1/materias
     *
     * @authenticated
     * @bodyParam clave_materia  string required Clave única (2-20 chars). Example: PROG-01
     * @bodyParam nombre_materia string required Nombre (3-100 chars). Example: Programación Web
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave_materia'  => 'required|string|min:2|max:20|unique:materias,clave_materia',
            'nombre_materia' => 'required|string|min:3|max:100|unique:materias,nombre_materia',
        ]);

        // Validación duplicado con 409
        $existe = \App\Models\Materia::where('clave_materia', $validated['clave_materia'])->first();
        if ($existe) {
            return $this->errorResponse(409, 'Conflict',
                "Ya existe una materia con la clave {$validated['clave_materia']}",
                '/api/v1/materias');
        }

        $materia = Materia::create($validated);

        return response()->json([
            'id_materia'     => $materia->id_materia,
            'clave_materia'  => $materia->clave_materia,
            'nombre_materia' => $materia->nombre_materia,
        ], 201);
    }

    /**
     * Obtener materia por ID.
     * GET /api/v1/materias/{id}
     *
     * @authenticated
     * @urlParam id integer required El ID de la materia. Example: 1
     */
    public function show($id)
    {
        $materia = Materia::find($id);

        if (!$materia) {
            return $this->errorResponse(404, 'Not Found',
                "La materia con id {$id} no existe",
                "/api/v1/materias/{$id}");
        }

        return response()->json([
            'id_materia'     => $materia->id_materia,
            'clave_materia'  => $materia->clave_materia,
            'nombre_materia' => $materia->nombre_materia,
        ], 200);
    }

    /**
     * Actualizar materia.
     * PUT /api/v1/materias/{id}
     *
     * @authenticated
     * @urlParam id integer required El ID de la materia. Example: 1
     * @bodyParam clave_materia  string required Nueva clave. Example: PROG-02
     * @bodyParam nombre_materia string required Nuevo nombre. Example: Programación Web Avanzada
     */
    public function update(Request $request, $id)
    {
        $materia = Materia::find($id);

        if (!$materia) {
            return $this->errorResponse(404, 'Not Found',
                "La materia con id {$id} no existe",
                "/api/v1/materias/{$id}");
        }

        $validated = $request->validate([
            'clave_materia'  => "required|string|min:2|max:20|unique:materias,clave_materia,{$id},id_materia",
            'nombre_materia' => "required|string|min:3|max:100|unique:materias,nombre_materia,{$id},id_materia",
        ]);

        $materia->update($validated);

        return response()->json([
            'id_materia'     => $materia->id_materia,
            'clave_materia'  => $materia->clave_materia,
            'nombre_materia' => $materia->nombre_materia,
        ], 200);
    }

    /**
     * Eliminar materia.
     * DELETE /api/v1/materias/{id}
     *
     * @authenticated
     * @urlParam id integer required El ID de la materia. Example: 1
     */
    public function destroy($id)
    {
        $materia = Materia::find($id);

        if (!$materia) {
            return $this->errorResponse(404, 'Not Found',
                "La materia con id {$id} no existe",
                "/api/v1/materias/{$id}");
        }

        $materia->delete();

        return response()->noContent(); // 204 según contrato
    }

    // ── Respuesta de error estándar del contrato OpenAPI ──────────────────
    private function errorResponse(int $status, string $error, string $message, string $path)
    {
        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'status'    => $status,
            'error'     => $error,
            'message'   => $message,
            'path'      => $path,
        ], $status);
    }
}
