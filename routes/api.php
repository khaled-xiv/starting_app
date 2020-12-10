<?php

use Illuminate\Support\Facades\Route;


Route::group([
    'namespace' => 'App\Http\Controllers',
    'prefix' => 'auth/user'
], function () {

    Route::post('login', 'User\UserAuthController@login');
    Route::post('signup', 'User\UserAuthController@signUp');
    Route::get('me', 'User\UserAuthController@me');
    Route::post('logout', 'User\UserAuthController@logout');
    Route::get('account/{token}', 'User\UserAuthController@verifyAccount');
    Route::post('recover', 'User\UserAuthController@recover');
    Route::post('check', 'User\UserAuthController@check');
    Route::post('reset', 'User\UserAuthController@reset');

});
