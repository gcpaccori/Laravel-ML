<?php

namespace App\DataTables;

use App\Models\Alarma;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class AlarmaDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Alarma> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('created_at', fn ($s) => $s->created_at?->format('d/m/Y H:i'))
            ->addColumn('alerta', function ($alerta) {
                $html = '<div class="fw-bold text-gray-800 mb-1">' . e($alerta->titulo) . '</div>';
                if ($alerta->mensaje) {
                    $html .= '<div class="text-muted fs-7">' . e($alerta->mensaje) . '</div>';
                }
                return $html;
            })
            ->addColumn('modulo', function ($alerta) {
                $nombreModulo = str_replace('_', ' ', $alerta->modulo);
                return '<span class="text-capitalize">' . e($nombreModulo) . '</span>';
            })
            ->addColumn('ubicacion', function ($alerta) {
                $piscigranja = $alerta->piscigranja?->nombre ?? '';
                $piscina = $alerta->piscina?->nombre ?? null;

                $html = '<div class="fw-semibold">' . e($piscigranja) . '</div>';
                if ($piscina) {
                    $html .= '<div class="text-muted fs-7">' . e($piscina) . '</div>';
                }
                return $html;
            })
            ->addColumn('parametro', function ($alerta) {
                if (!$alerta->parametro) {
                    return '—';
                }

                $texto = e($alerta->parametro);
                if ($alerta->valor_detectado !== null) {
                    $texto .= ' <span class="text-muted">· ' . e($alerta->valor_detectado) . '</span>';
                }
                return $texto;
            })
            ->addColumn('nivel', function ($alerta) {
                $nivelInfo = Alarma::getNivel($alerta->nivel);
                return '<span class="badge badge-light-' . $nivelInfo['type'] . '">' . $nivelInfo['label'] . '</span>';
            })
            ->addColumn('estado', function ($alerta) {
                $estadoInfo = Alarma::getEstado($alerta->estado);
                return '<span class="badge badge-light-' . $estadoInfo['type'] . ' text-capitalize">' . $estadoInfo['label'] . '</span>';
            })
            ->addColumn('action', function ($alerta) {
                $botones = [
                    [
                        'action' => 'Ver',
                        'type' => 'primary',
                        'icon' => 'View',
                        'id' => $alerta->id,
                        'name_funcion' => 'handleShow',
                        'disabled' => false
                    ],
                    [
                        'action' => 'Resuelto',
                        'type' => 'success',
                        'icon' => 'Check',
                        'id' => $alerta->id,
                        'name_funcion' => 'handleResolver',
                        'disabled' => false,
                        'disabled' => ($alerta->estado === 'resuelta')
                    ]
                ];
                return $botones;
            })
            ->rawColumns(['alerta', 'modulo', 'ubicacion', 'parametro', 'nivel', 'estado', 'action'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Alarma>
     */
    public function query(Alarma $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'piscigranja',
                'piscina',
            ]);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('alerta-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(5, 'desc') // Ordenar por fecha o ID por defecto
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
            Column::make('created_at')
                ->title('Fecha')
                ->addClass('min-w-100px'),

            Column::make('alerta')
                ->title('Alerta')
                ->addClass('min-w-300px')
                ->orderable(false),

            Column::computed('modulo')
                ->title('Módulo')
                ->addClass('min-w-150px'),

            Column::computed('ubicacion')
                ->title('Ubicación')
                ->addClass('min-w-175px')
                ->orderable(false),

            Column::computed('parametro')
                ->title('Parámetro')
                ->addClass('text-center min-w-150px')
                ->orderable(false),

            Column::computed('nivel')
                ->title('Nivel')
                ->addClass('text-center min-w-100px'),

            Column::computed('estado')
                ->title('Estado')
                ->addClass('text-center min-w-100px'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center min-w-150px'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Alarma_' . date('YmdHis');
    }
}
