@extends('dashboard.layouts.master')
@section('title', __('general.Add School') )
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
                        <h4 class="card-title">{{__('general.Add School')}}</h4>
                    </div>
                    <div class="card-body">
                        <form class="form" action="{{ route('admin.schools.store') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <!-- Name Arabic -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="name_ar">{{__('general.Name in Arabic')}}</label>
                                        <input
                                            value="{{ old('name_ar') }}"
                                            name="name_ar"
                                            type="text"
                                            id="name_ar"
                                            class="form-control form-control-sm @error('name_ar') is-invalid @else {{ old('name_ar') ? 'is-valid' : '' }} @enderror"
                                            placeholder="اسم المدرسة"
                                            required
                                        />
                                        @error('name_ar')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Name English -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="name_en">{{__('general.Name in English')}}</label>
                                        <input
                                            value="{{ old('name_en') }}"
                                            name="name_en"
                                            type="text"
                                            id="name_en"
                                            class="form-control form-control-sm @error('name_en') is-invalid @else {{ old('name_en') ? 'is-valid' : '' }} @enderror"
                                            placeholder="School Name"
                                            required
                                        />
                                        @error('name_en')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Description Arabic -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="description_ar">{{__('general.Description in Arabic')}}</label>
                                        <textarea
                                            name="description_ar"
                                            id="description_ar"
                                            class="form-control form-control-sm @error('description_ar') is-invalid @else {{ old('description_ar') ? 'is-valid' : '' }} @enderror"
                                            placeholder="وصف المدرسة"
                                            required
                                        >{{ old('description_ar') }}</textarea>
                                        @error('description_ar')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Description English -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="description_en">{{__('general.Description in English')}}</label>
                                        <textarea
                                            name="description_en"
                                            id="description_en"
                                            class="form-control form-control-sm @error('description_en') is-invalid @else {{ old('description_en') ? 'is-valid' : '' }} @enderror"
                                            placeholder="School Description"
                                            required
                                        >{{ old('description_en') }}</textarea>
                                        @error('description_en')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="levels">{{__('general.city')}}</label>
                                        <select name="city_id" id="city_id" class="form-control form-control-sm " required>
{{--                                            <option value="">{{ __('general.choose') }}</option>--}}

                                        @foreach($cities as $city)
                                                <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name_ar }}</option>
                                            @endforeach
                                        </select>
                                        @error('city_id')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="region_id">{{__('general.region')}}</label>
                                        <select name="region_id" id="region_id" class="form-control form-control-sm" required>
{{--                                            <option value="">{{ __('general.choose') }}</option>--}}
                                        </select>
                                        @error('region_id')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>


                                <!-- Address -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="address">{{__('general.Address')}}</label>
                                        <input
                                            value="{{ old('address') }}"
                                            name="address"
                                            type="text"
                                            id="address"
                                            class="form-control form-control-sm @error('address') is-invalid @else {{ old('address') ? 'is-valid' : '' }} @enderror"
                                            placeholder="123 Street"
                                            required
                                        />
                                        @error('address')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="location">{{__('general.Location')}}</label>
                                        <input
                                            value="{{ old('location') }}"
                                            name="location"
                                            type="text"
                                            id="location"
                                            class="form-control form-control-sm @error('location') is-invalid @else {{ old('location') ? 'is-valid' : '' }} @enderror"
                                            placeholder="Location"
                                            required
                                        />
                                        @error('location')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>


                                <!-- Phone 1 -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="phone1">{{__('general.Phone 1')}}</label>
                                        <input
                                            value="{{ old('phone1') }}"
                                            name="phone1"
                                            type="text"
                                            id="phone1"
                                            class="form-control form-control-sm @error('phone1') is-invalid @else {{ old('phone1') ? 'is-valid' : '' }} @enderror"
                                            placeholder="Phone 1"
                                            required
                                        />
                                        @error('phone1')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Phone 2 -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="phone2">{{__('general.Phone 2')}}</label>
                                        <input
                                            value="{{ old('phone2') }}"
                                            name="phone2"
                                            type="text"
                                            id="phone2"
                                            class="form-control form-control-sm @error('phone2') is-invalid @else {{ old('phone2') ? 'is-valid' : '' }} @enderror"
                                            placeholder="Phone 2"
                                        />
                                        @error('phone2')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="email">{{__('general.Email')}}</label>
                                        <input
                                            value="{{ old('email') }}"
                                            name="email"
                                            type="email"
                                            id="email"
                                            class="form-control form-control-sm @error('email') is-invalid @else {{ old('email') ? 'is-valid' : '' }} @enderror"
                                            placeholder="example@example.com"
                                            required
                                        />
                                        @error('email')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>


                                <!-- Image -->
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="image">{{__('general.Image')}}</label>
                                        <div class="custom-file">
                                        <input
                                            value="{{ old('image') }}"
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
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="status">{{__('general.Status')}}</label>
                                        <select
                                            name="status"
                                            id="status"
                                            class="form-control form-control-sm @error('status') is-invalid @else {{ old('status') ? 'is-valid' : '' }} @enderror"
                                            required
                                        >
                                            <option value="1" >{{ __('general.Active') }}</option>
                                            <option value="0" >{{ __('general.Inactive') }}</option>
                                        </select>
                                        @error('status')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label class="col-form-label-sm" for="levels">{{__('general.Levels')}}</label>
                                        <select
                                            name="levels[]"
                                            id="levels"
                                            class="form-control form-control-sm @error('levels') is-invalid @else {{ old('levels') ? 'is-valid' : '' }} @enderror"
                                            multiple
                                            required
                                        >
                                            <option value="primary" {{ in_array('primary', old('levels', [])) ? 'selected' : '' }}>{{ __('general.Primary') }}</option>
                                            <option value="intermediate" {{ in_array('intermediate', old('levels', [])) ? 'selected' : '' }}>{{ __('general.Intermediate') }}</option>
                                            <option value="secondary" {{ in_array('secondary', old('levels', [])) ? 'selected' : '' }}>{{ __('general.Secondary') }}</option>
                                        </select>

                                        @error('levels')
                                        <span class="col-form-label-sm text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Submit Button -->
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
@section('js')
    <script>
        function loadRegions(cityId, selectedRegionId = null) {
            $.ajax({
                url: "{{ route('admin.get.regions') }}",
                type: "GET",
                data: { city_id: cityId },
                success: function (res) {
                    $('#region_id').empty();
                    $.each(res, function (key, value) {
                        let selected = selectedRegionId == value.id ? 'selected' : '';
                        $('#region_id').append(`<option value="${value.id}" ${selected}>${value.name_ar}</option>`);
                    });
                }
            });
        }

        $(document).ready(function () {
            // أول تحميل
            let initialCityId = $('#city_id').val();
            if (initialCityId) {
                loadRegions(initialCityId);
            }

            // عند تغيير المدينة
            $('#city_id').on('change', function () {
                let cityId = $(this).val();
                loadRegions(cityId);
            });
        });
    </script>
@endsection
