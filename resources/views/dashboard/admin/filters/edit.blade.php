@extends('dashboard.layouts.master')
@section('title', __('Edit Filters'))
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
                        <h4 class="card-title">{{ __('Edit Filters') }}</h4>
                    </div>
                    <div class="card-body">
                        <form class="form" action="{{ route('admin.filters.update', $filterGroup->id) }}" method="post" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <!-- Name English -->
                                <div class="col-md-12 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="name_en">{{ __('general.Name in English') }}</label>
                                        <input
                                            value="{{ old('name_en', $filterGroup->name_en) }}"
                                            name="name_en"
                                            type="text"
                                            id="name_en"
                                            class="form-control form-control-sm @error('name_en') is-invalid @else {{ old('name_en', $filterGroup->name_en) ? 'is-valid' : '' }} @enderror"
                                            placeholder="Name in English"
                                            required
                                        />
                                        @error('name_en')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Filters -->
                                <div class="col-md-12 col-12">
                                    <label class="col-form-label-sm d-flex align-items-center justify-content-between">
                                        {{ __('general.Filters') }}
                                        <button type="button" class="btn btn-sm btn-success add-filter">+</button>
                                    </label>

                                    <div id="filters-wrapper">
                                        @if(old('filters'))
                                            @foreach(old('filters') as $filter)
                                                <div class="form-group d-flex mb-1">
                                                    <input type="text" name="filters[]" value="{{ $filter }}" class="form-control form-control-sm me-1" placeholder="{{ __('Filter name') }}">
                                                    <button type="button" class="btn btn-sm btn-danger remove-filter">-</button>
                                                </div>
                                            @endforeach
                                        @else
                                            @foreach($filterGroup->filters as $filter)
                                                <div class="form-group d-flex mb-1">
                                                    <input type="text" name="filters[]" value="{{ $filter->name_en }}" class="form-control form-control-sm me-1" placeholder="{{ __('Filter name') }}">
                                                    <button type="button" class="btn btn-sm btn-danger remove-filter">-</button>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    @error('filters.*')
                                    <span class="col-form-label-sm text-danger d-block">{{ $message }}</span>
                                    @enderror
                                </div>

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
        document.addEventListener('DOMContentLoaded', function () {
            const wrapper = document.querySelector('#filters-wrapper');
            const addButton = document.querySelector('.add-filter');

            addButton.addEventListener('click', function () {
                const div = document.createElement('div');
                div.classList.add('form-group', 'd-flex', 'mb-1');

                div.innerHTML = `
                    <input type="text" name="filters[]" class="form-control form-control-sm me-1" placeholder="{{ __('Filter name') }}">
                    <button type="button" class="btn btn-sm btn-danger remove-filter">-</button>
                `;

                wrapper.appendChild(div);
            });

            wrapper.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-filter')) {
                    e.target.closest('.form-group').remove();
                }
            });
        });
    </script>
@endsection
