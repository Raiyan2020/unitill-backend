<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminSettingController extends Controller
{
    public function index()
    {
        $rows = Setting::query()
            ->orderBy('set_group')
            ->orderBy('id')
            ->get([
                'id',
                'key_id',
                'title_en',
                'title_ar',
                'value',
                'set_group',
                'is_object',
            ]);

        return sendResponse($rows, 'Settings fetched');
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.key_id' => 'required|string|exists:settings,key_id',
            'items.*.value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $items = collect($validator->validated()['items'])
            ->unique('key_id')
            ->values();

        foreach ($items as $item) {
            Setting::query()
                ->where('key_id', $item['key_id'])
                ->update(['value' => $item['value']]);
        }

        $fresh = Setting::query()
            ->whereIn('key_id', $items->pluck('key_id')->all())
            ->get([
                'id',
                'key_id',
                'title_en',
                'title_ar',
                'value',
                'set_group',
                'is_object',
            ]);

        return sendResponse($fresh, 'Settings updated');
    }
}

