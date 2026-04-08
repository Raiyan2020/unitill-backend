
<x-datatable :dataTable="$dataTable" :title="__('general.Schools')">
    <x-slot:header>
        <a href="{{ route('admin.cities.create') }}" type="button" class="btn btn-primary waves-effect waves-light">{{__('dataTable.add')}}</a>
    </x-slot:header>
    <x-slot:script>
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

        <script>
            $(document).ready(function () {

                let table = $('#datatable').DataTable();

                function enableSortable() {
                    $('#datatable tbody').sortable({
                        handle: '.handle',
                        helper: function(e, tr){
                            var $originals = tr.children();
                            var $helper = tr.clone();
                            $helper.children().each(function(index){
                                $(this).width($originals.eq(index).width());
                            });
                            return $helper;
                        },
                        update: function () {
                            let order = {};

                            $('#datatable tbody tr').each(function(index) {
                                let id = $(this).attr('id').replace('city-', '');
                                order[id] = index + 1;
                            });

                            $.ajax({
                                url: "{{ route('admin.cities.sort') }}",
                                method: "POST",
                                data: {
                                    order: order,
                                    _token: "{{ csrf_token() }}"
                                }
                            });
                        }
                    });
                }

                // تفعيل أول مرة
                enableSortable();

                // إعادة التفعيل بعد كل redraw
                table.on('draw', function () {
                    enableSortable();
                });

            });
        </script>
        <script>
            $(document).on('change', '.sort-input', function () {
                let id = $(this).data('id');
                let sort = $(this).val();

                $.post("{{ route('admin.cities.updateSort') }}", {
                    id: id,
                    sort: sort,
                    _token: "{{ csrf_token() }}"
                });
            });
        </script>

    </x-slot:script>
</x-datatable>
