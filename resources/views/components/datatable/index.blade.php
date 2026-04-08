@props([
    'title',
    'dataTable'
])

@extends('dashboard.layouts.master')
@section('title', $title)
@section('css')
    <link rel="stylesheet" href="{{ asset('dashboard/assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

@endsection
@section('content')


    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">


                <div class="card-body">
                    {{ $header ?? '' }}
                    <div class="card-datatable table-responsive pt-0">

                    {{$dataTable->table()}}

                    </div>

                </div>
            </div>
        </div>
    {{ $content ?? '' }}


@endsection
@section('js')
    <!-- DATA TABLE JS -->
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/datatables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/responsive.bootstrap4.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/datatables.checkboxes.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/datatables.buttons.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/jszip.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/pdfmake.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/vfs_fonts.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/buttons.print.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/tables/datatable/dataTables.rowGroup.min.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>

    <!-- END: Page Vendor JS-->

    <!-- BEGIN: Theme JS-->
    <script src="{{ asset('dashboard/app-assets/js/core/app-menu.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/js/core/app.js') }}"></script>
    <!-- END: Theme JS-->

    <!-- BEGIN: Page JS-->
    <script src="{{ asset('dashboard/app-assets/js/scripts/tables/table-datatables-basic.js') }}"></script>
    <script src="{{ asset('dashboard/app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>


    {{$dataTable->scripts()}}

    <script>
        $(document).on('click', '.delete-btn', function () {
            let url = $(this).data('url');
            Swal.fire({
                title: "{{__('dataTable.Delete Operation')}}",
                text: "{{__('dataTable.will not able to revert')}}",
                type: "{{__('dataTable.warning')}}",
                showCancelButton: true,
                confirmButtonColor: "#ff0000",
                cancelButtonColor: "#898a8c",
                confirmButtonText: "{{__('dataTable.Delete')}}",
                confirmButtonClass: 'btn btn-danger',
                cancelButtonClass: 'btn bg-secondary bg-lighten-2 text-white ml-1',
                buttonsStyling: false,
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url,
                        type: 'delete',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success(response) {
                            console.log(response)
                            Swal.fire({
                                title: "{{__('dataTable.success')}}",
                                text: "{{__('dataTable.deleted successfully')}}",
                                type: "success",
                                confirmButtonClass: 'btn btn-success',
                            });
                            $('#datatable').DataTable().row($(this).parents('tr')).remove().draw();
                        },
                        error(error) {
                            console.log(error)
                            Swal.fire({
                                type: "error",
                                title: "{{__('dataTable.oops')}}",
                                text: "{{__('dataTable.something wrong')}}"
                            });
                        }
                    })
                }
            });
        })

    </script>

    <script>
        function updateStatus(id, route) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
            });

            $.ajax({
                url: route.replace(':id', id),
                type: 'GET',
                success: function(response) {
                    $('#datatable').DataTable().ajax.reload();
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });
        }

        // Click event handler for the icon
        function st(id, route, type) {
            var $icon;
            var currentStatus;

            if (type === 'status') {
                $icon = $('#icon-status-' + id);
                currentStatus = $icon.attr('src');
            } else {
                $icon = $('#icon-contact-' + id);
                currentStatus = $icon.attr('src');
            }

            // Fade out the current icon
            $icon.fadeOut(200, function() {
                // Determine the new icon source based on current status
                var newStatus;
                if (type === 'status') {
                    newStatus = currentStatus === "{{ asset('dashboard/icon/tick.png') }}" ? "{{ asset('dashboard/icon/error.png') }}" : "{{ asset('dashboard/icon/tick.png') }}";
                } else {
                    newStatus = currentStatus === "{{ asset('dashboard/icon/tick.png') }}" ? "{{ asset('dashboard/icon/error.png') }}" : "{{ asset('dashboard/icon/tick.png') }}";
                }

                // Change the icon source
                $icon.attr('src', newStatus);

                // Fade in the new icon
                $icon.fadeIn(200);
            });

            updateStatus(id, route);
        }
    </script>


    {{ $script ?? '' }}

@stop
