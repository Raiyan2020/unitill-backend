    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->

    <script src="{{ asset('dashboard/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/js/menu.js') }}"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{ asset('dashboard/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    @yield('vendor-js')
{{--    --}}{{--  <script src="{{ asset('dashboard/assets/vendor/libs/select2/select2.js') }}"></script>  --}}
{{--    --}}{{--  <script src="{{ asset('dashboard/assets/vendor/libs/tagify/tagify.js') }}"></script>  --}}
{{--    --}}{{--  <script src="{{ asset('dashboard/assets/vendor/libs/bootstrap-select/bootstrap-select.js') }}"></script>  --}}
{{--    --}}{{--  <script src="{{ asset('dashboard/assets/vendor/libs/moment/moment.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/@form-validation/popular.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/@form-validation/bootstrap5.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/@form-validation/auto-focus.js') }}"></script>--}}

{{--    <script src="{{ asset('dashboard/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>--}}
{{--    <script src="{{ asset('dashboard/assets/vendor/libs/sortablejs/sortable.js') }}"></script>--}}

{{--    <script src="{{ asset('dashboard/assets/vendor/libs/bloodhound/bloodhound.js') }}"></script>--}}
{{--     --}}
        <!-- Flat Picker -->
    <script src="{{ asset('dashboard/assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('dashboard/assets/vendor/libs/chartjs/chartjs.js') }}"></script>
    <!-- Main JS -->
    <script src="{{ asset('dashboard/assets/js/main.js') }}"></script>

    <script src="{{ asset('dashboard/assets/js/extended-ui-drag-and-drop.js') }}"></script>


    <!-- Page JS -->
    <script src="{{ asset('dashboard/assets/js/ui-popover.js') }}"></script>
{{--    <script src="{{ asset('dashboard/assets/js/charts-chartjs.js') }}"></script>--}}
    @yield('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.dropdown-user .nav-link').addEventListener('click', function(event) {
                event.preventDefault();
                const dropdownMenu = this.nextElementSibling;
                dropdownMenu.classList.toggle('show');
            });
        });

    </script>


