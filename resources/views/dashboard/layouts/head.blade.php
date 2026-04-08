<head>
    <meta charset="utf-8" />
    <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>{{__('general.Dashboard')}} | @yield('title')</title>

    <meta name="description" content="" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{URL::asset('dashboard/icon/Group.svg')}}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/fonts/tabler-icons.css') }}" />
{{--    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/fonts/flag-icons.css') }}" />--}}

    <!-- Core CSS -->

    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/css/rtl/theme-default.css') }}" class="template-customizer-theme-css" />

    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
{{--    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/libs/node-waves/node-waves.css') }}" />--}}

    <link rel="stylesheet" href="{{ URL::asset('dashboard/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
{{--    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/libs/typeahead-js/typeahead.css') }}" />--}}
    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />

{{--    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/libs/bootstrap-select/bootstrap-select.css') }}" />--}}
{{--    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/libs/select2/select2.css') }}" />--}}

    <!-- Page CSS -->
    @yield('css')
    <style>

        /* Base style for dropdown items */
        .animated-dropdown li {
            opacity: 1; /* Visible by default */
            transition: background-color 0.17s ease, color 0.17s ease; /* Smooth color transition on hover */
        }

        /* Hover effect for each li item */
        .animated-dropdown li:hover {
            background-color: #e0f7fa; /* Light background color on hover */
            color: #007c91; /* Text color change on hover */
        }

        .custom-dropdown-menu {
            position: absolute !important;
            top: 100% !important;
            width: 200px;
            padding: 10px !important;
            border-radius: 8px !important;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1) !important;
            text-align: left;
            /*display: none !important; !* Initially hidden *!*/
            opacity: 0 !important;
            visibility: hidden; /* Use visibility to hide instead of display */
            transform: scaleY(0) !important; /* Collapsed by default */
            transform-origin: top;
            transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease !important;
        }

        .side-sclaex {
            transform: scaleX(1) !important; /* Collapsed by default */
            transform-origin: left;
            transition:  transform 0.3s ease !important;
        }
        html[dir="rtl"] .side-sclaex {
            transform-origin: right;
        }

        .side-sclaex:hover {
            transform: scaleX(1.112) !important; /* Collapsed by default */
        }


        /* Show the dropdown with animation */
        .custom-dropdown-menu.show {
            opacity: 1 !important;
            visibility: visible; /* Make visible with transition */
            transform: scaleY(1) !important; /* Expands smoothly */
        }

        /* LTR alignment */
        html[dir="ltr"] .custom-dropdown-menu {
            right: 0px !important;
        }

        /* RTL alignment */
        html[dir="rtl"] .custom-dropdown-menu {
            left: 0px !important;
        }

        /* Remove bullet points for list items */
        .custom-dropdown-menu li {
            list-style-type: none !important;
            margin: 5px 0; /* Adjust spacing between items */
        }

        .custom-dropdown-menu .btn-logout {
            width: 100% !important;
            text-align: center; /* Center-align the button text */
        }




        .language-switcher {
            display: flex;
            gap: 10px; /* Adjust the gap between language options */
            align-items: center;
            margin-right: 20px; /* Adjust this value as needed for the desired spacing */
        }

        [dir="rtl"] .language-switcher {
            margin-left: 20px; /* Spacing in RTL (Arabic) */
        }


        .language-switcher .nav-link {
            padding: 5px 10px;
            color: #88878d !important;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s, background-color 0.2s;
            border-radius: 5px;
        }

        .language-switcher .nav-link:hover {
            color: #7367f0 !important; /* Adjust hover color */
            background-color: #f0f0f0; /* Adjust hover background */
        }

        .language-switcher .nav-link.active {
            font-weight: bold;
            background-color: #7367f0 ; /* Adjust active background color */
            color: white !important; /* Adjust active text color */
            border-radius: 7px;
            box-shadow: 0 0 10px 1px rgba(115, 103, 240, 0.7);
        }

        .datatable_img {
            border: 1px solid #dcdcdc;
            padding: 1px;
            border-radius: 100%;
            width: 55px;
            height: 55px;
            object-fit: contain;
        }
        .dataTables_processing.card {
            /*position: relative;*/
            background: none;
            border: none;
            box-shadow: none;
            text-align: center;
            font-size: 0; /* إخفاء أي نص داخل العنصر */
            height: 60px; /* ضبط ارتفاع كافي */
        }
        div.dataTables_processing > div:last-child > div {
            background-image: url({{asset('load.png')}});
            background-size: contain;
            background-repeat: no-repeat;
            width: 30px;
            height: 100px;

        }
        .dataTables_processing.card .dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin: 0 5px;
            background-image: url('https://img.icons8.com/?size=256w&id=38895&format=png');
            background-size: contain;
            background-repeat: no-repeat;
            animation: bounce 1.2s infinite ease-in-out both;
            background-color: #7367f0 !important;
        }

        .dataTables_processing.card .dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .dataTables_processing.card .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        .dataTables_processing.card .dot:nth-child(4) {
            animation-delay: 0.6s;
        }

        @keyframes bounce {
            0%, 80%, 100% {
                transform: scale(0.5); /* بداية ونهاية بحجم أصغر */
            }
            40% {
                transform: scale(1); /* توسع في منتصف الحركة */
            }
        }

    </style>

    {{--  <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/css/pages/app-logistics-dashboard.css') }}" />  --}}
    {{--  <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/css/pages/cards-advance.css') }}">  --}}

    <!-- Helpers -->
    <script src="{{ asset('dashboard/assets/vendor/js/helpers.js') }}"></script>

    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    {{--      <script src="{{ asset('dashboard/assets/vendor/js/template-customizer.js') }}"></script>--}}

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('dashboard/assets/js/config.js') }}"></script>
</head>
