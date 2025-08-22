<?php

namespace App\DataTables;

use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class RoleDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Role> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['active'])
            ->editColumn('active', function ( Role $s ) {
                if ($s->active) {
                    return '<div class="badge badge-success fw-bold">ACTIVO</div>';
                }else{
                    return '<div class="badge badge-danger fw-bold">INACTIVO</div>';
                }
            })
            ->addColumn('action', function (Role $s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->editColumn('created_at', function (Role $s) {
                return $s->created_at->format('d/m/Y - h:i a');
            })
            ->editColumn('updated_at', function (Role $s) {
                return $s->updated_at->format('d/m/Y - h:i a');
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Role>
     */
    public function query(Role $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('role-table')
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
            Column::make('name')->title('Nombre'),
            Column::make('guard_name')->title('Guard Name'),
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
        return 'Role_' . date('YmdHis');
    }
}
