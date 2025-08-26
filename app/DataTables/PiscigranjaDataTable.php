<?php

namespace App\DataTables;

use App\Models\Piscigranja;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class PiscigranjaDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Piscigranja> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['activo'])
            ->addColumn('action', function ($s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->addColumn('ubigeo', function ($s) {
                $depa = $s->departamento?->name ?? '-';
                $prov = $s->provincia?->name ?? '-';
                $dist = $s->distrito?->name ?? '-';

                return "{$dist}, {$prov}, {$depa}";
            })
            ->editColumn('activo', function ( $s ) {
                if ($s->activo) {
                    return '<div class="badge badge-success fw-bold">SI</div>';
                }else{
                    return '<div class="badge badge-danger fw-bold">NO</div>';
                }
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Piscigranja>
     */
    public function query(Piscigranja $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('piscigranja-table')
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
            Column::make('nombre')->title('Nombre'),
            Column::make('descripcion')->title('Descripción'),
            Column::computed('ubigeo')->title('Ubigeo (Di/Pr/De)'),
            Column::make('direccion')->title('Direccion'),
            Column::make('latitud')->title('Latitud'),
            Column::make('longitud')->title('Longitud'),
            // Column::make('propietario'),
            // Column::make('telefono_contacto'),
            // Column::make('email_contacto'),
            Column::make('activo')->title('Activo')->addClass('text-center'),
            Column::computed('action')->title('Acciones')->addClass('text-center')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Piscigranja_' . date('YmdHis');
    }
}
