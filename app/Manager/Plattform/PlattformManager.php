<?php
namespace App\Manager\Plattform;

use App\Tenant\Provider;

class PlattformManager{
    protected $subdomain=null;
    public function __construct($subdomain=null)
    {
        if($subdomain){
            $this->subdomain=$subdomain;
        }
        else{
            $this->subdomain=config('tenant.identifier');
        }
    }
    /**
     * Diese Funktion liefert einem Plattform-spezifische Daten zurück
     */
    public function getPlattformSettings(Provider $provider,array $settings){
        /**
         * In Abhängigkeit vom Provider die gewünschten Einstellungen erfragen.
         */
        //erst mal den Provider-Typ holen, da sich dieser Vorgang nach dem Typ orientiert.
        $provider_type=$provider->type;


        switch($provider_type->provider_key){
            case "check24":
                return $this->doCheck24Settings($provider,$settings);
                break;
        }

        return null;
    }
    /**
     * Mit dieser Funktion kann jeder PlattformController dem Plattform-Manager mitteilen, was er gemacht hat.
     * Dadurch kann der Plattform-Manager Aktionen durchführen.
     */
    public function plattformTellsAction(Provider $provider,array $message){

    }

    //########################################################################################
    /**
     * Hier kommen plattform-spezifische Funktionen, die von Aussen nicht aufgerufen werden
     * können.
     */
    protected function doCheck24Settings(Provider $provider,array $settings){

        $data=[];
        $subdomain=$this->subdomain;

        if(isset($settings['receipts'])){
            //Wir fügen in die Variable Data noch die Konfiguration für die Packscheine und Retourenscheine ein
            $data['receipts']=config("plattform-manager.check24.receipts.{$subdomain}");
        }
        if(isset($settings['ftp'])){
            /*
                Wir greifen hier auf die fiktive Konfiguration config('tenant.identifier') zu aus dem AppServiceProvider
                Das ist aber nur vorübergehend, bis wir die Einstellungen über die Oberfläche des Check24-Plattformmanagers
                einpflegen können.
            */
            switch($subdomain){
                case "wunderschoen-mode": case 'modemai':
                    $data['host']='partnerftp.testsieger.de';
                    $data['host']='193.238.61.103';
                    $data['user']='partner28234';
                    $data['password']='wjrgh96w';
                    $data['port']='44021';

                    return $data;
                    break;
                case "zoergiebel":
                    $data['host']='partnerftp.testsieger.de';
                    $data['host']='193.238.61.103';
                    $data['user']='partner28765';
                    $data['password']='cmqk6mut';
                    $data['port']='44021';

                    return $data;
                    break;
                case "schwoeppe":
                    $data['host']='partnerftp.testsieger.de';
                    $data['host']='193.238.61.103';
                    $data['user']='partner28845';
                    $data['password']='o6iz30nx';
                    $data['port']='44021';

                    return $data;
                    break;
                case "melchior":
                    $data['host']='partnerftp.testsieger.de';
                    $data['host']='193.238.61.103';
                    $data['user']='partner28871';
                    $data['password']='6zstilwe';
                    $data['port']='44021';

                    return $data;
                    break;
                case "modebauer":
                    $data['host']='partnerftp.testsieger.de';
                    $data['host']='193.238.61.103';
                    $data['user']='partner28797';
                    $data['password']='3tjzy3aw';
                    $data['port']='44021';
                    return $data;
                    break;
                case "fashionundtrends":
                    $data['host']='partnerftp.testsieger.de';
                    $data['host']='193.238.61.103';
                    $data['user']='partner28839';
                    $data['password']='kyr17nv4';
                    $data['port']='44021';
                    return $data;
                    break;
            }
        }

        if(count($data)){
            return $data;
        }


        return null;
    }
}
