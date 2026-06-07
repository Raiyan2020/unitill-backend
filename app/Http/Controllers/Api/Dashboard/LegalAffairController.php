<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LegalAffair;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LegalAffairController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $languages = Language::query()->active()->ordered()->get(['id', 'code']);

        $query = LegalAffair::query()->with('translations')->orderByDesc('id');

        if ($search !== '') {
            $query->whereHas('translations', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $rows = $query->paginate($perPage)->through(function (LegalAffair $row) use ($languages) {
            $translations = [];
            foreach ($languages as $language) {
                $translation = $row->translations->firstWhere('language_id', $language->id);
                $translations[$language->code] = [
                    'title' => $translation?->title ?? '',
                    'subtitle' => $translation?->subtitle ?? '',
                    'description' => $translation?->description ?? '',
                ];
            }

            return [
                'id' => $row->id,
                'key' => $row->key,
                'section' => $row->section,
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
                'translations' => $translations,
            ];
        });

        return sendResponse($rows, 'Legal affairs fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'nullable|string|max:100|unique:legal_affairs,key',
            'translations' => 'required|array|min:1',
            'section' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        $row = LegalAffair::create([
            'key' => $data['key'] ?? null,
            'section' => $data['section'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->upsertTranslations($row, $data['translations']);

        return sendResponse($row->fresh()->load('translations'), 'Legal affair created');
    }

    public function update(Request $request, int $id)
    {
        $row = LegalAffair::with('translations')->find($id);
        if (! $row) {
            return sendError('Legal affair not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|nullable|string|max:100|unique:legal_affairs,key,'.$id,
            'translations' => 'sometimes|required|array|min:1',
            'section' => 'sometimes|nullable|string|max:100',
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

        return sendResponse($row->fresh()->load('translations'), 'Legal affair updated');
    }

    public function destroy(int $id)
    {
        $row = LegalAffair::find($id);
        if (! $row) {
            return sendError('Legal affair not found', [], 404);
        }

        $row->delete();
        return sendResponse([], 'Legal affair deleted');
    }

    private function upsertTranslations(LegalAffair $row, array $translations): void
    {
        $languages = Language::query()->active()->get(['id', 'code']);

        foreach ($languages as $language) {
            $payload = $translations[$language->code] ?? null;
            $title = trim((string) (is_array($payload) ? ($payload['title'] ?? '') : ''));
            $subtitle = trim((string) (is_array($payload) ? ($payload['subtitle'] ?? '') : ''));
            $description = trim((string) (is_array($payload) ? ($payload['description'] ?? '') : ''));

            if ($title === '' && $subtitle === '' && $description === '') {
                continue;
            }

            $row->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => $title,
                    'subtitle' => $subtitle !== '' ? $subtitle : null,
                    'description' => $description !== '' ? $description : null,
                ]
            );
        }
    }
}
