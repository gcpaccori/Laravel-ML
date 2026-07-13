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
            ->editColumn('campania_etapa_id', fn ($s) => "{$s->nombre_etapa} - ({$s->nombre_piscina})")
            ->editColumn('fecha_inicial', fn ($s) => $s->fecha_inicial?->format('d/m/Y'))
            ->editColumn('fecha_muestreo', fn ($s) => $s->fecha_muestreo?->format('d/m/Y'))
            ->addColumn('campania_especie_id', fn ($s) => $s->nombre_especie)
            ->addColumn('campania_id', fn ($s) => $s->nombre_campania)
            ->addColumn('piscigranja_id', fn ($s) => $s->nombre_piscigranja)
            ->addColumn('action', function ($biometria) {

                $disabled = [];

                if (!$biometria->ultimo_registro) {
                    $disabled = ['edit', 'delete'];
                }

                return DataTableHelper::getAccionesPermitidasDelModulo(
                    $biometria->id,
                    null,
                    $disabled
                );

            })
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

            Column::make('id')
                ->title('Item')
                ->addClass('text-center'),

            Column::make('fecha_muestreo')
                ->title('Fecha Muestreo')
                ->addClass('text-center'),

            Column::make('fecha_inicial')
                ->title('Fecha Inicial')
                ->addClass('text-center'),

            Column::computed('piscigranja_id')
                ->title('Piscigranja')
                ->addClass('min-w-175px'),

            Column::computed('campania_id')
                ->title('Campaña')
                ->addClass('min-w-175px'),

            Column::computed('campania_especie_id')
                ->title('Especie')
                ->addClass('min-w-150px'),

            Column::make('campania_etapa_id')
                ->title('Etapa - Piscina')
                ->addClass('min-w-200px'),

            Column::make('tiempo_dias')
                ->title('Días')
                ->addClass('text-center'),

            Column::make('cantidad_muestreo')
                ->title('Muestras')
                ->addClass('text-center'),

            // Column::make('muestreo_porcentaje')
            //     ->title('% Muestreo')
            //     ->addClass('text-center'),

            // Column::make('cantidad_peces_iniciales')
            //     ->title('Peces Iniciales')
            //     ->addClass('text-center'),

            // Column::make('cantidad_peces_actuales')
            //     ->title('Peces Actuales')
            //     ->addClass('text-center'),

            // Column::make('bi_kg')
            //     ->title('BI (Kg)')
            //     ->addClass('text-center'),

            // Column::make('bf_kg')
            //     ->title('BF (Kg)')
            //     ->addClass('text-center'),

            // Column::make('prom_longitud_cm')
            //     ->title('Longitud Prom. (cm)')
            //     ->addClass('text-center'),

            // Column::make('prom_peso_g')
            //     ->title('Peso Prom. (g)')
            //     ->addClass('text-center'),

            // Column::make('tasa_crecimiento_g_dia')
            //     ->title('Crecimiento (g/día)')
            //     ->addClass('text-center'),

            // Column::make('total_alimento_consumido_kg')
            //     ->title('Alimento (Kg)')
            //     ->addClass('text-center'),

            // Column::make('conversion_alimenticia')
            //     ->title('Conversión')
            //     ->addClass('text-center'),

            // Column::make('tasa_supervivencia_porcentaje')
            //     ->title('Supervivencia (%)')
            //     ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center min-w-125px'),

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
