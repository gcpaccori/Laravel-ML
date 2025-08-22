<?php

namespace App\DataTables;

use App\Models\Modulo;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class ModuloDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Modulo> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['active', 'order'])
            ->editColumn('active', function ( Modulo $s ) {
                if ($s->active) {
                    return '<div class="badge badge-success fw-bold">ACTIVO</div>';
                }else{
                    return '<div class="badge badge-danger fw-bold">INACTIVO</div>';
                }
            })
            ->editColumn('order', function ( Modulo $s ) {
                return '<div class="badge badge-info fw-bold">'.$s->order.'</div>';
            })
            ->editColumn('cod_father', function ( Modulo $s ) {
                return $s->parent?->name ?? '-';
            })
            ->editColumn('created_at', function (Modulo $s) {
                return $s->created_at->format('d/m/Y - h:i a');
            })
            ->editColumn('updated_at', function (Modulo $s) {
                return $s->updated_at->format('d/m/Y - h:i a');
            })
            ->addColumn('action', function (Modulo $s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->addColumn('sistema_name', fn(Modulo $s) => $s->sistema?->name ?? '-')
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Modulo>
     */
    public function query(Modulo $model): QueryBuilder
    {
        $query = $model->newQuery();
        $query->with('sistema');

        if ($f_sistema_id = request('f_sistema_id')) {
            $query->where('sistema_id', $f_sistema_id);
        }

        if ( !is_null($f_active = request('f_active')) ) {
            $query->where('active', $f_active);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('modulo-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(1)
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                        Button::make('reset'),
                        Button::make('reload')
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {

        return [
            Column::make('id')->title('Item'),
            Column::computed('sistema_name')->title('Sistema'),
            Column::make('name')->title('Módulo'),
            Column::make('cod_father')->title('Padre'),
            Column::make('url')->title('Ruta'),
            Column::make('icon')->title('Icono'),
            Column::make('order')->title('Orden'),
            Column::make('active')->title('Activo'),
            // Column::make('created_at')->title('Fecha Creación'),
            // Column::make('updated_at')->title('Fecha Actualización'),
            Column::computed('action')
            ->title('Acciones')
            ->addClass('text-center min-w-100px')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Modulo_' . date('YmdHis');
    }
}
