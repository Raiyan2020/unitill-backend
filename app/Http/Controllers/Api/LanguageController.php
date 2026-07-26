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
        // Only active languages are offered to clients. Dropping this filter
        // exposes languages an admin has deliberately switched off, and the
        // mobile language picker renders whatever it is given.
        $languages = Language::query()
            ->where('is_active', true)
            ->ordered()
            ->get();

        return sendResponse(LanguageResource::collection($languages));
    }
}
