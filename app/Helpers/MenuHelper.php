<?php

namespace App\Helpers;

use App\Models\Sistema;
use Illuminate\Support\Facades\Auth;

class MenuHelper
{
    public static function getMenuPorUsuario()
    {
        $sistemas = Sistema::with([
            'modulos' => function ($q) {
                $q->whereNull('cod_father')
                ->where('active', true)
                ->orderBy('order')
                ->with([
                    'acciones',
                    'children' => function ($q) {
                        $q->orderBy('order')
                            ->with([
                                'acciones',
                                'children' => function ($q) {
                                    $q->orderBy('order')->with('acciones');
                                }
                            ]);
                    }
                ]);
            }
        ])->orderBy('order')->get();

        $menu = [];

        foreach ($sistemas as $sistema) {
            $modulosFiltrados = [];

            foreach ($sistema->modulos as $modulo) {
                $moduloItem = self::procesarModulo($modulo);

                if ($moduloItem) {
                    $modulosFiltrados[] = $moduloItem;
                }
            }

            if (count($modulosFiltrados)) {
                $menu[] = [
                    'id' => $sistema->id,
                    'label' => $sistema->name,
                    'icon' => $sistema->icon ?? 'Monitor',
                    'url' => $sistema->url,
                    'children' => $modulosFiltrados
                ];
            }
        }

        return $menu;
    }

    protected static function procesarModulo($modulo)
    {
        $usuario = Auth::user();

        // Permiso explícito sobre el módulo (por ejemplo: 'usuario', 'roles')
        $permisoDirecto = strtolower($modulo->url);

        // Permiso sobre alguna acción (por ejemplo: 'usuario.index', 'usuario.create')
        $tienePermisoAccion = $modulo->acciones->contains(function ($accion) use ($modulo, $usuario) {
            $permiso = strtolower($modulo->url) . '.' . strtolower($accion->name);
            return $usuario?->can($permiso);
        });

        // Verifica si tiene el permiso directo (sin acciones)
        $tienePermisoDirecto = $usuario?->can($permisoDirecto);

        $subModulos = [];

        foreach ($modulo->children as $child) {
            $childItem = self::procesarModulo($child);
            if ($childItem) {
                $subModulos[] = $childItem;
            }
        }

        if (!$tienePermisoAccion && !$tienePermisoDirecto && empty($subModulos)) {
            return null;
        }

        return [
            'id' => $modulo->id,
            'label' => $modulo->name,
            'icon' => $modulo->icon ?? 'Menu',
            'url' => $modulo->url,
            'children' => $subModulos ?: null,
        ];
    }
}
