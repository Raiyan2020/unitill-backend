<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalAffairResource;
use App\Models\Language;
use App\Models\LegalAffair;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __invoke(Request $request)
    {
        $lang = $request->header('lang') === 'en' ? 'en' : 'ar';
        $popupImage = setting('login_popup_image');
        $appLogo = setting('app_logo');

        $policies = LegalAffair::query()
            ->where('is_active', true)
            ->where('section', 'policies')
            ->with(['translations.language'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $data = [
            'app' => [
                'name' => setting('app_name', 'UniTill'),
                'slogan' => setting('app_slogan', 'BUY, SELL, REPEAT!'),
                'logo' => $appLogo ? asset($appLogo) : null,
            ],
            'default_language' => Language::query()
                ->active()
                ->where('is_default', true)
                ->value('code') ?? 'ar',
            'post_price' => setting('post_price'),
            'post_duration' => setting('post_duration', '30'),
            'terms_conditions' => $lang === 'en'
                ? setting('terms_conditions_en')
                : setting('terms_conditions'),
            'policies' => LegalAffairResource::collection($policies),
            'contact_email' => setting('contact_email'),
            'contact_phone' => setting('contact_phone'),
            'popup_image' => $popupImage ? asset($popupImage) : null,
            'enable_popup_image' => setting('enable_login_popup_image') !== '0',
            'pusher' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
            'firebase' => [
                'all_users_topic' => config('fcm.all_users_topic', 'unitill_all'),
            ],
        ];

        return sendResponse($data);
    }
}
