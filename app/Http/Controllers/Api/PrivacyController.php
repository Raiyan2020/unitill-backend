<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LegalDocumentService;
use App\Services\TermsAcceptanceService;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function __invoke(
        Request $request,
        LegalDocumentService $documents,
        TermsAcceptanceService $terms
    ) {
        $document = $documents->get('privacy_policy', $request->header('lang'));
        if (! $document) {
            return sendError('Privacy policy is not available.', [], 404);
        }

        $currentTerms = $terms->current();

        return sendResponse([
            'version' => $currentTerms->version,
            'locale' => $document['locale'],
            'title' => $document['title'],
            'content' => $document['content'],
            'effective_at' => $document['effective_at'] ?? $currentTerms->effective_at->toIso8601String(),
            'accepted' => null,
        ]);
    }
}
