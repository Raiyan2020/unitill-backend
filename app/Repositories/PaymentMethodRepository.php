<?php
namespace App\Repositories;

use App\Models\PaymentMethod;

class PaymentMethodRepository
{
    public function all()
    {
        return PaymentMethod::all();
    }

    public function find($id)
    {
        return PaymentMethod::findOrFail($id);
    }

    public function create(array $data)
    {
        return PaymentMethod::create($data);
    }

    public function update(PaymentMethod $paymentMethod, array $data)
    {
        return $paymentMethod->update($data);
    }

    public function delete(PaymentMethod $paymentMethod)
    {
        return $paymentMethod->delete();
    }
}
