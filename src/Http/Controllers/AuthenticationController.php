<?php

namespace JustijnDepover\BouwsoftPhpClient\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    /**
     * Connect Bouwsoft app
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function appConnect() {
        $config = Auth::user();
        $config->bouwsoft_requestId = null;
        $config->bouwsoft_clientNr = null;
        $config->bouwsoft_refreshToken = null;
        $config->bouwsoft_accessToken = null;
        $config->bouwsoft_tokenExpires = null;
        $config->bouwsoft_serverUrl = null;
        $config->save();

        $connection = app()->make('Bouwsoft\Connection');
        return redirect('/');
    }
}
