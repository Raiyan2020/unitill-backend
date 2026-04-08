@extends('dashboard.layouts.master')
@section('title', __('Update PaymentMethod') )
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
                        <h4 class="card-title">{{__('general.Update PaymentMethod')}} </h4>
                    </div>
                    <div class="card-body">
                        <form class="form" action="{{ route('admin.payment-methods.update', $paymentMethod->id) }}" method="post" enctype="multipart/form-data">
                            @method('PUT')
                            @csrf
                            <div class="row">
                                <!-- Name Arabic -->
{{--                                <div class="col-md-6 col-12">--}}
{{--                                    <div class="form-group">--}}
{{--                                        <label class="col-form-label-sm" for="name_ar">{{__('general.Name in Arabic')}}</label>--}}
{{--                                        <input--}}
{{--                                            value="{{ old('name_ar', $paymentMethod->name_ar) }}"--}}
{{--                                            name="name_ar"--}}
{{--                                            type="text"--}}
{{--                                            id="name_ar"--}}
{{--                                            class="form-control form-control-sm @error('name_ar') is-invalid @else {{ old('name_ar', $paymentMethod->name_ar) ? 'is-valid' : '' }} @enderror"--}}
{{--                                            required--}}
{{--                                        />--}}
{{--                                        @error('name_ar') <span class="col-form-label-sm text-danger">{{ $message }}</span> @enderror--}}
{{--                                    </div>--}}
{{--                                </div>--}}

                                <!-- Name English -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="name_en">{{__('general.Name in English')}}</label>
                                        <input
                                            value="{{ old('name_en', $paymentMethod->name_en) }}"
                                            name="name_en"
                                            type="text"
                                            id="name_en"
                                            class="form-control form-control-sm @error('name_en') is-invalid @else {{ old('name_en', $paymentMethod->name_en) ? 'is-valid' : '' }} @enderror"
                                            required
                                        />
                                        @error('name_en') <span class="col-form-label-sm text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="code">{{__('general.code')}}</label>
                                        <input
                                            value="{{ old('code', $paymentMethod->slug) }}"
                                            name="slug"
                                            type="text"
                                            id="slug"
                                            class="form-control form-control-sm @error('slug') is-invalid @else {{ old('slug') ? 'is-valid' : '' }} @enderror"
                                            placeholder="slug"
                                            required
                                        />
                                        @error('slug')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <!-- Image -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="status">{{__('general.Status')}}</label>
                                        <select
                                            name="status"
                                            id="status"
                                            class="form-control form-control-sm @error('status') is-invalid @else {{ old('status') ? 'is-valid' : '' }} @enderror"
                                            required
                                        >
                                            <option value="active" {{ old('status', $paymentMethod->status ?? '') == 'active' ? 'selected' : '' }}>{{ __('general.Active') }}</option>
                                            <option value="inactive" {{ old('status', $paymentMethod->status ?? '') == 'inactive' ? 'selected' : '' }}>{{ __('general.Inactive') }}</option>
                                        </select>
                                        @error('status')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="image">{{__('general.Image')}}</label>
                                        <div class="custom-file">
                                            <input
                                                value="{{ old('image', $paymentMethod->image) }}"
                                                name="image"
                                                type="file"
                                                class="custom-file-input @error('image') is-invalid @else {{ old('image') ? 'is-valid' : '' }} @enderror"
                                                id="customFile"
                                            />
                                            <label class="custom-file-label" for="customFile">{{__('general.Choose file')}}</label>
                                        </div>
                                        @error('image')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Status -->


                                <!-- Submit -->
                                <div class="col-12">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">{{ __('general.Update') }}</button>
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
@section('js')
    <script>
        document.getElementById('customFile').addEventListener('change', function (e) {
            // Get the selected file name
            var fileName = e.target.files[0] ? e.target.files[0].name : '{{__('general.Choose file')}} ';
            // Update the label text
            e.target.nextElementSibling.textContent = fileName;
        });
    </script>

@stop
@section('js')
@endsection

