<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __invoke(Request $request)
    {

        $data['post_price'] = setting('post_price');
        $data['post_duration'] = setting('post_duration');
        $data['terms_conditions'] = $request->header('lang') == 'en' ? setting('terms_conditions_en') : setting('terms_conditions');
        $data['contact_email'] = setting('contact_email');
        $data['contact_phone'] = setting('contact_phone');

        $data['popup_image'] = asset(setting('login_popup_image'))  ;
        $data['enable_popup_image'] = setting('enable_login_popup_image')  == '0'  ? false : true;

        return sendResponse($data);


    }
}
