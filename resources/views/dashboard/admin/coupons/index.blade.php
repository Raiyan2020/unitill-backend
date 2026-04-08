
<x-datatable :dataTable="$dataTable" :title="__('categories')">
    <x-slot:header>
        <a href="{{ route('admin.coupons.create') }}" type="button" class="btn btn-primary waves-effect waves-light">{{__('dataTable.add')}}</a>
    </x-slot:header>
    <x-slot:script>
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

{{--        <script>--}}
{{--            $(document).on('change', '.sort-input', function () {--}}
{{--                let id = $(this).data('id');--}}
{{--                let sort = $(this).val();--}}

{{--                $.post("{{ route('admin.coupons.updateSort') }}", {--}}
{{--                    id: id,--}}
{{--                    sort: sort,--}}
{{--                    _token: "{{ csrf_token() }}"--}}
{{--                });--}}
{{--            });--}}
{{--        </script>--}}

    </x-slot:script>
</x-datatable>
