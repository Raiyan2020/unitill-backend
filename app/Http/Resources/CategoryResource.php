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
            // Images are uploaded to the Laravel `public` disk
            // (storage/app/public/categories) and served through the
            // `public/storage` symlink at /storage/... — so the URL must carry
            // the `storage/` prefix. Absolute URLs are passed through as-is.
            $data['image'] = $this->image
                ? (str_starts_with($this->image, 'http')
                    ? $this->image
                    : asset('storage/' . ltrim($this->image, '/')))
                : null;
            $data['children'] = CategoryResource::collection($this->whenLoaded('children'));

            // Per-category attribute definitions power the post-ad dynamic
            // fields and the browse filters (same shape as the ads-list
            // `filter_options`). Only main categories carry them.
            $data['attributes'] = $this->whenLoaded('attributeDefinitions', function () use ($lang) {
                return $this->attributeDefinitions
                    ->map(fn ($definition) => [
                        'slug' => $definition->slug,
                        'label' => $definition->labelForLanguageCode($lang),
                        'input_type' => $definition->input_type,
                        'options' => $definition->options ?? [],
                        'is_required' => (bool) $definition->is_required,
                    ])
                    ->values()
                    ->all();
            });
        }

        return $data;
    }
}
