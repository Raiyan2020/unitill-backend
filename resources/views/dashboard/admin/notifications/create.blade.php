@extends('dashboard.layouts.master')
@section('title', __('general.Notifications'))
@section('css')
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/bootstrap.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/bootstrap-extended.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/components.css') }}">
@endsection

@section('content')
    <section id="multiple-column-form">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('general.Add Notifications') }}</h4>
                    </div>
                    <div class="card-body">
                        <form class="form" action="{{ route('admin.notifications.store') }}" method="post">
                            @csrf
                            <div class="row row-sm">
                                <div class="form-group col-md-12 has-success mg-t-10">
                                    <label for="name">{{ __('general.title_en') }}  :</label>
                                    <input value="{{old('title')}}" id="title" type="text" class="form-control " name="title"  required>
                                    @error('title')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
{{--                                <div class="form-group col-md-12 has-success mg-t-10">--}}
{{--                                    <label for="name">{{ __('general.title_ar') }}  :</label>--}}
{{--                                    <input value="{{old('title_ar')}}" id="title_ar" type="text" class="form-control " name="title_ar"  required>--}}
{{--                                    @error('title_ar')--}}
{{--                                    <span class="text-danger">{{ $message }}</span>--}}
{{--                                    @enderror--}}
{{--                                </div>--}}
                                <div class="form-group col-md-12 has-success mg-t-10">
                                    <label for="email">{{ __('general.content_en') }}  :</label>
                                    <textarea id="body" class="form-control " name="body" required>{{old('body')}}</textarea>
                                    @error('body')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
{{--                                <div class="form-group col-md-12 has-success mg-t-10">--}}
{{--                                    <label for="email">{{ __('general.content_en') }}  :</label>--}}
{{--                                    <textarea id="body_ar" class="form-control " name="body_ar" required>{{old('body_ar')}}</textarea>--}}
{{--                                    @error('body_ar')--}}
{{--                                    <span class="text-danger">{{ $message }}</span>--}}
{{--                                    @enderror--}}
{{--                                </div>--}}
{{--                                <div class="form-group col-md-12 has-success mg-t-10">--}}
{{--                                    <label for="name">{{ __('general.url') }}  :</label>--}}
{{--                                    <input value="{{old('url')}}" id="url" type="url" class="form-control " name="url">--}}
{{--                                    @error('url')--}}
{{--                                    <span class="text-danger">{{ $message }}</span>--}}
{{--                                    @enderror--}}
{{--                                </div>--}}

                                {{--                            <div class="form-group col-md-12 has-success mg-t-10">--}}
                                {{--                                <label class="form-label mg-b-0">{{__('messages.users')}}</label>--}}
                                {{--                                <select name="users[]" id="users" class="form-control testselect2" multiple>--}}
                                {{--                                    @foreach($users as $user)--}}
                                {{--                                        <option value="{{ $user->id }}">{{ $user->name }}</option>--}}
                                {{--                                    @endforeach--}}
                                {{--                                </select>--}}
                                {{--                                @error('users')--}}
                                {{--                                <span class="text-danger">{{ $message }}</span>--}}
                                {{--                                @enderror--}}
                                {{--                            </div>--}}

                                <div class="modal-footer">
                                    <div class="form-group col-md-12 has-success mg-t-20">
                                        <button type="submit" class="btn btn-success" >{{ __('general.save') }}</button>
                                    </div>
                                </div>
                               
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
