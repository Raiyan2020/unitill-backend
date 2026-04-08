<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="ltr">
<!-- BEGIN: Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=0,minimal-ui">
    <meta name="description" content="Vuexy admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities.">
    <meta name="keywords" content="admin template, Vuexy admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="PIXINVENT">
    <title>{{__('Login Page - USell Dashboard')}}</title>
    <link rel="apple-touch-icon" href="{{ URL::asset('dashboard/icon/Group.svg') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ URL::asset('dashboard/icon/Group.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;1,400;1,500;1,600" rel="stylesheet">

    <!-- BEGIN: Vendor CSS-->
{{--    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/vendors/css/vendors.min.css') }}">--}}
    <!-- END: Vendor CSS-->

    <!-- BEGIN: Theme CSS-->
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/bootstrap.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/bootstrap-extended.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/colors.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/components.css') }}">
{{--    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/themes/dark-layout.css') }}">--}}
{{--    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/themes/bordered-layout.css') }}">--}}
{{--    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/themes/semi-dark-layout.css') }}">--}}

    <!-- BEGIN: Page CSS-->
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/plugins/forms/form-validation.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/app-assets/css/pages/page-auth.css') }}">
    <!-- END: Page CSS-->

    <!-- BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{ URL::asset('dashboard/assets/css/style.css') }}">


</head>
<!-- END: Head-->

<!-- BEGIN: Body-->

<body class="vertical-layout vertical-menu-modern blank-page navbar-floating footer-static  " data-open="click" data-menu="vertical-menu-modern" data-col="blank-page">
<!-- BEGIN: Content-->
<div class="app-content content ">
    <div class="content-overlay"></div>
    <div class="header-navbar-shadow"></div>
    <div class="content-wrapper">
        <div class="content-header row">
        </div>
        <div class="content-body">
            <div class="auth-wrapper auth-v1 px-2">
                <div class="auth-inner py-2">
                    <!-- Login v1 -->
                    <div class="card mb-0">
                        <div class="card-body">
                            <a href="javascript:void(0);" class="brand-logo d-flex align-items-center text-decoration-none">
                                <div class="text-center">
                                    <img src="{{ asset('logo.png') }}" height="60" alt="Logo" class="mb-1">
{{--                                    <h2 class="brand-text text-primary mb-0">{{ __('USell') }}</h2>--}}
                                </div>
                            </a>

                            <h4 class="card-title mb-1">{{__('Welcome to USell!')}} 👋</h4>
                            <p class="card-text mb-2">{{ __('auth.description_admin') }}</p>


                            <form class="auth-login-form mt-2" method="POST" action="{{ route($prefix .'.login') }}" onsubmit="disableButton()">
                                @csrf
                                <input type="hidden" name="user_type" value="{{$prefix}}">
                                <div class="form-group">
                                    <label for="login-email" class="form-label">{{ __('auth.Email') }}</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="login-email"
                                        name="login-email"
                                        value="{{ old('login-email', Cookie::get('email')) }}"
                                        placeholder="john@example.com"
                                        aria-describedby="login-email"
                                        tabindex="1"
                                        autofocus
                                    />
                                </div>

                                @error('login-email') <span id="login-email-error" class="error"><strong>{{ $message }}</strong></span> @enderror

                                <div class="form-group">
                                    <div class="d-flex justify-content-between">
                                        <label for="login-password">{{ __('auth.Password') }}</label>
                                    </div>
                                    <div class="input-group input-group-merge form-password-toggle">
                                        <input
                                            type="password"
                                            class="form-control form-control-merge"
                                            id="login-password"
                                            name="login-password"
                                            value="{{ old('login-password', Cookie::get('password')) }}"
                                            tabindex="2"
                                            placeholder="•••••••••••"
                                            aria-describedby="login-password"
                                        />
                                        <div class="input-group-append">
                                            <span class="input-group-text cursor-pointer"><i data-feather="eye"></i></span>
                                        </div>
                                    </div>
                                    @error('login-password') <span id="login-password-error" class="error"><strong>{{ $message }}</strong></span> @enderror
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input
                                            class="custom-control-input"
                                            type="checkbox"
                                            id="remember-me"
                                            name="remember"
                                            tabindex="3"
                                            {{ old('remember') || Cookie::has('email') ? 'checked' : '' }}
                                        />
                                        <label class="custom-control-label" for="remember-me">{{ __('auth.Remember Me') }}</label>
                                    </div>
                                </div>

                                <button id="loginButton" class="btn btn-primary btn-block" tabindex="4">
                                    {{ __('auth.Sign in') }}
                                </button>
                            </form>

                        </div>
                    </div>
                    <!-- /Login v1 -->
                </div>
            </div>

        </div>
    </div>
</div>
<!-- END: Content-->

<script>
    function disableButton() {
        const button = document.getElementById('loginButton');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing in...';
    }
</script>

<script src="{{ URL::asset('dashboard/app-assets/vendors/js/vendors.min.js') }}"></script>
<!-- BEGIN Vendor JS-->

<!-- BEGIN: Page Vendor JS-->
<script src="{{ URL::asset('dashboard/app-assets/vendors/js/forms/validation/jquery.validate.min.js') }}"></script>
<!-- END: Page Vendor JS-->

<!-- BEGIN: Theme JS-->
<script src="{{ URL::asset('dashboard/app-assets/js/core/app-menu.js') }}"></script>
<script src="{{ URL::asset('dashboard/app-assets/js/core/app.js') }}"></script>
<!-- END: Theme JS-->

<!-- BEGIN: Page JS-->
<script src="{{ URL::asset('dashboard/app-assets/js/scripts/pages/page-auth-login.js') }}"></script>
<!-- END: Page JS-->


<script>
    $(window).on('load', function() {
        if (feather) {
            feather.replace({
                width: 14,
                height: 14
            });
        }
    })
</script>
</body>
<!-- END: Body-->

</html>
