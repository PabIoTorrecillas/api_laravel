<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * @group Gestión de Alumnos
 *
 * Endpoints para administrar alumnos de la institución.
 */
class AlumnoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // Punto 4.1 del contrato: rol específico para catálogos generales.
        // Lectura: cualquier autenticado | Escritura: Maestro o Admin
        return [
            new Middleware('role:Alumno,Maestro,Admin', only: ['index', 'show', 'misCalificaciones']),
            new Middleware('role:Maestro,Admin',        except: ['index', 'show', 'misCalificaciones']),
        ];
    }

    /**
     * Listar todos los alumnos.
     * * Obtiene una lista de todos los alumnos registrados junto con su información de usuario.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": [
     * {
     * "id_usuario": 1,
     * "num_ctrl": "19030001",
     * "usuario": {
     * "id_usuario": 1,
     * "nombre": "Juan Pérez",
     * "email": "juan@example.com",
     * "id_rol": 2
     * }
     * }
     * ],
     * "message": "Lista de alumnos recuperada."
     * }
     */
    public function index()
    {
        $alumnos = Alumno::with('usuario')->get();
        return $this->sendResponse($alumnos, 'Lista de alumnos recuperada.');
    }

    /**
     * Crear un nuevo alumno.
     * * Registra un usuario y su perfil de alumno correspondiente utilizando una transacción.
     * <aside class="warning"><strong>Roles permitidos:</strong> Admin.</aside>
     * * @authenticated
     * @bodyParam nombre string required El nombre completo del alumno. Example: Juan Pérez
     * @bodyParam email string required El correo electrónico institucional. Example: juan@example.com
     * @bodyParam password string required La contraseña del alumno (mínimo 8 caracteres). Example: password123
     * @bodyParam num_ctrl string required El número de control único del alumno. Example: 19030001
     * @bodyParam id_rol integer required El ID del rol que se le asignará. Example: 2
     *
     * @response 201 {
     * "success": true,
     * "data": {
     * "id_usuario": 1,
     * "num_ctrl": "19030001",
     * "usuario": {
     * "id_usuario": 1,
     * "nombre": "Juan Pérez",
     * "email": "juan@example.com",
     * "id_rol": 2
     * }
     * },
     * "message": "Alumno creado con éxito."
     * }
     * @response 422 {
     * "message": "The given data was invalid.",
     * "errors": {
     * "email": ["El correo electrónico ya ha sido registrado."]
     * }
     * }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     * @response 500 {
     * "success": false,
     * "message": "Error al procesar el registro",
     * "data": ["Algo dentro del servidor falló..."]
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => 'required|min:8',
            'num_ctrl' => 'required|unique:alumnos,num_ctrl',
            'id_rol'   => 'required|exists:roles,id_rol'
        ]);

        try {
            $alumno = DB::transaction(function () use ($request) {
                $usuario = User::create([
                    'nombre'   => $request->nombre,
                    'email'    => $request->email,
                    'password' => Hash::make($request->password),
                    'id_rol'   => $request->id_rol
                ]);

                return Alumno::create([
                    'id_usuario' => $usuario->id_usuario,
                    'num_ctrl'   => $request->num_ctrl
                ])->load('usuario');
            });

            return $this->sendResponse($alumno, 'Alumno creado con éxito.', 201);

        } catch (\Exception $e) {
            return $this->sendError('Error al procesar el registro', [$e->getMessage()], 500);
        }
    }

    /**
     * Mostrar un alumno específico.
     * * Retorna la información detallada de un alumno por su ID de usuario, incluyendo sus grupos y equipos.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno, Maestro, Admin.</aside>
     * * @authenticated
     * @urlParam id integer required El ID del usuario asociado al alumno. Example: 1
     *
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_usuario": 1,
     * "num_ctrl": "19030001",
     * "usuario": { "nombre": "Juan Pérez", "email": "juan@example.com" },
     * "grupos": [],
     * "equipos": []
     * },
     * "message": "Datos del alumno obtenidos."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Alumno no encontrado.",
     * "data": []
     * }
     */
    public function show($id)
    {
        $alumno = Alumno::with(['usuario', 'grupos', 'equipos'])
                        ->where('id_usuario', $id)
                        ->first();

        if (!$alumno) {
            return $this->sendError('Alumno no encontrado.', [], 404);
        }

        return $this->sendResponse($alumno, 'Datos del alumno obtenidos.');
    }

    /**
     * Actualizar datos del alumno.
     * * Actualiza la información básica del usuario y el número de control del alumno.
     * <aside class="warning"><strong>Roles permitidos:</strong> Admin.</aside>
     * * @authenticated
     * @urlParam id integer required El ID del usuario asociado al alumno. Example: 1
     * @bodyParam nombre string El nuevo nombre del alumno. Example: Juan Modificado
     * @bodyParam email string El nuevo correo del alumno. Example: juan_mod@example.com
     * @bodyParam num_ctrl string El nuevo número de control. Example: 19030002
     *
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_usuario": 1,
     * "num_ctrl": "19030002",
     * "usuario": {
     * "id_usuario": 1,
     * "nombre": "Juan Modificado",
     * "email": "juan_mod@example.com"
     * }
     * },
     * "message": "Alumno actualizado."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Alumno no encontrado.",
     * "data": []
     * }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function update(Request $request, $id)
    {
        $alumno = Alumno::where('id_usuario', $id)->first();
        if (!$alumno) return $this->sendError('Alumno no encontrado.', [], 404);

        $usuario = User::find($id);

        $usuario->update($request->only(['nombre', 'email']));
        $alumno->update($request->only(['num_ctrl']));

        return $this->sendResponse($alumno->load('usuario'), 'Alumno actualizado.');
    }

    /**
     * Eliminar un alumno.
     * * Elimina lógicamente (o físicamente) al usuario, y en cascada se eliminará su registro de alumno.
     * <aside class="warning"><strong>Roles permitidos:</strong> Admin.</aside>
     * * @authenticated
     * @urlParam id integer required El ID del usuario asociado al alumno a eliminar. Example: 1
     *
     * @response 200 {
     * "success": true,
     * "data": [],
     * "message": "Alumno eliminado correctamente."
     * }
     * @response 404 {
     * "success": false,
     * "message": "Usuario no existe.",
     * "data": []
     * }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     */
    public function destroy($id)
    {
        $usuario = User::find($id);
        if (!$usuario) return $this->sendError('Usuario no existe.', [], 404);

        $usuario->delete(); // Gracias al ON DELETE CASCADE, borra al Alumno también.

        return $this->sendResponse([], 'Alumno eliminado correctamente.');
    }

    /**
     * Mis Calificaciones.
     * * Obtiene el historial de exposiciones y evaluaciones del alumno autenticado.
     * <aside class="notice"><strong>Roles permitidos:</strong> Alumno.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": [
     * {
     * "equipo": "Los Dinamita",
     * "exposiciones": [
     * { "tema": "IA", "evaluaciones": [ { "observaciones": "Buen trabajo", "detalles": [...] } ] }
     * ]
     * }
     * ],
     * "message": "Calificaciones obtenidas."
     * }
     * @response 403 { "message": "Solo los alumnos pueden ver sus calificaciones." }
     */
    public function misCalificaciones(Request $request)
    {
        $user = $request->user()->load('rol');
        
        if ($user->rol->nombre_rol !== 'Alumno') {
            return $this->sendError('Solo los alumnos pueden ver sus calificaciones.', [], 403);
        }

        $alumno = Alumno::with(['equipos.exposiciones.evaluaciones.detalles.criterio'])
                        ->where('id_usuario', $user->id_usuario)
                        ->first();

        return $this->sendResponse($alumno->equipos, 'Calificaciones obtenidas.');
    }
}