<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/suggestions', function (Request $request) {
//    return "lo que sea";
    return $request;
});
Route::post('/suggestions', [\App\Http\Controllers\SuggestionController::class, 'store']);
