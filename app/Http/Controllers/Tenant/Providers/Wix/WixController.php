<?php

namespace App\Http\Controllers\Tenant\Providers\Wix;

use App\Helpers\Miscellaneous;
use App\Http\Controllers\Controller;
use App\Tenant\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Stream\Stream;
use Illuminate\Support\Facades\Log;
use App\Tenant\TenantUser;
use Illuminate\Http\JsonResponse;


class WixController extends Controller
{

    /**
     * Der WixController wird immer mit einem Kunden Instanziiert.
     * Sollte der Benutzer keine Wix-Credentials haben und oder keine Applikationscredentials
     * global in der Konfigurationsdatei für Wix hinterlegt sein, so wirft das Programm eine
     * Exception.
     */

    /**
     * Hier folgen die Credentials
     */
    //User-Credentials
    protected $instance_id=null;
    //Global-Credentials
    protected $app_id=null;
    protected $app_secret_key=null;

    //Access und Refresh Token initial auf Null setzen
    protected $access_token=null;
    protected $refresh_token=null;
    protected $access_token_timeout=null;

    protected $client=null;

    public function __construct()
    {
        $this->checkLoadGlobalCredentials();
        $this->checkLoadUserCredentials();

        $this->client=new Client();

    }
    /**
     * Jetzt kommen alle API-Aufruf-Methoden an die Reihe
     */
    public function test(){
        $this->checkLoadAccessToken();

        //var_dump([$this->access_token,$this->refresh_token]); Auskommentiert

        $this->useAccessToken();
    }

    public function newWixOrdertoVisticle(Request $request){
        $content=$request->getContent();

        $parts=explode('.',$content);
        $jwt_encoded=$parts[1];

        $jwt_decoded=base64_decode($jwt_encoded);

        $data=json_decode($jwt_decoded);

        $data=json_decode($data->data);




        //echo Auth::guard('wix')->user()->tenant->subdomain;
        Log::channel('wix')->info('#######################');

        Log::channel("wix")->info($data->number);

        echo "schmoock shit";
        Log::channel('wix')->info('#######################');


        return new JsonResponse(null,200);
    }
    /**
     * Die folgenden zwei Methoden sollten immer in jeder Methode aufgerufen werden,
     * die sich an der Api bedient.
     * Zu Beginn einer Methode muss die Methode "checkLoadAccessToken" aufgerufen werden.
     * Diese Methode lädt entweder den AccessToken initial oder erfrischt diesen über
     * ein bestehendes Refresh-Token.
     * Die Methode "useAccessToken" muss am Ende einer Methode aufgerufen werden.
     * Sie nullt einfach den Access-Token, da er mit jedem Aufruf an die API aufgebraucht wird.
     */
    protected function checkLoadAccessToken()
    {

        $load_token=false;
        //ist ein Token gesetzt?
        if(!$this->access_token){
            $load_token=true;
        }
        elseif($this->access_token && !$this->access_token_timeout){
            $time=time();
            if(!$this->checkTimeout($this->access_token_timeout,$time)){
                $load_token=true;
            }
        }


        if($load_token && $this->refresh_token){
            //Hier benutzen wir die Methode über den Refresh für den Access-Token
            $url=config('wix.access_token.refresh_token_url');
            echo 'INitial';
            $data=[
                'headers'=>['content-type'=>'application/json','user-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0'],
                'body'=>json_encode([
                    'grant_type'=>'refresh_token',
                    'client_id'=>$this->app_id,
                    'client_secret'=>$this->app_secret_key,
                    'refresh_token'=>$this->refresh_token,
                ])
            ];
            $req=$this->client->post($url,$data);
            $status = $req->getStatusCode();
            $BodyClose = $req->getBody()->getContents();
            $data = json_decode($BodyClose);
        }

        //Refresh-Access-Token setzen und TimeOut
        $this->access_token_timeout=time();
        $this->access_token=$data->access_token;
        $this->refresh_token=$data->refresh_token;

        //Jetzt speichern wir den Refresh-Token in die Datenbank!
        $data_to_save=[
            'instance_id'=>$this->instance_id,
            'refresh_token'=>$this->refresh_token,
        ];
        Setting::setWixCredentials($data_to_save);
    }
    protected function useAccessToken(){
        $this->access_token=null;
    }
    public function getDataFromWixShop($url,$body = "",$method='get'){
        $this->checkLoadAccessToken();
        try {
            $header=[
                'content-type'=>'application/json',
                'user-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0',
                'Authorization'=>$this->access_token,
                ];

            $data=[
                'headers'=>$header,
                'body'=>json_encode($body),
            ];


            /*
            $req = $client->request("post",$url_authorization,
                    ['json'=> $body,'verify' => true]);
                    */
            $req=$this->client->request($method,$url,$data);
            //$req=$client->post($url_authorization,['json'=> $body,'debug' => true]);

            $status = $req->getStatusCode();
            $BodyClose = $req->getBody()->getContents();


            //$BodyClose->close();
            if($status != 200)
            {
                Log::info("Der Versuch, die Authorisierung bei Wix abzuschliessen mit der URL:
                            {$url_authorization} hat folgenden Status-Code zurückgeliefert: {$status}");

                $this->useAccessToken();
                return null;
            }
            $data = json_decode($BodyClose);

            //Und weiter geht es nach dem Try-Catch-Block

        } catch(GuzzleException $e) {
            $failed = true;
            switch($e->getCode()) {
                case 404: return "Artikel nicht gefunden"; break;
                case 401:
                    Log::info("Folgende Meldung und Statuscode für den Authorisierungvorgang bei Wix \n
                              {$e->getCode()} - {$e->getMessage()}");
                break;
                case 429:
                case 500:
                    Log::info("Folgende Meldung und Statuscode für den Authorisierungvorgang bei Wix \n
                    {$e->getCode()} - {$e->getMessage()}");
                    echo $e->getMessage();
                    Log::error($e->getMessage());
                break;
                default:
                    echo $e->getMessage();
                    Log::error($e->getMessage());
                break;
            }
            $this->useAccessToken();
           return null;
        }
        catch(\Exception $e){
            echo $e->getMessage();
                    Log::error($e->getMessage());
            $this->useAccessToken();
            return $e->getMessage();

        }

        $this->useAccessToken();
        return $data;
    }
    public function sendDataToWixShop($url,$body = null,$method='post'){
        $this->checkLoadAccessToken();
        try {
            $header=[
                'Authorization'=> $this->access_token,
                'content-type'=>'application/json',
                'user-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0'
        ];

            //Header ergänzen
            $data=[
                'headers'=>$header,
                'body'=>json_encode($body),
            ];

            $req=$this->client->request($method,$url,$data);
            //$req=$client->post($url_authorization,['json'=> $body,'debug' => true]);

            $status = $req->getStatusCode();
            $BodyClose = $req->getBody()->getContents();


            //$BodyClose->close();
            if($status != 200)
            {
                Log::info("Der Versuch, die Authorisierung bei Wix abzuschliessen mit der URL:
                            {$url_authorization} hat folgenden Status-Code zurückgeliefert: {$status}");

                $this->useAccessToken();
                return null;
            }
            $data = json_decode($BodyClose);

            //Und weiter geht es nach dem Try-Catch-Block

        } catch(GuzzleException $e) {
            $failed = true;
            switch($e->getCode()) {
                case 401:
                    Log::info("Folgende Meldung und Statuscode für den Authorisierungvorgang bei Wix \n
                              {$e->getCode()} - {$e->getMessage()}");
                break;
                case 429:
                case 500:
                    Log::info("Folgende Meldung und Statuscode für den Authorisierungvorgang bei Wix \n
                    {$e->getCode()} - {$e->getMessage()}");
                    echo $e->getMessage();
                    Log::error($e->getMessage());
                break;
                default:
                    echo $e->getMessage();
                    Log::error($e->getMessage());
                break;
            }
            $this->useAccessToken();
           return null;
        }
        catch(\Exception $e){
            echo $e->getMessage();
                    Log::error($e->getMessage());
            $this->useAccessToken();
            return null;

        }
        $this->useAccessToken();
        return $data;
    }
    /**
     * Gibt True zurück, wenn nicht abgelaufen, ansonsten false
     */
    protected function checkTimeout($time_old,$time_new){
        $valid_time=config('wix.access_token.valid_time');
        $valid_time_seconds=$valid_time*60;

        if(($time_new-$time_old)>=$valid_time_seconds){
            return false;
        }
        else{
            return true;
        }
    }
    /**
     * Lädt die globalen Credentials für Wix.
     * Ist nichts hinterlegt, wird eine Exception ausgeworfen.
     */
    protected function checkLoadGlobalCredentials(){
        if(!config('wix.app_id')|| !config('wix.app_secret_key')){
            throw new \Exception('Es sind keine globalen Wix-Credentials gesetzt.');
        }
        $this->app_id=config('wix.app_id');
        $this->app_secret_key=config('wix.app_secret_key');
    }
    protected function checkLoadUserCredentials(){
        $credentials=Setting::getWixCredentials();
        if(!$credentials){
            throw new \Exception('Benutzer hat keine Wix-Credentials');
        }
        $this->refresh_token=$credentials['refresh_token'];
        $this->instance_id=$credentials['instance_id'];
    }
}
