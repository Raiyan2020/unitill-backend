<?php

namespace App\DataTables;

use App\Models\Notification;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class NotificationsDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('is_read', function ($notification) {
                return $notification->is_read ? '<span class="badge bg-success">مقروء</span>' : '<span class="badge bg-warning">غير مقروء</span>';
            })
            ->editColumn('type', function ($notification) {
                return ucfirst($notification->type);
            })
            //user
            ->editColumn('user', function ($notification) {
                return $notification->user ? $notification->user->name : '-';
            })
            //created_at
            ->editColumn('created_at', function ($notification) {
                return $notification->created_at->format('Y-m-d H:i:s');
            })
            ->rawColumns(['is_read', 'action']);
    }

    public function query(Notification $model)
    {
        return $model->newQuery();
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('datatable')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'desc')
            ->addTableClass('table table-hover');
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->title(__('dataTable.id')),
            //user_id
            Column::make('title')->title(__('dataTable.title')),
            Column::make('body')->title(__('dataTable.body')),
            Column::make('type')->title(__('dataTable.type')),
            Column::make('user')->title(__('dataTable.user')),
            Column::make('created_at')->title(__('dataTable.created_at')),

//            Column::make('is_read')->title(__('dataTable.status')),
//            Column::computed('action')->title(__('dataTable.action'))->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'notifications_' . date('YmdHis');
    }
}
