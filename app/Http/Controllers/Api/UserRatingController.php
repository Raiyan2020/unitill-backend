<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRatingRequest;
use App\Http\Resources\UserRatingResource;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRating;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserRatingController extends Controller
{
    public function store(StoreUserRatingRequest $request)
    {
        $lang = $request->header('lang') === 'ar';

        $rating = UserRating::create([
            'rater_id' => Auth::id(),
            'rated_user_id' => $request->input('rated_user_id'),
            'score' => $request->input('score'),
            'comment' => $request->input('comment'),
            'ad_id' => $request->input('ad_id'),
        ]);

        $rating->load('rater:id,first_name,last_name,name');

        // Notify the rated user that they received a new rating (push + inbox).
        $ratedUser = User::find($rating->rated_user_id);
        if ($ratedUser && $ratedUser->id !== Auth::id()) {
            $raterName = trim((string) ($rating->rater->first_name ?? '').' '.($rating->rater->last_name ?? ''))
                ?: ($rating->rater->name ?? '');
            $score = (int) $rating->score;

            app(PushNotificationService::class)->notifyUser(
                $ratedUser,
                __('api.rating.new_title'),
                // NOTE: this resolves in the rater's language, not the recipient's.
                // Users have no stored locale, so the notification is sent in
                // whatever language the sender's app was set to.
                __('api.rating.new_body', ['name' => $raterName, 'score' => $score]),
                [
                    'type' => 'rating',
                    'related_data' => (string) $ratedUser->id,
                    'rating_id' => (string) $rating->id,
                ],
                UserNotification::TYPE_SYSTEM,
                'notify_system',
            );
        }

        return sendResponse(
            new UserRatingResource($rating),
            __('api.rating.submitted')
        );
    }

    public function index(Request $request, $user_id = null)
    {
        if ($user_id) {
            $user = User::findOrFail($user_id);
        } else {
            $user = Auth::user();
            if (! $user) {
                return sendError(
                    __('api.session.auth_required'),
                    [],
                    401
                );
            }
        }

        $perPage = max(1, min((int) $request->input('per_page', 10), 50));

        $ratings = UserRating::query()
            ->where('rated_user_id', $user->id)
            ->with('rater:id,first_name,last_name,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $distributionRows = UserRating::query()
            ->where('rated_user_id', $user->id)
            ->selectRaw('score, COUNT(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');

        $distribution = [];
        for ($stars = 5; $stars >= 1; $stars--) {
            $distribution[] = [
                'stars' => $stars,
                'count' => (int) ($distributionRows[$stars] ?? 0),
            ];
        }

        $totalReviews = (int) $distributionRows->sum();
        $averageRating = $totalReviews > 0
            ? round((float) UserRating::query()->where('rated_user_id', $user->id)->avg('score'), 1)
            : 0.0;

        return sendResponse([
            'user_id' => $user->id,
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'distribution' => $distribution,
            'reviews' => UserRatingResource::collection($ratings)->response()->getData(true),
        ]);
    }
}
