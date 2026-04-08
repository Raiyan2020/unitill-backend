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
                        <form class="form"
                              action="{{ route('admin.coupons.store') }}"
                              method="post">
                            @csrf

                            <div class="row">

                                <!-- Code -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="code">{{ __('Coupon Code') }}</label>
                                        <input type="text"
                                               id="code"
                                               name="code"
                                               class="form-control form-control-sm @error('code') is-invalid @enderror"
                                               value="{{ old('code') }}"
                                               required>
                                        @error('code')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Type -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="type">{{ __('Type') }}</label>
                                        <select name="type"
                                                id="type"
                                                class="form-control form-control-sm @error('type') is-invalid @enderror"
                                                required>
                                            <option value="percent" {{ old('type') == 'percent' ? 'selected' : '' }}>
                                                {{ __('Percentage') }}
                                            </option>
                                            <option value="fixed" {{ old('type') == 'fixed' ? 'selected' : '' }}>
                                                {{ __('Fixed Amount') }}
                                            </option>
                                        </select>
                                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Value -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="value">{{ __('Value') }}</label>
                                        <input type="number"
                                               step="0.01"
                                               id="value"
                                               name="value"
                                               class="form-control form-control-sm @error('value') is-invalid @enderror"
                                               value="{{ old('value') }}"
                                               required>
                                        @error('value')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Max Uses -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="max_uses">{{ __('Max Uses') }}</label>
                                        <input type="number"
                                               id="max_uses"
                                               name="max_uses"
                                               class="form-control form-control-sm @error('max_uses') is-invalid @enderror"
                                               value="{{ old('max_uses') }}">
                                        @error('max_uses')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Max Uses Per User -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="max_uses_per_user">{{ __('Max Uses Per User') }}</label>
                                        <input type="number"
                                               id="max_uses_per_user"
                                               name="max_uses_per_user"
                                               class="form-control form-control-sm @error('max_uses_per_user') is-invalid @enderror"
                                               value="{{ old('max_uses_per_user') }}">
                                        @error('max_uses_per_user')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Starts At -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="starts_at">{{ __('Starts At') }}</label>
                                        <input type="date"
                                               id="starts_at"
                                               name="starts_at"
                                               class="form-control form-control-sm @error('starts_at') is-invalid @enderror"
                                               value="{{ old('starts_at') }}">
                                        @error('starts_at')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Expires At -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="expires_at">{{ __('Expires At') }}</label>
                                        <input type="date"
                                               id="expires_at"
                                               name="expires_at"
                                               class="form-control form-control-sm @error('expires_at') is-invalid @enderror"
                                               value="{{ old('expires_at') }}">
                                        @error('expires_at')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="is_active">{{ __('Status') }}</label>
                                        <select name="is_active"
                                                id="is_active"
                                                class="form-control form-control-sm @error('is_active') is-invalid @enderror"
                                                required>
                                            <option value="1" {{ old('is_active') == '1' ? 'selected' : '' }}>
                                                {{ __('Active') }}
                                            </option>
                                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>
                                                {{ __('Inactive') }}
                                            </option>
                                        </select>
                                        @error('is_active')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('general.Save') }}
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
