<?php

namespace App\DataTables;

use App\Models\Category;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class CategoryDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($category) {
                return view('components.datatable.actions', [
                    'id' => $category->id,
                    'routeEdit' => 'admin.categories.edit',
                    'routeDelete' => 'admin.categories.destroy',
                    'name' => $category->name_ar,
                ]);
            })
            ->filterColumn('name_en', function ($query, $keyword) {
                $query->whereHas('translations', function ($q) use ($keyword) {
                    $q->where('name', 'like', '%'.$keyword.'%');
                });
            })
            ->addColumn('sort', function ($city) {
                return '<input type="number"
                class="form-control sort-input"
                value="'.$city->sort.'"
                data-id="'.$city->id.'"
                style="width:80px">';
            })
            //image
            ->addColumn('image', function ($category) {
                if (! $category->image) {
                    return '<span class="text-muted">—</span>';
                }

                return '<img src="'.asset($category->image).'" alt="" class="img-thumbnail" style="width: 50px; height: 50px;">';
            })
            ->addColumn('name_en', function ($category) {
                return $category->name_en;
            })
            ->addColumn('filter_group', function ($category) {
                return $category->filterGroup ? $category->filterGroup->name_en : __('no filter group');
            })
            ->rawColumns(['image', 'action', 'status', 'filter_group','sort']);
    }

    public function query(Category $model)
    {
        return $model->newQuery()
            ->whereNull('parent_id')
            ->with('translations')
            ->orderBy('sort', 'asc');
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
            Column::computed('sort')
                ->title('Sort')
                ->orderable(false)
                ->searchable(false),
            Column::make('id')->title(__('dataTable.id')),
            Column::make('image')->title(__('dataTable.image')),
            Column::computed('name_en')
                ->title(__('dataTable.name'))
                ->orderable(false),
            Column::make('status')->title(__('dataTable.status')),
            Column::make('filter_group')->title(__('filter')),
            Column::computed('action')->title(__('dataTable.action'))->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'categories_' . date('YmdHis');
    }
}
