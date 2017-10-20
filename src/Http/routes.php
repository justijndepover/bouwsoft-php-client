<?php

Route::group([ 'prefix' => 'bouwsoft', 'middleware' => ['web','auth'] ], function() {
    Route::get('connect', ['as' => 'bouwsoft.connect', 'uses' => 'JustijnDepover\BouwsoftPhpClient\Http\Controllers\AuthenticationController@appConnect']);
});