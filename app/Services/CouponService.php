<?php

namespace App\Services;

use App\Repositories\CouponRepository;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponService
{
    use ImageTrait;

    protected $repo;

    public function __construct(CouponRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * إنشاء كوبون
     */
    public function create(Request $request): Coupon
    {
        $data = $request->validated();

        // لو عندك صورة للكوبون (اختياري)
        if ($request->has('image')) {
            $data['image'] = $this->uploadImage('admin', $request->image);
        }

        return $this->repo->create($data);
    }

    /**
     * تحديث كوبون
     */
    public function update(Coupon $coupon, Request $request)
    {
        $data = $request->validated();


        return $this->repo->update($coupon, $data);
    }

    /**
     * حذف كوبون
     */
    public function delete($id)
    {
        return $this->repo->deleteById($id);
    }

    /**
     * تفعيل / تعطيل الكوبون
     */
    public function updateStatus(int $id, int $status)
    {
        $coupon = $this->repo->findById($id);
        return $this->repo->update($coupon, [
            'status' => $status
        ]);
    }
}
