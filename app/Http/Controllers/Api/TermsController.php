<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TermsAcceptanceService;
use Illuminate\Http\Request;

class TermsController extends Controller
{
    public function __construct(private readonly TermsAcceptanceService $terms) {}

    public function current(Request $request)
    {
        $terms = $this->terms->current();
        $ar = $request->header('lang') === 'ar';
        // This endpoint stays public, but an optional Sanctum bearer token must
        // still be resolved explicitly because the route has no auth middleware.
        $user = $request->user('sanctum');

        return sendResponse([
            'version' => $terms->version,
            'title' => $ar ? ($terms->title_ar ?: $terms->title_en) : $terms->title_en,
            'content' => $ar ? ($terms->content_ar ?: $terms->content_en) : $terms->content_en,
            'effective_at' => $terms->effective_at->toIso8601String(),
            'accepted' => $user
                ? $user->termsAcceptances()->where('terms_version_id', $terms->id)->exists()
                : null,
        ]);
    }

    public function accept(Request $request)
    {
        $data = $request->validate([
            'terms_version' => ['required', 'string', 'max:50'],
            'accepted' => ['required', 'accepted'],
        ]);

        $acceptance = $this->terms->accept($request->user(), $request, $data['terms_version']);

        return sendResponse([
            'version' => $acceptance->termsVersion->version,
            'accepted_at' => $acceptance->accepted_at->toIso8601String(),
        ], 'Terms accepted');
    }

    public function history(Request $request)
    {
        return sendResponse($request->user()->termsAcceptances()
            ->with('termsVersion:id,version,effective_at')
            ->latest('accepted_at')
            ->get()
            ->map(fn ($row) => [
                'version' => $row->termsVersion->version,
                'effective_at' => $row->termsVersion->effective_at?->toIso8601String(),
                'accepted_at' => $row->accepted_at?->toIso8601String(),
                'source' => $row->source,
            ]));
    }
}
