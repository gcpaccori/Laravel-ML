<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\SessionLogDataTable;

class SessionLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

    }

    public function datatable(SessionLogDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function columns(SessionLogDataTable $dataTable)
    {
        $columns = DataTableHelper::getColumnsFromDatatable($dataTable, ['action', 'user_id']);
        return $columns;
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
