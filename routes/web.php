<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('', "App\Http\Controllers\ManageTheme@index");

Route::get('id/{id}', "App\Http\Controllers\ManageTheme@single")->where('id', '[A-Za-z0-9]+'); 

Route::post('', "App\Http\Controllers\ManageTheme@update");