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

class WixInstallController extends Controller
{
    public static function startInstallation(Request $request)
    {
        try{
            //Wir greifen den Inhalt des Tokens ab, der uns
            //von Wix gesandt wurde
            $token=
            $request->query('token');

            //Gibt es einen Wert so leiten wir den Benutzer weiter, ansonsten gibt es eine Fehlermeldung
            if(!empty($token)){
                //Wir holen alle erforderlichen Daten
                $app_id=config('wix.app_id');
                $app_id=urlencode($app_id);
                $redirect_url=config('wix.redirect_url_path');
                $redirect_url=config('app.url') . $redirect_url;
                $redirect_url=urlencode($redirect_url);
                //jetzt erstellen wir unseren Query-String
                $query="token={$token}&appId={$app_id}&redirectUrl={$redirect_url}";

                $wix_install_url=config('wix.wix_install_url') . "?{$query}";

                return redirect()->away($wix_install_url);
            }
            else{
                return 'Da ist wohl was schief gelaufen bei der Installation von unserer Wix-App';
            }
        }
        catch(\Exception $e){
            echo $e->getMessage();
        }

    }
    public static function completeInstallation(Request $request){
        //Jetzt greifen wir die Query Parameter mal ab
        $authorization_code=$request->query('code');
        $instance_id=$request->query('instanceId');


        if(empty($authorization_code) || empty($instance_id)){
            return 'Da ist was schief gelaufen bei der Übermittlung der Authorisierungszusicherung vom Kunden zu uns.';
        }

        //Jetzt holen wir aus der Basic-Authentifikation den Wix Benutzer und den dazugehörigen Tenant
        $wix_user=Auth::guard('wix')->user();
        $tenant=$wix_user->tenant;

        //Wir erstellen die URL zur Zusicherung der Authentifizierung
        $url_authorization=config('wix.wix_authorization_url');
        echo "{$url_authorization}   ---";

        //Wir holen die restichen notwendigen Daten
        $client_id=config('wix.app_id');
        $client_secret=config('wix.app_secret_key');

        //Nun senden wir die Daten, um den Authorisierungsvorgang zu festigen
        $client = new Client();

        try {
            $header=['content-type'=>'application/json','user-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0'];
            $body=[
                "grant_type"=> "authorization_code",
                "client_id"=> $client_id,
                "client_secret" => $client_secret,
                "code" => $authorization_code,
            ];


            /*
            $req = $client->request("post",$url_authorization,
                    ['json'=> $body,'verify' => true]);
                    */
            $req=$client->post($url_authorization,['body'=> json_encode($body),'headers'=>$header,'verify' => true]);
            //$req=$client->post($url_authorization,['json'=> $body,'debug' => true]);

            $status = $req->getStatusCode();
            $BodyClose = $req->getBody()->getContents();


            //$BodyClose->close();
            if($status != 200)
            {
                Log::info("Der Versuch, die Authorisierung bei Wix abzuschliessen mit der URL:
                            {$url_authorization} hat folgenden Status-Code zurückgeliefert: {$status}");
                return;
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
           return;
        }
        catch(\Exception $e){
            echo $e->getMessage();
                    Log::error($e->getMessage());
                    return;

        }


        //Wir haben jetzt die Daten von der Abfrage deserialisiert und müssen nun den Authorisierungsvorgang abschliessen
        //holen wir doch den Access und Refresh-Token
        $access_token=$data->access_token;
        $refresch_token=$data->refresh_token;


        //Wir holen noch die URL zum Abschluss
        $finish_url=config('wix.wix_finish_url');
        $finish_url.=urlencode($access_token);

        //Bevor wir nun den Benutzer an diese angepasste URL weiterleiten,
        //müssen wir die Daten, die wir für den Verbungsaufbau zur Wix-Api benötigen, abspeichern.
        Miscellaneous::loadTenantDBConnection($tenant->subdomain);
        $key_values=[
            'refresh_token'=>$refresch_token,
            'instance_id'=>$instance_id,
        ];
        Setting::setWixCredentials($key_values);


        //Abschluss: weiterleiten
        return redirect()->away($finish_url);
    }

    public static function newWixOrdertoVisticle(Request $request){
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

}
