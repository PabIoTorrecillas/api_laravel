<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Usar $request->path() devuelve el path sin el dominio y sin doble prefijo.
        // Lo normalizamos con barra inicial para seguir el formato del contrato.
        $path = '/' . $request->path();

        if (!$request->user()) {
            return response()->json([
                'timestamp' => now()->toIso8601String(),
                'status'    => 401,
                'error'     => 'Unauthorized',
                'message'   => 'Token JWT inválido o expirado',
                'path'      => $path,
            ], 401);
        }

        $userRole = $request->user()->rol->nombre_rol ?? null;

        // Admin siempre puede todo
        if ($userRole === 'Admin') {
            return $next($request);
        }

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'timestamp' => now()->toIso8601String(),
                'status'    => 403,
                'error'     => 'Forbidden',
                'message'   => 'Access denied. You do not have the correct role.',
                'path'      => $path,
            ], 403);
        }

        return $next($request);
    }
}
