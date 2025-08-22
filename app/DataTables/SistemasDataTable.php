<?php

namespace App\DataTables;

use App\Models\Sistema;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class SistemasDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Sistema> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['order'])
            ->editColumn('created_at', function (Sistema $s) {
                return $s->created_at->format('d/m/Y - h:i a');
            })
            ->editColumn('updated_at', function (Sistema $s) {
                return $s->updated_at->format('d/m/Y - h:i a');
            })
            ->addColumn('action', function (Sistema $s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->editColumn('order', function ( Sistema $s ) {
                return '<div class="badge badge-success fw-bold">'.$s->order.'</div>';
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Sistema>
     */
    public function query(Sistema $model): QueryBuilder
    {
        $query = $model->newQuery();

        if ($fecha_at = request('fecha_at')) {
            $query->whereDate('created_at', $fecha_at);
        }
        if ($f_updated = request('f_updated')) {
            $query->whereDate('updated_at', $f_updated);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('sistemas-table')
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
            Column::make('name')->title('Descripción'),
            Column::make('icon')->title('Icono'),
            Column::make('url')->title('Ruta'),
            Column::make('order')->title('Orden')->addClass('text-center'),
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
        return 'Sistemas_' . date('YmdHis');
    }
}
