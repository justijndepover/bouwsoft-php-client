<?php

namespace JustijnDepover\BouwsoftPhpClient\Http\Controllers;

use App\Http\Controllers\Controller;

class AuthenticationController extends Controller
{
    /**
     * Connect Bouwsoft app
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function appConnect() {
        $connection = app()->make('Bouwsoft\Connection');
        return redirect('/');
    }
}
