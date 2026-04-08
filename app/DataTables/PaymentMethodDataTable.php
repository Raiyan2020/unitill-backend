<?php

namespace App\DataTables;

use App\Models\City;
use App\Models\PaymentMethod;
use App\Models\School;
use Illuminate\Support\Facades\App;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class PaymentMethodDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($payment) {
                if ($payment->id !=5){
                    return view('components.datatable.actions', [
                        'id' => $payment->id,
                        'routeEdit' => 'admin.payment-methods.edit',
                        'routeDelete' => 'admin.payment-methods.destroy',
                        'name' => App::getLocale() === 'ar' ? $payment->name_ar : $payment->name_en,
                    ]);
                }else{
                    return '<span class="badge badge-danger">Not Allowed</span>';
                }


            })
            ->editColumn('status', function ($payment) {
                return $payment->status == 'active' ? 'Active' : 'Inactive';
            })
            ->editColumn('image', function ($payment) {
                return $payment->image ? '<img src="'.asset($payment->image).'" width="50" height="50">' : '';
            })
            ->rawColumns(['action', 'image']);

    }

    public function query(PaymentMethod $model)
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
            Column::make('image')->title(__('dataTable.image'))->orderable(false)->searchable(false),
            Column::make('name_en')->title(__('dataTable.name')),
            Column::make('slug')->title(__('dataTable.slug')),
            Column::make('status')->title(__('dataTable.status')),
            Column::computed('action')->title(__('dataTable.action'))->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'cities_' . date('YmdHis');
    }
}

