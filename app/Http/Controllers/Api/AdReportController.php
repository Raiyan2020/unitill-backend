<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdReportRequest;
use App\Models\Ad;
use App\Models\AdReport;
use App\Support\AdReportReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdReportController extends Controller
{
    public function reasons(Request $request)
    {
        return sendResponse(
            AdReportReason::options($request->header('lang', 'en'))
        );
    }

    public function store(StoreAdReportRequest $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();

        $ad = Ad::query()
            ->published()
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('public_id', $id);
            })
            ->first();

        if (! $ad) {
            return sendError(
                $lang ? 'الإعلان غير موجود' : 'Ad not found',
                [],
                404
            );
        }

        if ($ad->user_id === $user->id) {
            return sendError(
                $lang ? 'لا يمكنك الإبلاغ عن إعلانك' : 'You cannot report your own ad',
                [],
                422
            );
        }

        if (AdReport::query()->where('user_id', $user->id)->where('ad_id', $ad->id)->exists()) {
            return sendError(
                $lang ? 'لقد أبلغت عن هذا الإعلان مسبقاً' : 'You have already reported this ad',
                [],
                422
            );
        }

        $report = AdReport::create([
            'user_id' => $user->id,
            'ad_id' => $ad->id,
            'reason' => $request->input('reason'),
            'comment' => $request->input('comment'),
            'status' => 'pending',
        ]);

        return sendResponse([
            'id' => $report->id,
            'ad_id' => $ad->id,
            'reason' => $report->reason,
            'status' => $report->status,
        ], $lang ? 'تم إرسال البلاغ بنجاح' : 'Report submitted successfully');
    }
}
