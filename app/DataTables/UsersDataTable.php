<?php

namespace App\DataTables;

use App\Models\User;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class UsersDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<User> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['action', 'last_login_at'])
            ->addColumn('action', function (User $s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->addColumn('two_factor_confirmed_at', function (User $s) {
                return $s->two_factor_confirmed_at ? $s->two_factor_confirmed_at->format('d/m/Y - H:i') : '-';
            })
            ->addColumn('role', function (User $s) {
                return ucwords($s->roles->first()?->name ?? '-');
            })
            // ->filterColumn('role', function ($query, $keyword) {
            //     $query->whereHas('roles', function ($q) use ($keyword) {
            //         $q->whereRaw('LOWER(name) LIKE ?', ["%".strtolower($keyword)."%"]);
            //     });
            // })
            ->editColumn('last_login_at', function (User $user) {
                if ($user->isOnline()) {
                    return '<div class="badge bg-success text-white fw-bold">En línea</div>';
                }

                return sprintf(
                    '<div class="badge badge-light fw-bold">%s</div>',
                    $user->last_login_at ? $user->last_login_at->diffForHumans() : '-'
                );
            })
            ->editColumn('created_at', function (User $s) {
                return $s->created_at->format('d/m/Y - H:i');
            })
            ->editColumn('updated_at', function (User $s) {
                return $s->updated_at->format('d/m/Y - H:i');
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<User>
     */
    public function query(User $model): QueryBuilder
    {
        $query = $model->newQuery();

        if ($fecha_at = request('fecha_at')) {
            $query->whereDate('created_at', $fecha_at);
        }
        if ($f_updated = request('f_updated')) {
            $query->whereDate('updated_at', $f_updated);
        }

        if ($roleId = request('role_id_f')) {
            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('id', $roleId);
            });
        }
        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('users-table')
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
            Column::make('name')->title('Nombres')->addClass('min-w-175px'),
            Column::make('email')->title('Correo Electrónico')->addClass('min-w-200px'),
            Column::computed('role')->title('Rol'),
            Column::make('last_login_at')->title('Último Acceso')->addClass('text-center min-w-150px'),
            Column::make('two_factor_confirmed_at')->title('Dos Factores')->addClass('text-center min-w-150px'),
            Column::make('created_at')->title('Fecha Creación')->addClass('text-center min-w-150px'),
            // Column::make('updated_at')->title('Fecha Actualización')->addClass('text-center min-w-175px'),
            Column::computed('action')->title('Acciones')->addClass('text-center min-w-100px')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Users_' . date('YmdHis');
    }
}
