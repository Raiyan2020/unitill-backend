<?php

namespace App\DataTables;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class UsersDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($user) {
                $auth = auth('admin')->check() ? 'admin' : 'school';

                $viewData = [
                    'id' => $user->id,
                    'name' => $user->name,
                ];

                if ($auth === 'admin') {
                    $viewData['routeEdit'] = 'admin.users.edit';
                    $viewData['routeDelete'] = 'admin.users.destroy';
                }

                return view('components.datatable.actions', $viewData);

            })
            //phone
            ->addColumn('phone', function ($post) {
                  $countryCode = $post->country_code ?? '';
                  $phone = $post->country_code . $post->phone ?? '';
                    return $phone;

            })

            ->addColumn('created_at', function ($user) {
                return $user->created_at->format('Y-m-d H:i');
            })
            //status 1 == active 2 == pending 0 == inactive
            ->addColumn('status', function ($user) {
                return $user->status == 1 ? __('dataTable.active') : ($user->status == 2 ? __('dataTable.pending') : __('dataTable.inactive'));
            })
            //children_count

            //orders_count

            ->rawColumns(['action', 'image']);
    }

    public function query(User $model)
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
            Column::make('name')->title(__('dataTable.name')),
            Column::make('phone')->title(__('dataTable.phone')),
//            Column::make('country_code')->title(__('dataTable.country_code')),
            Column::make('email')->title(__('dataTable.email')),

            Column::make('status')->title(__('dataTable.status')),
            Column::make('created_at')->title(__('dataTable.created_at')),

            Column::computed('action')->title(__('dataTable.action'))->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'schools_' . date('YmdHis');
    }
}

