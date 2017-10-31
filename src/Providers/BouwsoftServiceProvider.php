<?php

namespace JustijnDepover\BouwsoftPhpClient\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use JustijnDepover\BouwsoftPhpClient\Models\Connection;

class BouwsoftServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');

        $this->loadViewsFrom(__DIR__.'/../views', 'bouwsoftphpclient');

        $this->publishes([
            __DIR__.'/../views' => base_path('resources/views/vendor/bouwsoft'),
            // __DIR__.'/../config/bouwsoft.php' => config_path('bouwsoft.php')
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Bouwsoft\Connection', function() {

            $config = Auth::user();

            $connection = new Connection();
            $connection->setAppKey(env('BOUWSOFT_APP_KEY', ''));

            if(isset($config->bouwsoft_requestId)) {
                $connection->setRequestId($config->bouwsoft_requestId);
            }
            if(isset($config->bouwsoft_clientNr)) {
                $connection->setClientNr($config->bouwsoft_clientNr);
            }
            if(isset($config->bouwsoft_refreshToken)) {
                $connection->setRefreshToken(decrypt($config->bouwsoft_refreshToken));
            }
            if(isset($config->bouwsoft_accessToken)) {
                $connection->setAccessToken($config->bouwsoft_accessToken);
            }
            if(isset($config->bouwsoft_tokenExpires)) {
                $connection->setTokenExpires($config->bouwsoft_tokenExpires);
            }
            if(isset($config->bouwsoft_serverUrl)) {
                $connection->setServerUrl($config->bouwsoft_serverUrl);
            }


            try {

                 $connection->connect();

            } catch (\Exception $e) {
                throw new \Exception('Could not connect to Bouwsoft: ' . $e->getMessage());
            }

            $config->bouwsoft_requestId = $connection->getRequestId();
            $config->bouwsoft_clientNr = $connection->getClientNr();
            $config->bouwsoft_accessToken = $connection->getAccessToken();
            $config->bouwsoft_refreshToken = encrypt($connection->getRefreshToken());
            $config->bouwsoft_tokenExpires = $connection->getTokenExpires();
            $config->bouwsoft_serverUrl = $connection->getServerUrl();
            $config->save();

            return $connection;
        });
    }
}
