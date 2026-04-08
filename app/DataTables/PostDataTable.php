<?php
namespace App\DataTables;

use App\Models\Post;
use Carbon\Carbon;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class PostDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($post) {
                return view('components.datatable.actions', [
                    'id' => $post->id,
                    'routeEdit' => 'admin.posts.edit',
                    'routeDelete' => 'admin.posts.destroy',
                    'name' => $post->title,
                ]);
            })
            ->addColumn('image', function ($post) {
                return '<img src="' . asset($post->image) . '" alt="' . $post->title . '" class="img-thumbnail" style="width: 50px; height: 50px;">';
            })
            ->addColumn('user', function ($post) {
                return $post->user?->name ?? '-';
            })
            //phone
            ->addColumn('phone_user', function ($post) {
                //code to return phone or '-'
                $countryCode = $post->user?->country_code ?? '';
                $phone = $post->user?->phone ?? '';
                return $countryCode ?  $countryCode . ' ' . $phone : $phone;

            })
            //phone_post
            ->addColumn('phone_post', function ($post) {
                return  $post->phone ?? '-';
            })

            ->addColumn('category', function ($post) {
                return $post->category?->name_en ?? '-';
            })
            ->addColumn('city', function ($post) {
                return $post->city?->name_en ?? '-';
            })
            ->addColumn('status', function ($post) {
                return $post->status  == 1 ? '<span class="badge bg-success">' . __('dataTable.active') . '</span>' :
                    '<span class="badge bg-danger">' . __('dataTable.inactive') . '</span>' ;
            })
            //payment_status
            ->addColumn('payment_status', function ($post) {
                return $post->payment_status == 1 ? '<span class="badge bg-success">' . __('dataTable.paid') . '</span>' :
                    '<span class="badge bg-danger">' . __('dataTable.unpaid') . '</span>';
            })
            //payment_method_id
            ->addColumn('payment_method_id', function ($post) {
                return $post->paymentMethod?->name_en ?? '-';
            })
            //created_at
            ->addColumn('created_at', function ($post) {
                return $post->created_at->format('Y-m-d H:i:s');
            })
            //end_date
            ->addColumn('end_date', function ($post) {
                $date = Carbon::parse($post->end_date);
                return $date > now() ? '<span class="badge bg-success">' . $date->format('Y-m-d') . '</span>' :
                    '<span class="badge bg-danger">' . $date->format('Y-m-d') . '</span>';

            })
            ->filterColumn('end_date', function ($query, $keyword) {
                $query->whereDate('end_date', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('title', function ($query, $keyword) {
                $query->where('title', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('payment_method_id', function ($query, $keyword) {
                $query->whereHas('paymentMethod', function ($q) use ($keyword) {
                    $q->where('name_en', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('category', function ($query, $keyword) {
                $query->whereHas('category', function ($q) use ($keyword) {
                    $q->where('name_en', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('city', function ($query, $keyword) {
                $query->whereHas('city', function ($q) use ($keyword) {
                    $q->where('name_en', 'like', '%' . $keyword . '%');
                });
            })


            ->rawColumns(['image', 'action', 'status','payment_status','end_date']);
    }

    public function query(Post $model)
    {
        return $model->newQuery()->with(['user', 'category', 'city']);
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
            Column::make('image')->title(__('dataTable.image')),
            Column::make('title')->title(__('dataTable.title')),
            Column::make('phone_user')->title(__('dataTable.phone')),
            Column::make('phone_post')->title(__('phone user')),
            Column::make('user')->title(__('dataTable.user')),

            Column::make('category')->title(__('dataTable.category')),
            Column::make('city')->title(__('dataTable.city')),
            Column::make('payment_method_id')->title(__('payment')),
            Column::make('status')->title(__('dataTable.status')),
            Column::make('payment_status')->title(__('dataTable.payment_status')),
            Column::make('paid_amount')->title(__('paid amount')),
            Column::make('end_date')->title(__('Expiration date')),
            Column::make('created_at')->title(__('dataTable.created_at')),
            Column::computed('action')->title(__('dataTable.action'))->exportable(false)->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'posts_' . date('YmdHis');
    }
}
