<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

/**
 * @group Gestión de Autenticación
 *
 * Endpoints para administrar el acceso al sistema.
 */
class AuthController extends Controller
{
    /**
     * Inicio de sesión.
     * @bodyParam email string required El correo del usuario. Example: admin@example.com
     * @bodyParam password string required La contraseña. Example: password
     * @response 200 {
     * "success": true,
     * "data": {
     * "token": "1|ra67sdf...",
     * "user": { "id_usuario": 1, 
     * "nombre": "Admin", 
     * "email": "admin@example.com", 
     * "id_rol": 1, "created_at": "2026-03-06T18:01:01.000000Z",
     *  "updated_at": "2026-03-06T18:01:01.000000Z",
     * "rol": {
     *           "id_rol": 1,
     *           "nombre_rol": "Maestro",
     *           "created_at": "2026-03-06T18:01:00.000000Z",
     *           "updated_at": "2026-03-06T18:01:00.000000Z"
     *       } }
     * },
     * "message": "Usuario autenticado con éxito."
     * }
     * @response 401 {
     * "success": false,
     * "message": "Credenciales incorrectas.",
     * "data": { "error": "No autorizado" }
     * }
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        // Soporta login por email o por nombre de usuario
        $user = User::where('email', $request->username)
                    ->orWhere('nombre', $request->username)
                    ->with('rol')
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'timestamp' => now()->toIso8601String(),
                'status'    => 401,
                'error'     => 'Unauthorized',
                'message'   => 'Token JWT inválido o expirado',
                'path'      => '/api/v1/auth/login',
            ], 401);
        }

        $token = $user->createToken('API_TOKEN')->plainTextToken;

        return response()->json([
            'token'     => $token,
            'tipo'      => 'Bearer',
            'expira_en' => 3600,
        ], 200);
    }

    /**
     * Registro de usuario.
     * @bodyParam nombre string required Nombre completo. Example: Nuevo Usuario
     * @bodyParam email string required Correo único. Example: nuevo@example.com
     * @bodyParam password string required Mínimo 8 caracteres. Example: password123
     * @bodyParam id_rol integer required ID del rol. Example: 2
     * @response 201 {
     *"success": true,
     *"data": {
     *    "token": "4|Y7CHpCxOr...",
     *    "user": {
     *        "nombre": "maestro",
     *        "email": "maestro@mail.com",
     *        "id_rol": 2,
     *        "updated_at": "2026-03-08T05:50:38.000000Z",
     *        "created_at": "2026-03-08T05:50:38.000000Z",
     *        "id_usuario": 3,
     *        "rol": {
     *            "id_rol": 2,
     *            "nombre_rol": "Alumno",
     *            "created_at": "2026-03-06T18:01:00.000000Z",
     *            "updated_at": "2026-03-06T18:01:00.000000Z"
     *        }
     *    }
     *},
     *"message": "Usuario registrado correctamente."
     *}
     * @response 401 { "message": "Unauthenticated." }
     * @response 403 { "message": "Access denied. You do not have the correct role." }
     * @response 422 {
     *"message": "The password field confirmation does not match. (and 1 more error)",
     *"errors": {
     *    "password": [
     *        "The password field confirmation does not match."
     *    ],
     *    "id_rol": [
     *        "The id rol field is required."
     *    ]
     *}
     *}
     */
    public function register(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:255',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => 'required|min:8|confirmed',
            'id_rol'   => 'required|exists:roles,id_rol'
        ]);

        $user = User::create([
            'nombre'   => $request->nombre,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'id_rol'   => $request->id_rol,
        ]);

        $success['token'] = $user->createToken('API_TOKEN')->plainTextToken;
        $success['user']  = $user->load('rol');

        return $this->sendResponse($success, 'Usuario registrado correctamente.', 201);
    }

    /**
     * Cerrar sesión.
     * @authenticated
     * @response 200 { "success": true, "data": [], "message": "Sesión cerrada y token eliminado." }
     * @response 401 { "message": "Unauthenticated." }
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->sendResponse([], 'Sesión cerrada y token eliminado.');
    }

    /**
     * Obtener perfil del usuario autenticado.
     * * Devuelve los datos del usuario logueado. Dependiendo de su rol (Alumno, Maestro o Admin), incluye dinámicamente sus grupos y materias correspondientes.
     * <aside class="notice"><strong>Roles permitidos:</strong> Todos los usuarios autenticados.</aside>
     * * @authenticated
     * @response 200 {
     * "success": true,
     * "data": {
     * "id_usuario": 1,
     * "nombre": "Juan Pérez",
     * "email": "juan@example.com",
     * "rol": { "id_rol": 2, "nombre_rol": "Alumno" },
     * "alumno": {
     * "num_ctrl": "19030001",
     * "grupos": [ { "id_grupo": 1, "materia": { "nombre_materia": "Programación Web" } } ]
     * }
     * },
     * "message": "Datos del usuario logueado."
     * }
     * @response 401 { "message": "Unauthenticated." }
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('rol');
        $nombreRol = $user->rol->nombre_rol ?? '';

        if ($nombreRol === 'Alumno') {
            $user->load('alumno.grupos.materia');
        } elseif ($nombreRol === 'Maestro') {
            $user->load('maestro.grupos.materia');
        }

        return $this->sendResponse($user, 'Datos del usuario logueado.');
    }
}