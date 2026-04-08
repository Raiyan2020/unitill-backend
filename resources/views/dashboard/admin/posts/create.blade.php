@extends('dashboard.layouts.master')
@section('title', __('general.Add Coupon'))
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
                        <h4 class="card-title">{{ __('general.Add Coupon') }}</h4>
                    </div>
                    <div class="card-body">
                        <form class="form" action="{{ route('admin.coupons.store') }}" method="post">
                            @csrf
                            <div class="row">
                                <!-- Coupon Code -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="code" class="col-form-label-sm">{{ __('general.code') }}</label>
                                        <input
                                            type="text"
                                            id="code"
                                            name="code"
                                            class="form-control form-control-sm @error('code') is-invalid @enderror"
                                            value="{{ old('code') }}"
                                            placeholder="{{ __('general.code') }}"
                                            required
                                        />
                                        @error('code')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Discount Type -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="type">{{ __('general.Type') }}</label>
                                        <select
                                            name="type"
                                            id="type"
                                            class="form-control form-control-sm @error('type') is-invalid @else {{ old('type') ? 'is-valid' : '' }} @enderror"
                                            required
                                        >
                                            <option value="percentage" {{ old('type') == 'percentage' ? 'selected' : '' }}>{{ __('general.Percentage') }}</option>
                                            <option value="fixed" {{ old('type') == 'fixed' ? 'selected' : '' }}>{{ __('general.Fixed') }}</option>
                                        </select>
                                        @error('type')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Discount Value -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="discount" class="col-form-label-sm">{{ __('general.Discount Value') }}</label>
                                        <input
                                            type="number"
                                            id="discount"
                                            name="discount"
                                            class="form-control form-control-sm @error('discount') is-invalid @enderror"
                                            value="{{ old('discount') }}"
                                            required
                                        />
                                        @error('discount')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Max Usage -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="use_number" class="col-form-label-sm">{{ __('general.use_number') }}</label>
                                        <input
                                            type="number"
                                            id="use_number"
                                            name="use_number"
                                            class="form-control form-control-sm @error('use_number') is-invalid @enderror"
                                            value="{{ old('use_number') }}"
                                            required
                                        />
                                        @error('use_number')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Expiry Date -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="end_at" class="col-form-label-sm">{{ __('general.Expires At') }}</label>
                                        <input
                                            type="date"
                                            id="end_at"
                                            name="end_at"
                                            class="form-control form-control-sm @error('end_at') is-invalid @enderror"
                                            value="{{ old('end_at') }}"
                                            required
                                        />
                                        @error('end_at')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="status">{{ __('general.Status') }}</label>
                                        <select
                                            name="status"
                                            id="status"
                                            class="form-control form-control-sm @error('status') is-invalid @else {{ old('status') ? 'is-valid' : '' }} @enderror"
                                            required
                                        >
                                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>{{ __('general.Active') }}</option>
                                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>{{ __('general.Inactive') }}</option>
                                        </select>
                                        @error('status')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>


                                <!-- Submit -->
                                <div class="col-12">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">{{ __('general.Save') }}</button>
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
