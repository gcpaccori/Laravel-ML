<?php

namespace App\Helpers;

use App\Models\Modulo;
use Illuminate\Support\Facades\Auth;

class DataTableHelper
{
    public static function getColumnsFromDatatable($datatable, $exclude = [])
    {
        $columns = collect($datatable->getColumns())
        ->reject(fn($col) => in_array($col->data, $exclude))
        ->map(function ($col) {
            return [
                'data' => $col->data,
                'title' => $col->title ?? ucfirst($col->data),
                'orderable' => $col->orderable ?? true,
                'searchable' => $col->searchable ?? true,
                'className' => $col->className ?? '',
                'width' => $col->width ?? null,
            ];
        })
        ->values()
        ->toArray();

        return response()->json($columns);
    }

    public static function getAccionesPermitidasDelModulo($resourceId = null, string $controllerName = null)
    {
        // Inferir el nombre del controlador si no se proporciona
        if (!$controllerName) {
            $controllerAction = class_basename(request()->route()->getActionName());
            $controllerName = explode('@', $controllerAction)[0] ?? null;
        }

        // $moduloUrl = strtolower(preg_replace('/([a-z])([A-Z])/', '$1$2', str_replace('Controller', '', $controllerName)));
        $moduloUrl = strtolower(str_replace('Controller', '', $controllerName));

        $modulo = Modulo::where('url', $moduloUrl)->first();

        if (!$modulo) return [];

        $acciones = $modulo->acciones()
            ->where('location', 'T')
            ->where('active', true)
            ->get()
            ->filter(function ($accion) use ($modulo) {
                $permiso = strtolower($modulo->url) . '.' . strtolower($accion->name);
                return Auth::user()?->can($permiso);
            });

        // Mapeamos directamente al formato requerido en la columna `action`
        return $acciones->map(function ($accion) use ($resourceId) {
            return [
                'type' => $accion->type,
                'icon' => $accion->icon,
                'action' => $accion->accion,
                'name_funcion' => $accion->name_funcion,
                'id' => $resourceId,
            ];
        })->toArray();
    }

    public static function getAccionesPermitidasEnMarco(string $controllerName = null)
    {
        if (!$controllerName) {
            $controllerAction = class_basename(request()->route()->getActionName());
            $controllerName = explode('@', $controllerAction)[0] ?? null;
        }

        // $moduloUrl = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', str_replace('Controller', '', $controllerName)));
        $moduloUrl = strtolower(str_replace('Controller', '', $controllerName));

        $modulo = Modulo::where('url', $moduloUrl)->first();

        if (!$modulo) return [];

        return $modulo->acciones()
            ->where('location', 'M')
            ->where('active', true)
            ->get()
            ->filter(function ($accion) use ($modulo) {
                $permiso = strtolower($modulo->url) . '.' . strtolower($accion->name);
                return Auth::user()?->can($permiso);
            })
            ->map(function ($accion) {
                return [
                    'type' => $accion->type,
                    'name' => $accion->name,
                    'icon' => $accion->icon,
                    'action' => $accion->accion,
                    'name_funcion' => $accion->name_funcion
                ];
            })
            ->values()
            ->toArray();
    }

}
