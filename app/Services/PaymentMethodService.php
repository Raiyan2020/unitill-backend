<?php
namespace App\Services;

use App\Repositories\PaymentMethodRepository;
use App\Traits\ImageTrait;
use Illuminate\Support\Facades\Storage;

class PaymentMethodService
{
    use ImageTrait;

    protected $paymentMethodRepo;

    public function __construct(PaymentMethodRepository $paymentMethodRepo)
    {
        $this->paymentMethodRepo = $paymentMethodRepo;
    }

    public function createPaymentMethod(array $data, $image = null)
    {
        if ($image) {
            $data['image'] = $this->uploadImage('admin', $image);
        }

        $data['name_ar'] = $data['name_ar'] ?? $data['name_en'];

        return $this->paymentMethodRepo->create($data);
    }

    public function updatePaymentMethod($paymentMethod, array $data, $image = null)
    {
        if ($image) {
            $data['image'] = $this->uploadImage('admin', $image);
        }

        $data['name_ar'] = $data['name_ar'] ?? $data['name_en'];

        return $this->paymentMethodRepo->update($paymentMethod, $data);
    }

    public function deletePaymentMethod($paymentMethod)
    {
        if ($paymentMethod->image) {
            Storage::disk('public')->delete($paymentMethod->image);
        }
        return $this->paymentMethodRepo->delete($paymentMethod);
    }
}
