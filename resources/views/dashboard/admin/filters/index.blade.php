
<x-datatable :dataTable="$dataTable" :title="__('general.Filters')">
    <x-slot:header>
        <a href="{{ route('admin.filters.create') }}" type="button" class="btn btn-primary waves-effect waves-light">{{__('dataTable.add')}}</a>
    </x-slot:header>
</x-datatable>
