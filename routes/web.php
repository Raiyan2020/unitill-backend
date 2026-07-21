<?php


use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Laravel\Telescope\Telescope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


//test
Route::get('/test', function () {
    return view('test');
});
// Route::group(
//     ['prefix' => LaravelLocalization::setLocale(), 'middleware' => ['localize']], // يمكن أن يكون middleware مختلف حسب إعداداتك
//     function () {
//         Route::get('/', function () {
//             // Redirect the project root to the React (Vite) dashboard dev server.
//             return redirect()->away(env('FRONTEND_URL', 'http://localhost:5173'));
//         });

//     }
//     //send email test


// );

Route::group(
    ['prefix' => LaravelLocalization::setLocale(), 'middleware' => ['localize']],
    function () {
        Route::get('/{any?}', function () {
          
            $path = public_path('dist/index.html');
            if (File::exists($path)) {
                return File::get($path);
            }
            abort(404, 'Frontend build not found.');
        })->where('any', '.*');
    }
);


Route::get('email', function () {
    try {
        Mail::to('aalshy00@gmail.com')->send(new OtpMail('1234'));
        return response()->json(['ok' => true, 'message' => 'sent']);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

