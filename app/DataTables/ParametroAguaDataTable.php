<?php

namespace App\DataTables;

use App\Models\ParametroAgua;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class ParametroAguaDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<ParametroAgua> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('piscina', function ($s) {
                return $s->piscina?->nombre ?? '-';
            })
            ->addColumn('piscigranja', function ($s) {
                return $s->piscina?->piscigranja?->nombre ?? '-';
            })
            ->editColumn('temperatura', function ($s) {
                return "{$s->temperatura} °C";
            })
            ->editColumn('ph', function ($s) {
                return "{$s->ph} pH";
            })
            ->editColumn('oxigeno_disuelto', function ($s) {
                return "{$s->oxigeno_disuelto} mg/L";
            })
            ->editColumn('ion_nitrato', function ($s) {
                return "{$s->ion_nitrato} mg/L";
            })
            ->editColumn('fecha_medicion', function ($s) {
                return $s->fecha_medicion ? $s->fecha_medicion->format('d/m/Y - H:i:s') : '-';
            })
            ->editColumn('created_at', function ($s) {
                return $s->created_at ? $s->created_at->format('d/m/Y - H:i:s') : '-';
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<ParametroAgua>
     */
    public function query(ParametroAgua $model): QueryBuilder
    {
        $query = $model->newQuery()
            ->with(['piscina.piscigranja'])
            ->orderBy('fecha_medicion', 'desc');

        // Filtros que vienen del request
        $piscigranjaId = request('piscigranja_id');
        $piscinaId = request('piscina_id');
        $tipoTiempo = request('tipo_tiempo', 'D');

        if ($piscigranjaId && $piscigranjaId !== 'T') {
            $query->whereHas('piscina', function ($q) use ($piscigranjaId) {
                $q->where('piscigranja_id', $piscigranjaId);
            });
        }

        if ($piscinaId && $piscinaId !== 'T') {
            $query->where('piscina_id', $piscinaId);
        }

        // Filtros de tiempo
        if ($tipoTiempo === 'D' && request()->filled('fecha')) {
            $query->whereDate('fecha_medicion', request('fecha'));
        } elseif ($tipoTiempo === 'M' && request()->filled('mes')) {
            $mes = request('mes');
            $query->whereYear('fecha_medicion', substr($mes, 0, 4))
                ->whereMonth('fecha_medicion', substr($mes, 5, 2));
        } elseif ($tipoTiempo === 'Y' && request()->filled('anio')) {
            $query->whereYear('fecha_medicion', request('anio'));
        }

        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('parametroagua-table')
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
            Column::make('id')->title('Código')->addClass('text-center'),
            Column::make('created_at')->title('Fecha Creación')->addClass('text-center min-w-175px'),
            Column::make('fecha_medicion')->title('Fecha Medición')->addClass('text-center min-w-175px'),
            Column::computed('piscigranja')->title('Piscigranja')->addClass('min-w-200px'),
            Column::computed('piscina')->title('Piscina')->addClass('min-w-150px'),
            Column::make('temperatura')->title('Temperatura')->addClass('text-center'),
            Column::make('ph')->title('Grado de Acidez')->addClass('text-center min-w-175px'),
            Column::make('oxigeno_disuelto')->title('Oxigeno Disuelto')->addClass('text-center min-w-175px'),
            Column::make('ion_nitrato')->title('Ion de Nitrato')->addClass('text-center min-w-175px'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'ParametroAgua_' . date('YmdHis');
    }
}
