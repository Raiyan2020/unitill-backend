<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\RedirectResponse;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class LanguageController extends Controller
{
    public function switch($lang): RedirectResponse
    {
        LaravelLocalization::setLocale($lang);
//        session(['locale' => $lang]);
        // إعادة توجيه المستخدم إلى الصفحة السابقة
        return redirect()->back();

    }
}
