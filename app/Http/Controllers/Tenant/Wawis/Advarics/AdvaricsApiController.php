<?php

namespace App\Http\Controllers\Tenant\Wawis\Advarics;

use App\Http\Controllers\Controller;
use App\Tenant,App\Tenant\Setting, Config,Redirect,Response;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use Log;
use App\Tenant\Provider, App\Tenant\ArticleProvider, App\Tenant\Provider_Type;
use App\Tenant\Branch;
use App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Brand; use App\Tenant\BrandsSuppliers;
use App\Tenant\Attribute_Set;
use Illuminate\Support\Str;
use Carbon\Carbon;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Stream\Stream;

class AdvaricsApiController extends Controller
{

    private $http_client;
    private $service_id;
    private $AttrSetGroups;
    private $AttrSetMainGroups;
    private $providers;
    private $customer;

    public function __construct($service_id)
    {
        $this->http_client = new Client();
        $this->service_id = $service_id;


        $tenant = Tenant::where('advarics_service_id', '=', $service_id)->first();
        if(!$tenant)
        {   Log::channel('single')->info('Advarics Customer not found as Visticle Tenant: '.$service_id);
            return;
        }
        $this->customer = $tenant->subdomain;

         //Set DB Connection
         \DB::purge('tenant');
         $config = Config::get('database.connections.tenant');
         $config['database'] = $tenant->db;
         $config['username'] = $tenant->db_user;
         $config['password'] = decrypt($tenant->db_pw);
         config()->set('database.connections.tenant', $config);
         \DB::connection('tenant');

        // Hauptgruppen IDs abfragen
        $this->AttrSetGroups = [];
        $this->AttrSetGroups = Attribute_Set::where('id', '=', '1')->with('groups')->get();
        if(isset($this->AttrSetGroups[0])){$this->AttrSetGroups = $this->AttrSetGroups[0]->groups;}
        // Filter Gruppen aussondern
        $this->AttrSetMainGroups = [];
        foreach($this->AttrSetGroups as $AttrGroup)
        {   if($AttrGroup->name == "Farbe"){ $this->AttrSetMainGroups['colors'] = $AttrGroup->id; }
            if($AttrGroup->name == "Größe"){ $this->AttrSetMainGroups['sizes'] = $AttrGroup->id; }
            if($AttrGroup->name == "Länge"){ $this->AttrSetMainGroups['lengths'] = $AttrGroup->id; }
        }
        $this->providers = Provider::all();

    }

    public function getArticles($customer = false,$EchologAktiv = false,$body = [])
    {
        if(!$customer || $customer == "false"){return;}
        $tenant = Tenant::where('subdomain', '=', $customer)->first();
        if(!$tenant)
        {   Log::channel('single')->info('Advarics Customer not found as Visticle Tenant: '.$customer);
            return;
        }

         //Set DB Connection
         \DB::purge('tenant');
         $config = Config::get('database.connections.tenant');
         $config['database'] = $tenant->db;
         $config['username'] = $tenant->db_user;
         $config['password'] = decrypt($tenant->db_pw);
         config()->set('database.connections.tenant', $config);
         \DB::connection('tenant');


         Log::info("IMPORT Advarics Artikel für Kunde: ".$customer." AdvaricsID: ".$this->service_id);
        do{
            //zur Steuerung der Schleife
            $articles=null;

            $path = '/WebShop/GetArticles';
            if(count($body)==0)
            {
                //$body["onlyWithStock"]=0;
            }
            $body['identification'] = [ 'id' => $this->service_id];
            $body['take']=10;


            /**
             * @author Tanju Özsoy
             * 10.02.2021
             * Wir schauen, ob es für Adverics ein lastChangedAt-Datumswert gibt.
             * Wenn ja, so berücksichtigen wir das für unseren Api-Aufruf, denn Wir holen uns dann nur
             * die Artikel, deren Wert für changedAt größer ist als unser abgespeicherter Wert.
             */

            $last_changed_at=Setting::getAdvericsLastChangedAt();

            //Jetzt setzen wir ein Initialdatum, das immer mit einem größeren Wert ersetzt wird;
            $init_date=Carbon::createFromFormat('Y-m-d H:i:s','1900-01-01 00:00:00');
            $replace_date=clone $init_date;
            if($last_changed_at){
                $init_date=clone $last_changed_at;
                $replace_date=clone $init_date;
                //Wir wandeln es in Carbon um und zählen eine microsekunde darauf
                $last_changed_at=$last_changed_at->addMicrosecond();
                $last_changed_at=$last_changed_at->format('Y-m-d H:i:s.u');
                $last_changed_at=str_replace(' ','T',$last_changed_at);
                $body['fromChangedAt']=$last_changed_at;

                Log::info('Gespeichertes ChangedAt-Datumswert: ' . $last_changed_at);
                echo 'Gespeichertes ChangedAt-Datumswert: ' . $last_changed_at;
            }


            $res = $this->callAPI_neu('POST', $path, $body);
            if(!$res) { return false; }
            if(!is_object($res['res'])) { return false; }
            if(!isset($res['res']->articles)) { return false; }
            $articles = $res['res']->articles;
            foreach($articles as $article) {

                $changing_date=$article->changedAt;
                $changing_date=str_replace('T',' ',$changing_date);
                $changing_date=Carbon::createFromFormat('Y-m-d H:i:s.u',$changing_date);

                if($changing_date->greaterThan($replace_date) )
                {
                    $replace_date=clone $changing_date;
                }
                $this->createAdvaricsArticle($article,$customer,$EchologAktiv);

                //Wenn die beiden Datumswerte voneinander abweichen, gibt es ein neues Datum zum speichern
                if($init_date->notEqualTo($replace_date)){
                    var_dump([$init_date,$replace_date]);
                    $replace_date_formatted=$replace_date->format('Y-m-d H:i:s.u');
                    Setting::setAdvericsLastChangedAt($replace_date_formatted);


                }
            }
            //Wenn die beiden Datumswerte voneinander abweichen, gibt es ein neues Datum zum speichern
            if($init_date->notEqualTo($replace_date)){
                $replace_date_formatted=$replace_date->format('Y-m-d H:i:s.u');
                Setting::setAdvericsLastChangedAt($replace_date_formatted);

                Log::info('Neues ChangedAt-Datumswert: ' . $replace_date_formatted);
            }
            echo count($articles) . ' Artikel im Paket import';
        }while($articles);



        Log::channel('single')->info('Advarics Artikel Batch abgeschlossen für: '.$customer);
        echo 'Advarics Artikel Batch abgeschlossen für: '.$customer;
        if($EchologAktiv){echo "\nFERTIG";}
    }

    public function ImportStock($customer = false,$EchologAktiv = false,$body = [])
    {
        if(!$customer || $customer == "false"){return;}
        $tenant = Tenant::where('subdomain', '=', $customer)->first();
        if(!$tenant)
        {   Log::channel('single')->info('Advarics Customer not found as Visticle Tenant: '.$customer);
            return;
        }

         //Set DB Connection
         \DB::purge('tenant');
         $config = Config::get('database.connections.tenant');
         $config['database'] = $tenant->db;
         $config['username'] = $tenant->db_user;
         $config['password'] = decrypt($tenant->db_pw);
         config()->set('database.connections.tenant', $config);
         \DB::connection('tenant');

        $path = '/WebShop/GetArticles';
        $allArticles = Article::get();
        $countArtikelMitBestand = 0;
        if($allArticles)
        {   foreach($allArticles as $thisArticle)
            {   if(count($body)==0){
                    $body['identification'] = [ 'id' => $this->service_id ];
                    $body["onlyWithStock"]=0;
                    $body["articleId"]=str_replace('vstcl-', '',$thisArticle->vstcl_identifier);
                    //$body["articleNo"]=$thisArticle->ean;
                }
                $res = $this->callAPI_neu('POST', $path, $body);
                if(!$res) { continue; } if(!is_object($res['res'])) { continue; } if(!isset($res['res']->articles)) {continue; }
                $articles = $res['res']->articles;
                foreach($articles as $article)
                {   $updateIt = $this->UpdateArticleStock($article,$thisArticle,$customer,$EchologAktiv);
                    if($updateIt && $updateIt > 0){$countArtikelMitBestand++;}
                }
                $body=[];
            }
        }
        Log::channel('single')->info('Advarics Stock Batch abgeschlossen für: '.$customer);
        if($EchologAktiv){echo "\nFERTIG\nArtikel mit Bestand: ".$countArtikelMitBestand;}
    }

    public function UpdateArticleStock($articleData,$thisArticle, $customer = false,$EchologAktiv = false)
    {   if(!$customer || $customer == "false"){return;}
        if(!$thisArticle){return;}
        $processDate = date('YmdHis');
        $article = $thisArticle;

        $branch = Branch::where('wawi_ident', '=', 'adv-000')->first();
        if(!$branch){$branch = Branch::updateOrCreate( [ 'wawi_ident' => 'adv-000'],[ 'active' => 1, 'name' => 'Advarics', 'wawi_number' => "000"  ] ); }

        $thisStock = 0; $thisStockGesamt = 0; $thisCounter = 0;
        foreach($articleData->details as $variationData)
        {
            if(is_object($variationData))
            {   $thisStock = ($variationData->stock < 0)? 0 : $variationData->stock;
                if($articleData->goodsGroupNo == "9000" && $variationData->stock > 0){$thisStock = 100;} // Gutscheine
                if($articleData->supplierNo == "9001"){$thisStock = 100;} // Fashionbox Zörgiebel
                $variation = Article_Variation::where('vstcl_identifier', '=', "vstcl-".$variationData->gtin)->where('fk_article_id', '=', $article->id)->first();
                if(!$variation){ continue; }
                $variation->updateOrCreateStockInBranch($branch, $thisStock, $processDate);
                $thisCounter++; $thisStockGesamt = $thisStockGesamt + $thisStock;
            }
        }
        $article->batch_nr = $processDate;$article->save();
        if($thisStockGesamt > 0)
        {   foreach($this->providers as $provider)
            { ArticleProvider::updateOrCreate(['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null], ['active' => 1] ); }
        }

        if($EchologAktiv){echo "[".$article->id.":".$thisStockGesamt."]";}
        return $thisStockGesamt;
    }

    public function createAdvaricsArticle($articleData, $customer = false,$EchologAktiv = false)
    {   if(!$customer || $customer == "false"){return;} $processDate = date('YmdHis');
        $tenant = Tenant::where('subdomain', '=', $customer)->first();
        if(!$tenant)
        {   Log::channel('single')->info('Advarics Customer not found as Visticle Tenant: '.$customer);
            return;
        }

         //Set DB Connection
         \DB::purge('tenant');
         $config = Config::get('database.connections.tenant');
         $config['database'] = $tenant->db;
         $config['username'] = $tenant->db_user;
         $config['password'] = decrypt($tenant->db_pw);
         config()->set('database.connections.tenant', $config);
         \DB::connection('tenant');

        $branch = Branch::where('wawi_ident', '=', 'adv-000')->first();
        if(!$branch){$branch = Branch::updateOrCreate( [ 'wawi_ident' => 'adv-000'],[ 'active' => 1, 'name' => 'Advarics', 'wawi_number' => "000"  ] ); }


        $article_nummer = $articleData->articleNo;
        if($articleData->supplierColorNo != "")
        {
            $thisString = $article_nummer.'--'.$articleData->supplierColorNo;
            if(strpos($thisString, "ä")){$thisString=str_replace("ä", "AE", $thisString);}
            if(strpos($thisString, "Ä")){$thisString=str_replace("Ä", "AE", $thisString);}
            if(strpos($thisString, "ö")){$thisString=str_replace("ö", "OE", $thisString);}
            if(strpos($thisString, "Ö")){$thisString=str_replace("Ö", "OE", $thisString);}
            if(strpos($thisString, "ü")){$thisString=str_replace("ü", "UE", $thisString);}
            if(strpos($thisString, "Ü")){$thisString=str_replace("Ü", "UE", $thisString);}
            if(strpos($thisString, "(")){$thisString=str_replace("(", "", $thisString);}
            if(strpos($thisString, ")")){$thisString=str_replace(")", "", $thisString);}
            if(strpos($thisString, " ")){$thisString=str_replace(" ", "-", $thisString);}
            if(strpos($thisString, "\u00df")){$thisString=str_replace("\u00df", "SS", $thisString);}
            if(strpos($thisString, "ß")){$thisString=str_replace("ß", "SS", $thisString);}
            $article_nummer = $thisString;
        }


        $ThisSlug = Str::slug($articleData->articleName, '-');
        $article = Article::where('vstcl_identifier','=', "vstcl-".$articleData->articleId)->where('fk_wawi_id', '=', 1)
        ->where('name', '=', $articleData->articleName)->where('ean', '=', $articleData->articleNo)
        ->where('number', '=', $articleData->articleId)->where('fk_attributeset_id', '=', 1 )->first();
        // wenns den Artikel schon gibt, keine Änderung, sonst UpdateCreate

        if(!$article)
        {   $article = Article::updateOrCreate(
                [   'vstcl_identifier' => "vstcl-".$articleData->articleId, 'fk_wawi_id' => 1 ],
                [   'name' => $articleData->articleName,
                    'description' => $articleData->description1,
                    'slug' => $ThisSlug,
                    'ean' => $articleData->articleNo,
                    'number' => $articleData->articleId,
                    'fk_attributeset_id' => 1,
                    'fk_wawi_id' => 1,
                    'type'=>'article',
                    'active' => 1
                ]
            );
            if($article->wasRecentlyCreated) {$article->batch_nr=$processDate;$article->save();}
            if($EchologAktiv){echo "\n".$article->id.": [A(".$article->id.")]";}
        }else{if($EchologAktiv){echo "\n".$article->id.": [*A(".$article->id.")]";}}

        // beginn aktuelle Attribute sammeln
        $thisArticleAttributes = []; $articleAttributes = $article->attributes()->get(); $AttrChange=0;
        if($articleAttributes)
        {   foreach($articleAttributes as $articleAttribute)
            { $thisArticleAttributes[$articleAttribute->name] = $articleAttribute->value; }
        }
        // ende aktuelle Attribute sammeln

        if(isset($thisArticleAttributes['hersteller']) && $thisArticleAttributes['hersteller'] != $articleData->supplierName
        || !isset($thisArticleAttributes['hersteller'])){$article->updateOrCreateAttribute('hersteller', $articleData->supplierName);$AttrChange=1;}

        if(isset($thisArticleAttributes['hersteller-nr']) && $thisArticleAttributes['hersteller-nr'] != $articleData->supplierNo
        || !isset($thisArticleAttributes['hersteller-nr'])){$article->updateOrCreateAttribute('hersteller-nr', $articleData->supplierNo);$AttrChange=1;}

        if(isset($thisArticleAttributes['adv-color-number']) && $thisArticleAttributes['adv-color-number'] != $articleData->supplierColorNo
        || !isset($thisArticleAttributes['adv-color-number'])){$article->updateOrCreateAttribute('adv-color-number', $articleData->supplierColorNo);$AttrChange=1;}

        if(isset($thisArticleAttributes['adv-color']) && $thisArticleAttributes['adv-color'] != (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName)
        || !isset($thisArticleAttributes['adv-color'])){$article->updateOrCreateAttribute('adv-color', (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName));$AttrChange=1;}

        if(isset($thisArticleAttributes['adv-sizeTable']) && $thisArticleAttributes['adv-sizeTable'] != $articleData->sizeTableName
        || !isset($thisArticleAttributes['adv-sizeTable'])){$article->updateOrCreateAttribute('adv-sizeTable', $articleData->sizeTableName);$AttrChange=1;}


        if(!empty($articleData->lastIncome))
        {   if(isset($thisArticleAttributes['adv-lastIncome']) && $thisArticleAttributes['adv-lastIncome'] != $articleData->lastIncome
            || !isset($thisArticleAttributes['adv-lastIncome'])){$article->updateOrCreateAttribute('adv-lastIncome', $articleData->lastIncome);$AttrChange=1;}
        }
        if(!empty($articleData->lastIncomeSeasonNo))
        {   if(isset($thisArticleAttributes['adv-lastIncomeSeasonNo']) && $thisArticleAttributes['adv-lastIncomeSeasonNo'] != $articleData->lastIncomeSeasonNo
            || !isset($thisArticleAttributes['adv-lastIncomeSeasonNo'])){$article->updateOrCreateAttribute('adv-lastIncomeSeasonNo', $articleData->lastIncomeSeasonNo);$AttrChange=1;}
        }
        if(!empty($articleData->lastIncomeSeasonName))
        {   if(isset($thisArticleAttributes['adv-lastIncomeSeasonName']) && $thisArticleAttributes['adv-lastIncomeSeasonName'] != $articleData->lastIncomeSeasonName
            || !isset($thisArticleAttributes['adv-lastIncomeSeasonName'])){$article->updateOrCreateAttribute('adv-lastIncomeSeasonName', $articleData->lastIncomeSeasonName);$AttrChange=1;}
        }

        if($AttrChange==1){ if($EchologAktiv){echo " "."[A-Attr]";} }

        $brandSlug = Str::slug($articleData->articleName, '-');
        $brand = Brand::updateOrCreate( [ 'name' => $articleData->brand ],['slug'=>$brandSlug] );
        $brandSupplier = BrandsSuppliers::updateOrCreate(
            [ 'hersteller-nr'=> $articleData->supplierNo ],
            [ 'fk_brand_id'=>$brand->id,'hersteller_name' => $articleData->supplierName] );

        $thisStock = 0;
        foreach($articleData->details as $variationData)
        {
            if(is_object($variationData))
            {
                $thisStock = $variationData->stock;
                if($articleData->goodsGroupNo == "9000" && $variationData->stock > 0){$thisStock = 100;} // Gutscheine
                if($articleData->supplierNo == "9001"){$thisStock = 100;} // Fashionbox Zörgiebel

                //Article - Variations
                $variation = Article_Variation::where('vstcl_identifier', '=', "vstcl-".$variationData->gtin)->where('fk_article_id', '=', $article->id)->where('fk_attributeset_id', '=', 1)->first();
                // wenns die Variation schon gibt, keine Änderung, sonst UpdateCreate
                if(!$variation)
                {   $variation = Article_Variation::updateOrCreate(
                        [ 'vstcl_identifier' => "vstcl-".$variationData->gtin,'fk_article_id' => $article->id ],
                        [ 'fk_attributeset_id' => 1, 'ean' => $variationData->gtin, 'stock' => 0, 'type' => 'article', 'active' => 1]
                    );
                    if($EchologAktiv){echo ""."[V(".$variation->id.")]";}
                }else{if($EchologAktiv){echo ""."[*V(".$variation->id.")]";}}

                // beginn aktuelle Attribute sammeln
                $thisVariationAttributes = []; $variationAttributes = $variation->attributes()->get(); $VAttrChange=0;
                if($variationAttributes)
                {   foreach($variationAttributes as $variationAttribute)
                    { $thisVariationAttributes[$variationAttribute->name] = $variationAttribute->value; }
                }
                // ende aktuelle Attribute sammeln
                $variation->updateOrCreateStockInBranch($branch, $thisStock);

                $variation->updateOrCreatePrice('standard', $variationData->recommendedPrice);
                $variation->updateOrCreatePrice('discount', $variationData->price);


                if($variationData->orderQty>0)
                {
                    if(isset($thisVariationAttributes['adv-vpe']) && $thisVariationAttributes['adv-vpe'] != $variationData->orderQty
                    || !isset($thisVariationAttributes['adv-vpe'])){$variation->updateOrCreateAttribute('adv-vpe', $variationData->orderQty);$VAttrChange=1;}
                }
                if($variationData->size != "")
                {
                    $ThisSizeAttrGroupId = ((isset($this->AttrSetMainGroups['sizes']))? $this->AttrSetMainGroups['sizes'] :  null );
                    if(isset($thisVariationAttributes['adv-size']) && $thisVariationAttributes['adv-size'] != $variationData->size
                    || !isset($thisVariationAttributes['adv-size'])){$variation->updateOrCreateAttribute('adv-size', $variationData->size, $ThisSizeAttrGroupId);$VAttrChange=1;}
                    if(isset($thisVariationAttributes['fee-size']) && $thisVariationAttributes['fee-size'] != $variationData->size
                    || !isset($thisVariationAttributes['fee-size'])){$variation->updateOrCreateAttribute('fee-size', $variationData->size, $ThisSizeAttrGroupId);$VAttrChange=1;}

                    if(isset($thisVariationAttributes['adv-sizeTable-number']) && $thisVariationAttributes['adv-sizeTable-number'] != $articleData->sizeTableNo
                    || !isset($thisVariationAttributes['adv-sizeTable-number'])){$variation->updateOrCreateAttribute('adv-sizeTable-number', $articleData->sizeTableNo);$VAttrChange=1;}
                    if(isset($thisVariationAttributes['adv-sizeTable']) && $thisVariationAttributes['adv-sizeTable'] != $articleData->sizeTableName
                    || !isset($thisVariationAttributes['adv-sizeTable'])){$variation->updateOrCreateAttribute('adv-sizeTable', $articleData->sizeTableName);$VAttrChange=1;}
                }
                if($articleData->supplierColorNo != "0")
                {
                    $supplierColor = (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName);

                    $ThisColorAttrGroupId = ((isset($this->AttrSetMainGroups['colors']))? $this->AttrSetMainGroups['colors'] :  null );

                    if(isset($thisVariationAttributes['adv-color']) && $thisVariationAttributes['adv-color'] != (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName)
                    || !isset($thisVariationAttributes['adv-color'])){$variation->updateOrCreateAttribute('adv-color', (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName));$VAttrChange=1;}
                    if(isset($thisVariationAttributes['fee-color']) && $thisVariationAttributes['fee-color'] != (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName)
                    || !isset($thisVariationAttributes['fee-color'])){$variation->updateOrCreateAttribute('fee-color', (($articleData->supplierColorName == "")? $articleData->supplierColorNo : $articleData->supplierColorName));$VAttrChange=1;}
                }
                if($VAttrChange==1){ if($EchologAktiv){echo " "."[V-Attr]";} }
            }
        }

        if($thisStock > 0)
        {
            foreach($this->providers as $provider)
            { ArticleProvider::updateOrCreate(['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null], ['active' => 1] ); }
        }//else{ArticleProvider::updateOrCreate(['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null], ['active' => 0] );}


        //Categories
        $cat = Category::where('wawi_number', '=', $articleData->goodsGroupNo)
        ->where('wawi_name', '=', $articleData->goodsGroupName)->first();
        if(!$cat)
        {   $cat = Category::updateOrCreate(
                [ 'fk_wawi_id' => 1,'wawi_number' => $articleData->goodsGroupNo],
                [ 'wawi_name' => $articleData->goodsGroupName]
            );
            foreach($cat->shopcategories()->get() as $shopcat) {
                $article->categories()->syncWithoutDetaching($shopcat->id);
            }

        }
        $article->categories()->syncWithoutDetaching($cat->id);
        if($EchologAktiv){echo "[C]";}

    }

    private function callAPI_neu($method, $path, $body = [],$tryCount=0)
    {   if($tryCount < 10)
        {   $client = new Client(); $failed = false;
            try
            {    $req = $this->http_client->request(
                    $method,
                    env('ADVARICS_API_URL', 'https://retailtest.advarics.net/external').$path,
                    [   'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                        'body' => json_encode($body)
                    ]
                );
                $status = $req->getStatusCode();
                if($status != 200)
                {   Log::info("Adverics - Typ: ".$method.' - Status: '.$status." Versuch-Nr: ".$tryCount." - url: ".env('ADVARICS_API_URL', 'https://retailtest.advarics.net/external').$path);
                    $tryCount++; sleep( 3 ); return $this->callAPI_neu($method, $path, $body,$tryCount);
                }
                $failed = false;
                $data = json_decode($req->getBody());
                return [ 'res' => $data ];

            } catch(GuzzleException $e) {
                $failed = true;
                switch($e->getCode()) {
                    case 401:
                        Log::info("Adverics - ".'Typ: '.$method.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount." Key konnte sich nicht authentifizieren! URL: ".env('ADVARICS_API_URL', 'https://retailtest.advarics.net/external').$path);
                    break;
                    case 429:
                    case 500:
                        Log::info("Adverics - ".'Typ: '.$method.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount." URL: ".env('ADVARICS_API_URL', 'https://retailtest.advarics.net/external').$path);
                    break;
                    default:
                        Log::error($e->getMessage());
                    break;
                }
                $tryCount++; sleep( 3 );
                return $this->callAPI_neu($method, $path, $body,$tryCount);
            }
        } return false;
    }
    private function callAPI($method, $path, $body = []) {
        try {   $res = $this->http_client->request(
                $method,
                env('ADVARICS_API_URL', 'https://retailtest.advarics.net/external').$path,
                [   'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                    'body' => json_encode($body)
                ]
            );
            return [ 'res' => json_decode($res->getBody()) ];
        }
        catch (GuzzleException $e) {
            Log::error($e->getMessage());
            return false;
        }

    }

}
