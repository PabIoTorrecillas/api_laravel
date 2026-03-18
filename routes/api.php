<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\MaestroController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\ExposicionController;
use App\Http\Controllers\RubricaController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\MateriaController;

/*
|--------------------------------------------------------------------------
| API Routes — versión v1 (contrato OpenAPI 1.1.0)
|--------------------------------------------------------------------------
|
| El servidor base del contrato es: http://localhost:8080/api/v1
| En Laravel el prefijo /api ya está configurado en bootstrap/app.php,
| por lo que aquí solo necesitamos el prefijo 'v1'.
|
*/

Route::prefix('v1')->group(function () {

    /*
    |----------------------------------------------------------------------
    | RUTAS PÚBLICAS
    |----------------------------------------------------------------------
    */
    // POST /api/v1/auth/login  — contrato OpenAPI §paths./auth/login
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

    /*
    |----------------------------------------------------------------------
    | RUTAS PROTEGIDAS (Bearer token — Sanctum)
    |----------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // ── Autenticación ──────────────────────────────────────────────
        Route::get('/me',       [AuthController::class, 'me']);
        Route::post('/logout',  [AuthController::class, 'logout']);

        // Registro de usuarios: solo Admin
        Route::post('/register', [AuthController::class, 'register'])
             ->middleware('role:Admin')
             ->name('register');

        // ── Catálogos generales ────────────────────────────────────────
        // Contrato §4.1: rol específico para Profesores, Alumnos, Materias, Grupos
        // Lectura: todos los autenticados | Escritura: Maestro o Admin
        Route::apiResource('materias',    MateriaController::class);
        Route::apiResource('alumnos',     AlumnoController::class);
        Route::apiResource('maestros',    MaestroController::class);
        Route::apiResource('grupos',      GrupoController::class);

        // ── Recursos operativos ────────────────────────────────────────
        Route::apiResource('rubricas',    RubricaController::class);
        Route::apiResource('exposiciones',ExposicionController::class);
        Route::apiResource('equipos',     EquipoController::class);

        // ── Evaluaciones (contrato §paths./evaluaciones) ───────────────
        Route::apiResource('evaluaciones', EvaluacionController::class);

        // ── Rutas especiales ───────────────────────────────────────────
        Route::post('grupos/{id}/inscribir',       [GrupoController::class, 'inscribirAlumnos']);
        Route::put('equipos/{id}/integrantes',     [EquipoController::class, 'updateIntegrantes']);
        Route::get('/mis-calificaciones',          [AlumnoController::class, 'misCalificaciones']);
    });
});

/*
|--------------------------------------------------------------------------
| Compatibilidad: rutas sin prefijo v1 (para clientes legados)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->name('login.legacy');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('role:Admin');

    Route::apiResource('alumnos',      AlumnoController::class);
    Route::apiResource('maestros',     MaestroController::class);
    Route::apiResource('rubricas',     RubricaController::class);
    Route::apiResource('exposiciones', ExposicionController::class);
    Route::apiResource('evaluaciones', EvaluacionController::class);
    Route::apiResource('grupos',       GrupoController::class);
    Route::apiResource('equipos',      EquipoController::class);
    Route::apiResource('materias',     MateriaController::class);

    Route::post('grupos/{id}/inscribir',   [GrupoController::class, 'inscribirAlumnos']);
    Route::put('equipos/{id}/integrantes', [EquipoController::class, 'updateIntegrantes']);
    Route::get('/mis-calificaciones',      [AlumnoController::class, 'misCalificaciones']);
});
