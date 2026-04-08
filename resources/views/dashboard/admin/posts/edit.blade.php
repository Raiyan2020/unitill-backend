@extends('dashboard.layouts.master')
@section('title', __('Edit Post'))
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
                        <h4 class="card-title">{{ __('Edit Post') }}</h4>
                    </div>
                    <div class="card-body">
                        <form class="form"
                              action="{{ route('admin.posts.update', $post->id) }}"
                              method="post"
                              enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row">

                                {{-- user_id --}}
{{--                                <div class="col-md-6 col-12">--}}
{{--                                    <div class="form-group">--}}
{{--                                        <label>{{ __('User') }}</label>--}}
{{--                                        <select name="user_id" class="form-control form-control-sm @error('user_id') is-invalid @enderror" required>--}}
{{--                                            @foreach($users as $user)--}}
{{--                                                <option value="{{ $user->id }}" {{ old('user_id', $post->user_id) == $user->id ? 'selected' : '' }}>--}}
{{--                                                    {{ $user->name ?? $user->email }}--}}
{{--                                                </option>--}}
{{--                                            @endforeach--}}
{{--                                        </select>--}}
{{--                                        @error('user_id') <span class="text-danger">{{ $message }}</span> @enderror--}}
{{--                                    </div>--}}
{{--                                </div>--}}

                                {{-- title --}}
                                <div class="col-md-12 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Title') }}</label>
                                        <input type="text" name="title"
                                               value="{{ old('title', $post->title) }}"
                                               class="form-control form-control-sm @error('title') is-invalid @enderror" required>
                                        @error('title') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- description --}}
                                <div class="col-md-12 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Description') }}</label>
                                        <textarea name="description" rows="4"
                                                  class="form-control form-control-sm @error('description') is-invalid @enderror">{{ old('description', $post->description) }}</textarea>
                                        @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- price --}}
                                <div class="col-md-4 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Price') }}</label>
                                        <input type="number" step="0.01" name="price"
                                               value="{{ old('price', $post->price) }}"
                                               class="form-control form-control-sm @error('price') is-invalid @enderror">
                                        @error('price') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- city_id --}}
                                <div class="col-md-4 col-12">
                                    <div class="form-group">
                                        <label>{{ __('City') }}</label>
                                        <select name="city_id" class="form-control form-control-sm @error('city_id') is-invalid @enderror">
                                            <option value="">{{ __('Choose') }}</option>
                                            @foreach($cities as $city)
                                                <option value="{{ $city->id }}" {{ old('city_id', $post->city_id) == $city->id ? 'selected' : '' }}>
                                                    {{ $city->name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('city_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- category_id --}}
                                <div class="col-md-4 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Category') }}</label>
                                        <select name="category_id" class="form-control form-control-sm @error('category_id') is-invalid @enderror">
                                            <option value="">{{ __('Choose') }}</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->id }}" {{ old('category_id', $post->category_id) == $cat->id ? 'selected' : '' }}>
                                                    {{ $cat->name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('category_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- filter_id --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Filter') }}</label>
                                        <select name="filter_id" class="form-control form-control-sm @error('filter_id') is-invalid @enderror">
                                            <option value="">{{ __('Choose') }}</option>
                                            @foreach($filters as $filter)
                                                <option value="{{ $filter->id }}" {{ old('filter_id', $post->filter_id) == $filter->id ? 'selected' : '' }}>
                                                    {{ $filter->name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('filter_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- address --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Address') }}</label>
                                        <input type="text" name="address"
                                               value="{{ old('address', $post->address) }}"
                                               class="form-control form-control-sm @error('address') is-invalid @enderror">
                                        @error('address') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- phone --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Phone') }}</label>
                                        <input type="text" name="phone"
                                               value="{{ old('phone', $post->phone) }}"
                                               class="form-control form-control-sm @error('phone') is-invalid @enderror">
                                        @error('phone') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- email --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Email') }}</label>
                                        <input type="email" name="email"
                                               value="{{ old('email', $post->email) }}"
                                               class="form-control form-control-sm @error('email') is-invalid @enderror">
                                        @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                {{-- end_date --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('End Date') }}</label>
                                        <input type="date" name="end_date"
                                               value="{{ old('end_date', optional($post->end_date)->format('Y-m-d')) }}"
                                               class="form-control form-control-sm @error('end_date') is-invalid @enderror">
                                        @error('end_date') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- status (Fix comparison: 1/0) --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="status">{{ __('general.Status') }}</label>
                                        <select name="status" id="status"
                                                class="form-control form-control-sm @error('status') is-invalid @enderror" required>
                                            <option value="1" {{ (string)old('status', $post->status) === '1' ? 'selected' : '' }}>{{ __('general.Active') }}</option>
                                            <option value="0" {{ (string)old('status', $post->status) === '0' ? 'selected' : '' }}>{{ __('general.Inactive') }}</option>
                                        </select>
                                        @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- payment_status --}}
                                <div class="col-md-4 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Payment Status') }}</label>
                                        <select name="payment_status" class="form-control form-control-sm @error('payment_status') is-invalid @enderror">
                                            <option value="0" {{ old('payment_status', $post->payment_status) == '0' ? 'selected' : '' }}>pending</option>
                                            <option value="1" {{ old('payment_status', $post->payment_status) == '1' ? 'selected' : '' }}>paid</option>
                                        </select>
                                        @error('payment_status') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- paid_amount --}}
                                <div class="col-md-4 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Paid Amount') }}</label>
                                        <input type="number" step="0.01" name="paid_amount"
                                               value="{{ old('paid_amount', $post->paid_amount) }}"
                                               class="form-control form-control-sm @error('paid_amount') is-invalid @enderror">
                                        @error('paid_amount') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                {{-- payment_method_id --}}
                                <div class="col-md-4 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Payment Method') }}</label>
                                        <select name="payment_method_id" class="form-control form-control-sm @error('payment_method_id') is-invalid @enderror">
                                            <option value="">{{ __('Choose') }}</option>
                                            @foreach($paymentMethods as $method)
                                                <option value="{{ $method->id }}" {{ old('payment_method_id', $post->payment_method_id) == $method->id ? 'selected' : '' }}>
                                                    {{ $method->name_en }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('payment_method_id') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>



                                {{-- image --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Image') }}</label>
                                        <input type="file" name="image" accept="image/*"
                                               class="form-control form-control-sm @error('image') is-invalid @enderror">
                                        @error('image') <span class="text-danger">{{ $message }}</span> @enderror

                                        @if($post->image)
                                            <small class="d-block mt-1">Current: {{ $post->image }}</small>
                                        @endif
                                    </div>
                                </div>

                                {{-- video --}}
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label>{{ __('Video') }}</label>
                                        <input type="file" name="video" accept="video/*"
                                               class="form-control form-control-sm @error('video') is-invalid @enderror">
                                        @error('video') <span class="text-danger">{{ $message }}</span> @enderror

                                        @if($post->video)
                                            <small class="d-block mt-1">Current: {{ $post->video }}</small>
                                        @endif
                                    </div>
                                </div>

                                {{-- Submit --}}
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">{{ __('general.Update') }}</button>
                                </div>

                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
