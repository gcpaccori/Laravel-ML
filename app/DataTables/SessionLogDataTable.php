<?php

namespace App\DataTables;

use App\Models\SessionLog;
use Laravel\Jetstream\Agent;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class SessionLogDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<SessionLog> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            // ->rawColumns(['location'])
            ->addColumn('action', '')
            ->addColumn('device', function (SessionLog $s) {
                $ua = $s->user_agent;
                // return 'WebKit - ' . $m[1];
                if (preg_match('/AppleWebKit\/([\d\.]+)/i', $ua, $m)) {
                    return 'WebKit';
                } elseif (preg_match('/Gecko\/([\d\.]+)/i', $ua, $m)) {
                    return 'Gecko';
                } elseif (preg_match('/Trident\/([\d\.]+)/i', $ua, $m)) {
                    return 'Trident';
                } elseif (preg_match('/Blink\/([\d\.]+)/i', $ua, $m)) {
                    return 'Blink';
                }

                return '-';
            })
            ->addColumn('device_type', function (SessionLog $s) {
                $agent = tap(new Agent(), fn ($agent) => $agent->setUserAgent($s->user_agent));

                if ( $agent->isMobile() ) {
                    return 'Móvil';
                } elseif ( $agent->isTablet() ) {
                    return 'Tablet';
                } elseif ( $agent->isDesktop() ) {
                    return 'Escritorio';
                }
                return '-';
            })
            ->addColumn('platform', function (SessionLog $s) {
                $agent = tap(new Agent(), fn ($agent) => $agent->setUserAgent($s->user_agent));
                return $agent->platform();
            })
            ->addColumn('browser', function (SessionLog $s) {
                $agent = tap(new Agent(), fn ($agent) => $agent->setUserAgent($s->user_agent));
                return $agent->browser();
            })
            ->editColumn('last_activity', function (SessionLog $s) {
                return $s->last_activity->format('d/m/Y - H:i');
            })
            ->editColumn('user_id', function (SessionLog $s) {
                return $s->user->name ?? '-';
            })
            ->editColumn('location', function (SessionLog $s) {
                $location = json_decode($s->location, true);
                return $location;
            })
            ->editColumn('status', function (SessionLog $s) {
                return $s->status === 'login' ? 'Iniciar sesión' : 'Cerrar sesión';
            })
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<SessionLog>
     */
    public function query(SessionLog $model): QueryBuilder
    {
        $query = $model->newQuery();
        if ($user_id_f = request('user_id_f')) {
            $query->where('user_id', $user_id_f);
        }
        return $query;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('sessionlog-table')
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
            Column::make('last_activity')->title('Fecha y Hora')->addClass('text-center'),
            Column::make('user_id')->title('Usuario'),
            Column::make('status')->title('Transacción')->addClass('text-center'),
            Column::make('ip_address')->title('IP')->addClass('text-center'),
            Column::make('location')->title('Ubicación')->addClass('text-center'),
            Column::computed('device')->title('Dispositivo')->addClass('text-center'),
            Column::computed('device_type')->title('Tipo')->addClass('text-center'),
            Column::computed('platform')->title('Plataforma')->addClass('text-center'),
            Column::computed('browser')->title('Navegador')->addClass('text-center'),
            Column::computed('action')->title('Acciones')->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'SessionLog_' . date('YmdHis');
    }
}
