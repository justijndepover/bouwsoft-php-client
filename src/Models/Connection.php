<?php

namespace JustijnDepover\BouwsoftPhpClient\Models;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use Illuminate\Support\Facades\Auth;

/**
 * Class Connection
 *
 * @package JustijnDepover\BouwsoftPhpClient
 *
 */
class Connection
{
    /**
     * @var string
     */
    private $baseUrl = 'https://Charon.bouwsoft.be';

    /**
     * @var string
     */
    private $apiUrl = '/api/v1';

    /**
     * @var string
     */
    private $tokenUrl = '/Authorize/RefreshToken';

    /**
     * @var string
     */
    private $refreshUrl = '/Authorize/AccessToken';

    /**
     * @var string
     */
    private $serverUrl;

    /**
     * @var
     */
    private $clientNr;

    /**
     * @var
     */
    private $requestId;

    /**
     * @var
     */
    private $refreshToken;

    /**
     * @var
     */
    private $accessToken;

    /**
     * @var
     */
    private $tokenExpires;

    /**
     * @var
     */
    private $appKey;

    /**
     * @var Client
     */
    private $client;

    /**
     *
     */
    protected $middleWares = [];

    /**
     * @return Client
     */
    private function client()
    {
        if ($this->client) {
            return $this->client;
        }

        $handlerStack = HandlerStack::create();
        foreach ($this->middleWares as $middleWare) {
            $handlerStack->push($middleWare);
        }

        $this->client = new Client([
            'http_errors' => true,
            'handler' => $handlerStack,
        ]);

        return $this->client;
    }

    public function insertMiddleWare($middleWare)
    {
        $this->middleWares[] = $middleWare;
    }

    /**
     * @return object
     */
    public function connect()
    {
        $client = $this->client();

        // Redirect for authorization if needed (no access token or refresh token given)
        if ($this->needsAuthentication()) {
            $this->redirectForAuthorization();
        }

        // If refresh token is not set or token has expired, acquire new token
        if (empty($this->refreshToken)) {
            $this->acquireRefreshToken();
        }

        if($this->tokenHasExpired()) {
            $this->acquireAccessToken();
        }

        return $client;
    }

    /**
     * @return bool
     */
    public function needsAuthentication()
    {
        return empty($this->requestId);
    }

    /**
     * @param $url
     * @param array $params
     * @return mixed
     * @throws ApiException
     */
    public function redirectForAuthorization()
    {
        $url = $this->getAuthUrl();

        try {
            $headers = ['appkey' => $this->appKey];
            $request = new Request('GET', $url, $headers, null);

            $response = $this->client()->send($request);
            $return = $this->parseResponse($response);

            $this->setRequestId($return['RequestId']);

            $user = Auth::user();
            $user->bouwsoft_requestId = $return['RequestId'];
            $user->save();

            header('Location: ' . $return['RequestURL']);
            exit;

        } catch (Exception $e) {
            throw new Exception($e);

            $this->parseExceptionForErrorMessages($e);
        }
    }

    /**
     * @return bool
     */
    private function tokenHasExpired()
    {
        if (empty($this->tokenExpires)) {
            return true;
        }

        return $this->tokenExpires <= time();
    }

    /**
     * @return void
     */
    private function acquireRefreshToken()
    {
        $url = $this->getAuthUrl();

        $headers = [
            'appkey' => $this->appKey,
            'requestid' => $this->requestId
        ];

        $request = new Request('GET', $url, $headers, null);

        try {
            $response = $this->client()->send($request);

            $return = $this->parseResponse($response);

            $this->setClientNr($return['ClientNr']);
            $this->setRefreshToken($return['RefreshToken']);
            $this->setAccessToken($return['AccessToken']);
            $this->setServerUrl($return['ServerName']);
            $date = \DateTime::createFromFormat('Y-m-d\TH:i\Z', $return['ValidUntil']);
            $this->setTokenExpires($date->getTimestamp());
        } catch (Exception $e) {
            // the request id was not authorized
            $this->requestId = '';
            $user = Auth::user();
            $user->bouwsoft_requestId = '';
            $user->save();
            $this->redirectForAuthorization();
        }
    }

    private function acquireAccessToken()
    {
        $url = $this->getRefreshUrl();

        $headers = [
            'clientnr' => $this->clientNr,
            'refreshtoken' => $this->refreshToken
        ];

        $request = new Request('GET', $url, $headers, null);

        $response = $this->client()->send($request);

        $return = $this->parseResponse($response);

        $this->setAccessToken($return['AccessToken']);
        $this->setServerUrl($return['ServerName']);
        $date = \DateTime::createFromFormat('Y-m-d\TH:i\Z', $return['ValidUntil']);
        $this->setTokenExpires($date->getTimestamp());
    }

    /**
     * @param Response $response
     * @param bool $returnSingleIfPossible
     * @return mixed
     * @throws ApiException
     */
    private function parseResponse(Response $response)
    {
        try {

            if ($response->getStatusCode() === 204) {
                return [];
            }

            Psr7\rewind_body($response);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json;
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * @return string
     */
    public function getAuthUrl()
    {
        return $this->baseUrl . $this->apiUrl . $this->tokenUrl;
    }

    /**
     * @return string
     */
    public function getRefreshUrl()
    {
        return $this->baseUrl . $this->apiUrl . $this->refreshUrl;
    }

    /**
     * @param string $method
     * @param $endpoint
     * @param null $body
     * @param array $params
     * @param array $headers
     * @return Request
     */
    private function createRequest($method = 'GET', $endpoint, $body = null, array $params = [], array $headers = [])
    {
        // Add default json headers to the request
        $headers = array_merge($headers, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation'
        ]);

        // If refresh token is not set or token has expired, acquire new token
        if (empty($this->refreshToken)) {
            $this->acquireRefreshToken();
        }
        if($this->tokenHasExpired()) {
            $this->acquireAccessToken();
        }

        // Create param string
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        // Create the request
        $request = new Request($method, $endpoint, $headers, $body);

        return $request;
    }

    /**
     * Parse the reponse in the Exception to return the Exact error messages
     * @param Exception $e
     * @throws ApiException
     */
    private function parseExceptionForErrorMessages(Exception $e)
    {
        if (! $e instanceof BadResponseException) {
            throw new ApiException($e->getMessage());
        }

        $response = $e->getResponse();
        Psr7\rewind_body($response);
        $responseBody = $response->getBody()->getContents();
        $decodedResponseBody = json_decode($responseBody, true);

        if (! is_null($decodedResponseBody) && isset($decodedResponseBody['error']['message']['value'])) {
            $errorMessage = $decodedResponseBody['error']['message']['value'];
        } else {
            $errorMessage = $responseBody;
        }

        throw new ApiException('Error ' . $response->getStatusCode() .': ' . $errorMessage);
    }

    /**
     * @param $url
     * @param array $params
     * @return mixed
     * @throws ApiException
     */
    public function get($url, $params = [])
    {
        $url = ((strpos($this->serverUrl, 'https://') !== false)?'':'https://') . $this->serverUrl . $this->apiUrl . '/' . $url;

        $headers = [
            'Clientnr' => $this->clientNr,
            'AccessToken' => $this->accessToken
        ];

        try {
            $request = $this->createRequest('GET', $url, null, $params, $headers);
            // $request = new Request('GET', $url, $headers, null);
            $response = $this->client()->send($request);

            return $this->parseResponse($response);
        } catch (Exception $e) {
            // if($e->getResponse()->getReasonPhrase() == 'UNAUTHORIZED') {
            //     $user = Auth::user();
            //
            //     $user->bouwsoft_clientNr = '';
            //     $user->bouwsoft_requestId = '';
            //     $user->bouwsoft_accessToken = '';
            //     $user->bouwsoft_refreshToken = '';
            //     $user->bouwsoft_tokenExpires = '';
            //     $user->bouwsoft_serverUrl = '';
            //
            //     $user->save();
            //
            //     return redirect('/');
            // }

            throw new Exception("Error Processing Request: " . $e->getMessage());

            $this->parseExceptionForErrorMessages($e);
        }
    }

    /**
     * @param $url
     * @param array $params
     * @return mixed
     * @throws ApiException
     */
    public function put($url, $params = [])
    {
        $url = ((strpos($this->serverUrl, 'https://') !== false)?'':'https://') . $this->serverUrl . $this->apiUrl . '/' . $url;

        $headers = [
            'Clientnr' => $this->clientNr,
            'AccessToken' => $this->accessToken
        ];

        try {
            $request = $this->createRequest('PUT', $url, null, $params, $headers);
            // $request = new Request('GET', $url, $headers, null);
            $response = $this->client()->send($request);

            return $this->parseResponse($response);
        } catch (Exception $e) {
            // if($e->getResponse()->getReasonPhrase() == 'UNAUTHORIZED') {
            //     $user = Auth::user();
            //
            //     $user->bouwsoft_clientNr = '';
            //     $user->bouwsoft_requestId = '';
            //     $user->bouwsoft_accessToken = '';
            //     $user->bouwsoft_refreshToken = '';
            //     $user->bouwsoft_tokenExpires = '';
            //     $user->bouwsoft_serverUrl = '';
            //
            //     $user->save();
            //
            //     return redirect('/');
            // }

            throw new Exception("Error Processing Request: " . $e->getMessage());

            $this->parseExceptionForErrorMessages($e);
        }
    }

    /* GETTERS */

    /**
     * @return mixed
     */
    public function getClientNr()
    {
        return $this->clientNr;
    }

    /**
     * @return mixed
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return mixed
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @return mixed
     */
    public function getTokenExpires()
    {
        return $this->tokenExpires;
    }

    /**
     * @return mixed
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /**
     * @return mixed
     */
    public function getServerUrl()
    {
        return $this->serverUrl;
    }

    /* SETTERS */

    /**
     * @return mixed $clientNr
     */
    public function setClientNr($clientNr)
    {
        $this->clientNr = $clientNr;
    }

    /**
     * @return mixed $clientNr
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * @return mixed $refreshToken
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    /**
     * @return mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return mixed $tokenExpires
     */
    public function setTokenExpires($tokenExpires)
    {
        $this->tokenExpires = $tokenExpires;
    }

    /**
     * @return mixed $appKey
     */
    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }

    /**
     * @return mixed $serverUrl
     */
    public function setServerUrl($serverUrl)
    {
        $this->serverUrl = $serverUrl;
    }
}