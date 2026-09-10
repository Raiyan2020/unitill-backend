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

        // If we're handling this request at all, the OS did NOT hand the tap
        // straight to the app (not installed, or Universal/App Link verification
        // isn't active) — so send a real phone straight to the app/store before
        // rendering anything, instead of flashing the web preview first. Link-
        // preview crawlers (WhatsApp, iMessage, social cards) must still get the
        // HTML with its Open Graph tags, so they're excluded below.
        if ($redirect = $this->appOrStoreRedirect($request)) {
            return $redirect;
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

    private function appOrStoreRedirect(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        $ua = (string) $request->userAgent();

        if ($ua === '' || preg_match('/bot|crawl|spider|facebookexternalhit|whatsapp|telegrambot|slackbot|discordbot|linkedinbot|twitterbot|applebot|googlebot|bingbot|pinterest|skypeuripreview|embedly|quora link preview|vkshare|w3c_validator/i', $ua)) {
            return null;
        }

        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return redirect()->away(config('app_stores.ios_url'));
        }

        if (preg_match('/Android/i', $ua)) {
            // One shot at opening the already-installed app to this exact ad via
            // its own /ads/* intent filter, before giving up and going to the
            // Play Store — works even if App Link domain verification hasn't
            // kicked in yet for this install.
            $intentUrl = 'intent://'.$request->getHost().$request->getRequestUri()
                .'#Intent;scheme=https;package='.config('app_stores.android_package')
                .';S.browser_fallback_url='.rawurlencode(config('app_stores.android_url')).';end';

            return redirect()->away($intentUrl);
        }

        return null;
    }
}
