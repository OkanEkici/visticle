<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

use App\Tenant\Setting;
use App\Tenant\Settings_Attribute;
use Redirect,Response;
use Cache;
use Log;

class ZalandoApiController extends Controller
{
    //general props
    private $access_token;
    private $client_id;
    private $client_secret;
    private $http_client;
    private $url;

    //zalando props
    private $merchant_id;
    private $username;
    private $bpids;
    private $groups;
    private $scopes;

    //config
    private $standardHeaders;

    public function __construct() {
        $this->url = env('ZALANDO_API_URL', 'https://api-sandbox.merchants.zalando.com');
        $this->http_client = new Client();
    }

    public function getProduct() {
        $this->getOrFetchAccessToken();
    }

    //https://developers.merchants.zalando.com/docs/openapi/authentication.html
    private function getOrFetchAccessToken() {
        $this->access_token = cache('zalToken'.auth()->id());
        if($this->access_token == null) {

            $credentials = Setting::getZalandoClientCredentials();
            $this->client_id = $credentials['client_id'];
            $this->client_secret = $credentials['client_secret'];

            try{
                $res = $this->http_client->request('POST', $this->url.'/auth/token', [
                    'auth' => [$this->client_id, $this->client_secret],
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                        'scope' => 'access_token_only'
                    ]
                ]);
                $data = json_decode($res->getBody());
                $status = $res->getStatusCode();
                dd($data);
                $ttl = $data->expires_in;
                $token = $data->access_token;
                $this->access_token = Cache::remember('zalToken'.auth()->id(), $ttl, function() use ($token) {
                    return $token;
                });
            }
            catch(GuzzleException $e) {
                Log::error($e->getMessage());
            }
        }
        $this->standardHeaders = [
            'Authorization' => 'Bearer '.$this->access_token,
            'Accept' => 'application/json',
        ];
    }

    //https://developers.merchants.zalando.com/docs/openapi/authentication.html
    private function getCustomerInfos() {
        $this->getOrFetchAccessToken();
        if($this->merchant_id != null) {
            return;
        }
        try {
            $res = $this->http_client->request('GET', $this->url.'/auth/me', [
                'headers' => $this->standardHeaders
            ]);
            $data = json_decode($res->getBody());
            $status = $res->getStatusCode();

            $this->groups = $data->groups;
            $this->scopes = $data->scopes;
            $this->username = $data->username;
            $this->bpids = $data->bpids;
            $this->merchant_id = $data->user_id;

        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/products.html
    public function associateProduct($ean) {
        //TODO: Provide local SKUs for product
        $this->getCustomerInfos();
        $apiPath = '/merchants/'.$this->merchant_id.'/products/identifier/'.$ean;
        try{
            $res = $this->http_client->request(
                'PUT', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/products.html
    public function getProductDetails($ean) {
        $this->getCustomerInfos();
        $apiPath = 'products/identifiers/'.$ean;
        try{
            $res = $this->http_client->request(
                'GET', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/product-attributes.html
    public function getOutlines() {
        $this->getCustomerInfos();
        $apiPath = '/merchants/'.$this->merchant_id.'/outlines';
        try{
            $res = $this->http_client->request(
                'GET', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/product-attributes.html
    public function getOutlineByLabel($outline_label) {
        $this->getCustomerInfos();
        $apiPath = '/merchants/'.$this->merchant_id.'/outlines/'.$outline_label;
        try{
            $res = $this->http_client->request(
                'GET', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/product-attributes.html
    public function getTypeByLabel($type_label) {
        $this->getCustomerInfos();
        $apiPath = '/merchants/'.$this->merchant_id.'/attribute-types/'.$type_label;
        try{
            $res = $this->http_client->request(
                'GET', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/product-attributes.html
    public function getAttributesForType($type_label) {
        $this->getCustomerInfos();
        $apiPath = '/merchants/'.$this->merchant_id.'/attribute-types/'.$type_label.'/attributes';
        try{
            $res = $this->http_client->request(
                'GET', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    //https://developers.merchants.zalando.com/docs/openapi/product-attributes.html
    public function getAttributeByLabelForType($type_label, $attribute_label) {
        $this->getCustomerInfos();
        $apiPath = '/merchants/'.$this->merchant_id.'/attribute-types/'.$type_label.'/attributes/'.$attribute_label;
        try{
            $res = $this->http_client->request(
                'GET', 
                $this->url.$apiPath, 
                [
                    'headers' => $this->standardHeaders
                ]
            );
            return json_decode($res->getBody());
        }
        catch(GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }
    }
    
}
