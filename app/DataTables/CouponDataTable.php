<?php

namespace App\DataTables;

use App\Models\Coupon;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class CouponDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($coupon) {
                return view('components.datatable.actions', [
                    'id' => $coupon->id,
                    'routeEdit' => 'admin.coupons.edit',
                    'routeDelete' => 'admin.coupons.destroy',
                    'name' => $coupon->code,
                ]);
            })

            ->addColumn('type', function ($coupon) {
                return ucfirst($coupon->type);
            })

            ->addColumn('value', function ($coupon) {
                return $coupon->type === 'percent'
                    ? $coupon->value . ' %'
                    : $coupon->value;
            })

            ->addColumn('status', function ($coupon) {
                return $coupon->is_active
                    ? '<span class="badge badge-success" style="color: green">Active</span>'
                    : '<span class="badge badge-danger" style="color: red">Inactive</span>';
            })

            ->addColumn('validity', function ($coupon) {
                if ($coupon->expires_at && $coupon->expires_at < now()) {
                    return '<span class="badge badge-danger" style="color: red">Expired</span>';
                }
                return '<span class="badge badge-success"  style="color: green">Valid</span>';
            })

            ->rawColumns(['action', 'status', 'validity']);
    }

    public function query(Coupon $model)
    {
        return $model->latest()->newQuery();
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
            Column::make('code')->title(__('Coupon Code')),
            Column::computed('type')->title(__('Type')),
            Column::computed('value')->title(__('Value')),
            Column::make('used_count')->title(__('Used')),
            Column::computed('status')->title(__('Status')),
            Column::computed('validity')->title(__('Validity')),
            Column::computed('action')
                ->title(__('dataTable.action'))
                ->exportable(false)
                ->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'coupons_' . date('YmdHis');
    }
}
