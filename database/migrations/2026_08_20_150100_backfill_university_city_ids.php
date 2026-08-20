<?php

use App\Models\University;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * One-time backfill: universities.city was always a free-text string
     * ("Oxford", "London", ...), never linked to the cities table. This
     * matches each university to an existing city row by its English
     * translation name so registration can auto-fill a student's city_id
     * from their university, instead of asking them to pick one manually.
     *
     * Matches by name only — whatever is actually in `cities` on this
     * environment, seeded or dashboard-managed. Universities whose city
     * string doesn't match any existing city are left with city_id = null
     * and logged, rather than guessed at; fix those individually via the
     * dashboard once the missing city exists.
     */
    public function up(): void
    {
        $cityIdsByName = DB::table('city_translations')
            ->join('languages', 'languages.id', '=', 'city_translations.language_id')
            ->where('languages.code', 'en')
            ->select('city_translations.city_id', DB::raw('LOWER(TRIM(city_translations.name)) as name_key'))
            ->pluck('city_id', 'name_key');

        $unmatched = [];

        University::query()->whereNotNull('city')->where('city', '!=', '')->chunkById(200, function ($universities) use ($cityIdsByName, &$unmatched) {
            foreach ($universities as $university) {
                $key = strtolower(trim($university->city));
                $cityId = $cityIdsByName[$key] ?? null;

                if ($cityId) {
                    $university->forceFill(['city_id' => $cityId])->save();
                } else {
                    $unmatched[] = "{$university->name} (city: {$university->city})";
                }
            }
        });

        if (! empty($unmatched)) {
            Log::warning('University city backfill: no matching city found for '.count($unmatched).' universities', [
                'universities' => $unmatched,
            ]);
        }
    }

    public function down(): void
    {
        // Not reversible in isolation — city_id may have since been edited
        // directly via the dashboard. The column drop in the paired schema
        // migration is what actually undoes this.
    }
};
