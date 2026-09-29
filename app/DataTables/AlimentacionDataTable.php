<?php

namespace App\DataTables;

use App\Models\AlimentacionTabla;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AlimentacionDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<AlimentacionTabla> $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))

            ->editColumn('created_at', function ($tabla) {
                return $tabla->created_at?->format('d/m/Y H:i');
            })

            ->addColumn('campania', function ($tabla) {

                $campania = $tabla->campaniaEspecie?->campania;

                if (!$campania) {
                    return '—';
                }

                return '<span class="fw-semibold text-gray-800">'
                    . e($campania->nombre)
                    . '</span>';
            })

            ->addColumn('especie', function ($tabla) {

                $especie = $tabla->campaniaEspecie?->especie;

                if (!$especie) {
                    return '—';
                }

                return '<span class="fw-semibold text-gray-800">'
                    . e($especie->nombre)
                    . '</span>';
            })

            ->addColumn('titulo_tabla', function ($tabla) {

                if (!$tabla->titulo) {
                    return '<span class="text-muted">Sin título</span>';
                }

                return '<div class="fw-bold text-gray-800">'
                    . e($tabla->titulo)
                    . '</div>';
            })

            ->addColumn('poblacion', function ($tabla) {

                return number_format(
                    $tabla->poblacion_inicial,
                    0,
                    ',',
                    '.'
                );
            })

            ->addColumn('mortalidad', function ($tabla) {

                return number_format(
                    $tabla->mortalidad_porcentaje,
                    2,
                    ',',
                    '.'
                ) . ' %';
            })

            ->addColumn('duracion', function ($tabla) {

                return '<span class="badge badge-light-primary">'
                    . $tabla->numero_semanas
                    . ' semanas'
                    . '</span>';
            })

            ->addColumn('estado', function ($tabla) {

                if ($tabla->calculado) {

                    return '<span class="badge badge-light-success">
                                Calculado
                            </span>';
                }

                return '<span class="badge badge-light-warning">
                            Pendiente
                        </span>';
            })

            ->addColumn('responsable', function ($tabla) {

                return $tabla->responsable
                    ? e($tabla->responsable)
                    : '<span class="text-muted">—</span>';
            })

            ->addColumn('action', function ($tabla) {

                return [
                    [
                        'action' => 'Ver',
                        'type' => 'primary',
                        'icon' => 'View',
                        'id' => $tabla->id,
                        'name_funcion' => 'handleShow',
                        'disabled' => false,
                    ],
                    [
                        'action' => 'Editar',
                        'type' => 'warning',
                        'icon' => 'Edit',
                        'id' => $tabla->id,
                        'name_funcion' => 'handleEdit',
                        'disabled' => false,
                    ],
                    [
                        'action' => 'Eliminar',
                        'type' => 'danger',
                        'icon' => 'Delete',
                        'id' => $tabla->id,
                        'name_funcion' => 'handleDelete',
                        'disabled' => false,
                    ],
                ];
            })

            ->rawColumns([
                'campania',
                'especie',
                'titulo_tabla',
                'duracion',
                'estado',
                'responsable',
                'action',
            ])

            ->setRowId('id');
    }

    /**
     * Get the dataTable query source.
     *
     * @return QueryBuilder<AlimentacionTabla>
     */
    public function query(AlimentacionTabla $model): QueryBuilder
    {
        $request = request();

        return $model->newQuery()
            ->with([
                'campaniaEspecie.campania:id,nombre',
                'campaniaEspecie.especie:id,nombre',
            ])

            ->when(
                $request->filled('campania_especie_id'),
                fn (QueryBuilder $query) =>
                    $query->where(
                        'campania_especie_id',
                        $request->input('campania_especie_id')
                    )
            )

            ->when(
                $request->filled('calculado'),
                fn (QueryBuilder $query) =>
                    $query->where(
                        'calculado',
                        $request->input('calculado')
                    )
            )

            ->when(
                $request->filled('filtro'),
                function (QueryBuilder $query) use ($request) {

                    $filtro = $request->input('filtro');

                    $query->where(function ($q) use ($filtro) {

                        $q->where('titulo', 'like', "%{$filtro}%")
                            ->orWhere('responsable', 'like', "%{$filtro}%")

                            ->orWhereHas(
                                'campaniaEspecie.campania',
                                fn ($q) =>
                                    $q->where(
                                        'nombre',
                                        'like',
                                        "%{$filtro}%"
                                    )
                            )

                            ->orWhereHas(
                                'campaniaEspecie.especie',
                                fn ($q) =>
                                    $q->where(
                                        'nombre',
                                        'like',
                                        "%{$filtro}%"
                                    )
                            );
                    });
                }
            );
    }

    /**
     * Optional method if you want to use the HTML builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('alimentacion-table');
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [

            Column::make('id')
                ->title('#')
                ->addClass('text-center'),

            Column::computed('campania')
                ->title('Campaña')
                ->addClass('min-w-175px')
                ->orderable(false),

            Column::computed('especie')
                ->title('Especie')
                ->addClass('min-w-150px')
                ->orderable(false),

            Column::computed('titulo_tabla')
                ->title('Tabla')
                ->addClass('min-w-200px')
                ->orderable(false),

            Column::make('poblacion_inicial')
                ->title('Población')
                ->addClass('text-center min-w-125px'),

            Column::computed('mortalidad')
                ->title('Mortalidad')
                ->addClass('text-center min-w-110px')
                ->orderable(false),

            Column::computed('duracion')
                ->title('Duración')
                ->addClass('text-center min-w-125px')
                ->orderable(false),

            Column::computed('estado')
                ->title('Estado')
                ->addClass('text-center min-w-125px')
                ->orderable(false),

            Column::computed('responsable')
                ->title('Responsable')
                ->addClass('min-w-200px')
                ->orderable(false),

            Column::make('created_at')
                ->title('Fecha')
                ->addClass('min-w-125px'),

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
        return 'Alimentacion_' . date('YmdHis');
    }
}
