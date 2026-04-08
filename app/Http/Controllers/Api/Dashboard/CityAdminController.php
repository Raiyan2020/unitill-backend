<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CityAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $countryId = $request->input('country_id');

        $languages = Language::query()->active()->ordered()->get(['id', 'code']);

        $query = City::query()->with(['translations', 'country.translations'])->orderByDesc('id');

        if (! empty($countryId)) {
            $query->where('country_id', (int) $countryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $cities = $query->paginate($perPage)->through(function (City $city) use ($languages) {
            $translations = [];
            foreach ($languages as $language) {
                $translations[$language->code] = $city->nameForLanguageCode($language->code);
            }

            return [
                'id' => $city->id,
                'name_ar' => $city->nameForLanguageCode('ar'),
                'name_en' => $city->nameForLanguageCode('en'),
                'translations' => $translations,
                'country_id' => $city->country_id,
                'country_name' => $city->country?->nameForLanguageCode('en') ?: '',
                'status' => $city->status,
                'code' => $city->code,
                'sort' => (int) $city->sort,
            ];
        });

        return sendResponse($cities, 'Cities fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'translations' => 'required|array|min:1',
            'country_id' => 'nullable|integer|exists:countries,id',
            'country_code' => 'nullable|string|size:2',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'code' => 'nullable|string|max:50',
            'sort' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if (! empty($data['country_id']) && empty($data['country_code'])) {
            $country = Country::find($data['country_id']);
            $data['country_code'] = $country?->country_code;
        }

        if (! empty($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        $city = City::create([
            'country_id' => $data['country_id'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'status' => $data['status'],
            'code' => $data['code'] ?? null,
            'sort' => $data['sort'] ?? 0,
        ]);

        $this->upsertTranslations($city, $data['translations']);

        return sendResponse($city->fresh()->load('translations'), 'City created');
    }

    public function update(Request $request, int $id)
    {
        $city = City::with('translations')->find($id);

        if (! $city) {
            return sendError('City not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'translations' => 'sometimes|required|array|min:1',
            'country_id' => 'sometimes|nullable|integer|exists:countries,id',
            'country_code' => 'sometimes|nullable|string|size:2',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'code' => 'sometimes|nullable|string|max:50',
            'sort' => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if (array_key_exists('country_id', $data) && ! empty($data['country_id']) && ! array_key_exists('country_code', $data)) {
            $country = Country::find($data['country_id']);
            $data['country_code'] = $country?->country_code;
        }

        if (isset($data['country_code']) && ! empty($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        $city->update($data);

        if (array_key_exists('translations', $data)) {
            $this->upsertTranslations($city, $data['translations']);
        }

        return sendResponse($city->fresh()->load('translations'), 'City updated');
    }

    public function destroy(int $id)
    {
        $city = City::find($id);

        if (! $city) {
            return sendError('City not found', [], 404);
        }

        $city->delete();

        return sendResponse([], 'City deleted');
    }

    private function upsertTranslations(City $city, array $translations): void
    {
        $languages = Language::query()->active()->get(['id', 'code']);

        foreach ($languages as $language) {
            $value = trim((string) ($translations[$language->code] ?? ''));
            if ($value === '') {
                continue;
            }

            $city->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['name' => $value]
            );
        }
    }
}
