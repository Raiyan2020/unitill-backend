<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Functions;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ImageTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ImageTrait;

    public function show($user_id = null)
    {
        if ($user_id) {
            $user = User::findOrFail($user_id);
        } else {
            $user = Auth::user();
        }

        return  sendResponse( new UserResource($user));
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();
        $data = $request->only(['first_name', 'last_name', 'phone', 'email','city_id']);
        $data['name'] = $data['first_name'] . ' ' . $data['last_name'];

        $user->update($data);

        return sendResponse(new UserResource($user), 'Profile updated successfully.');
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
