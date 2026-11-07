<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductShareController;
use Illuminate\Http\Request;
use App\Support\FrontendUrl;

Route::get('/share/products/{id}', [ProductShareController::class, 'show'])
    ->name('products.share');

$redirectToPasswordReset = function (Request $request) {
    $query = array_filter(
        $request->only(['token', 'email', 'role']),
        static fn ($value) => is_string($value) && $value !== ''
    );
    $queryString = http_build_query($query);

    return redirect()->away(
        FrontendUrl::base() . '/password/reset' . ($queryString === '' ? '' : '?' . $queryString)
    );
};

Route::get('/password-reset', $redirectToPasswordReset)
    ->name('password.reset');

Route::get('/public/password-reset', $redirectToPasswordReset);

Route::get('/', function () {
    return view('welcome');
});
