<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['namespace' => 'Api', 'as' => 'api.'], function () {
    Route::name('login')->post('login', 'AuthController@login');
    Route::name('refresh')->post('refresh', 'AuthController@refresh');
    Route::name('logout')->post('logout', 'AuthController@logout')->middleware(['auth:api']);
    Route::name('acount')->post('acount', 'UserController@store');
    Route::get('getClient/{value}', 'ClientController@getClient');
    Route::get('getProduct/{value}', 'ProductController@getProduct');
    Route::get('getOpportunityClient/{value}', 'OpportunityController@getOpportunityClient');
    Route::group(['middleware' => 'auth:api'], function () {
        Route::name('me')->get('me', 'AuthController@me');
        Route::get('document/{document}', 'ClientController@document');
        Route::resource('users', 'UserController', ['except' => ['create', 'edit']]);
        Route::resource('products', 'ProductController', ['except' => ['create', 'edit']]);
        Route::resource('clients', 'ClientController', ['except' => ['create', 'edit']]);
        Route::resource('opportunities', 'OpportunityController', ['except' => ['create', 'edit']]);
        Route::resource('timelines', 'TimelineController', ['except' => ['create', 'edit']]);
        Route::resource('items', 'ProductOpportunityController', ['except' => ['create', 'edit']]);
    });
});
