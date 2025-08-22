<?php

namespace App\DataTables;

use App\Models\Accion;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class AccionsDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Accion> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['active', 'location'])
            ->editColumn('active', function ( Accion $s ) {
                if ($s->active) {
                    return '<div class="badge badge-success fw-bold">ACTIVO</div>';
                }else{
                    return '<div class="badge badge-danger fw-bold">INACTIVO</div>';
                }
            })
            ->editColumn('location', function ( Accion $s ) {
                if ($s->location === 'T') {
                    return '<div class="badge badge-info fw-bold">TABLA</div>';
                }else{
                    return '<div class="badge badge-warning fw-bold">MARCO</div>';
                }
            })
            ->editColumn('created_at', function (Accion $s) {
                return $s->created_at->format('d/m/Y - h:i a');
            })
            ->editColumn('updated_at', function (Accion $s) {
                return $s->updated_at->format('d/m/Y - h:i a');
            })
            ->addColumn('action', function (Accion $s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Accion>
     */
    public function query(Accion $model): QueryBuilder
    {
        $query = $model->newQuery();

        if ($fecha_at = request('fecha_at')) {
            $query->whereDate('created_at', $fecha_at);
        }
        if ($f_updated = request('f_updated')) {
            $query->whereDate('updated_at', $f_updated);
        }
        if ($f_name_funcion = request('f_name_funcion')) {
            $query->whereLike('name_funcion', "%$f_name_funcion%");
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('accions-table')
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
            Column::make('id')->title('Item')->addClass('text-center'),
            Column::make('accion')->title('Acción'),
            Column::make('name')->title('Nombre'),
            Column::make('icon')->title('Icono'),
            Column::make('type')->title('Tipo'),
            Column::make('name_funcion')->title('Función'),
            Column::make('location')->title('Locación')->addClass('text-center'),
            Column::make('active')->title('Estado'),
            Column::make('created_at')->title('Fecha Creación'),
            Column::make('updated_at')->title('Fecha Actualización'),
            Column::computed('action')
            ->title('Acciones')
            ->addClass('text-center')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Accions_' . date('YmdHis');
    }
}
