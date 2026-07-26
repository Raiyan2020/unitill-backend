<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdReport;
use App\Support\AdReportReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdReportAdminController extends Controller
{
    private const STATUSES = ['pending', 'reviewed', 'dismissed'];

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $reason = (string) $request->input('reason', '');

        $query = AdReport::query()
            ->with([
                'user:id,name,first_name,last_name,email',
                'ad:id,public_id,title,status,user_id',
            ])
            // Pending first — those are the ones waiting on a decision
            ->orderByRaw("FIELD(status, 'pending', 'reviewed', 'dismissed')")
            ->orderByDesc('id');

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($reason !== '') {
            $query->where('reason', $reason);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('ad', function ($ad) use ($search) {
                        $ad->where('title', 'like', "%{$search}%")
                            ->orWhere('public_id', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(fn (AdReport $report) => $this->present($report));

        return sendResponse([
            'reports' => $rows,
            'counts' => $this->statusCounts(),
            'reasons' => AdReportReason::options($request->header('lang', 'en')),
        ], 'Ad reports fetched');
    }

    public function show(int $id)
    {
        $report = AdReport::with([
            'user:id,name,first_name,last_name,email',
            'ad:id,public_id,title,description,status,price,currency,user_id',
            'ad.user:id,name,first_name,last_name,email',
        ])->find($id);

        if (! $report) {
            return sendError('Report not found', [], 404);
        }

        return sendResponse(
            $this->present($report) + [
                'ad_description' => $report->ad?->description,
                'ad_owner' => $report->ad?->user ? [
                    'id' => $report->ad->user->id,
                    'name' => $this->displayName($report->ad->user),
                    'email' => $report->ad->user->email,
                ] : null,
            ],
            'Report details'
        );
    }

    /**
     * Changes the report status only. Acting on the ad itself (pause/delete)
     * stays in the ads admin routes so deletion logic lives in one place.
     */
    public function update(Request $request, int $id)
    {
        $report = AdReport::find($id);

        if (! $report) {
            return sendError('Report not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $report->update(['status' => $request->input('status')]);

        return sendResponse(
            $this->present($report->fresh(['user', 'ad'])),
            'Report status updated'
        );
    }

    private function statusCounts(): array
    {
        $counts = AdReport::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'reviewed' => (int) ($counts['reviewed'] ?? 0),
            'dismissed' => (int) ($counts['dismissed'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    private function present(AdReport $report): array
    {
        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'reason_label' => AdReportReason::label($report->reason, 'en'),
            'comment' => $report->comment,
            'status' => $report->status,
            'created_at' => optional($report->created_at)->toDateTimeString(),
            'ad' => $report->ad ? [
                'id' => $report->ad->id,
                'public_id' => $report->ad->public_id,
                'title' => $report->ad->title,
                'status' => $report->ad->status,
            ] : null,
            'reporter' => $report->user ? [
                'id' => $report->user->id,
                'name' => $this->displayName($report->user),
                'email' => $report->user->email,
            ] : null,
        ];
    }

    private function displayName($user): string
    {
        $name = trim(implode(' ', array_filter([$user->first_name, $user->last_name])));

        return $name !== '' ? $name : (string) ($user->name ?? $user->email ?? '-');
    }
}
