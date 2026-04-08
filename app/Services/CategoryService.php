<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Language;
use App\Repositories\CategoryRepository;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    use ImageTrait;

    protected $repo;

    public function __construct(CategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function create(Request $request): Category
    {
        $isSub = $request->filled('parent_id');

        if (! $isSub && ! $request->hasFile('image')) {
            throw ValidationException::withMessages([
                'image' => [__('validation.required', ['attribute' => 'image'])],
            ]);
        }

        $data = [
            'parent_id' => $request->parent_id,
            'status' => $request->status,
            'filter_group_id' => $isSub ? null : $request->filter_group_id,
            'sort' => $isSub ? 0 : (Category::whereNull('parent_id')->max('sort') + 1),
        ];

        if (! $isSub && $request->hasFile('image')) {
            $data['image'] = $this->uploadImage('admin', $request->image);
        }

        if ($isSub) {
            $data['image'] = null;
        }

        $category = $this->repo->create($data);
        $this->syncTranslations($category, $request);

        return $category;
    }

    public function update(Category $category, Request $request)
    {
        $data = ['status' => $request->status];

        if ($category->parent_id) {
            $this->repo->update($category, $data);
            $this->syncTranslations($category, $request);

            return $category;
        }

        $data['filter_group_id'] = $request->filter_group_id;
        if ($request->hasFile('image')) {
            $data['image'] = $this->uploadImage('admin', $request->image);
        }

        $this->repo->update($category, $data);
        $this->syncTranslations($category, $request);

        return $category;
    }

    public function delete($id)
    {
        return $this->repo->deleteById($id);
    }

    public function getFiltersByCategory($categoryId, $lang = 'ar')
    {
        $category = $this->repo->getCategoryWithActiveFilters($categoryId);

        if (! $category || ! $category->filterGroup) {
            return null;
        }

        $filters = $category->filterGroup->filters->map(function ($filter) use ($lang) {
            return [
                'id' => $filter->id,
                'name' => $lang == 'en' ? $filter->name_en : $filter->name_ar,
            ];
        });

        return $filters->toArray();
    }

    protected function syncTranslations(Category $category, Request $request): void
    {
        foreach (['ar' => $request->input('name_ar'), 'en' => $request->input('name_en')] as $code => $name) {
            if ($name === null || $name === '') {
                continue;
            }
            $lang = Language::where('code', $code)->first();
            if (! $lang) {
                continue;
            }
            CategoryTranslation::updateOrCreate(
                ['category_id' => $category->id, 'language_id' => $lang->id],
                ['name' => $name]
            );
        }
    }
}
