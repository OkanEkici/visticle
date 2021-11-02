<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Psr7\Response;

use App\Tenant\Article, App\Tenant\Article_Attribute, App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute;
use App\Tenant\Setting, App\Tenant\ArticleProvider;
use App\Tenant\Settings_Attribute;
//use Storage;

use App\Tenant\Article_Price, App\Tenant\Article_Marketing;
use App\Tenant\Article_Variation, App\Tenant\Article_Variation_Attribute, App\Tenant\Article_Variation_Image, App\Tenant\Article_Variation_Image_Attribute, App\Tenant\Article_Variation_Price;
use App\Tenant\Category;
use App\Tenant\BranchArticle_Variation;
use App\Tenant\Provider, App\Tenant\Provider_Type;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Sparesets;use App\Tenant\Sparesets_Articles;use App\Tenant\Sparesets_Categories;use App\Tenant\Sparesets_SpareArticles;
use App\Tenant\Equipmentsets;use App\Tenant\Equipmentsets_Articles;use App\Tenant\Equipmentsets_Categories;use App\Tenant\Equipmentsets_EquipmentArticles;
use App\Tenant\Attribute_Group, App\Tenant\Customer, App\Tenant\Price_Groups;
use App\Tenant\Price_Customer_Articles, App\Tenant\Price_Customer_Categories, App\Tenant\Price_Groups_Articles, App\Tenant\Price_Groups_Categories, App\Tenant\Price_Groups_Customers;
use App\Tenant\PaymentConditions;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Stream\Stream;
use Illuminate\Support\Facades\Log;

class VSShopSyncArticles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels; 

    protected $request = false;
    protected $for_curstomer = false;
    protected $EchologAktiv = false;
    protected $shops = [];


    public function __construct(Request $request,$for_curstomer = "", $EchologAktiv = false)
    {
        $this->request = $request;
        $this->for_curstomer = $for_curstomer;
        $this->EchologAktiv = $EchologAktiv;
        $this->shops = false;
        
        $type = Provider_Type::where('provider_key','=','shop')->first();
        if($type) { $this->shops = Provider::where('fk_provider_type','=', $type->id)->get(); }
    }

    public function handle()
    {   if(!$this->for_curstomer){return;}
        $excludeCustomers = ['demo1','demo2', 'demo3','stilfaktor'];
        if(in_array($this->for_curstomer, $excludeCustomers)) { return; }
        if($this->EchologAktiv){echo "\n"."Starte Batch: ".$this->for_curstomer;}
        Log::info("Starte Batch: ".$this->for_curstomer);
        $Tenant_type = config()->get('tenant.tenant_type');
        $synchroType = Synchro_Type::where('key','=','shop_content_update')->first();
        $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
        $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
        $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
        $hasSynchro = true;
        $synchro = null;
        if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS) 
        { $hasSynchro = false; }

        if($hasSynchro) {
            $synchro = Synchro::create(
                [
                    'fk_synchro_type_id' => $synchroType->id,
                    'fk_synchro_status_id' => $inProgressSynchroS->id,
                    'start_date' => date('Y-m-d H:i:s')
                ]
            );
        }
        $success_count = 0; $error_count = 0;
        
        $batches = Article::select('batch_nr')->distinct()->get();
        $expected_count = count($batches);        
        $promises=[];
        $customers = [];//Customer::with(['payment_conditions','customer_article_prices','customer_category_vouchers'])->get();
        $Tenant_type = config()->get('tenant.tenant_type');
        $client = new Client();
        $ts1 = strtotime(date("Y-m-d H:i:s")); 
        if(!empty($batches)) 
        {
            if($this->EchologAktiv){echo "\n"."pre Cat Batch für ".$this->for_curstomer;}
            // Kategorien seperat vorher übertragen
            foreach($this->shops as $shop) 
            {
                $categories = Category::with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();
                if($this->EchologAktiv){echo "\n"."sende Kategorien ".count($categories);}
                $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => [], 'categories' =>$categories, 'customers' =>[] ],1, $shop->apikey);  
            }
            if($this->EchologAktiv){echo " [OK]";}

            $batchCount = 0;
            if($this->EchologAktiv){echo "\n"."starte Batch für ".$this->for_curstomer;}
            foreach($batches as $batch_nr) 
            {
                if($batch_nr['batch_nr'] == null) { continue; }

                if($this->EchologAktiv){echo "\n"."Batch Nr: ".$batch_nr['batch_nr'];}
                foreach($this->shops as $shop) {
                    $shop_id = $shop->id;

                    $max=10; // in Xer Abfragen abarbeiten
                    $articleIDs = Article::inRandomOrder()->where('batch_nr','=',$batch_nr['batch_nr'])->whereHas('provider', function($query) use($shop_id) { $query->where('fk_provider_id', $shop_id)->where('active','=', 1); })->get()->pluck("id");
                    $current=0; $thisArticle_current=0;
                    $articleID200 = [];
                    foreach($articleIDs as $articleID)
                    { $current++;$thisArticle_current++;  
                        //Prüfung ob Artikel im Shop deaktiviert oder aktualisiert werden soll
                        $CheckArticle = Article::where('id','=', $articleID)->first();
                        $check=$this->checkArticleVSShop($CheckArticle);
                        if($check){$articleID200[] = $articleID;}
                        
                        if($current==$max || $thisArticle_current==count($articleIDs))
                        {   $current=0;
                            $articles_send_count=0;
                            
                            if($Tenant_type=='vstcl-industry')
                            {
                                $articles = Article::inRandomOrder()->whereIn('id', $articleID200)
                                ->where('batch_nr','=',$batch_nr['batch_nr'])->with([
                                    'attributes' => function($query) { $query->with('group'); },
                                    'images' => function($query) { $query->with('attributes'); },
                                    'categories' => function($query) { $query->where('fk_wawi_id', '=', null); },
                                    'prices', 'marketing', 'shipments',
                                    'attribute_set' => function($query) { $query->with(['groups']); },
                                    'variations' => function($query) {
                                        $query->with([
                                            'prices','branches',
                                            'images' => function($query) { $query->with('attributes'); },
                                            'attribute_set' => function($query) { $query->with(['groups']); },
                                            'attributes' => function($query) { $query->with('group'); }
                                            ,'sparesets_spare_article'   => function($query) { $query->with('spareset'); }
                                            ,'sparesets_article'   => function($query) { $query->with('spareset'); }
                                            ,'equipmentsets_equipment_article'  => function($query) { $query->with('equipmentset'); }
                                            ,'equipmentsets_article' => function($query) { $query->with('equipmentset'); }
                                        ]);
                                    }
                                    ,'sparesets_spare_article'   => function($query) { $query->with('spareset'); }
                                    ,'sparesets_article'  => function($query) { $query->with('spareset'); }
                                    ,'equipmentsets_equipment_article'  => function($query) { $query->with('equipmentset'); }
                                    ,'equipmentsets_article' => function($query) { $query->with('equipmentset'); }
                                ]);
                                $articles_send_count = $articles->count();
                                $articles = $articles->get();
                            }
                            else
                            {
                                $articles = Article::inRandomOrder()->whereIn('id', $articleID200)
                                ->where('batch_nr','=',$batch_nr['batch_nr'])->with([
                                    'attributes' => function($query) { $query->with('group'); },
                                    'images' => function($query) { $query->with('attributes'); },
                                    'categories' => function($query) { $query->where('fk_wawi_id', '=', null); },
                                    'prices', 'marketing', 'shipments',
                                    'attribute_set' => function($query) { $query->with(['groups']); },
                                    'variations' => function($query) {
                                        $query->with([
                                            'prices','branches',
                                            'images' => function($query) { $query->with('attributes'); },
                                            'attribute_set' => function($query) { $query->with(['groups']); },
                                            'attributes' => function($query) { $query->with('group'); }
                                        ]);
                                    }
                                ]);
                                $articles_send_count = $articles->count();
                                $articles = $articles->get();
                            }
                            
                            //if($this->EchologAktiv){echo "\n"."yield ".(($articles_send_count < count($articleID200))?$articles_send_count:count($articleID200))." ".$thisArticle_current."/".count($articleIDs);}
                            $promises[] = yield $client->postAsync($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, 
                            [  'content-type' => 'application/json',
                                'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles ] ] 
                            )->then(function($response) use ($shop,$articles) {
                                $status = $response->getStatusCode();
                                if($status != 200) { 
                                    Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry"); 
                                    $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);  
                                }
                            }, function($exception) use ($shop,$articles) {
                                Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry"); 
                                $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);  
                            });
                            //foreach($promises as $promise) { $promise->wait(); }
                           /* 
                            $ts2 = strtotime(date("Y-m-d H:i:s"));
                            $seconds_diff = $ts2 - $ts1; $time = ($seconds_diff);
                            if($this->EchologAktiv){echo " [OK (".$time."s)]\n";}
                            
                            $ts1 = strtotime(date("Y-m-d H:i:s")); */
                            //Article::whereIn('id', $articleID200)->update(['batch_nr' => null]);
                            $articleID200 = []; // Reset für die nächsten 200
                        }
                        
                        if(count($promises)==4)
                        {
                            if($this->EchologAktiv){echo "\n [sende ".count($promises)." Promise]....";}
                            $ts1 = strtotime(date("Y-m-d H:i:s"));
                            $eachPromise = new EachPromise($promises, 
                            [   'concurrency' => 4,
                                'fulfilled' => function (Response $response) 
                                {   $status = $response->getStatusCode();
                                    if($status != 200) {  Log::info('Fail-VSShop-Batch Sync: '.$this->for_curstomer);  }
                                    else{if($this->EchologAktiv){echo "[erfolgreich]";}}  
                                },
                                'rejected' => function ($reason) 
                                { Log::info('Fail-VSShop-Batch Sync: '.$this->for_curstomer);if($this->EchologAktiv){echo "[fail]";}}
                            ]);
                            $eachPromise->promise()->wait();
                            $ts2 = strtotime(date("Y-m-d H:i:s"));
                            $seconds_diff = $ts2 - $ts1; $time = ($seconds_diff);
                            if($this->EchologAktiv){echo " [erledigt (".$time."s)]\n";}
                            $promises=[]; $ts1 = strtotime(date("Y-m-d H:i:s")); 
                        }
                    }                    
                }
                $success_count++;
                $batchCount++;
                Article::where('batch_nr','=', $batch_nr['batch_nr'])->update(['batch_nr' => null]);
            }
            
                // $results = Promise\unwrap($promises);
            if($this->EchologAktiv){echo "\n[Fertig]"; Log::info('Erfolgreich-VSShop-Batch Sync: '.$this->for_curstomer);}
        }
        if($synchro) {
            $synchro->expected_count = $expected_count - 1;
            $synchro->success_count = $success_count;
            $synchro->failed_count = $error_count;
            $synchro->fk_synchro_status_id = $successSynchroS->id;
            $synchro->end_date = date('Y-m-d H:i:s');
            $synchro->save();
        }
        
    }

    //Make calls do the Shop API
    private function callShopAPI_neu(string $type = 'GET', $url, $options = [],$tryCount=0, $apikey) 
    {   if($tryCount < 10)
        {   
            $client = new Client();
            $failed = false;
            try {        

                $req = $client->request($type, $url,
                [  'content-type' => 'application/json',
                    'form_params'=>[json_encode($options)]
                ]);
                $status = $req->getStatusCode();
                if($status != 200) { Log::info($url.' - Typ: '.$type.' - Status: '.$status." Versuch-Nr: ".$tryCount); 
                    $tryCount++; sleep( 3 );
                    return $this->callShopAPI_neu($type, $url, $options,$tryCount, $apikey);    
                }
                $data = json_decode($req->getBody());
                $failed = false; return true; 
                

            } catch(GuzzleException $e) {
                $failed = true;
                switch($e->getCode()) {
                    case 401:
                        Log::info('Typ: '.$type.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount." VSShop API Key konnte sich nicht authentifizieren! URL: ".$url);
                    break;
                    case 429:
                    case 500:
                        Log::info('Typ: '.$type.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount." URL: ".$url);
                    break;
                    default:
                        Log::error($e->getMessage());
                    break;
                }
                $tryCount++; sleep( 3 );
                return $this->callShopAPI_neu($type, $url, $options,$tryCount, $apikey);
            }
            
        } return false;
        
    }
    public function checkArticleVSShop($article = null)
    {
        if($article)
        {   // Bedingungen :: //Preis //Artikelname //Bild //Bestand
            $articleVariations = $article->variations()->get(); // ->where('active','=',1)
            $imageCount = $article->images()->count();
            $GesamtStock = 0; $pricesCheck = 1;
            foreach($articleVariations as $articleVariation)
            {
                $GesamtStock += $articleVariation->getStock(); 
                $imageCount += $articleVariation->images()->count();  
                if(!$articleVariation->prices()->first()){$pricesCheck = 0;} 
            }
            
            if(
            $pricesCheck == 0
            || $article->filledName() == "" || empty($article->filledName())
            || $imageCount <= 0
            || $GesamtStock == 0
            ){ usleep(500000);                 
                foreach($this->shops as $shop) {
                    if($shop->url == null || $shop->apikey == null) { continue; }
                    else{$this->callShopApi('PUT', $shop->url.'/api/v1'.'/deactive_article/'.$article->id.'?apikey='.$shop->apikey, []);}
                } return false; }
            return true;
        }
        return false;
    }
    //Make calls do the Shop API    
    private function callShopAPI(string $type = 'GET', $url, $options = [],$tryCount=0) 
    {   if($tryCount < 10){
            $client = new Client();
            try
            {   $req = $client->request($type, $url, $options);
                $status = $req->getStatusCode();
                $data = json_decode($req->getBody());
                if($status != 200) 
                { Log::info($url.' - Typ: '.$type.' - Status: '.$status." Versuch-Nr: ".$tryCount); }
            }
            catch(BadResponseException $e) {
                if ($e->hasResponse()) {
                    if(($e->getCode()==429)||($e->getCode()==500))
                    { Log::info($url.' - Typ: '.$type.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount); }
                    else{Log::error($e->getMessage());}                
                    $tryCount++; sleep( 3 );
                    return $this->callShopApi($type, $url, $options,$tryCount);
                }
                
            }
        } return false;        
    }
}
