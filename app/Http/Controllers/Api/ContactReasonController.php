<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactReasonResource;
use App\Models\ContactReason;
use Illuminate\Http\Request;

class ContactReasonController extends Controller
{
    public function __invoke(Request $request)
    {
        $reasons = ContactReason::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return sendResponse(ContactReasonResource::collection($reasons));
    }
}
