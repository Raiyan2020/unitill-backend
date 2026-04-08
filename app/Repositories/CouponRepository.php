<?php

namespace App\Repositories;

use App\Models\Coupon;

class CouponRepository
{
    public function create(array $data): Coupon
    {
        return Coupon::create($data);
    }

    public function update(Coupon $coupon, array $data): bool
    {
        return $coupon->update($data);
    }

    public function deleteById(int|string $id): bool
    {
        return (bool) Coupon::destroy($id);
    }

    public function findById(int|string $id): ?Coupon
    {
        return Coupon::find($id);
    }
}
