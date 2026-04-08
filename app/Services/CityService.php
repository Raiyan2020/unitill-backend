<?php
namespace App\Services;

use App\Repositories\CityRepository;
use App\Traits\ImageTrait;
use Illuminate\Support\Facades\Storage;

class CityService
{
    use ImageTrait;

    protected $cityRepo;

    public function __construct(CityRepository $cityRepo)
    {
        $this->cityRepo = $cityRepo;
    }

    public function createCity(array $data, $image = null)
    {
        if ($image) {
            $data['image'] = $this->uploadImage('admin', $image);
        }

        $data['name_ar'] = $data['name_ar'] ?? $data['name_en'];

        return $this->cityRepo->create($data);
    }

    public function updateCity($city, array $data, $image = null)
    {
        if ($image) {
            $data['image'] = $this->uploadImage('admin', $image);
        }

        $data['name_ar'] = $data['name_ar'] ?? $data['name_en'];

        return $this->cityRepo->update($city, $data);
    }

    public function deleteCity($city)
    {
        if ($city->image) {
            Storage::disk('public')->delete($city->image);
        }
        return $this->cityRepo->delete($city);
    }
}
