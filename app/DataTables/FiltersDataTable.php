<?php

namespace App\DataTables;

use App\Models\FilterGroup;
use Illuminate\Support\Facades\App;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class FiltersDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($filter) {
                return view('components.datatable.actions', [
                    'id' => $filter->id,
                    'routeEdit' => 'admin.filters.edit',
                    'routeDelete' => 'admin.filters.destroy',
                    'name' => App::getLocale() === 'ar' ? $filter->name_ar : $filter->name_en,
                ]);
            })

            ->rawColumns(['action', 'image']);

    }

    public function query(FilterGroup $model)
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
            Column::make('name_en')->title(__('dataTable.name')),
            Column::make('status')->title(__('dataTable.status')),
            Column::computed('action')->title(__('dataTable.action'))->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'cities_' . date('YmdHis');
    }
}

