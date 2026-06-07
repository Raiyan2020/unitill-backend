<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //__invoke
    public function __invoke(Request $request)
    {
        $query = Category::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->with('translations')
            ->orderBy('sort');

        if (! $request->boolean('parents_only')) {
            $query->with([
                'children' => function ($q) {
                    $q->where('status', 'active')->with('translations')->orderBy('id');
                },
            ]);
        }

        $categories = $query->get();

        return sendResponse(CategoryResource::collection($categories));
    }
}
