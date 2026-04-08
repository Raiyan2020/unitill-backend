<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactReason;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactReasonAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $languages = Language::query()->active()->ordered()->get(['id', 'code']);

        $query = ContactReason::query()->with('translations')->orderByDesc('id');
        if ($search !== '') {
            $query->whereHas('translations', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $rows = $query->paginate($perPage)->through(function (ContactReason $row) use ($languages) {
            $translations = [];
            foreach ($languages as $language) {
                $translations[$language->code] = $row->nameForLanguageCode($language->code);
            }

            return [
                'id' => $row->id,
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
                'translations' => $translations,
            ];
        });

        return sendResponse($rows, 'Contact reasons fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'translations' => 'required|array|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $row = ContactReason::create([
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->upsertTranslations($row, $data['translations']);
        return sendResponse($row->fresh()->load('translations'), 'Contact reason created');
    }

    public function update(Request $request, int $id)
    {
        $row = ContactReason::with('translations')->find($id);
        if (! $row) {
            return sendError('Contact reason not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'translations' => 'sometimes|required|array|min:1',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();
        $row->update($data);

        if (array_key_exists('translations', $data)) {
            $this->upsertTranslations($row, $data['translations']);
        }

        return sendResponse($row->fresh()->load('translations'), 'Contact reason updated');
    }

    public function destroy(int $id)
    {
        $row = ContactReason::find($id);
        if (! $row) {
            return sendError('Contact reason not found', [], 404);
        }

        $row->delete();
        return sendResponse([], 'Contact reason deleted');
    }

    private function upsertTranslations(ContactReason $row, array $translations): void
    {
        $languages = Language::query()->active()->get(['id', 'code']);

        foreach ($languages as $language) {
            $value = trim((string) ($translations[$language->code] ?? ''));
            if ($value === '') {
                continue;
            }

            $row->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['name' => $value]
            );
        }
    }
}
