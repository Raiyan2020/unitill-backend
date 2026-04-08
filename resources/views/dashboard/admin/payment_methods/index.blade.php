
<x-datatable :dataTable="$dataTable" :title="__('payment methods')">
    <x-slot:header>
        <a href="{{ route('admin.payment-methods.create') }}" type="button" class="btn btn-primary waves-effect waves-light">{{__('dataTable.add')}}</a>
    </x-slot:header>
</x-datatable>
