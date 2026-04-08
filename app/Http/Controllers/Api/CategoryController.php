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
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->with([
                'translations',
                'children' => function ($q) {
                    $q->where('status', 'active')->with('translations')->orderBy('id');
                },
            ])
            ->orderBy('sort')
            ->get();

        return sendResponse(CategoryResource::collection($categories));
    }
}
