<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalAffair;
use Illuminate\Http\Request;

class LegalAffairController extends Controller
{
    /**
     * Public list of active legal affairs (policies), localized by the `lang` header.
     */
    public function index(Request $request)
    {
        $code = in_array($request->header('lang'), ['en', 'ar', 'fr', 'es', 'zh'], true)
            ? $request->header('lang')
            : 'en';

        $items = LegalAffair::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(function (LegalAffair $affair) use ($code) {
                $translation = $affair->translationRowFor($code);

                return [
                    'id' => $affair->id,
                    'key' => $affair->key,
                    'section' => $affair->section,
                    'title' => $translation->title ?? '',
                    'subtitle' => $translation->subtitle ?? '',
                    'description' => $translation->description ?? '',
                ];
            })
            ->values();

        return sendResponse($items);
    }
}
