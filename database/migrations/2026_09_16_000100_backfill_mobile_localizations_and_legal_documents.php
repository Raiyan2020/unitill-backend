<?php

use Database\Seeders\LegalAffairSeeder;
use Database\Seeders\MultilingualContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Production data backfill. Deliberately idempotent and non-destructive:
     * deployment requires only `php artisan migrate --force`; no seeder runs.
     */
    public function up(): void
    {
        $this->ensureLanguages();
        $languages = DB::table('languages')->pluck('id', 'code');

        $this->backfillCategoryTranslations($languages->all());
        $this->backfillAttributeTranslations($languages->all());

        // Uses the canonical, reviewed legal copy but only its safe upsert path;
        // unlike LegalAffairSeeder::run(), this never truncates production data.
        (new LegalAffairSeeder)->syncRecords(['terms_of_service', 'privacy_policy']);
    }

    public function down(): void
    {
        // Translation and legal-content upserts are intentionally retained.
        // Removing them could replace readable content with the old Arabic
        // fallback for Spanish/Chinese users and is not a safe rollback.
    }

    private function ensureLanguages(): void
    {
        $languages = [
            'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'sort_order' => 1],
            'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'sort_order' => 2],
            'fr' => ['name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'sort_order' => 3],
            'es' => ['name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'sort_order' => 4],
            'zh' => ['name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr', 'sort_order' => 5],
        ];

        foreach ($languages as $code => $values) {
            if (DB::table('languages')->where('code', $code)->exists()) {
                DB::table('languages')->where('code', $code)->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('languages')->insert($values + [
                'code' => $code,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, int> $languages */
    private function backfillCategoryTranslations(array $languages): void
    {
        $englishId = $languages['en'] ?? null;
        if (! $englishId) {
            return;
        }

        foreach (MultilingualContentSeeder::CATEGORY_NAMES as $englishName => $translations) {
            $categoryIds = DB::table('category_translations')
                ->where('language_id', $englishId)
                ->where('name', $englishName)
                ->pluck('category_id');

            foreach ($categoryIds as $categoryId) {
                foreach ($translations as $code => $name) {
                    $languageId = $languages[$code] ?? null;
                    if (! $languageId) {
                        continue;
                    }

                    DB::table('category_translations')->updateOrInsert(
                        ['category_id' => $categoryId, 'language_id' => $languageId],
                        ['name' => $name, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
        }
    }

    /** @param array<string, int> $languages */
    private function backfillAttributeTranslations(array $languages): void
    {
        $englishId = $languages['en'] ?? null;
        if (! $englishId) {
            return;
        }

        $definitions = DB::table('category_attribute_definitions')->get();

        foreach ($definitions as $definition) {
            $categoryEnglishName = DB::table('category_translations')
                ->where('category_id', $definition->category_id)
                ->where('language_id', $englishId)
                ->value('name');
            $labels = MultilingualContentSeeder::ATTRIBUTE_LABELS[$categoryEnglishName.'.'.$definition->slug]
                ?? MultilingualContentSeeder::ATTRIBUTE_LABELS[$definition->slug]
                ?? [];
            $values = $this->optionValues($definition->options);

            foreach (['en', 'ar', 'fr', 'es', 'zh'] as $code) {
                $languageId = $languages[$code] ?? null;
                if (! $languageId) {
                    continue;
                }

                $existing = DB::table('category_attribute_definition_translations')
                    ->where('category_attribute_definition_id', $definition->id)
                    ->where('language_id', $languageId)
                    ->first();
                $label = $labels[$code] ?? $existing?->label;

                if (! $label && $code === 'en') {
                    $label = Str::ucfirst(str_replace('_', ' ', $definition->slug));
                }
                if (! $label) {
                    continue;
                }

                $options = $this->optionMap($values, $code);
                DB::table('category_attribute_definition_translations')->updateOrInsert(
                    [
                        'category_attribute_definition_id' => $definition->id,
                        'language_id' => $languageId,
                    ],
                    [
                        'label' => $label,
                        'options' => $options === null
                            ? ($existing?->options ?? null)
                            : json_encode($options, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                        'created_at' => $existing?->created_at ?? now(),
                    ]
                );
            }
        }
    }

    /** @return array<int, string> */
    private function optionValues(mixed $storedOptions): array
    {
        $options = is_string($storedOptions) ? json_decode($storedOptions, true) : $storedOptions;

        return collect(is_array($options) ? $options : [])
            ->map(fn ($option) => is_array($option)
                ? (string) ($option['value'] ?? $option['label'] ?? '')
                : (string) $option)
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<int, string> $values
     * @return array<string, string>|null
     */
    private function optionMap(array $values, string $code): ?array
    {
        if ($values === []) {
            return null;
        }

        $map = [];
        foreach ($values as $value) {
            $label = $code === 'en'
                ? $value
                : (MultilingualContentSeeder::OPTION_LABELS[$value][$code] ?? null);
            if ($label !== null) {
                $map[$value] = $label;
            }
        }

        return $map ?: null;
    }
};
