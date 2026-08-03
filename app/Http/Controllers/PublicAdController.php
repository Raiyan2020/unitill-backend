<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use Illuminate\Http\Request;

/**
 * The public, no-auth landing page behind every shared ad link
 * ({app.url}/ads/{public_id}), including the Open Graph tags for link previews.
 */
class PublicAdController extends Controller
{
    public function show(Request $request, string $publicId)
    {
        $ad = Ad::query()
            ->where('public_id', $publicId)
            ->with([
                'images',
                'city.translations',
                'mainCategory.translations',
                'subCategory.translations',
                'attributeValues.definition.translations',
                'user',
            ])
            ->first();

        // Same visibility rule as GET /api/ads/{id} for a guest: drafts and
        // pending-payment ads get the 404, never a preview.
        $isVisible = $ad
            && $ad->status === 'published'
            && ! $ad->isExpired();

        if (! $isVisible) {
            return response()->view('public.ad-not-found', [
                'appName' => setting('app_name', 'UniTill'),
            ], 404);
        }

        $lang = $request->query('lang', 'en');

        return response()->view('public.ad', [
            'ad' => $ad,
            'lang' => $lang,
            'appName' => setting('app_name', 'UniTill'),
            'images' => $ad->images
                ->map(fn ($image) => url('/storage/'.ltrim((string) $image->path, '/')))
                ->values()
                ->all(),
            'coverImage' => $ad->coverImageUrl(),
            'categoryPath' => array_values(array_filter([
                $ad->mainCategory?->nameForLanguageCode($lang),
                $ad->subCategory?->nameForLanguageCode($lang),
            ])),
            'attributes' => $ad->attributeValues
                ->groupBy(fn ($row) => $row->definition?->slug)
                ->map(function ($rows) use ($lang) {
                    $definition = $rows->first()->definition;
                    $values = $rows->pluck('value')->filter()->values();

                    return [
                        'label' => $definition?->labelForLanguageCode($lang),
                        'value' => $values
                            ->map(fn ($value) => $definition
                                ? $definition->optionLabelForLanguageCode($lang, (string) $value)
                                : (string) $value)
                            ->implode(', '),
                    ];
                })
                ->filter(fn ($row) => $row['label'] !== null && $row['value'] !== '')
                ->values()
                ->all(),
        ]);
    }
}
