<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));

        $languages = Language::query()->active()->ordered()->get(['id', 'code']);

        $query = Country::query()->with('translations')->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('country_code', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $countries = $query->paginate($perPage)->through(function (Country $country) use ($languages) {
            $translations = [];
            foreach ($languages as $language) {
                $translations[$language->code] = $country->nameForLanguageCode($language->code);
            }

            return [
                'id' => $country->id,
                'country_code' => $country->country_code,
                'status' => $country->status,
                'sort' => (int) $country->sort,
                'name_ar' => $country->nameForLanguageCode('ar'),
                'name_en' => $country->nameForLanguageCode('en'),
                'translations' => $translations,
            ];
        });

        return sendResponse($countries, 'Countries fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string|size:2|unique:countries,country_code',
            'translations' => 'required|array|min:1',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        $country = Country::create([
            'country_code' => strtoupper($data['country_code']),
            'status' => $data['status'],
            'sort' => $data['sort'] ?? 0,
        ]);

        $this->upsertTranslations($country, $data['translations']);

        return sendResponse($country->fresh()->load('translations'), 'Country created');
    }

    public function update(Request $request, int $id)
    {
        $country = Country::with('translations')->find($id);

        if (! $country) {
            return sendError('Country not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'country_code' => ['sometimes', 'required', 'string', 'size:2', Rule::unique('countries', 'country_code')->ignore($id)],
            'translations' => 'sometimes|required|array|min:1',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'sort' => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if (isset($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        $country->update($data);

        if (array_key_exists('translations', $data)) {
            $this->upsertTranslations($country, $data['translations']);
        }

        return sendResponse($country->fresh()->load('translations'), 'Country updated');
    }

    public function destroy(int $id)
    {
        $country = Country::find($id);

        if (! $country) {
            return sendError('Country not found', [], 404);
        }

        $country->delete();

        return sendResponse([], 'Country deleted');
    }

    private function upsertTranslations(Country $country, array $translations): void
    {
        $languages = Language::query()->active()->get(['id', 'code']);

        foreach ($languages as $language) {
            $value = trim((string) ($translations[$language->code] ?? ''));
            if ($value === '') {
                continue;
            }

            $country->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['name' => $value]
            );
        }
    }
}
