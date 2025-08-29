<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\ParametroAguaDataTable;

class HistorialAguaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new ParametroAguaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/HistorialAgua', [
            'title' => 'Historial de Parámetros del Agua',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Historial de Parámetros del Agua']
            ],
            'columns' => $columns,
        ]);
    }

    public function datatable(ParametroAguaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
