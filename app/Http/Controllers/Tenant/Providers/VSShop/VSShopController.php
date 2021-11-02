<?php

namespace App\Http\Controllers\Tenant\Providers\VSShop;

use App\Http\Controllers\Controller;
use App\Tenant\Article, App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Attribute, App\Tenant\Article_Price, App\Tenant\Article_Marketing;
use App\Tenant\Article_Variation;
use App\Tenant\ArticleProvider;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Article_Variation_Price;
use App\Tenant\Category;
use App\Tenant\BranchArticle_Variation;
use App\Tenant\Provider, App\Tenant\Provider_Type, App\Tenant\Provider_Config;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Sparesets;use App\Tenant\Sparesets_Articles;use App\Tenant\Sparesets_Categories;use App\Tenant\Sparesets_SpareArticles;
use App\Tenant\Equipmentsets;use App\Tenant\Equipmentsets_Articles;use App\Tenant\Equipmentsets_Categories;use App\Tenant\Equipmentsets_EquipmentArticles;
use App\Tenant\Attribute_Group;
use App\Tenant\Customer;
use App\Tenant\Price_Customer_Articles;
use App\Tenant\Price_Customer_Categories;
use App\Tenant\Price_Groups_Articles;
use App\Tenant\Price_Groups_Categories;
use App\Tenant\Price_Groups_Customers;
use App\Tenant\PaymentConditions;
use App\Tenant\Price_Groups;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Stream\Stream;

use App\Http\Controllers\Tenant\ArticleController;
use Log;


use Illuminate\Support\Facades\Http;

use App\Jobs\VSShopSyncArticles;

class VSShopController extends Controller
{

    private $shops = [];

    /**
     * @author Tanju Özsoy
     * 09.01.2021
     * Wir erstellen eine Klassenfunktion, die ein Singleton einer Instanz der eigenen Klasse zurückliefert.
     *
     *
     */
    private static $main_sender=null;
    public static function getMainSender() : VSShopController {
        if(!self::$main_sender){
            self::$main_sender=new VSShopController();
        }
        return self::$main_sender;
    }

    public function __construct() {



        $type = Provider_Type::where('provider_key','=','shop')->first();
        if($type) { $this->shops = Provider::where('fk_provider_type','=', $type->id)->get(); }
    }

    public function syncShopArticles(Request $request,$for_curstomer = "", $EchologAktiv = false)
    {
        /*
            $collection->each(function ($item, $key) {
                ProcessItem::dispatch($item)->onQueue('processing');
            });
        */
        //$this->dispatch(new VSShopSyncArticles($this->request,$this->for_curstomer, $this->EchologAktiv));
        //VSShopSyncArticles::dispatch($this->request,$this->for_curstomer, $this->EchologAktiv);
    }

    //START SEND DATA
    public static function schedulePromise($callable, ...$args)
    {
        register_shutdown_function(function ($callable, ...$args) {
            @session_write_close();
            @ignore_user_abort(true);
            call_user_func($callable, ...$args);
        }, $callable, ...$args);
    }
    //Article Batch
    public function article_batch($for_curstomer = "", $EchologAktiv = false, $only_article_id = false,Array $article_list=[])
    {
        $excludeCustomers = ['demo1','demo2', 'demo3','stilfaktor'];
        if(in_array($for_curstomer, $excludeCustomers) && !$EchologAktiv) { return; }


        //Log::info("Starte Batch: ".$for_curstomer);
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
        $promisesNeu=[];
        $customers = [];//Customer::with(['payment_conditions','customer_article_prices','customer_category_vouchers'])->get();
        $Tenant_type = config()->get('tenant.tenant_type');
        $client = new Client();
        $ts1 = strtotime(date("Y-m-d H:i:s"));
        if(!empty($batches))
        {
            if($EchologAktiv){echo "\n"."pre Cat Batch für ".$for_curstomer;}
            // Kategorien seperat vorher übertragen
            foreach($this->shops as $shop)
            {   if($shop->url == null || $shop->apikey == null) { continue; }
                $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $shop->id]);
                $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
                $shop_sortingDB = ($shop_sortingDB && $shop_sortingDB != "") ? json_decode($shop_sortingDB->value) : [];
                /*
                $categories = Category::with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();
                */


                /**
                 * Tanju Özsoy 16.03.2021 Umstellung auf Kategoriebäume
                 */
                $standard_provider=Category::getSystemStandardProvider()['provider'];
                $categories = $standard_provider->realCategories()->with(['category_vouchers','sparesets_categories','equipmentsets_categories'])
                            ->where('fk_wawi_id','=',null)
                            ->get();

                if($EchologAktiv){echo "\n"."sende Kategorien ".count($categories);}
                $this->callTenantShops('POST','/article_batch', ['body' => json_encode(['articles' =>[], 'categories' =>$categories, 'customers' =>[],'shop_sorting'=>$shop_sortingDB ])], $shop);
            }
            if($EchologAktiv){echo " [OK]";}

            $batchCount = 0;
            if($EchologAktiv){echo "\n"."starte Batch für ".$for_curstomer;}
            foreach($batches as $batch_nr)
            {
                if($batch_nr['batch_nr'] == null) { continue; }

                if($EchologAktiv){echo "\n"."Batch Nr: ".$batch_nr['batch_nr'];}
                foreach($this->shops as $shop) {
                    $shop_id = $shop->id;
                    if($shop->url == null || $shop->apikey == null) { continue; }
                    /**
                     * @author Tanju Özsoy <email@email.com>
                     * 08.01.2021
                     *
                     *
                     */
                    $max=5; // in Xer Abfragen abarbeiten
                    $articleIDs = Article::inRandomOrder()->where('batch_nr','=',$batch_nr['batch_nr'])->whereHas('provider', function($query) use($shop_id) { $query->where('fk_provider_id', $shop_id)->where('active','=', 1); })->get()->pluck("id");

                    /**
                     * @author Tanju Özsoy
                     * 11.01.2021
                     * Wird eine Liste mit Artikeln übergeben, so grenzen wir das auch auf die Liste ein
                     */

                    if(isset($article_list)){
                        if(count($article_list)){
                            $articleIDs = Article::inRandomOrder()->whereIn('id',$article_list)->where('batch_nr','=',$batch_nr['batch_nr'])->whereHas('provider', function($query) use($shop_id) { $query->where('fk_provider_id', $shop_id)->where('active','=', 1); })->get()->pluck("id");
                        }
                    }

                    $current=0; $thisArticle_current=0;
                    $articleID200 = [];
                    foreach($articleIDs as $articleID)
                    { $current++;$thisArticle_current++;
                        //Prüfung ob Artikel im Shop deaktiviert oder aktualisiert werden soll
                        $CheckArticle = Article::where('id','=', $articleID)->first();
                        $check=$this->checkArticleVSShop($CheckArticle);

                        //#######
                        //$check=true;
                        //#######
                        if($check){$articleID200[] = $articleID;}else{ Article::where('id', $articleID)->update(['batch_nr' => null]); if($EchologAktiv){echo (($check)?"":" [".$articleID.":0]");}}



                        if($current==$max || $thisArticle_current==count($articleIDs))
                        {   $current=0;
                            //$articles_send_count=0;

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
                                ])->get();
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
                                ])->get();
                            }

                            if($articles){array_push($promises,$articles);}


                            $current=0;

                            $articleID200 = []; // Reset für die nächsten 200
                        }





                    }

                    /*
                        if(count($promises)==3 || ($thisArticle_current==count($articleIDs)&&count($promises)>0))
                        {
                            $articles1 = false;$articles2 = false;$articles3 = false;
                            $ts1 = strtotime(date("Y-m-d H:i:s"));
                            if(count($promises)>0 && isset($promises[0])){$articles1 = $promises[0];}
                            if(count($promises)>1 && isset($promises[1])){$articles2 = $promises[1];}
                            if(count($promises)>2 && isset($promises[2])){$articles3 = $promises[2];}

							$countSendArtikel = ((($articles1))?count($articles1):0)+((($articles2))?count($articles2):0)+((($articles3))?count($articles3):0);
                            $countSendArtikel=0;

                            if($countSendArtikel>0)
                            {
                                if($EchologAktiv){echo "\n [sende (".$countSendArtikel.") ".$thisArticle_current."/".count($articleIDs)."]....";}
                                if(($articles1))
                                {
                                    $client1 = new Client();
                                    $client1->postAsync($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                    [  'content-type' => 'application/json',
                                        'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles1 ] ]
                                    )->then(function($response) use (&$shop,&$articles1,&$EchologAktiv) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                            $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles1, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                        }else{if($EchologAktiv){echo "[OK#1]";}
                                            $thisIDs = [];foreach($articles1 as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$articles1,&$EchologAktiv) {
                                        if($EchologAktiv){echo "[fail#1]";}
                                        Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                        $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles1, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                    })->wait();
                                }
                                if(($articles2))
                                {   $client2 = new Client();
                                    $client2->postAsync($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                    [  'content-type' => 'application/json',
                                        'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles2 ] ]
                                    )->then(function($response) use (&$shop,&$articles2,&$EchologAktiv) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                            $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles2, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                        }else{if($EchologAktiv){echo "[OK#2]";}
                                            $thisIDs = [];foreach($articles2 as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$articles2,&$EchologAktiv) {
                                        if($EchologAktiv){echo "[fail#2]";}
                                        Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                        $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles2, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                    })->wait();
                                }
                                if(($articles3))
                                {   $client3 = new Client();
                                    $client3->postAsync($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                    [  'content-type' => 'application/json',
                                        'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles3 ] ]
                                    )->then(function($response) use (&$shop,&$articles3,&$EchologAktiv) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                            $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles3, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                        }else{if($EchologAktiv){echo "[OK#3]";}
                                            $thisIDs = [];foreach($articles3 as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$articles3,&$EchologAktiv) {
                                        if($EchologAktiv){echo "[fail#3]";}
                                        Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                        $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles3, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                    })->wait();
                                }

                                $ts2 = strtotime(date("Y-m-d H:i:s"));
                                $seconds_diff = $ts2 - $ts1; $time = ($seconds_diff);
                                if($EchologAktiv){echo " [erledigt (".$time."s)]";}
                            }

                            $promisesNeu=[];
                            foreach($promises as $promis){
                                $promisesNeu[]=['anzahl'=>count($promis)];
                            }
                            return ['gesamt'=>$articleIDs,
                                'übertragen'=>$promisesNeu];

                            $promises=[]; $ts1 = strtotime(date("Y-m-d H:i:s"));
                        }
                        */
                    /**
                         * @author Tanju Özsoy <oezsoy@visc-media.de>
                         * 08.01.2021
                         * Anpassung des paketierten Versands!
                         */

                        if(count($promises)>0)
                        {
                            $articles=null;
                            $ts1 = strtotime(date("Y-m-d H:i:s"));


                            $countSendArtikel=0;
                            foreach($promises as $promis){
                                if($promis){
                                    $countSendArtikel+=$promis->count();
                                }
                            }


                            //$countSendArtikel=0;

                            if($countSendArtikel>0)
                            {
                                if($EchologAktiv){echo "\n [sende (".$countSendArtikel.") ".$thisArticle_current."/".count($articleIDs)."]....";}

                                foreach($promises as $promis){

                                    try {

                                        $articles=$promis;

                                        $client = new Client();
                                        $req=
                                        $client->post($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                        [  'content-type' => 'application/json',
                                            'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles ] ]
                                        );
                                        //$req=$client->post($url_authorization,['json'=> $body,'debug' => true]);

                                        $status = $req->getStatusCode();
                                        $BodyClose = $req->getBody()->getContents();


                                        //$BodyClose->close();
                                        if($status != 200) {
                                            Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                            $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                            ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                        }
                                        else
                                        {
                                            if($EchologAktiv)
                                            {
                                                echo "[OK#1]";
                                            }
                                            $thisIDs = [];foreach($articles as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                        $data = json_decode($BodyClose);

                                        //Und weiter geht es nach dem Try-Catch-Block

                                    }
                                    /*
                                    catch(GuzzleException $e) {
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
                                    */
                                    catch(\Exception $e){
                                        if($EchologAktiv){echo "[fail#1]";}
                                        Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                        $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);

                                        if($return){
                                            $thisIDs = [];foreach($articles as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }



                                    /*
                                    $client = new Client();
                                    $client->postAsync($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                    [  'content-type' => 'application/json',
                                        'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles ] ]
                                    )->then(function($response) use (&$shop,&$articles,&$EchologAktiv) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                            $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                        }else{if($EchologAktiv){echo "[OK#1]";}
                                            $thisIDs = [];foreach($articles as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$articles,&$EchologAktiv) {
                                        if($EchologAktiv){echo "[fail#1]";}
                                        Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                        $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                    })->wait();
                                    */


                                    /*
                                    $articles=$promis;
                                    $client = new Client();
                                    $client->postAsync($shop->url.'/api/v1/article_batch?apikey='.$shop->apikey,
                                    [  'content-type' => 'application/json',
                                        'json' =>  [ 'customers' => [],'categories' => [],'articles' => $articles ] ]
                                    )->then(function($response) use (&$shop,&$articles,&$EchologAktiv) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                            $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                        }else{if($EchologAktiv){echo "[OK#1]";}
                                            $thisIDs = [];foreach($articles as $article){$thisIDs[]=$article->id;}
                                            Article::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$articles,&$EchologAktiv) {
                                        if($EchologAktiv){echo "[fail#1]";}
                                        Log::info('Fail: '.$shop->url.'/api/v1/article_batch?apikey='.$shop->apikey." retry");
                                        $return = $this->callShopAPI_neu('POST', $shop->url.'/api/v1/article_batch?apikey='.$shop->apikey, ['articles' => $articles, 'categories' =>[], 'customers' =>[] ],1, $shop->apikey);
                                    })->wait();
                                    */
                                }


                                $ts2 = strtotime(date("Y-m-d H:i:s"));
                                $seconds_diff = $ts2 - $ts1; $time = ($seconds_diff);
                                if($EchologAktiv){echo " [erledigt (".$time."s)]";}
                            }

                            //########
                            /*
                            foreach($promises as $promis){
                                $promisesNeu[]=['anzahl'=>count($promis)];
                            }
                            */
                            //#######

                            $promises=[]; $ts1 = strtotime(date("Y-m-d H:i:s"));
                        }
                }
                $success_count++;
                $batchCount++;
                //Article::where('batch_nr','=', $batch_nr['batch_nr'])->update(['batch_nr' => null]);
            }
            if($EchologAktiv){echo "\n[Fertig]";}
            Log::info("Artikel Batch abgeschlossen: ".$for_curstomer);
        }
        if($synchro) {
            $synchro->expected_count = $expected_count - 1;
            $synchro->success_count = $success_count;
            $synchro->failed_count = $error_count;
            $synchro->fk_synchro_status_id = $successSynchroS->id;
            $synchro->end_date = date('Y-m-d H:i:s');
            $synchro->save();
        }

        //######
        /*
        if(isset($promisesNeu)){
            return ['gesamt'=>count($articleIDs),
                                'übertragen'=>$promisesNeu];
        }
        */
    }

    //Article
    public function update_article(Article $article) {
        $sendArticle = Article::with(['categories'])->where('id','=',$article->id)->first();
        if($sendArticle && $article->batch_nr == null) { $this->callTenantShops('PUT','/articles/'.$article->id, ['body' => $sendArticle], false, $article); }
    }
    public static function update_article_job(Article $article){
        dispatch(function()use($article){
            $sender=VSShopController::getMainSender();
            $sender->update_article($article);
        });
    }
    public function create_article(Article $article) {
        if($article->batch_nr == null) { $this->callTenantShops('POST','/articles', ['body' => $article], false, $article); }
    }
    public static function create_article_job(Article $article){
        dispatch(function() use ($article){
            $sender=VSShopController::getMainSender();
            $sender->create_article($article);
        });
    }
    public function delete_article(Article $article) {
        if($article->batch_nr == null) { $this->callTenantShops('POST','/articles_delete/'.$article->id,[], false, $article); }
    }
    public static function delete_article_job(Article $article){
        dispatch(function() use ($article){
            $sender=VSShopController::getMainSender();
            $sender->delete_article($article);
        });
    }
    //ArticleProvider
    public function update_articleprovider(ArticleProvider $articleprovider) {
        $article = $articleprovider->article()->first();
        $shop = $articleprovider->provider()->first();
        if($articleprovider->active == 1) { $this->callTenantShops('PUT','/activate_article/'.$article->id, [], $shop, $article); }
        else { usleep(500000); $this->callTenantShops('PUT','/deactive_article/'.$article->id, [], $shop, $article); }
    }
    public static function update_articleprovider_job(ArticleProvider $articleprovider){
        dispatch(function() use($articleprovider){
            $sender=VSShopController::getMainSender();
            $sender->update_articleprovider($articleprovider);
        });
    }
    public function create_articleprovider(ArticleProvider $articleprovider) {
        $article = $articleprovider->article()->first();
        $shop = $articleprovider->provider()->first();
        if($article->batch_nr == null) {
            if($articleprovider->active == 1) { $this->callTenantShops('PUT','/activate_article/'.$article->id, [], $shop, $article); }
            else { usleep(500000); $this->callTenantShops('PUT','/deactive_article/'.$article->id, [], $shop, $article); }
        }
    }
    public static function create_articleprovider_job(ArticleProvider $articleprovider){
        dispatch(function() use($articleprovider){
            $sender=VSShopController::getMainSender();
            $sender->create_articleprovider($articleprovider);
        });
    }
    public function delete_articleprovider(ArticleProvider $articleprovider) {
        $article = $articleprovider->article()->first();
        $shop = $articleprovider->provider()->first();
        usleep(500000); $this->callTenantShops('PUT','/deactive_article/'.$article->id, [], $shop, $article);
    }
    public static function delete_articleprovider_job(ArticleProvider $articleprovider){
        dispatch(function() use($articleprovider){
            $sender=VSShopController::getMainSender();
            $sender->delete_articleprovider($articleprovider);
        });
    }
    //Article_Attribute
    public function update_article_attr(Article_Attribute $attr) {
        $article = $attr->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT','/attributes/'.$attr->id, ['body' => $attr], false, $article); }
    }
    public static function update_article_attr_job(Article_Attribute $attr){
        dispatch(function() use($attr){
            $sender=VSShopController::getMainSender();
            $sender->update_article_attr($attr);
        });
    }
    public function update_article_attr_manuell(Article_Attribute $attr) {
        $article = $attr->article()->first();
        if($article) { $this->callTenantShops('PUT','/attributes/'.$attr->id, ['body' => $attr], false, $article); }
    }
    public function create_article_attr(Article_Attribute $attr) {
        $article = $attr->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/attributes', ['body' => $attr], false, $article); }
    }
    public static function create_article_attr_job(Article_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->create_article_attr($attr);
        });
    }
    public function delete_article_attr(Article_Attribute $attr) {
        $article = $attr->article()->first();
        if($article /*&& $article->batch_nr == null*/) { $this->callTenantShops('POST','/attributes_delete/'.$attr->id,[], false, $article); }
    }
    public static function delete_article_attr_job(Article_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_attr($attr);
        });
    }
    //Article_Image
    public function update_article_image(Article_Image $article_image) {
        $article = $article_image->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article_image->article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT','/images/'.$article_image->id, ['body' => $article_image], false, $article); }
    }
    public static function update_article_image_job(Article_image $article_image){
        dispatch(function()use($article_image){
            $sender=VSShopController::getMainSender();
            $sender->update_article_image($article_image);
        });
    }
    public function create_article_image(Article_Image $article_image) {
        $article = $article_image->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article_image->article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/images', ['body' => $article_image], false, $article); }
    }
    public static function create_article_image_job(Article_image $article_image){
        dispatch(function()use($article_image){
            $sender=VSShopController::getMainSender();
            $sender->create_article_image($article_image);
        });
    }
    public function delete_article_image(Article_Image $article_image) {
        $article = $article_image->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article_image->article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/images_delete/'.$article_image->id,[], false, $article); }
    }
    public static function delete_article_image_job(Article_image $article_image){
        dispatch(function()use($article_image){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_image($article_image);
        });
    }
    //Article_Image_Attribute
    public function update_article_image_attr(Article_Image_Attribute $article_image_attr) {
        $image = $article_image_attr->image()->first();
        $article = $image->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT','/image_attributes/'.$article_image_attr->id, ['body' => $article_image_attr], false, $article); }
    }
    public static function update_article_image_attr_job(Article_Image_Attribute $article_image_attr){
        dispatch(function()use($article_image_attr){
            $sender=VSShopController::getMainSender();
            $sender->update_article_image_attr($article_image_attr);
        });
    }
    public function create_article_image_attr(Article_Image_Attribute $article_image_attr) {
        $image = $article_image_attr->image()->first();
        $article = $image->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/image_attributes', ['body' => $article_image_attr], false, $article); }
    }
    public static function create_article_image_attr_job(Article_Image_Attribute $article_image_attr){
        dispatch(function()use($article_image_attr){
            $sender=VSShopController::getMainSender();
            $sender->create_article_image_attr($article_image_attr);
        });
    }
    public function delete_article_image_attr(Article_Image_Attribute $article_image_attr) {
        $image = $article_image_attr->image()->first();
        if($image)
        {   $article = $image->article()->first();
            if($article){ if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/image_attributes_delete/'.$article_image_attr->id,[], false, $article); } }
        }

    }
    public static function delete_article_image_attr_job(Article_Image_Attribute $article_image_attr){
        dispatch(function()use($article_image_attr){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_image_attr($article_image_attr);
        });
    }
    //Article_Price
    public function update_article_price(Article_Price $article_price) {
        $article = $article_price->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article_price->article);
        if($article && $article->batch_nr == null && $article_price->batch_nr == null) {usleep(500000);  $this->callTenantShops('PUT','/prices/'.$article_price->id, ['body' => $article_price], false, $article); }

    }
    public static function update_article_price_job(Article_Price $article_price){
        dispatch(function()use($article_price){
            $sender=VSShopController::getMainSender();
            $sender->update_article_price($article_price);
        });
    }
    public function create_article_price(Article_Price $article_price) {
        $article = $article_price->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article_price->article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/prices', ['body' => $article_price], false, $article); }
    }
    public static function create_article_price_job(Article_Price $article_price){
        dispatch(function()use($article_price){
            $sender=VSShopController::getMainSender();
            $sender->create_article_price($article_price);
        });
    }
    public function delete_article_price(Article_Price $article_price) {
        $article = $article_price->article()->first();

        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/prices_delete/'.$article_price->id,[], false, $article); }
    }
    public static function delete_article_price_job(Article_Price $article_price){
        dispatch(function()use($article_price){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_price($article_price);
        });
    }
    //Article_Marketing
    public function update_article_marketing(Article_Marketing $marketing) {
        $article = $marketing->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($marketing->article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT','/marketing/'.$marketing->id, ['body' => $marketing], false, $article); }
    }
    public static function update_article_marketing_job(Article_Marketing $marketing){
        dispatch(function()use($marketing){
            $sender=VSShopController::getMainSender();
            $sender->update_article_marketing($marketing);
        });
    }
    public function create_article_marketing(Article_Marketing $marketing) {
        $article = $marketing->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($marketing->article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/marketing', ['body' => $marketing], false, $article); }
    }
    public static function create_article_marketing_job(Article_Marketing $marketing){
        dispatch(function()use($marketing){
            $sender=VSShopController::getMainSender();
            $sender->create_article_marketing($marketing);
        });
    }
    public function delete_article_marketing(Article_Marketing $marketing) {
        $article = $marketing->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST','/marketing_delete/'.$marketing->id,[], false, $article); }
    }
    public static function delete_article_marketing_job(Article_Marketing $marketing){
        dispatch(function()use($marketing){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_marketing($marketing);
        });
    }
    //Article_Variation
    public function update_article_variation(Article_Variation $article_variation) {
        $article = $article_variation->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT', '/variations/'.$article_variation->id, ['body' => $article_variation], false, $article); }
    }
    public static function update_article_variation_job(Article_Variation $article_variation){
        dispatch(function()use($article_variation){
            $sender=VSShopController::getMainSender();
            $sender->update_article_variation($article_variation);
        });
    }
    public function create_article_variation(Article_Variation $article_variation) {
        $article = $article_variation->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variations', ['body' => $article_variation], false, $article); }
    }
    public static function create_article_variation_job(Article_Variation $article_variation){
        dispatch(function()use($article_variation){
            $sender=VSShopController::getMainSender();
            $sender->create_article_variation($article_variation);
        });
    }
    public function delete_article_variation(Article_Variation $article_variation) {
        $article = $article_variation->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variations_delete/'.$article_variation->id,[], false, $article); }
    }
    public static function delete_article_variation_job(Article_Variation $article_variation){
        dispatch(function()use($article_variation){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_variation($article_variation);
        });
    }
    //Article_Variation_Attribute
    public function update_article_variation_attr_manuell(Article_Variation_Attribute $attr) {
        $article = $attr->variation()->first()->article()->first();
        if($article) { $this->callTenantShops('PUT', '/variation_attributes/'.$attr->id, ['body' => $attr], false, $article); }

    }
    public static function update_article_variation_attr_job(Article_Variation_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->update_article_variation_attr($attr);
        });

    }
    public function update_article_variation_attr(Article_Variation_Attribute $attr) {
        $article = $attr->variation()->first()->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT', '/variation_attributes/'.$attr->id, ['body' => $attr], false, $article); }
    }
    public function create_article_variation_attr(Article_Variation_Attribute $attr) {
        $article = $attr->variation()->first()->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variation_attributes', ['body' => $attr], false, $article); }
    }
    public static function create_article_variation_attr_job(Article_Variation_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->create_article_variation_attr($attr);
        });
    }
    public function delete_article_variation_attr(Article_Variation_Attribute $attr) {
        $article = $attr->variation()->first()->article()->first();
        if($article /* && $article->batch_nr == null*/) { $this->callTenantShops('POST', '/variation_attributes_delete/'.$attr->id,[], false, $article); }
    }
    public static function delete_article_variation_attr_job(Article_Variation_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_variation_attr($attr);
        });
    }
    //Article_Variation_Image
    public function update_article_variation_image(Article_Variation_Image $image) {
        $article = $image->variation()->first()->article()->first();
         /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT', '/variation_images/'.$image->id, ['body' => $image], false, $article); }
    }
    public static function update_article_variation_image_job(Article_Variation_Image $image){
        dispatch(function()use($image){
            $sender=VSShopController::getMainSender();
            $sender->update_article_variation_image($image);
        });
    }
    public function create_article_variation_image(Article_Variation_Image $image) {
        $article = $image->variation()->first()->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variation_images', ['body' => $image], false, $article); }
    }
    public static function create_article_variation_image_job(Article_Variation_Image $image){
        dispatch(function()use($image){
            $sender=VSShopController::getMainSender();
            $sender->create_article_variation_image($image);
        });
    }
    public function delete_article_variation_image(Article_Variation_Image $image) {
        $article = $image->variation()->first()->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variation_images_delete/'.$image->id,[], false, $article); }
    }
    public static function delete_article_variation_image_job(Article_Variation_image $image){
        dispatch(function()use($image){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_variation_image($image);
        });
    }
    //Article_Variation_Image_Attribute
    public function update_article_variation_image_attr(Article_Variation_Image_Attribute $attr) {
        $article = $attr->image()->first()->variation()->first()->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('PUT', '/variation_image_attributes/'.$attr->id, ['body' => $attr], false, $article); }
    }
    public static function update_article_variation_image_attr_job(Article_Variation_Image_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->update_article_variation_image_attr($attr);
        });
    }
    public function create_article_variation_image_attr(Article_Variation_Image_Attribute $attr) {
        $article = $attr->image()->first()->variation()->first()->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variation_image_attributes', ['body' => $attr], false, $article); }
    }
    public static function create_article_variation_image_attr_job(Article_Variation_Image_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->create_article_variation_image_attr($attr);
        });
    }
    public function delete_article_variation_image_attr(Article_Variation_Image_Attribute $attr) {
        $article = $attr->image()->first()->variation()->first()->article()->first();
        if($article && $article->batch_nr == null) { $this->callTenantShops('POST', '/variation_image_attributes_delete/'.$attr->id,[], false, $article); }
    }
    public static function delete_article_variation_image_attr_job(Article_Variation_Image_Attribute $attr){
        dispatch(function()use($attr){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_variation_image_attr($attr);
        });
    }
    //Article_Variation_Price
    public function update_article_variation_price(Article_Variation_Price $price) {
        $article = $price->variation()->first()->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($article /*&& $article->batch_nr == null*/) { $this->callTenantShops('PUT','/variation_prices/'.$price->id, ['body' => $price], false, $article); }
    }
    public static function update_article_variation_price_job(Article_Variation_Price $price){
        dispatch(function()use($price){
            $sender=VSShopController::getMainSender();
            $sender->update_article_variation_price($price);
        });
    }
    public function create_article_variation_price(Article_Variation_Price $price) {

        $article = $price->variation()->first()->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($article /*&& $article->batch_nr == null*/) { $this->callTenantShops('POST','/variation_prices', ['body' => $price], false, $article); }
    }
    public static function create_article_variation_price_job(Article_Variation_price $price){
        dispatch(function()use($price){
            $sender=VSShopController::getMainSender();
            $sender->create_article_variation_price($price);
        });
    }
    public function delete_article_variation_price(Article_Variation_Price $price) {
        $article = $price->variation()->first()->article()->first();
        if($article /*&& $article->batch_nr == null*/ ) { $this->callTenantShops('POST','/variation_prices_delete/'.$price->id,[], false, $article); }
    }
    public static function delete_article_variation_price_job(Article_Variation_Price $price){
        dispatch(function()use($price){
            $sender=VSShopController::getMainSender();
            $sender->delete_article_variation_price($price);
        });
    }
    //Categories
    public function update_category(Category $category) { $this->callTenantShops('PUT','/categories/'.$category->id, ['body' => $category]); }
    public static function update_category_job(Category $category){
        dispatch(function()use($category){
            $sender=VSShopController::getMainSender();
            $sender->update_category($category);
        });
    }
    public function create_category(Category $category) { $this->callTenantShops('POST','/categories', ['body' => $category]); }
    public static function create_category_job(Category $category){
        dispatch(function()use($category){
            $sender=VSShopController::getMainSender();
            $sender->create_category($category);
        });
    }
    public function delete_category(Category $category) { $this->callTenantShops('POST','/categories_delete/'.$category->id); }
    public static function delete_category_job(Category $category){
        dispatch(function()use($category){
            $sender=VSShopController::getMainSender();
            $sender->delete_category($category);
        });
    }
    //Variation Branch Stock Batch
    public function price_batch($customer,$EchologAktiv=false) {
        $success_count = 0; $Tenant_type = config()->get('tenant.tenant_type');
        $error_count = 0;
        $batches = Article_Variation_Price::select('batch_nr')->distinct()->get()->toArray();
        $expected_count = count($batches);
        $updatedEans = []; $promises=[];
        if(!empty($batches)) {

            $batchCount = 0;
            foreach($batches as $batch_nr)
            {
                if($batch_nr['batch_nr'] == null) { continue; }

                if($EchologAktiv){echo "\n"."Batch Nr: ".$batch_nr['batch_nr'];}
                foreach($this->shops as $shop) {
                    $shop_id = $shop->id;

                    $max=1000; $current=0; $this_current=0; $maxIDsContainer = []; // in Xer Abfragen abarbeiten
                    $PreisIDs = Article_Variation_Price::inRandomOrder()->where('batch_nr','=',$batch_nr['batch_nr'])->get()->pluck("id");

                    foreach($PreisIDs as $PreisID)
                    { $current++;$this_current++;

                        $maxIDsContainer[] = $PreisID;

                        if($current==$max || $this_current==count($PreisIDs))
                        {   $current=0;$articles_send_count=0;

                            if($Tenant_type=='vstcl-industry'){ $VarPreise = false; } // Empfänger wird später erschaffen im IND Shop
                            else{ $VarPreise = Article_Variation_Price::with(['variation'=>function($query){ $query->select('id','vstcl_identifier'); }])->whereIn('id', $maxIDsContainer)->where('batch_nr','=',$batch_nr['batch_nr'])->inRandomOrder()->get(); }

                            if($VarPreise){$promises[] = $VarPreise;}
                            $maxIDsContainer = []; // Reset für die nächsten
                        }

                        if(count($promises)==3 || ($this_current==count($PreisIDs)&&count($promises)>0))
                        {   $send1 = false;$send2 = false;$send3 = false;
                            $ts1 = strtotime(date("Y-m-d H:i:s"));
                            if(count($promises)>0 && isset($promises[0])){$send1 = $promises[0];}
                            if(count($promises)>1 && isset($promises[1])){$send2 = $promises[1];}
                            if(count($promises)>2 && isset($promises[2])){$send3 = $promises[2];}

							$countSend = ((($send1))?count($send1):0)+((($send2))?count($send2):0)+((($send3))?count($send3):0);
                            if($countSend>0)
                            {
                                if($EchologAktiv){echo "\n [sende (".$countSend.") ".$this_current."/".count($PreisIDs)."]....";}
                                if(($send1))
                                {
                                    $client1 = new Client();
                                    $thisURL1 = $shop->url.'/api/v1/prices_batch?apikey='.$shop->apikey;
                                    $client1->postAsync($thisURL1,
                                    [  'content-type' => 'application/json','json' =>  [ 'prices' => $send1 ] ]
                                    )->then(function($response) use (&$shop,&$send1,&$EchologAktiv,&$thisURL1) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            if($EchologAktiv){echo "[#1-fail]";}
                                            Log::info('['.$status.']Price-Fail: '.$thisURL1." retry");
                                            $return1 = $this->callShopAPI_neu('POST', $thisURL1, ['prices' => $send1],1, $shop->apikey);
                                        }else{
                                            $thisIDs = [];foreach($send1 as $price){$thisIDs[]=$price->id;}
                                            if($EchologAktiv){echo "\n[#1: ".json_encode($thisIDs)." ]";}
                                            Article_Variation_Price::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$send1,&$EchologAktiv,&$thisURL1) {
                                        if($EchologAktiv){echo "[#1-e-fail]";}
                                        Log::info('Price-Fail: '.$thisURL1." retry");
                                        $return1 = $this->callShopAPI_neu('POST', $thisURL1, ['prices' => $send1],1, $shop->apikey);
                                    })->wait();
                                }

                                if(($send2))
                                {
                                    $client2 = new Client();
                                    $thisURL2 = $shop->url.'/api/v1/prices_batch?apikey='.$shop->apikey;
                                    $client2->postAsync($thisURL2,
                                    [  'content-type' => 'application/json','json' =>  [ 'prices' => $send2 ] ]
                                    )->then(function($response) use (&$shop,&$send2,&$EchologAktiv,&$thisURL2) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            if($EchologAktiv){echo "[#2-fail]";}
                                            Log::info('['.$status.']Price-Fail: '.$thisURL2." retry");
                                            $return2 = $this->callShopAPI_neu('POST', $thisURL2, ['prices' => $send2],1, $shop->apikey);
                                        }else{
                                            $thisIDs = [];foreach($send2 as $price){$thisIDs[]=$price->id;}
                                            if($EchologAktiv){echo "\n[#2: ".json_encode($thisIDs)." ]";}
                                            Article_Variation_Price::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$send2,&$EchologAktiv,&$thisURL2) {
                                        if($EchologAktiv){echo "[#2-e-fail]";}
                                        Log::info('Price-Fail: '.$thisURL2." retry");
                                        $return2 = $this->callShopAPI_neu('POST', $thisURL2, ['prices' => $send2],1, $shop->apikey);
                                    })->wait();
                                }

                                if(($send3))
                                {
                                    $client3 = new Client();
                                    $thisURL3 = $shop->url.'/api/v1/prices_batch?apikey='.$shop->apikey;
                                    $client3->postAsync($thisURL3,
                                    [  'content-type' => 'application/json','json' =>  [ 'prices' => $send3 ] ]
                                    )->then(function($response) use (&$shop,&$send3,&$EchologAktiv,&$thisURL3) {
                                        $status = $response->getStatusCode();
                                        $BodyClose = $response->getBody()->close();
                                        if($status != 200) {
                                            if($EchologAktiv){echo "[#3-fail]";}
                                            Log::info('['.$status.']Price-Fail: '.$thisURL3." retry");
                                            $return3 = $this->callShopAPI_neu('POST', $thisURL3, ['prices' => $send3],1, $shop->apikey);
                                        }else{
                                            $thisIDs = [];foreach($send3 as $price){$thisIDs[]=$price->id;}
                                            if($EchologAktiv){echo "\n[#3: ".json_encode($thisIDs)." ]";}
                                            Article_Variation_Price::whereIn('id', $thisIDs)->update(['batch_nr' => null]);
                                        }
                                    }, function($exception) use (&$shop,&$send3,&$EchologAktiv,&$thisURL3) {
                                        if($EchologAktiv){echo "[#3-e-fail]";}
                                        Log::info('Price-Fail: '.$thisURL3." retry");
                                        $return3 = $this->callShopAPI_neu('POST', $thisURL3, ['prices' => $send3],1, $shop->apikey);
                                    })->wait();
                                }

                                $ts2 = strtotime(date("Y-m-d H:i:s"));
                                $seconds_diff = $ts2 - $ts1; $time = ($seconds_diff);
                                if($EchologAktiv){echo " [OK (".$time."s)]";}
                            }
                            $promises=[]; $ts1 = strtotime(date("Y-m-d H:i:s"));
                        }
                    }
                }
                $success_count++;
                $batchCount++;
            }
        }
        if($EchologAktiv){echo "\nFertig";}
        //Log::info("Preis-Batch abgeschlossen für ".$customer);
    }
    //Variation Branch Stock Batch
    public function stock_batch() {
        $success_count = 0;
        $error_count = 0;
        $batches = BranchArticle_Variation::select('batch_nr')->distinct()->get()->toArray();
        $expected_count = count($batches);
        $hasSynchro = true;
        $synchro = null;
        $updatedEans = [];
        if(!empty($batches)) {
            if(count($batches) > 1) {
                $synchroType = Synchro_Type::where('key','=','shop_stock_update')->first();
                $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
                $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
                $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
                if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS) {
                    $hasSynchro = false;
                }

                if($hasSynchro) {
                    $synchro = Synchro::create(
                        [
                            'fk_synchro_type_id' => $synchroType->id,
                            'fk_synchro_status_id' => $inProgressSynchroS->id,
                            'start_date' => date('Y-m-d H:i:s')
                        ]
                    );
                }
            }
            $batchCount = 0;
            foreach($batches as $batch_nr) {
                if($batch_nr['batch_nr'] == null) { continue; }
                $variations = [];
                $stockUpdates = BranchArticle_Variation::where('batch_nr', '=', $batch_nr['batch_nr'])->get();
                foreach($stockUpdates as $stockUpdate) {
                    if(!isset($variations[$stockUpdate->fk_article_variation_id])) {
                        $variation = Article_Variation::find($stockUpdate->fk_article_variation_id);
                        $variations[$stockUpdate->fk_article_variation_id] = $variation->getStock();
                        $updatedEans[$variation->getEan()] = $variation->getStock();
                    }
                }

                foreach($this->shops as $shop) {
                    $shop_id = $shop->id;
                    $this->callTenantShops('POST','/stock_batch', ['body' => json_encode(['stocks' => $variations])], $shop);
                }
                BranchArticle_Variation::where('batch_nr','=', $batch_nr['batch_nr'])->update(['batch_nr' => null]);
                $success_count++;
                $batchCount++;

            }
        }
        if($synchro) {
            $synchro->expected_count = $expected_count - 1;
            $synchro->success_count = $success_count;
            $synchro->failed_count = $error_count;
            $synchro->fk_synchro_status_id = $successSynchroS->id;
            $synchro->end_date = date('Y-m-d H:i:s');
            $synchro->add_data = serialize($updatedEans);
            $synchro->save();
        }
    }
    //Variation Branch Stock
    public function update_stock(BranchArticle_Variation $stock) {
        $variation = $stock->article_variation()->first();
        $article = $variation->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($variation && $stock->batch_nr == null) {
            $stock_total = $variation->getStock();
            $this->callTenantShops('PUT','/stock/'.$variation->id, ['body' => $stock_total],false,$article);
        }
    }
    public static function update_stock_job(BranchArticle_Variation $stock){
        dispatch(function()use($stock){
            $sender=VSShopController::getMainSender();
            $sender->update_stock($stock);
        });
    }
    public function create_stock(BranchArticle_Variation $stock) {
        $variation = $stock->article_variation()->first();
        $article = $variation->article()->first();
        /*
            Tanju Özsoy - 12.01.2021
        */
        $this->update_article($article);
        if($variation && $stock->batch_nr == null) {
            $stock_total = $variation->getStock();
            $this->callTenantShops('PUT','/stock/'.$variation->id, ['body' => $stock_total],false,$article);
        }
    }
    public static function create_stock_job(BranchArticle_Variation $stock){
        dispatch(function()use($stock){
            $sender=VSShopController::getMainSender();
            $sender->create_stock($stock);
        });
    }
    public function delete_stock(BranchArticle_Variation $stock) {
        $variation = $stock->article_variation()->first();
        $article = $variation->article()->first();
        if($variation && $stock->batch_nr == null) {
            $stock_total = $variation->getStock();
            $this->callTenantShops('PUT','/stock/'.$variation->id, ['body' => $stock_total],false,$article);
        }
    }
    public static function delete_stock_job(BranchArticle_Variation $stock){
        dispatch(function()use($stock){
            $sender=VSShopController::getMainSender();
            $sender->delete_stock($stock);
        });
    }

    // Zubehörsets
    public function update_eqset(Equipmentsets $eqset)
    { $this->callTenantShops('POST','/eqset/'.$eqset->id, ['body' => $eqset]); }
    public static function update_eqset_job(Equipmentsets $eqset){
        dispatch(function()use($eqset){
            $sender=VSShopController::getMainSender();
            $sender->update_eqset($eqset);
        });
    }
    public function create_eqset(Equipmentsets $eqset)
    { $this->callTenantShops('POST','/eqset', ['body' => $eqset]); }
    public static function create_eqset_job(Equipmentsets $eqset){
        dispatch(function()use($eqset){
            $sender=VSShopController::getMainSender();
            $sender->create_eqset($eqset);
        });
    }
    public function delete_eqset(Equipmentsets $eqset)
    { $this->callTenantShops('POST','/eqset_delete/'.$eqset->id, ['body' => $eqset]); }
    public static function delete_eqset_job(Equipmentsets $eqset){
        dispatch(function()use($eqset){
            $sender=VSShopController::getMainSender();
            $sender->delete_eqset($eqset);
        });
    }
    // Zubehörset Artikel
    public function update_eqset_article(Equipmentsets_Articles $eqsetArticle) { $this->callTenantShops('POST','/eqset_article/'.$eqsetArticle->id, ['body' => $eqsetArticle]); }
    public static function update_eqset_article_job(Equipmentsets_Articles $eqsetArticle){
        dispatch(function()use($eqsetArticle){
            $sender=VSShopController::getMainSender();
            $sender->update_eqset_article($eqsetArticle);
        });
    }
    public function create_eqset_article(Equipmentsets_Articles $eqsetArticle) { $this->callTenantShops('POST','/eqset_article', ['body' => $eqsetArticle]); }
    public static function create_eqset_article_job(Equipmentsets_Articles $eqsetArticle){
        dispatch(function()use($eqsetArticle){
            $sender=VSShopController::getMainSender();
            $sender->create_eqset_article($eqsetArticle);
        });
    }
    public function delete_eqset_article(Equipmentsets_Articles $eqsetArticle) { $this->callTenantShops('POST','/eqset_article_delete/'.$eqsetArticle->id, ['body' => $eqsetArticle]); }
    public static function delete_eqset_article_job(Equipmentsets_Articles $eqsetArticle){
        dispatch(function()use($eqsetArticle){
            $sender=VSShopController::getMainSender();
            $sender->delete_eqset_article($eqsetArticle);
        });
    }
    // Zubehörset Kategorie
    public function update_eqset_category(Equipmentsets_Categories $eqsetCategory) { $this->callTenantShops('POST','/eqset_category/'.$eqsetCategory->id, ['body' => $eqsetCategory]); }
    public static function update_eqset_category_job(Equipmentsets_Categories $eqsetCategory){
        dispatch(function()use($eqsetCategory){
            $sender=VSShopController::getMainSender();
            $sender->update_eqset_category($eqsetCategory);
        });
    }
    public function create_eqset_category(Equipmentsets_Categories $eqsetCategory) { $this->callTenantShops('POST','/eqset_category', ['body' => $eqsetCategory]); }
    public static function create_eqset_category_job(Equipmentsets_Categories $eqsetCategory){
        dispatch(function()use($eqsetCategory){
            $sender=VSShopController::getMainSender();
            $sender->create_eqset_category($eqsetCategory);
        });
    }
    public function delete_eqset_category(Equipmentsets_Categories $eqsetCategory) { $this->callTenantShops('POST','/eqset_category_delete/'.$eqsetCategory->id, ['body' => $eqsetCategory]); }
    public static function delete_eqset_category_job(Equipmentsets_Categories $eqsetCategory){
        dispatch(function()use($eqsetCategory){
            $sender=VSShopController::getMainSender();
            $sender->delete_eqset_category();
        });
    }
    // Zubehörset Zubehör-Artikel
    public function update_eqset_eq_article(Equipmentsets_EquipmentArticles $eqsetEQArticle) { $this->callTenantShops('POST','/eqset_eq_article/'.$eqsetEQArticle->id, ['body' => $eqsetEQArticle]); }
    public static function update_eqset_eq_article_job(Equipmentsets_EquipmentArticles $eqsetEQArticle){
        dispatch(function()use($eqsetEQArticle){
            $sender=VSShopController::getMainSender();
            $sender->update_eqset_eq_article($eqsetEQArticle);
        });
    }
    public function create_eqset_eq_article(Equipmentsets_EquipmentArticles $eqsetEQArticle) { $this->callTenantShops('POST','/eqset_eq_article', ['body' => $eqsetEQArticle]); }
    public static function create_eqset_eq_article_job(Equipmentsets_EquipmentArticles $eqsetEQArticle){
        dispatch(function()use($eqsetEQArticle){
            $sender=VSShopController::getMainSender();
            $sender->create_eqset_eq_article($eqsetEQArticle);
        });
    }
    public function delete_eqset_eq_article(Equipmentsets_EquipmentArticles $eqsetEQArticle) { $this->callTenantShops('POST','/eqset_eq_article_delete/'.$eqsetEQArticle->id, ['body' => $eqsetEQArticle]); }
    public static function delete_eqset_eq_article_job(Equipmentsets_EquipmentArticles $eqsetEQArticle){
        dispatch(function()use($eqsetEQArticle){
            $sender=VSShopController::getMainSender();
            $sender->delete_eqset_eq_article($eqsetEQArticle);
        });
    }
    // Ersatzteilsets
    public function update_spareset(Sparesets $spareset) { $this->callTenantShops('POST','/spareset/'.$spareset->id, ['body' => $spareset]); }
    public static function update_spareset_job(Sparesets $spareset){
        dispatch(function()use($spareset){
            $sender=VSShopController::getMainSender();
            $sender->update_spareset($spareset);
        });
    }
    public function create_spareset(Sparesets $spareset) { $this->callTenantShops('POST','/spareset', ['body' => $spareset]); }
    public static function create_spareset_job(Sparesets $spareset){
        dispatch(function()use($spareset){
            $sender=VSShopcontroller::getMainSender();
            $sender->create_spareset($spareset);
        });
    }
    public function delete_spareset(Sparesets $spareset) { $this->callTenantShops('POST','/spareset_delete/'.$spareset->id, ['body' => $spareset]); }
    public static function delete_spareset_job(Sparesets $spareset){
        dispatch(function()use($spareset){
            $sender=VSShopController::getMainSender();
            $sender->delete_spareset($spareset);
        });
    }
    // Ersatzteilset Artikel
    public function update_spareset_article(Sparesets_Articles $sparesetArticle) { $this->callTenantShops('POST','/spareset_article/'.$sparesetArticle->id, ['body' => $sparesetArticle]); }
    public static function update_spareset_article_job(Sparesets_Articles $sparesetArticle){
        dispatch(function()use($sparesetArticle){
            $sender=VSShopController::getMainSender();
            $sender->update_spareset_article($sparesetArticle);
        });
    }
    public function create_spareset_article(Sparesets_Articles $sparesetArticle) { $this->callTenantShops('POST','/spareset_article', ['body' => $sparesetArticle]); }
    public static function create_spareset_article_job(Sparesets_Articles $sparesetArticle){
        dispatch(function()use($sparesetArticle){
            $sender=VSShopController::getMainSender();
            $sender->create_spareset_article($sparesetArticle);
        });
    }
    public function delete_spareset_article(Sparesets_Articles $sparesetArticle) { $this->callTenantShops('POST','/spareset_article_delete/'.$sparesetArticle->id, ['body' => $sparesetArticle]); }
    public static function delete_spareset_article_job(Sparesets_Articles $sparesetArticle){
        dispatch(function()use($sparesetArticle){
            $sender=VSShopController::getMainSender();
            $sender->delete_spareset_article($sparesetArticle);
        });
    }
    // Ersatzteilset Kategorie
    public function update_spareset_category(Sparesets_Categories $sparesetCategory) { $this->callTenantShops('POST','/spareset_category/'.$sparesetCategory->id, ['body' => $sparesetCategory]); }
    public static function update_spareset_category_job(Sparesets_Categories $sparesetCategory){
        dispatch(function()use($sparesetCategory){
            $sender=VSShopController::getMainSender();
            $sender->update_spareset_category($sparesetCategory);
        });
    }
    public function create_spareset_category(Sparesets_Categories $sparesetCategory) { $this->callTenantShops('POST','/spareset_category', ['body' => $sparesetCategory]); }
    public static function create_spareset_category_job(Sparesets_Categories $sparesetCategory){
        dispatch(function()use($sparesetCategory){
            $sender=VSShopController::getMainSender();
            $sender->create_spareset_category($sparesetCategory);
        });
    }
    public function delete_spareset_category(Sparesets_Categories $sparesetCategory) { $this->callTenantShops('POST','/spareset_category_delete/'.$sparesetCategory->id, ['body' => $sparesetCategory]); }
    public static function delete_spareset_category_category(Sparesets_Categories $sparesetCategory){
        dispatch(function()use($sparesetCategory){
            $sender=VSShopController::getMainSender();
            $sender->delete_spareset_category($sparesetCategory);
        });
    }
    // Ersatzteilset Ersatzteil-Artikel
    public function update_spareset_spare_article(Sparesets_SpareArticles $sparesetSpareArticle) { $this->callTenantShops('POST','/spareset_spare_article/'.$sparesetSpareArticle->id, ['body' => $sparesetSpareArticle]); }
    public static function update_spareset_spare_article_job(Sparesets_SpareArticles $sparesetSpareArticle){
        dispatch(function()use($sparesetSpareArticle){
            $sender=VSShopController::getMainSender();
            $sender->update_spareset_spare_article($sparesetSpareArticle);
        });
    }
    public function create_spareset_spare_article(Sparesets_SpareArticles $sparesetSpareArticle) { $this->callTenantShops('POST','/spareset_spare_article', ['body' => $sparesetSpareArticle]); }
    public static function create_spareset_spare_article_job(Sparesets_SpareArticles $sparesetSpareArticle){
        dispatch(function()use ($sparesetSpareArticle){
            $sender=VSShopController::getMainSender();
            $sender->create_spareset_spare_article($sparesetSpareArticle);
        });
    }
    public function delete_spareset_spare_article(Sparesets_SpareArticles $sparesetSpareArticle) { $this->callTenantShops('POST','/spareset_spare_article_delete/'.$sparesetSpareArticle->id, ['body' => $sparesetSpareArticle]); }
    public function delete_spareset_spare_article_job(Sparesets_SpareArticles $sparesetSpareArticle){
        dispatch(function()use($sparesetSpareArticle){
            $sender=VSShopController::getMainSender();
            $sender->delete_spareset_spare_article($sparesetSpareArticle);
        });
    }
    // Attribut Gruppen aktualisieren
    public function update_attribute_group(Attribute_Group $attribute_group) { $this->callTenantShops('POST','/attribute_group/'.$attribute_group->id, ['body' => $attribute_group]); }
    public static function update_attribute_group_job(Attribute_Group $attribute_group){
        dispatch(function()use($attribute_group){
            $sender=VSShopController::getMainSender();
            $sender->update_attribute_group($attribute_group);
        });
    }
    public function create_attribute_group(Attribute_Group $attribute_group) { $this->callTenantShops('POST','/attribute_group', ['body' => $attribute_group]); }
    public static function create_attribute_group_job(Attribute_Group $attribute_group){
        dispatch(function()use($attribute_group){
            $sender=VSShopController::getMainSender();
            $sender->create_attribute_group($attribute_group);
        });
    }
    public function delete_attribute_group(Attribute_Group $attribute_group) { $this->callTenantShops('POST','/attribute_group_delete/'.$attribute_group->id, ['body' => $attribute_group]); }
    public function delete_attribute_group_job(Attribute_Group $attribute_group){
        dispatch(function()use($attribute_group){
            $sender=VSShopController::getMainSender();
            $sender->delete_attribute_group($attribute_group);
        });
    }

    public function validate_shop_items($for_customer = "",$EchologAktiv=false)
    {
        //Log::info("validiere Shops für: ".$for_curstomer);
        foreach($this->shops as $shop)
        {
            $shop_id = $shop->id;

            $articleIDs = Article::whereHas('provider', function($query) use($shop_id)
                { $query->where('fk_provider_id', $shop_id)->where('active','=', 1);
                });

            $articleimagesIDs=clone $articleIDs;
            $variationIDs=clone $articleIDs;
            $articleIDs=$articleIDs->get()->pluck("id")->toArray();

            //$articleimagesIDs = Article_Image::whereIn('fk_article_id',$articleIDs)->pluck("id")->toArray();
            $articleimagesIDs = $articleimagesIDs->join('article__images','article__images.fk_article_id','=','articles.id')
                                ->pluck("article__images.id")->toArray();


            //$variationIDs = Article_Variation::whereIn('fk_article_id',$articleIDs)->pluck("id")->toArray();
            $variationIDs = $variationIDs->join('article__variations','article__variations.fk_article_id','=','articles.id');
            $variationimagesIDs=clone $variationIDs;
            $pricesIDs=clone $variationIDs;
            $variationIDs=$variationIDs->pluck("article__variations.id")->toArray();


            $variationimagesIDs=$variationimagesIDs->join('article__variation__images','article__variation__images.fk_article_variation_id','=','article__variations.id')
                                ->pluck("article__variation__images.id")->toArray();
            //$variationimagesIDs = Article_Variation_Image::whereIn('fk_article_variation_id',$variationIDs)->pluck("id")->toArray();


            $pricesIDs=$pricesIDs->join('article__variation__prices','article__variation__prices.fk_article_variation_id','=','article__variations.id')
                        ->pluck("article__variation__prices.id")->toArray();
            //$pricesIDs = Article_Variation_Price::whereIn('fk_article_variation_id',$variationIDs)->pluck("id")->toArray();

            if($EchologAktiv)
            {echo "\n"."sende Articles ".count($articleIDs)
                ." & Article_Images ".count($articleimagesIDs)
                ." & Article_Variations ".count($variationIDs)
                ." & Article_Variation_Images ".count($variationimagesIDs)
                ." & Article_Variation_Prices ".count($pricesIDs);
            }
            $this->callTenantShops('POST','/article_verify', ['body' => json_encode([
                'articleIDs' =>(($articleIDs)?$articleIDs:[])
                ,'articleimagesIDs' =>(($articleimagesIDs)?$articleimagesIDs:[])
                ,'variationIDs' =>(($variationIDs)?$variationIDs:[])
                ,'variationimagesIDs' => (($variationimagesIDs)?$variationimagesIDs:[])
                ,'pricesIDs' => (($pricesIDs)?$pricesIDs:[])
            ])], $shop);
            if($EchologAktiv){echo " [Shop Fertig:".$shop_id."]";}
        }
    }



    // first FillShop
    public function article_fill($for_curstomer = "",$EchologAktiv=false,$account_type='',$file_output=false) {
        Log::info("Starte Article Fill: ".$for_curstomer);
        $Tenant_type = config()->get('tenant.tenant_type');
        //$customers = Customer::with(['payment_conditions','customer_article_prices','customer_category_vouchers'])->get();
        $client = new Client();
        $ts1 = strtotime(date("Y-m-d H:i:s"));
        $promises=[];
        foreach($this->shops as $shop)
        {
            if($shop->url == null || $shop->apikey == null) { continue; }
            $shop_id = $shop->id;
            if($EchologAktiv){echo "\n"."sende Artikel zum Shop: ".$shop->url;}
            $request = new \Illuminate\Http\Request();
            $Controller = new ArticleController();
            $ergebnis = $Controller->sendArticlesToShop($request,$shop_id,$EchologAktiv,$account_type,$file_output,$for_curstomer);
            if($ergebnis){ if($EchologAktiv){echo "[Shop abgeschlossen]";}   }
        }
        if($EchologAktiv){echo "\n[Fertig]";}
    }

    // INDUSTRY
    public function shop_data($for_curstomer = "",$EchologAktiv=false)
    {
        //Log::info("Sende IND-Shop Data: ".$for_curstomer);
        if($this->shops && count($this->shops)>0)
        {
            foreach($this->shops as $shop)
            {
                if($shop->url == null || $shop->apikey == null) { continue; }
                $shop_id = $shop->id;
                $payment_conditions = PaymentConditions::all();
                $customers = Customer::with(['payment_conditions'])->get();

                //$categories = Category::with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();
                 /**
                 * Tanju Özsoy 16.03.2021 Anpassung an Kategoriebäume
                 */
                $standard_provider=Category::getSystemStandardProvider()['provider'];
                $categories=$standard_provider->realCategories()->with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();

                $price_groups = Price_Groups::all();
                $price_groups_articles = Price_Groups_Articles::all();
                $price_groups_categories = Price_Groups_Categories::all();
                $price_groups_customers = Price_Groups_Customers::all();
                $price_customer_articles = Price_Customer_Articles::all();
                $price_customer_categories = Price_Customer_Categories::all();

                $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $shop->id]);
                $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
                $shop_sortingDB = ($shop_sortingDB && $shop_sortingDB != "") ? json_decode($shop_sortingDB->value) : [];

                if($EchologAktiv){echo "\n"."sende Preisgruppen + Kategorien ".count($categories)." & Customer ".count($customers);}
                $this->callTenantShops('POST','/shop_data', ['body' => json_encode([
                    'articles' =>[]
                    , 'categories' =>(($categories)?$categories:[])
                    , 'customers' =>(($customers)?$customers:[])
                    , 'payment_conditions' => (($payment_conditions)?$payment_conditions:[])
                    ,'price_groups' => (($price_groups)?$price_groups:[])
                    ,'price_groups_articles' => (($price_groups_articles)?$price_groups_articles:[])
                    ,'price_groups_categories' => (($price_groups_categories)?$price_groups_categories:[])
                    ,'price_groups_customers' => (($price_groups_customers)?$price_groups_customers:[])
                    ,'price_customer_articles' => (($price_customer_articles)?$price_customer_articles:[])
                    ,'price_customer_categories' => (($price_customer_categories)?$price_customer_categories:[])
                    ,'shop_sorting' => $shop_sortingDB
                ])], $shop);
                if($EchologAktiv){echo " [OK]";}
            }
        }
    }
    //END SEND DATA

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
            ){ usleep(500000); $this->callTenantShops('PUT','/deactive_article/'.$article->id); return false; }
            return true;
        }
        return false;
    }
    //Call function
    private function callTenantShops(string $type = 'GET', $url, $options = [], $shop = null, $article = null) {

        if($article != null) {$checkedArticle = $this->checkArticleVSShop($article);if(!$checkedArticle){return;}}

        if($shop != null) {
            if($shop->url == null || $shop->apikey == null) { return; }
            else{$this->callShopApi($type, $shop->url.'/api/v1'.$url.'?apikey='.$shop->apikey, $options);}

        }
        else {
            foreach($this->shops as $shop) {
                if($shop->url == null || $shop->apikey == null) { continue; }
                else{$this->callShopApi($type, $shop->url.'/api/v1'.$url.'?apikey='.$shop->apikey, $options);}
            }
        }
    }

    //Make calls do the Shop API
    private function callShopAPI(string $type = 'GET', $url, $options = [],$tryCount=0)
    {   if($tryCount < 10){
            $client = new Client();
            try{

                $req = $client->request($type, $url, $options);
                $status = $req->getStatusCode();
                $data = json_decode($req->getBody()); $BodyClose = $req->getBody()->close();
                if($status != 200)
                { Log::info($url.' - Typ: '.$type.' - Status: '.$status." Versuch-Nr: ".$tryCount); }
            }
            catch(BadResponseException $e) {
                if ($e->hasResponse()) {
                    if(($e->getCode()==429)||($e->getCode()==500)||($e->getCode()==503))
                    { Log::info($url.' - Typ: '.$type.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount); }
                    else{Log::error($e->getMessage());}
                    $tryCount++; sleep( 3 );
                    return $this->callShopApi($type, $url, $options,$tryCount);
                }

            }
        } return false;
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
                $status = $req->getStatusCode(); $BodyClose = $req->getBody()->close();
                if($status != 200) { Log::info($url.' - Typ: '.$type.' - Status: '.$status." Versuch-Nr: ".$tryCount);
                    $tryCount++; sleep( 3 );
                    return $this->callShopAPI_neu($type, $url, $options,$tryCount, $apikey);
                }
                $data = json_decode($BodyClose);
                $failed = false; return true;


            } catch(GuzzleException $e) {
                $failed = true;
                switch($e->getCode()) {
                    case 401:
                        Log::info('Typ: '.$type.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount." VSShop API Key konnte sich nicht authentifizieren! URL: ".$url);
                    break;
                    case 429:
                    case 500:
                        Log::info('Typ: '.$type.' - Status: '.$e->getCode()." Fail-Versuche: ".$tryCount." URL: ".$url."  Meldung: ".$e->getMessage());
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

}
