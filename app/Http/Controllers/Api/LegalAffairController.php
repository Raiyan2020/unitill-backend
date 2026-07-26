<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LegalAffair;
use Illuminate\Http\Request;

class LegalAffairController extends Controller
{
    /**
     * Public list of active legal affairs (policies), localized by the `lang` header.
     */
    public function index(Request $request)
    {
        // English is the default when no lang header is sent. Defaulting to
        // Arabic instead silently flips the policy text for every client that
        // omits the header, including the web dashboard.
        $code = $request->header('lang') === 'ar' ? 'ar' : 'en';
        $language = Language::where('code', $code)->first();
        $fallback = $code === 'en' ? null : Language::where('code', 'en')->first();

        $items = LegalAffair::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(function (LegalAffair $affair) use ($language, $fallback) {
    
                $translation = $language
                    ? $affair->translations->firstWhere('language_id', $language->id)
                    : null;

                $translation ??= $fallback
                    ? $affair->translations->firstWhere('language_id', $fallback->id)
                    : null;
                $translation ??= $affair->translations->first();

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
