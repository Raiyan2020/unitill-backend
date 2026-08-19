<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $parentId = $request->input('parent_id');
        $languages = Language::query()->active()->ordered()->get(['id', 'code']);

        $query = Category::query()->with('translations')->orderByDesc('id');

        if ($parentId === null || $parentId === '') {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', (int) $parentId);
        }

        if ($search !== '') {
            $query->whereHas('translations', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $rows = $query->paginate($perPage)->through(function (Category $row) use ($languages) {
            $translations = [];
            foreach ($languages as $language) {
                $translations[$language->code] = $row->nameForLanguageCode($language->code);
            }

            return [
                'id' => $row->id,
                'parent_id' => $row->parent_id,
                'status' => $row->status,
                'sort' => (int) $row->sort,
                'image' => $row->image,
                'image_url' => $row->image ? (str_starts_with($row->image, 'http') ? $row->image : asset('storage/' . ltrim($row->image, '/'))) : null,
                'listing_fee' => $row->listing_fee !== null ? (float) $row->listing_fee : null,
                'translations' => $translations,
            ];
        });

        return sendResponse($rows, 'Categories fetched');
    }

    public function store(Request $request)
    {
        $this->normalizeListingFee($request);

        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|integer|exists:categories,id',
            'translations' => 'required|array|min:1',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:5120',
            'listing_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
        }

        $row = Category::create([
            'parent_id' => $data['parent_id'] ?? null,
            'status' => $data['status'],
            'sort' => (int) ($data['sort'] ?? 0),
            'image' => $imagePath,
            'listing_fee' => $data['listing_fee'] ?? null,
        ]);

        $this->upsertTranslations($row, $data['translations']);

        return sendResponse($row->fresh()->load('translations'), 'Category created');
    }

    public function update(Request $request, int $id)
    {
        $row = Category::with('translations')->find($id);
        if (! $row) {
            return sendError('Category not found', [], 404);
        }

        $this->normalizeListingFee($request);

        $validator = Validator::make($request->all(), [
            'translations' => 'sometimes|required|array|min:1',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'sort' => 'sometimes|nullable|integer|min:0',
            'image' => 'sometimes|nullable|image|max:5120',
            'listing_fee' => 'sometimes|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            if ($row->image && Storage::disk('public')->exists($row->image)) {
                Storage::disk('public')->delete($row->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $row->update($data);

        if (array_key_exists('translations', $data)) {
            $this->upsertTranslations($row, $data['translations']);
        }

        return sendResponse($row->fresh()->load('translations'), 'Category updated');
    }

    public function destroy(int $id)
    {
        $row = Category::find($id);
        if (! $row) {
            return sendError('Category not found', [], 404);
        }

        if ($row->image && Storage::disk('public')->exists($row->image)) {
            Storage::disk('public')->delete($row->image);
        }

        $row->delete();
        return sendResponse([], 'Category deleted');
    }

    public function image(int $id)
    {
        $row = Category::find($id);
        if (! $row || ! $row->image) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($row->image)) {
            abort(404);
        }

        $path = Storage::disk('public')->path($row->image);
        return response()->file($path);
    }

    private function normalizeListingFee(Request $request): void
    {
        if ($request->input('listing_fee') === '') {
            $request->merge(['listing_fee' => null]);
        }
    }

    private function upsertTranslations(Category $row, array $translations): void
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
