<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function __invoke(Request $request)
    {
        $cities = City::query()
            ->where('status', 'active')
            ->with('translations')
            ->orderBy('sort', 'asc')
            ->get();
        return sendResponse(CityResource::collection($cities));

    }
}
