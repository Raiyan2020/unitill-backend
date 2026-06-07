<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ImageTrait;

    protected function profileQuery()
    {
        return User::query()
            ->with('city.translations')
            ->withCount([
                'ads as published_ads_count' => fn ($query) => $query->where('status', 'published'),
                'ratingsReceived as total_reviews_count',
            ])
            ->withAvg('ratingsReceived as average_rating', 'score');
    }

    public function show($user_id = null)
    {
        if ($user_id) {
            $user = $this->profileQuery()->findOrFail($user_id);
        } else {
            $user = $this->profileQuery()->findOrFail(Auth::id());
        }

        return sendResponse(new UserResource($user));
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';
        $data = [];

        if ($request->has('first_name')) {
            $data['first_name'] = $request->input('first_name');
        }

        if ($request->has('last_name')) {
            $data['last_name'] = $request->input('last_name');
        }

        if ($request->has('first_name') || $request->has('last_name')) {
            $data['name'] = trim(
                ($data['first_name'] ?? $user->first_name).' '.($data['last_name'] ?? $user->last_name)
            );
        }

        foreach (['phone', 'email', 'city_id'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($user->image);
            $data['image'] = uploader($request->file('image'), 'users');
        }

        $user->update($data);

        return sendResponse(
            new UserResource(
                $this->profileQuery()->findOrFail($user->id)
            ),
            $lang ? 'تم تحديث الملف الشخصي بنجاح' : 'Profile updated successfully.'
        );
    }

    protected function deleteStoredImage(?string $path): void
    {
        if (! $path) {
            return;
        }

        $stored = ltrim(str_replace('/storage/', '', $path), '/');

        if ($stored !== '') {
            Storage::disk('public')->delete($stored);
        }
    }

    //changePassword
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'old_password' => ['required'],
            'new_password' => ['required', 'min:6', 'confirmed'], // تستخدم new_password_confirmation تلقائياً
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first());
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return sendError('Old password is incorrect');
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return sendResponse(new UserResource($user), 'Password changed successfully.');
    }
    //destroy
    public function destroy()
    {
        $user = Auth::user();
        //posts delete
        $user->posts()->delete();
        $user->delete();
        return sendResponse(new UserResource($user));
    }

    //notificationSwitch
    public function notificationSwitch(Request $request)
    {
        $user = Auth::user();
        $user->notification_switch = $request->status ?? true;
        $user->save();

        return sendResponse(new UserResource($user));
    }

}
