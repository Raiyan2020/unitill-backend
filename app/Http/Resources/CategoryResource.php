<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'ar');

        $data = [
            'id' => $this->id,
            'name' => $this->nameForLanguageCode($lang),
            'status' => $this->status,
        ];

        if ($this->parent_id === null) {
            $data['image'] = $this->image ? asset($this->image) : null;
            $data['children'] = CategoryResource::collection($this->whenLoaded('children'));
        }

        return $data;
    }
}
