<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $search = trim((string) $request->input('search', ''));

        $query = Language::query()->ordered();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('native_name', 'like', "%{$search}%");
            });
        }

        return sendResponse($query->paginate($perPage), 'Languages fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:255',
            'native_name' => 'required|string|max:255',
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $data['code'] = strtolower($data['code']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($data['is_default']) {
            Language::query()->update(['is_default' => false]);
        }

        $language = Language::create($data);

        return sendResponse($language, 'Language created');
    }

    public function update(Request $request, int $id)
    {
        $language = Language::find($id);
        if (! $language) {
            return sendError('Language not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'required', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($id)],
            'name' => 'sometimes|required|string|max:255',
            'native_name' => 'sometimes|required|string|max:255',
            'direction' => ['sometimes', 'required', Rule::in(['ltr', 'rtl'])],
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        if (isset($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }

        if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
            Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
        }

        $language->update($data);

        return sendResponse($language->fresh(), 'Language updated');
    }

    public function destroy(int $id)
    {
        $language = Language::find($id);
        if (! $language) {
            return sendError('Language not found', [], 404);
        }

        if ($language->is_default) {
            return sendError('Default language cannot be deleted', [], 422);
        }

        $language->delete();
        return sendResponse([], 'Language deleted');
    }
}
