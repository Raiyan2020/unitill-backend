<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageResource;
use App\Models\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function __invoke(Request $request)
    {
        $languages = Language::query()
            ->ordered()
            ->get();

        return sendResponse(LanguageResource::collection($languages));
    }
}
