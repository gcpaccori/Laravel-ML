<?php

namespace App\Http\Middleware;

use Closure;
use Inertia\Inertia;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckModuloPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $modulo)
    {
        $user = Auth::user();

        $moduloModel = Modulo::where('url', $modulo)->where('active', true)->first();

        if (!$moduloModel) {
            return Inertia::render('errors/Error403', [
                'title' => 'Error 404',
                'toolbar' => [
                    ['label' => 'Inicio', 'route' => 'dashboard']
                ],
                'menssage' => 'El módulo no existe o no está activo'
            ]);
            // abort(404, 'Módulo no encontrado');
        }

        // Verifica permisos en el módulo y sus hijos recursivamente
        if ($this->hasPermissionRecursive($moduloModel, $user)) {
            return $next($request);
        }

        // Verifica si el usuario tiene permiso directo al módulo por nombre (sin acciones)
        if ($user->can($moduloModel->url)) {
            return $next($request);
        }

        return Inertia::render('errors/Error403', [
            'title' => 'Error 403',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'menssage' => 'No tienes permisos para acceder a este módulo'
        ]);

        // abort(403, 'No tienes permisos para acceder a este módulo');
    }

    protected function hasPermissionRecursive(Modulo $modulo, $user) : bool
    {
        // Verifica si el módulo tiene acciones activas con location='T' y permiso
        $acciones = $modulo->acciones()
            ->where('active', true)
            ->get();

        foreach ($acciones as $accion) {
            $permiso = strtolower($modulo->url) . '.' . strtolower($accion->name);
            if ($user->can($permiso)) {
                return true;
            }
        }

        // Recursividad: verificar en los hijos
        foreach ($modulo->children as $child) {
            if ($this->hasPermissionRecursive($child, $user)) {
                return true;
            }
        }

        return false;
    }
}
