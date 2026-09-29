<?php

namespace App\DataTables;

use App\Helpers\DataTableHelper;
use App\Models\CampaniaEspecie;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CampaniaEspecieDataTable extends DataTable
{
    /**
     * Construye el DataTable.
     */
    public function dataTable(EloquentBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('piscigranja', function ($campaniaEspecie) {
                return $campaniaEspecie->campania?->piscigranja?->nombre ?? '-';
            })
            ->addColumn('campania', function ($campaniaEspecie) {
                return $campaniaEspecie->campania?->nombre ?? '-';
            })
            ->addColumn('especie', function ($campaniaEspecie) {
                return $campaniaEspecie->especie?->nombre ?? '-';
            })
            ->addColumn('alimentacion', function ($campaniaEspecie) {
                $tabla = $campaniaEspecie->alimentacionTabla;

                if (!$tabla) {
                    return '<span class="badge badge-light-danger">
                                No generada
                            </span>';
                }

                if (!$tabla->calculado) {
                    return '<span class="badge badge-light-warning">
                                Pendiente
                            </span>';
                }

                return '<span class="badge badge-light-success">
                            Generada
                        </span>';
            })
            ->addColumn('resumen_alimentacion', function ($campaniaEspecie) {
                $tabla = $campaniaEspecie->alimentacionTabla;

                if (!$tabla) {
                    return '-';
                }

                return sprintf(
                    '<div>
                        <div class="fw-bold">%s</div>
                        <div class="text-muted fs-7">
                            Población: %s | Semanas: %s
                        </div>
                    </div>',
                    e($tabla->titulo ?? 'Tabla BFT'),
                    number_format($tabla->poblacion_inicial),
                    $tabla->numero_semanas
                );
            })
            ->addColumn('action', function ($campaniaEspecie) {
                $id = $campaniaEspecie->id;
                $tabla = $campaniaEspecie->alimentacionTabla;

                if (!$tabla) {
                    $disabled = ['edit', 'show'];
                }else{
                    $disabled = ['generar'];
                }

                return DataTableHelper::getAccionesPermitidasDelModulo(
                    $id,
                    null,
                    $disabled ?? []
                );
            })
            ->rawColumns([
                'alimentacion',
                'resumen_alimentacion',
            ])
            ->setRowId('id');
    }

    /**
     * Consulta principal.
     */
    public function query(CampaniaEspecie $model): EloquentBuilder
    {
        $request = request();

        return $model->newQuery()
            ->with([
                'campania:id,piscigranja_id,nombre',
                'campania.piscigranja:id,nombre',
                'especie:id,nombre',
                'alimentacionTabla:id,campania_especie_id,titulo,poblacion_inicial,numero_semanas,calculado',
            ])
            ->when(
                $request->filled('piscigranja_id'),
                function (EloquentBuilder $query) use ($request) {
                    $query->whereHas('campania', function ($q) use ($request) {
                        $q->where('piscigranja_id', $request->piscigranja_id);
                    });
                }
            )
            ->when(
                $request->filled('campania_id'),
                function (EloquentBuilder $query) use ($request) {
                    $query->where('campania_id', $request->campania_id);
                }
            )
            ->when(
                $request->filled('especie_id'),
                function (EloquentBuilder $query) use ($request) {
                    $query->where('especie_id', $request->especie_id);
                }
            )
            ->when(
                $request->filled('estado_alimentacion'),
                function (EloquentBuilder $query) use ($request) {

                    switch ($request->estado_alimentacion) {

                        case 'sin_tabla':
                            $query->whereDoesntHave('alimentacionTabla');
                            break;

                        case 'pendiente':
                            $query->whereHas('alimentacionTabla', function ($q) {
                                $q->where('calculado', false);
                            });
                            break;

                        case 'generada':
                            $query->whereHas('alimentacionTabla', function ($q) {
                                $q->where('calculado', true);
                            });
                            break;
                    }
                }
            )
            ->when(
                $request->filled('filtro'),
                function (EloquentBuilder $query) use ($request) {
                    $filtro = $request->filtro;
                    $query->where(function ($q) use ($filtro) {
                        $q->whereHas('campania.piscigranja', function ($q) use ($filtro) {
                            $q->where('nombre', 'like', "%{$filtro}%");
                        })
                        ->orWhereHas('campania', function ($q) use ($filtro) {
                            $q->where('nombre', 'like', "%{$filtro}%");
                        })
                        ->orWhereHas('especie', function ($q) use ($filtro) {
                            $q->where('nombre', 'like', "%{$filtro}%");
                        })
                        ->orWhereHas('alimentacionTabla', function ($q) use ($filtro) {
                            $q->where('titulo', 'like', "%{$filtro}%");
                        });
                    });
                }
            );
    }

    /**
     * Configuración HTML.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('campania-especie-table');
    }

    /**
     * Columnas.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')
                ->title('#')
                ->width(60),
            Column::computed('piscigranja')
                ->title('Piscigranja'),
            Column::computed('campania')
                ->title('Campaña'),
            Column::computed('especie')
                ->title('Especie')
                ->searchable(true)
                ->orderable(false),
            Column::computed('resumen_alimentacion')
                ->title('Tabla BFT')
                ->searchable(false)
                ->orderable(false),
            Column::computed('alimentacion')
                ->title('Estado')
                ->searchable(false)
                ->orderable(false)
                ->addClass('text-center'),
            Column::computed('action')
                ->title('Acciones')
                ->width(180)
                ->addClass('text-center'),
        ];
    }

    /**
     * Nombre del archivo exportado.
     */
    protected function filename(): string
    {
        return 'CampaniaEspecie_' . date('YmdHis');
    }
}
