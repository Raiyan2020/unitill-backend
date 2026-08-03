<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Social profile URLs, surfaced by GET /api/settings as `social_links`.
 * Ordinary settings rows so the dashboard can edit them with no extra screen.
 */
return new class extends Migration
{
    /** key_id => [title_en, title_ar] */
    private const KEYS = [
        'social_facebook' => ['Facebook URL', 'رابط فيسبوك'],
        'social_instagram' => ['Instagram URL', 'رابط إنستغرام'],
        'social_x' => ['X (Twitter) URL', 'رابط إكس (تويتر)'],
        'social_linkedin' => ['LinkedIn URL', 'رابط لينكدإن'],
        'social_tiktok' => ['TikTok URL', 'رابط تيك توك'],
        'social_youtube' => ['YouTube URL', 'رابط يوتيوب'],
    ];

    public function up(): void
    {
        foreach (self::KEYS as $key => [$titleEn, $titleAr]) {
            Setting::query()->firstOrCreate(
                ['key_id' => $key],
                [
                    'title_en' => $titleEn,
                    'title_ar' => $titleAr,
                    'value' => '',
                    'set_group' => 'social',
                    'is_object' => '0',
                ]
            );
        }
    }

    public function down(): void
    {
        Setting::query()->whereIn('key_id', array_keys(self::KEYS))->delete();
    }
};
