<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleTranslateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TranslateController extends Controller
{
    public function __construct(protected GoogleTranslateService $translator) {}

    /**
     * Translates exactly the text the client sent — a listing description, a
     * chat message, whatever the user tapped "Translate" on. Never automatic,
     * never bulk; the caller decides what gets sent here.
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:5000',
            'target' => ['required', 'string', Rule::in(['en', 'ar', 'es', 'fr', 'zh'])],
            'source' => ['sometimes', 'nullable', 'string', Rule::in(['en', 'ar', 'es', 'fr', 'zh'])],
        ]);

        if (! $this->translator->isConfigured()) {
            return sendError('Translation is not available right now.', [], 503);
        }

        $result = $this->translator->translate(
            $validated['text'],
            $validated['target'],
            $validated['source'] ?? null
        );

        return sendResponse([
            'translated_text' => $result['translated_text'],
            'source_language' => $result['detected_source_language'],
            'target_language' => $validated['target'],
        ], 'Translated');
    }
}
