<?php

namespace App\Console\Commands\Manager\Plattform\VSShop;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use App\Helpers\Miscellaneous;
use App\Manager\Content\ContentManager;
use App\Tenant\Article_Price;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use Config;
use Illuminate\Support\Facades\Log;
use stdClass;

class SendSynchros extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vsshop:send_syncros {customer} {priority}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sendet für einen bestimmten Kunden Datensätze aus der Synchrotabelle nach der angegebenen Priorität zum VSShop!.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected $tenant=null;
    protected $domain=null;
    protected $priority=null;
    protected $date_time=null;
    protected $content_manager=null;
    protected $provider=null;
    protected $http_client=null;
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain=$this->argument('customer');
        $tenant = Tenant::query()->where('subdomain','=',$domain)->first();

        if(!$tenant){
            $this->info("Für die angegebene Domain \"{$domain}\" gibt es keinen Kunden!!!");
            return;
        }
        $this->domain=$domain;
        $this->tenant=$tenant;


        //Alle verfügbaren Prioritäten holen
        $priorities=config('content-manager.priorities');

        $priority=$this->argument('priority');
        if(!$priorities[$priority]){
            $this->info("Die angegebene Priorität \"{$priority}\" ist nicht registriert!");
            return;
        }
        $this->priority=$priority;


        //Datenbank zum Kunden
        Miscellaneous::loadTenantDBConnection($domain);

        //provider holen!
        $provider=Provider::query()->whereHas('type',function($query){
            $query->where('provider_key','shop');
        })->first();

        if(!$provider){
            $this->info("Es ist kein Provider für den VSShop im System hinterlegt!");
            return;
        }
        $this->provider=$provider;

        //Wir verhindern mit dem Setzen der Datetime, dass wir fiktiv in einer unendlichen Schleife rumsitzen.
        //Es könnte ja sein, dass während der Übertragung dauernd Änderungen im System anfallen und das würde dazu führen,
        //dass diese Funktion die aufhört.
        $this->date_time=date('Y-m-d H:i:s');

        //Content-Manager instanziieren
        $this->content_manager=new ContentManager();

        //Guzzle HTTP-Client instanziieren
        $this->http_client=new Client();

        //vor dem Senden den Start loggen
        Log::channel('vsshop_syncro')->info("Starte Übertragung der Syncros an den VSShop mit URL \"{$this->provider->url}\" von \"{$this->domain}\".");

        //Jetzt die Synchros senden!
        try{
            $this->sendSynchros();
        }
        catch(\Exception $e){
            echo $e->getFile() . '  ' . $e->getLine();
        }


        //vor dem Senden den Start loggen
        Log::channel('vsshop_syncro')->info("Beende Übertragung der Syncros an den VSShop mit URL \"{$this->provider->url}\" von \"{$this->domain}\".");
    }
    protected function sendSynchros(){
        //Synchros holen!
        $synchros=$this->content_manager->getProviderSynchroChronological($this->provider->id,$this->priority,$this->date_time)->get();


        while($synchros->count()){

            //Synchros in das Paket schreiben
            $synchro_package=
            $this->buildSynchroPackage($synchros);

            //Synchro-Paket senden
            $failed_synchros=
            $this->sendSynchroPackage($synchro_package);

            //übertragene Synchros löschen
            //zuerst erstellen wir eine ID-Liste unserer Synchros
            //Dann löschen wir die IDs der fehlgelaufenen Datensätze aus dieser List
            //Diese Endliste übergeben wir sodann an den ContentManager
            $synchro_ids=$synchros->pluck('id')->all();
            foreach($failed_synchros as $failed_synchro){
                unset($synchro_ids[$failed_synchro]);
            }
            /**
             * @todo untere Zeile freischalten
             */
            $this->content_manager->deleteProviderSynchro_IDs($synchro_ids);

            //Noch vorhandene Synchros holen
            $synchros=$this->content_manager->getProviderSynchroChronological($this->provider->id,$this->priority,$this->date_time)->get();
        }
    }
    /*
        Diese Methode gibt ein Array zurück als Sendungspaket.

        Die einzelnen Paket-Einheiten haben folgenden Aufbau:

        [
            "operation"     :   "insert|update|delete",
            "synchro_id"    :   "Die ID des Synchro-Datensatzes",
            "article_id"    :   "Artikel-ID oder 0",
            "data"          :    [
                                    "class" => "Name der Datenbankklasse ohne Namensraum": entweder Article oder eine andere Datenbankklasse, oder auch Null, wenn es nur um einen Kontext geht",
                                    "class_object" => "Eine Instanz": entweder von ARticle, andere Datenbankklasse oder auch Null, wenn es nur um einen Kontext geht.",
                                    "class_object_deleted" => wird gesetzt und mit true belegt, wenn es kein class_object gibt. Das ist der Fall, wenn der tatsächliche Datensatz bereits gelöscht wurde.
                                    "class_object_id" => "eine ID": die ID der obigen Instanz oder Null, wenn es nur um einen Kontext geht.",
                                    "context" => "usual|angegebenen Kontext": entweder der WErt "usual" für ein normales update,insert oder delete der obigen INstanz, oder,
                                    die Kontextangabe aus der Synchro.",
                                    "context_value" => "Der Kontextwert aus der Synchro".
                                ]
        ]

    */
    protected function buildSynchroPackage($synchros){
        $synchro_package=[];


        foreach($synchros as $synchro){
            $operation=null;
            switch($synchro->operation){
                case 1:
                    $operation='insert';
                    break;
                case 2:
                    $operation='update';
                    break;
                case 3:
                    $operation='delete';
                    break;
            }

            $synchro_package_unit=[];
            $synchro_package_unit['operation']=$operation;
            $synchro_package_unit['synchro_id']=$synchro->id;
            //Artikel-ID kommt immer mitrein, auch wenn 0!!!
            $synchro_package_unit['article_id']=$synchro->fk_article_id;
            //Die Synchrodaten lassen wir uns noch von einer anderen Methode erstellen
            $synchro_package_unit['data']=$this->createSynchroUnitData($synchro,$operation);

            $synchro_package[]=$synchro_package_unit;


        }



        return $synchro_package;
    }
    /**
     * Diese Funktion baut für einen Synchrodatensatz folgendes Gerüst auf:
     * [
     *  "class" => "Name der Datenbankklasse ohne Namensraum": entweder Article oder eine andere Datenbankklasse, oder auch Null, wenn es nur um einen Kontext geht
     *  "class_object" => "Eine Instanz": entweder von ARticle, andere Datenbankklasse oder auch Null, wenn es nur um einen Kontext geht.
     *  "class_object_deleted" => wird gesetzt und mit true belegt, wenn es kein class_object gibt. Das ist der Fall, wenn der tatsächliche Datensatz bereits gelöscht wurde.
     *  "class_object_id" => "eine ID": die ID der obigen Instanz oder Null, wenn es nur um einen Kontext geht.
     *  "context" => "usual|angegebenen Kontext": entweder der WErt "usual" für ein normales update,insert oder delete der obigen INstanz, oder
     *                die Kontextangabe aus der Synchro."
     *  "context_value" => "Der Kontextwert aus der Synchro".
     * ]
     */
    protected function createSynchroUnitData($synchro,$operation){
        $data=[];


        //geht es um einen Artikel??
        if($synchro->subject==null && $synchro->subject_id==null && $synchro->context==null){
            $data['class']=$this->getClassNameOfSubject(Article::class);
            if($operation=='delete'){
                $data['class_object']=json_decode($synchro->deletion->value);
            }
            else{

               $data['class_object']=Article::find($synchro->fk_article_id);

            }
            $data['class_object_id']=$synchro->fk_article_id;

            //Wenn null, so ist das Objekt bereits gelöscht und schreiben wir ein zusätzliches Feld
            if(!$data['class_object']){
                $data['class_object_deleted']=true;
            }


        }
        //haben wir ganz normale weiter Synchros?
        elseif($synchro->subject!=null && $synchro->subject_id!=null){
            $data['class']=$this->getClassNameOfSubject($synchro->subject);
            if($operation=='delete'){


                $data['class_object']=json_decode($synchro->deletion->value);

            }
            else{
                $class=$synchro->subject;
                $data['class_object']=$class::find($synchro->subject_id);


            }
            //Wenn null, so ist das Objekt bereits gelöscht und schreiben wir ein zusätzliches Feld
            if(!$data['class_object']){
                $data['class_object_deleted']=true;
            }


            $data['class_object_id']=$synchro->subject_id;
        }
        //vielleicht gibt es ja nur einen Kontext?
        else{

            $data['class']=null;
            $data['class_object']=null;
            $data['class_object_id']=null;
        }

        //Wir legen noch den Context fest!! Sollte es ein gewöhnliches update,insert und delete sein schreiben wir als kontext usual,
        //ansonsten übernehmen wir die context-angabebe aus der synchro
        if($synchro->context){
            $data['context']=$synchro->context;
            if($synchro->context_value){
                $data['context_value']=json_decode($synchro->context_value);
            }
            else{
                $data['context_value']=null;
            }
        }
        else{
            $data['context']="usual";
            $data['context_value']=null;
        }

        return $data;
    }
    protected function getClassNameOfSubject($subject){
        $parts=explode('\\',$subject);

        $name=null;
        if(is_array($parts) && count($parts)){
            $name=$parts[count($parts)-1];
        }
        else{
            $name=$subject;
        }

        return $name;
    }
    /*
        Diese Methode sendet das Synchro-Paket an die Plattform und gibt die Synchro-IDs der Synchrodatensätze zurück,
        bei denen etwas nicht ordnungsgemäß gelaufen ist. Auf jeden Fall bekommt man ein Array zurück. Im Idealfall ein leeres.
    */
    protected function sendSynchroPackage($synchro_package) : array {
        $failed_synchros=[];

        $maximal_failed_attempts=config('plattform-manager.vsshop.transfer.max_failed_attempts');

        $attempts=0;
        $failed=false;
        $success=false;

        do{

            $attempts++;

            try {
                $header=[
                    'content-type'=>'application/json',
                    'user-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0',
                        ];

                //Synchro-Paket einbinden
                $new_package=new stdClass();
                $new_package->syncros=$synchro_package;
                //Header ergänzen
                $data=[
                    'headers'=>$header,
                    'body'=>json_encode($new_package),
                ];



                //URL holen und anpssen
                $url=$this->provider->url;
                if(substr($url,strlen($url)-1,1)!='/'){
                    $url.='/';
                }
                $url.=   config('plattform-manager.vsshop.api.syncro_path');
                $url.='?apikey=' . urlencode($this->provider->apikey);





                    $req=$this->http_client->request('post',$url,$data);
                    //$req=$client->post($url_authorization,['json'=> $body,'debug' => true]);

                    $status = $req->getStatusCode();
                    $BodyClose = $req->getBody()->getContents();



                    //$BodyClose->close();
                    if($status != 200)
                    {
                        Log::channel("vsshop_syncro")->info("Der Versuch, ein Syncro-Paket an den VSShop zu schicken mit der URL:
                                    {$url} hat folgenden Status-Code zurückgeliefert: {$status}");


                        if($status==500){
                            Log::channel("vsshop_syncro")->error(json_encode($BodyClose));
                        }
                    /**
                        * @todo fehlgeschlagene Syncros sammeln und in das entsprechende Array setzen
                        */
                    }
                    else{
                        $success=true;
                    }




                    $data = json_decode($BodyClose);
                }
            //Und weiter geht es nach dem Try-Catch-Block
             catch(GuzzleException $e) {
                $failed = true;
                switch($e->getCode()) {
                    case 401:
                        $code=$e->getCode();
                        $message=$e->getMessage();
                        Log::channel("vsshop_syncro")->info("Folgende Meldung und Statuscode für den Authorisierungvorgang beim VSShop \n
                                {$code} - {$message}");
                    break;
                    case 429:
                    case 500:
                        Log::channel("vsshop_syncro")->info("Folgende Meldung und Statuscode für den Authorisierungvorgang beim VSShop \n
                        {$e->getCode()} - {$e->getMessage()}");
                        echo $e->getMessage();
                        Log::channel("vsshop_syncro")->error($e->getMessage());
                    break;
                    default:
                        echo $e->getMessage();
                        Log::channel("vsshop_syncro")->error($e->getMessage());
                        Log::channel("vsshop_syncro")->info(json_encode($e->getMessage()));
                    break;
                }
            }
            catch(\Exception $e){
                $failed=true;
                echo $e->getMessage();
                        Log::channel("vsshop_syncro")->info($e->getMessage());


            }
        }
        while( $success==false && $attempts<$maximal_failed_attempts);

        if($success==false){
            //Die gesamte Übertragung unterbrechen mit einer Exception! Vorher quittieren
            Log::channel("vsshop_syncro")->info("Die Chronik der Syncros konnte nicht übertragen werden für den Kunden \"{$this->domain}\"");
            throw new \Exception("Die Chronik der Syncros konnte nicht übertragen werden für den Kunden \"{$this->domain}\"");
        }

        return $failed_synchros;
    }
}
