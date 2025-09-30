<?php

namespace App\DataTables;

use App\Models\Biometria;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class BiometriaDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Biometria> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('campania_etapa_id', fn($s) => "{$s->nombre_etapa} - ({$s->nombre_piscina})")
            ->editColumn('fecha_muestreo', fn($s) => $s->fecha_muestreo?->format('d/m/Y') ?? '-')
            ->addColumn('campania_especie_id', fn($s) => $s->nombre_especie)
            ->addColumn('campania_id', fn($s) => $s->nombre_campania)
            ->addColumn('piscigranja_id', fn($s) => $s->nombre_piscigranja)
            ->addColumn('action', fn($s) => DataTableHelper::getAccionesPermitidasDelModulo($s->id))
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Biometria>
     */
    public function query(Biometria $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'campaniaEtapa.etapa',
                'campaniaEtapa.piscina',
                'campaniaEtapa.campaniaEspecie.especie',
                'campaniaEtapa.campaniaEspecie.campania.piscigranja',
            ]);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('biometria-table')
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
            Column::computed('piscigranja_id')->title('Piscigranja')->addClass('min-w-200px'),
            Column::computed('campania_id')->title('Campaña')->addClass('min-w-200px'),
            Column::computed('campania_especie_id')->title('Especie')->addClass('min-w-125px'),
            Column::make('campania_etapa_id')->title('Etapa - Piscina')->addClass('min-w-175px'),
            Column::make('fecha_muestreo')->title('Fecha muestreo')->addClass('text-center min-w-100px'),
            Column::make('cantidad_peces_inicial')->title('N° Peces Iniciales')->addClass('text-center min-w-125px'),
            Column::make('cantidad_peces_final')->title('N° Peces Finales')->addClass('text-center min-w-125px'),
            Column::make('peso_inicial_gr')->title('Peso Inicial (g)')->addClass('text-center min-w-125px'),
            Column::make('peso_final_gr')->title('Peso Final (g)')->addClass('text-center min-w-125px'),
            Column::make('tamanio_inicial_cm')->title('Tamaño Inicial (cm)')->addClass('text-center min-w-125px'),
            Column::make('tamanio_final_cm')->title('Tamaño Final (cm)')->addClass('text-center min-w-125px'),
            Column::make('biomasa_inicial_kg')->title('Biomasa Inicial (Kg)')->addClass('text-center min-w-125px'),
            Column::make('biomasa_final_kg')->title('Biomasa Final (kg)')->addClass('text-center min-w-125px'),
            Column::make('tasa_supervivencia_porcentaje')->title('Tasa de supervivencia (%)')->addClass('text-center min-w-175px'),
            Column::make('tasa_crecimiento_especifico_porcentaje')->title('Tasa específica de crecimiento (%)')->addClass('text-center min-w-175px'),
            // Column::make('observaciones')->title('observaciones'),
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
        return 'Biometria_' . date('YmdHis');
    }
}
