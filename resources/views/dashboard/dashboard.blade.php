@extends('dashboard.layouts.master')

@section('title', __('general.analytics'))
@section('css')
    {{--    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/bootstrap.css') }}">--}}
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/bootstrap-extended.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/components.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/colors.css') }}">
    <script src="https://unpkg.com/feather-icons"></script>

@endsection

@section('content')
    <section id="statistics-card">
        <div class="row">
            <div class="col-lg-4 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header align-items-start pb-0">
                        <div>
                            <h2 class="font-weight-bolder">{{$usersCount}}</h2>
                            <p class="card-text">{{__('general.Number Users')}}</p>
                        </div>
                        <div class="avatar bg-light-primary" style="padding: 0.27rem;">
                            <div class="avatar-content">
                                <i data-feather="users" class="font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                    <div id="line-area-chart-5"></div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header align-items-start pb-0">
                        <div>
                            <h2 class="font-weight-bolder">{{$postsCount}}</h2>
                            <p class="card-text">{{__('Number Posts')}}</p>
                        </div>
                        <div class="avatar bg-light-success" style="padding: 0.27rem;">
                            <div class="avatar-content">
                                <i data-feather="user" class="font-medium-5"></i>
                                {{--                                <i data-feather="check-circle" class="font-medium-5"></i>--}}
                            </div>
                        </div>
                    </div>
                    <div id="line-area-chart-6"></div>
                </div>
            </div>


            <div class="col-lg-4 col-sm-6 col-12">
                <div class="card">
                    <div class="card-header align-items-start pb-0">
                        <div>
                            <h2 class="font-weight-bolder">{{$totalAmount}}</h2>
                            <p class="card-text">{{__('total Amount')}}</p>
                        </div>
                        <div class="avatar bg-light-danger" style="padding: 0.27rem;">
                            <div class="avatar-content">
                                <i data-feather="dollar-sign" class="font-medium-5"></i>
                            </div>
                        </div>
                    </div>
                    <div id="line-area-chart-3"></div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class=" col-12">
                <div class="card">
                    <div class="card-header bg-transparent pd-b-0 pd-t-20 bd-b-0">
                        <div class="d-flex justify-content-between">
                            {{--                            <h4 class="card-title mb-0">{{__('general.number of orders')}}</h4>--}}
                        </div>
                    </div>
                    <div class="card-body">
                        {!! $lineChart->render() !!}
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
{{--    <script src="{{asset('dashboard/app-assets/vendors/js/vendors.min.js')}}"></script>--}}
    <script src="{{asset('dashboard/app-assets/js/scripts/cards/card-statistics.js')}}"></script>
    <script src="{{asset('dashboard/app-assets/vendors/js/charts/apexcharts.min.js')}}"></script>
    <script src="{{asset('dashboard/app-assets/js/core/app-menu.js')}}"></script>
    <script src="{{asset('dashboard/app-assets/js/core/app.js')}}"></script>
    <script>
        feather.replace();
    </script>
@stop
