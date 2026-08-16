<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TermsVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TermsVersionAdminController extends Controller
{
    public function index()
    {
        return sendResponse(TermsVersion::withCount('acceptances')->latest('effective_at')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:50', 'unique:terms_versions,version'],
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'content_en' => ['required', 'string'],
            'content_ar' => ['nullable', 'string'],
            'effective_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $terms = DB::transaction(function () use ($data, $request) {
            TermsVersion::where('is_current', true)->lockForUpdate()->get();
            TermsVersion::where('is_current', true)->update(['is_current' => false]);

            return TermsVersion::create($data + [
                'is_current' => true,
                'effective_at' => $data['effective_at'] ?? now(),
                'published_by' => $request->user()?->id,
            ]);
        });

        return sendResponse($terms, 'New terms version published');
    }
}
