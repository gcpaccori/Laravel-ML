<?php

namespace App\DataTables;

use App\Models\Piscina;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class PiscinaDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Piscina> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['estado'])
            ->addColumn('action', function ($s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->addColumn('parametros_aguas', function ($s) {
                // return $s->parametrosAguas->count();
                $ultimo = $s->parametrosAguas()->latest('fecha_medicion')->first();
                return $ultimo ? $ultimo->fecha_medicion->format('d/m/Y H:i:s') : '-';
            })
            ->editColumn('piscigranja_id', function ($s) {
                return $s->piscigranja?->nombre ?? '-';
            })
            ->editColumn('estado', function ($s) {
                $estado = $s->estado;
                $badge = '<div class="badge badge-dark fw-bold">Error</div>';
                if ($estado  === 'operativa') {
                    $badge = '<div class="badge badge-success fw-bold">Operativa</div>';
                }
                if ($estado  === 'mantenimiento') {
                    $badge = '<div class="badge badge-warning fw-bold">Mantenimiento</div>';
                }
                if ($estado  === 'inactiva') {
                    $badge = '<div class="badge badge-danger fw-bold">Inactiva</div>';
                }
                return $badge;
            })
            ->setRowId('id');
    }
    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Piscina>
     */
    public function query(Piscina $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('piscina-table')
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
            Column::make('piscigranja_id')->title('Piscigranja'),
            Column::make('nombre')->title('Piscina'),
            Column::make('descripcion')->title('Descripción'),
            Column::make('superficie_m2')->title('Superficie (m2)')->addClass('text-center'),
            Column::make('profundidad_m')->title('Profundidad (m)')->addClass('text-center'),
            Column::computed('parametros_aguas')->title('U. Parámetro')->addClass('text-center'),
            Column::make('estado')->title('Estado'),
            Column::computed('action')->title('Acciones')->addClass('text-center min-w-100px')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Piscina_' . date('YmdHis');
    }
}
