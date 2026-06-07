<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRatingRequest;
use App\Http\Resources\UserRatingResource;
use App\Models\User;
use App\Models\UserRating;
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

        return sendResponse(
            new UserRatingResource($rating),
            $lang ? 'تم إرسال التقييم بنجاح' : 'Rating submitted successfully'
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
                    $request->header('lang') === 'ar' ? 'يجب تسجيل الدخول' : 'Authentication required',
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
