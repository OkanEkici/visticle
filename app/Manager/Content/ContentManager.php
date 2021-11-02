<?php
namespace App\Manager\Content;

use App\Tenant\Article;
use App\Tenant\Article_Variation;
use App\Tenant\ArticleProviderSync;
use App\Tenant\ArticleProviderSyncDeletion;
use App\Tenant\Provider;
use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Miscellaneous;
use App\Tenant\Article_Attribute;
use App\Tenant\Article_Image;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Marketing;
use App\Tenant\Article_Price;
use App\Tenant\Article_Shipment;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Article_Variation_Price;
use App\Tenant\Attribute_Group;
use App\Tenant\Attribute_Set;
use App\Tenant\Attribute_Sets_Attribute_Group;
use App\Tenant\BranchArticle_Variation;
use App\Tenant\Brand;
use App\Tenant\BrandsSuppliers;
use App\Tenant\Category;
use App\Tenant\CategoryArticle;
use App\Tenant\Customer;
use App\Tenant\Equipmentsets_Articles;
use App\Tenant\Equipmentsets_EquipmentArticles;
use App\Tenant\PaymentConditions;
use App\Tenant\PaymentConditionsCustomers;
use App\Tenant\Price_Customer_Articles;
use App\Tenant\Price_Customer_Categories;
use App\Tenant\Price_Groups;
use App\Tenant\Price_Groups_Articles;
use App\Tenant\Price_Groups_Customers;
use App\Tenant\Provider_Config_Attribute;
use App\Tenant\Sparesets;
use App\Tenant\Sparesets_Articles;
use App\Tenant\Sparesets_SpareArticles;
use Illuminate\Support\Facades\Log;
use stdClass;

class ContentManager{


    /*
        ####################################################################################################################################
        Jetzt kommen die Methoden, die für die Plattformen von Interesse sind im Umgang mit den Synchro-Datensätzen!!!
        ####################################################################################################################################
    */
    public function getProviderSynchroChronological($provider_id,$priority="immediate",$date_time=null){
        $this->checkPriority($priority);

        //Die Nummern holen für Operation und Priorität
        $priority_number=config("content-manager.priorities.{$priority}");

        $limit=config("plattform-manager.vsshop.transfer.limit");




        //Wir erstellen unsere Grund-Abfrage
        $query=ArticleProviderSync::query()
                ->where('fk_provider_id',$provider_id)
                ->where('priority',$priority_number)
                ->orderBy('created_at')
                ->limit($limit);

        //Wenn dein Zeitstempel übergeben wird, so berücksichtigen wir das!
        //Wir holen alle Synchros bis zu diesem Datum
        if($date_time){
            $query->whereDate('created_at','<=',$date_time);
        }
        return $query;
    }
    /**
     * Diese Methode löscht alles aus der Synchro für die IDs, die übergeben wurden.
     * Die IDs beziehen sich natürlich auf die Synchrotabelle.
     */
    public function deleteProviderSynchro_IDs($synchro_ids=[]){
        $query=ArticleProviderSync::query()
            ->whereIn('id',$synchro_ids);

        $query->delete();
    }

    /**
     * Diese Methode löscht alle Datensätze aus der Synchro!!
     *
     *
     *
     * @param $provider_id : die ID des Providers
     * @param $operation : update oder insert
     * @param $exclude : Eine Liste der Synchro-IDs, die vom Löschen ausgeschlossen werden müssen!
     * @param $priority : standard immediate, immediate oder scheduled
     * @param $subject_class : null | "no matter" | "Datenbankklassenname"
     * @param $subject_id: null | "no matter" | "ID der Datenbankklasse"
     * @param $context : null | "no matter" | "Ein beliebiger Text"
     * @param $context_value : null | "no matter" | "ein serialisierter Text"
     *
     * Erklärung zu den speziellen optionalen Parametern subject_class,subject_id, context und context_value können drei Wertetypen haben:
     * - null ==> die gefilterten Datensätze haben auch für die betreffende Spalte einen Null-wert
     * - "no matter" ==> die betreffende Spalte der gefilterten Datensätze haben einen beliebigen Wert, inklusive Null
     * - "Bestimmter Wert ausser "no matter" " ==> die betreffende Spalte der gefilterten Datensätze hat einen bestimmten Wert
     */
    public  function deleteProviderSynchro($provider_id,$operation,$exclude=[],$priority="immediate",$subject_class="no matter",$subject_id="no matter",$context="no matter",$context_value="no matter"){
        $query=$this->filterArticleProviderSync($provider_id,$operation,$priority,$subject_class,$subject_id,$context,$context_value);

        if(isset($exclude) && !empty($exclude) && is_array($exclude)){
            $query->whereNotIn('id',$exclude);
        }

        $query->delete();
    }
    /**
     * Diese Methode liefert ein Eloquent-Query-Builder Objekt zurück!!
     *
     *
     *
     * @param $provider_id : die ID des Providers
     * @param $operation : update oder insert
     * @param $priority : standard immediate, immediate oder scheduled
     * @param $subject_class : null | "no matter" | "Datenbankklassenname"
     * @param $subject_id: null | "no matter" | "ID der Datenbankklasse"
     * @param $context : null | "no matter" | "Ein beliebiger Text"
     * @param $context_value : null | "no matter" | "ein serialisierter Text"
     *
     * Erklärung zu den speziellen optionalen Parametern subject_class,subject_id, context und context_value können drei Wertetypen haben:
     * - null ==> die gefilterten Datensätze haben auch für die betreffende Spalte einen Null-wert
     * - "no matter" ==> die betreffende Spalte der gefilterten Datensätze haben einen beliebigen Wert, inklusive Null
     * - "Bestimmter Wert ausser "no matter" " ==> die betreffende Spalte der gefilterten Datensätze hat einen bestimmten Wert
     */
    public  function getProviderSynchro($provider_id,$operation,$priority="immediate",$subject_class="no matter",$subject_id="no matter",$context="no matter",$context_value="no matter"){
        $query=$this->filterArticleProviderSync($provider_id,$operation,$priority,$subject_class,$subject_id,$context,$context_value);

        return $query;
    }
    protected function filterArticleProviderSync($provider_id,$operation,$priority="immediate",$subject_class="no matter",$subject_id="no matter",$context="no matter",$context_value="no matter"){
        $this->checkOperation($operation);
        $this->checkPriority($priority);

        //Die Nummern holen für Operation und Priorität
        $operation_number=config("content-manager.operations.{$operation}");
        $priority_number=config("content-manager.priorities.{$priority}");

        //Wir erstellen unsere Grund-Abfrage
        $query=ArticleProviderSync::query()
                ->where('fk_provider_id',$provider_id)
                ->where('operation',$operation_number)
                ->where('priority',$priority_number);

        //Jetzt ergänzen wir die Abfrage um optionale Sachen

        // es muss nach einer bestimmten Datenbankklasse gesucht werden
        if($subject_class!=null && $subject_class!='no matter' && $subject_id!=null && $subject_id!='no matter'){
            $query->where('subject',$subject_class);
            $query->where('subject_id',$subject_id);
        }
        //Datenbankklasse muss null sein
        elseif($subject_class==null){
            $query->whereNull('subject');
        }

        //es muss nach einem bestimmten Kontext gesucht werden
        $consider_context=false;

        // es muss nach einem bestimmten Kontext gesucht werden
        if($context!=null && $context!='no matter'){
            $consider_context=true;
            $query->where('context',$context);
        }
        // Context muss null sein
        elseif($context==null){
            $query->whereNull('context');
        }

        //Jetzt berücksichtigen wir noch den Kontext-Wert
        if($consider_context){
            if($context_value!="no matter" && $context_value!=null){
                $query->where('context_value',$context_value);
            }
            elseif($context_value==null){
                $query->whereNull('context_value');
            }
        }

        return $query;
    }
    /*
        ####################################################################################################################################
        Ende Methoden, die für die Plattformen von Interesse sind im Umgang mit den Synchro-Datensätzen!!!
        ####################################################################################################################################
    */

    //======================================================================================================================================

    /*
        ####################################################################################################################################
        Jetzt kommt der Einstiegspunkt für alle Datenbankklassen!!!
        ####################################################################################################################################
    */
    /**
     * Registriert ein Model, dass zur Synchronisation mit den betroffenen Providern veranlassen kann.
     * Diese Methode ist der einzige Weg für das System, Änderungen mitzuteilen.
     * Alles andere sollte diese Methode veranlassen, so dass die Wechselwirkung zwischen dem System
     * und dem Contentmanager auf ein Minimum reduziert wird.
     *
     * @param Model $model
     * @param [type] $operation : eine Operationsart aus der Konfig content-manager
     * @return void
     */
    public  function registrateOperation(Model $model,$operation,$priority)
    {
        $this->checkOperation($operation);
        $this->checkPriority($priority);

        try{
            $this->switchOperation($model,$operation,$priority);
        }
        catch(\Exception $e){
            Log::channel('content_manager')->error($e->getMessage() . '---' . $e->getFile() . '---' . $e->getLine());
        }

    }
    public  function switchOperation(Model $model,$operation,$priority)
    {
        //Jetzt den reinen Klassennamen extrahieren
        /*
        $parts=explode('\\',get_class($model));
        $class_name=$parts[count($parts)-1];
        */


        $class_name=get_class($model);


        switch ($class_name)
        {
            case Article::class:
                $this->doArticleOperation($model,$operation,$priority);
                break;
            case Article_Attribute::class:
                $this->doArticleAttributeOperation($model,$operation,$priority);
                break;
            case Article_Image::class:
                $this->doArticleImageOperation($model,$operation,$priority);
                break;
            case Article_Price::class:
                $this->doArticlePriceOperation($model,$operation,$priority);
                break;
            case Article_Image_Attribute::class:
                $this->doArticleImageAttributeOperation($model,$operation,$priority);
                break;
            case Article_Marketing::class:
                $this->doArticleMarketingOperation($model,$operation,$priority);
                break;
            case Article_Shipment::class:
                $this->doArticleShipmentOperation($model,$operation,$priority);
                break;
            case Article_Variation::class:
                $this->doArticleVariationOperation($model,$operation,$priority);
                break;
            case Article_Variation_Image::class:
                $this->doArticleVariationImageOperation($model,$operation,$priority);
                break;
            case Article_Variation_Image_Attribute::class:
                $this->doArticleVariationImageAttributeOperation($model,$operation,$priority);
                break;
            case Article_Variation_Attribute::class:
                $this->doArticleVariationAttributeOperation($model,$operation,$priority);
                break;
            case Article_Variation_Price::class:
                $this->doArticleVariationPriceOperation($model,$operation,$priority);
                break;
            case Category::class:
                $this->doCategoryOperation($model,$operation,$priority);
                break;
            case CategoryArticle::class:
                $this->doCategoryArticleOperation($model,$operation,$priority);
                break;
            case Attribute_Group::class:
                $this->doAttributeGroupOperation($model,$operation,$priority);
                break;
            case Attribute_Set::class:
                $this->doAttributeSetOperation($model,$operation,$priority);
                break;
            case Attribute_Sets_Attribute_Group::class:
                $this->doAttributeSetAttributeGroupOperation($model,$operation,$priority);
                break;
            case BranchArticle_Variation::class:
                $this->doBranchArticleVariationOperation($model,$operation,$priority);
                break;
            case Brand::class:
                $this->doBrandsOperation($model,$operation,$priority);
                break;
            case BrandsSuppliers::class:
                $this->doBrandSuppliersOperation($model,$operation,$priority);
                break;
            case Customer::class:
                $this->doCustomerOperation($model,$operation,$priority);
                break;
            case Price_Groups_Customers::class:
                $this->doPricegroupsCustomersOperation($model,$operation,$priority);
                break;
            case Price_Customer_Articles::class:
                $this->doPriceCustomerArticlesOperation($model,$operation,$priority);
                break;
            case Price_Customer_Categories::class:
                $this->doPriceCustomersCategoriesOperation($model,$operation,$priority);
                break;
            case Sparesets_SpareArticles::class:
                $this->doSparesetsSpareArticlesOperation($model,$operation,$priority);
                break;
            case Sparesets::class:
                $this->doSparesetsOperation($model,$operation,$priority);
                break;
            case Sparesets_Articles::class:
                $this->doSparesetsArticlesOperation($model,$operation,$priority);
                break;
            case Equipmentsets_EquipmentArticles::class:
                $this->doEquipmentsetsEquipmentArticlesOperation($model,$operation,$priority);
                break;
            case Equipmentsets_Articles::class:
                $this->doEquipmentsetsArticlesOperation($model,$operation,$priority);
                break;
            case Price_Groups::class:
                $this->doPriceGroupsOperation($model,$operation,$priority);
                break;
            case Price_Groups_Articles::class:
                $this->doPriceGroupsArticlesOperation($model,$operation,$priority);
                break;
            case PaymentConditions::class:
                $this->doPaymentConditionsOperation($model,$operation,$priority);
                break;
            case PaymentConditionsCustomers::class:
                $this->doPaymentConditionsCustomersOperation($model,$operation,$priority);
                break;
            case Provider_Config_Attribute::class:
                $this->doProviderConfigAttributeOperation($model,$operation,$priority);
                break;
        }
    }
    /*
        ####################################################################################################################################
        Ende des Einstiegspunkts für alle Datenbankklassen!!!
        ####################################################################################################################################
    */
    //======================================================================================================================================
    /*
        ####################################################################################################################################
        Jetzt kommen die einzelnen Funktionen, die für jede Datenbankklasse in Abhängigkeit vom Provider einen Synchrodatensatz schreiben!!!
        ####################################################################################################################################
    */
    protected  function doProviderConfigAttributeOperation(Provider_Config_Attribute $provider_Config_Attribute,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            //Wir lassen auch nur Shop_sorting durch
            switch($provider->type->provider_key){
                case "shop":
                    if($provider_Config_Attribute->config->provider->id==$provider->id &&
                        $provider_Config_Attribute->name=='shop_sorting'
                        )
                        {

                            $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Provider_Config_Attribute::class,$provider_Config_Attribute->id,'shop_sorting');
                        }

                    break;
            }
        }
    }
    protected  function doPaymentConditionsCustomersOperation(PaymentConditionsCustomers $payment_condition_customer,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,PaymentConditionsCustomers::class,$payment_condition_customer->id);
                    break;
            }
        }
    }
    protected  function doPaymentConditionsOperation(PaymentConditions $payment_condition,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,PaymentConditions::class,$payment_condition->id);
                    break;
            }
        }
    }
    protected  function doPriceGroupsArticlesOperation(Price_Groups_Articles $price_aroups_articles,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$price_aroups_articles->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Price_Groups_Articles::class,$price_aroups_articles->id);
                    }

                    break;
            }
        }
    }
    protected  function doPriceGroupsOperation(Price_Groups $price_group,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Price_Groups::class,$price_group->id);
                    break;
            }
        }
    }
    protected  function doEquipmentsetsArticlesOperation(Equipmentsets_Articles $equipmentset_article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$equipmentset_article->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Equipmentsets_Articles::class,$equipmentset_article->id);
                    }

                    break;
            }
        }
    }
    protected  function doEquipmentsetsEquipmentArticlesOperation(Equipmentsets_EquipmentArticles $equipmentset_equipment_article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$equipmentset_equipment_article->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Equipmentsets_EquipmentArticles::class,$equipmentset_equipment_article->id);
                    }

                    break;
            }
        }
    }
    protected  function doSparesetsArticlesOperation(Sparesets_SpareArticles $spareset_spare_article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$spareset_spare_article->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Sparesets_SpareArticles::class,$spareset_spare_article->id);
                    }

                    break;
            }
        }
    }
    protected  function doSparesetsOperation(Sparesets $spareset,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Sparesets::class,$spareset->id);
                    break;
            }
        }
    }
    protected  function doSparesetsSpareArticlesOperation(Sparesets_SpareArticles $spareset_spare_article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$spareset_spare_article->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Sparesets_SpareArticles::class,$spareset_spare_article->id);
                    }

                    break;
            }
        }
    }
    protected  function doPriceCustomersCategoriesOperation(Price_Customer_Categories $price_customer_category,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Price_Customer_Categories::class,$price_customer_category->id);
                    break;
            }
        }
    }
    protected  function doPriceCustomerArticlesOperation(Price_Customer_Articles $price_customer_article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$price_customer_article->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Price_Customer_Articles::class,$price_customer_article->id);
                    }

                    break;
            }
        }
    }
    protected  function doPricegroupsCustomersOperation(Price_Groups_Customers $price_group_customer,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Price_Groups_Customers::class,$price_group_customer->id);
                    break;
            }
        }
    }
    protected  function doCustomerOperation(Customer $customer,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Customer::class,$customer->id);
                    break;
            }
        }
    }
    protected  function doBrandSuppliersOperation(BrandsSuppliers $brand_supplier,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,BrandsSuppliers::class,$brand_supplier->id,null,null,$brand_supplier,['fk_brand_id','hersteller-nr']);
                    break;
            }
        }
    }
    protected  function doBrandsOperation(Brand $brand,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Brand::class,$brand->id);
                    break;
            }
        }
    }
    protected  function doBranchArticleVariationOperation(BranchArticle_Variation $branch_article_variation,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$branch_article_variation->article_variation->article;

        if(!$article){
            return;
        }
        $provider_list=$this->getConnectedArticleProvider($article);


        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');


                    if($count){
                         //Gesamtbestand ermitteln für die Variation!
                        $total_stock=$branch_article_variation->article_variation->getStock();
                        $context_value=[
                            "stock" => $total_stock,
                        ];

                        //Wenn der Branch gerade gelöscht wird, so müssen wir vielleicht in Zukunft, vom Gesamtbestand dies abziehen!
                        //Aber bislang löschen wir ja keine Filialen!
                        /**
                         * @todo Bestand
                         */

                        try{
                            $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,
                                                    Article_Variation::class,$branch_article_variation->article_variation->id,
                                                    'stock',json_encode($context_value) );
                        }
                        catch(\Exception $e){
                            echo $e->getMessage();
                        }

                        }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');


                    if($count){
                            //Gesamtbestand ermitteln für die Variation!
                        $total_stock=$branch_article_variation->article_variation->getStock();
                        $context_value=[
                            "stock" => $total_stock,
                        ];

                        //Wenn der Branch gerade gelöscht wird, so müssen wir vielleicht in Zukunft, vom Gesamtbestand dies abziehen!
                        //Aber bislang löschen wir ja keine Filialen!
                        /**
                         * @todo Bestand
                         */

                        try{
                            $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,
                                                    Article_Variation::class,$branch_article_variation->article_variation->id,
                                                    'stock',json_encode($context_value) );
                        }
                        catch(\Exception $e){
                            echo $e->getMessage();
                        }

                        }

                    break;
            }
        }
    }
    protected  function doAttributeSetAttributeGroupOperation(Attribute_Sets_Attribute_Group $attribute_set_attribute_group,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Für Artikel schreiben wir einfach 0
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Attribute_Sets_Attribute_Group::class,$attribute_set_attribute_group->id,null,null,$attribute_set_attribute_group,['fk_attributeset_id','fk_attributegroup_id']);
                    break;
            }
        }
    }
    protected  function doAttributeSetOperation(Attribute_Set $attribute_set,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Für Artikel schreiben wir einfach 0
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Attribute_Group::class,$attribute_set->id);
                    break;
            }
        }
    }
    protected  function doAttributeGroupOperation(Attribute_Group $attribute_group,$operation,$priority){
        $provider_list=Provider::all();

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Für Artikel schreiben wir einfach 0
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Attribute_Group::class,$attribute_group->id);
                    break;
            }
        }
    }
    protected  function doCategoryArticleOperation(CategoryArticle $category_article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$category_article->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,CategoryArticle::class,$category_article->id,null,null,$category_article,['article_id','category_id']);
                    }

                    break;
            }
        }
    }
    protected  function doCategoryOperation(Category $category,$operation,$priority){
        //Wir holen jetzt alle Provider der Kategorie, mit denen es auch verknüpft ist!
        //Wir kümmern uns aber nur darum, wenn es eine tatsächliche Kategorie ist und nicht eine Warengruppe

        if(!$category->wawi_number){
            return;
        }


        $provider_list=$category->providers;

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wir setzen die Artikel-ID einfach auf 0, da es hier nicht um einen Artikel geht
                    $this->writeProviderSynchro(0,$provider->id,$operation,$priority,Category::class,$category->id);
                    break;
            }
        }
    }
    protected  function doArticleVariationPriceOperation(Article_Variation_Price $article_Variation_price,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_Variation_price->variation->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Price::class,$article_Variation_price->id);
                    }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Price::class,$article_Variation_price->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleVariationAttributeOperation(Article_Variation_Attribute $article_Variation_attribute,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_Variation_attribute->variation->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Attribute::class,$article_Variation_attribute->id);
                    }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Attribute::class,$article_Variation_attribute->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleVariationImageAttributeOperation(Article_Variation_Image_Attribute $article_variation_image_attribute,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_variation_image_attribute->image->variation->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Image_Attribute::class,$article_variation_image_attribute->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleVariationImageOperation(Article_Variation_Image $article_variation_image,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_variation_image->variation->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                     //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Image::class,$article_variation_image->id);
                    }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation_Image::class,$article_variation_image->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleVariationOperation(Article_Variation $article_variation,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_variation->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation::class,$article_variation->id);
                    }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Variation::class,$article_variation->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleShipmentOperation(Article_Shipment $article_shipment,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_shipment->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Shipment::class,$article_shipment->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleMarketingOperation(Article_Marketing $article_marketing,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_marketing->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Marketing::class,$article_marketing->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticlePriceOperation(Article_Price $article_price,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_price->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Price::class,$article_price->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleImageAttributeOperation(Article_Image_Attribute $article_image_attribute,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_image_attribute->image->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Image_Attribute::class,$article_image_attribute->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleImageOperation(Article_Image $article_image,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_image->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Image::class,$article_image->id);
                    }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Image::class,$article_image->id);
                    }

                    break;
            }
        }
    }
    protected  function doArticleAttributeOperation(Article_Attribute $article_attribute,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $article=$article_attribute->article;
        $provider_list=$this->getConnectedArticleProvider($article);

        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){
                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Attribute::class,$article_attribute->id);
                    }
                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');
                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority,Article_Attribute::class,$article_attribute->id);
                    }
                    break;
            }
        }
    }
    protected  function doArticleOperation(Article $article,$operation,$priority){
        //Wir holen jetzt alle Provider des Articles, mit denen es auch verknüpft ist!
        $provider_list=$this->getConnectedArticleProvider($article);




        //Wir können hier dann Fallunterscheidungen treffen. Für jede Plattform kann es
        //abweichungen geben und für jeden Operationstyp!
        //Für einen Artikel jedoch sehe ich momentan nichts besonderes vor!
        foreach($provider_list as $provider){
            //für den VSShop!!
            switch($provider->type->provider_key){

                case "shop":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','shop');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');


                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority);
                    }

                    break;
                case "wix":
                    //Wenn Artikel keinen Provider hat, heisst es, er wurde neu angelegt und muss eh mit allen Plattformen verknüpft werden!
                    //oder es gibt eine Providerzuweisung, wodurch eine genaue Überprüfung statt finden muss
                    $query=Article::query()->where('id',$article->id)
                            ->where(function($query){
                                $query->whereHas('realProviders',function($query){
                                    $query->whereHas('type',function($query){
                                        $query->where('provider_key','wix');
                                    });
                                })
                                ->orWhereDoesntHave('realProviders');
                            });
                    $count=$query->count('*');


                    if($count){
                        $this->writeProviderSynchro($article->id,$provider->id,$operation,$priority);
                    }

                    break;
            }
        }
    }
     /*
        ####################################################################################################################################
        Ende der einzelnen Funktionen, die für jede Datenbankklasse in Abhängigkeit vom Provider einen Synchrodatensatz schreiben!!!
        ####################################################################################################################################
    */

    //======================================================================================================================================

    /*
        ####################################################################################################################################
        Jetzt kommen die Methoden, die die Synchros schreiben!!!
        ####################################################################################################################################
    */
    /**
     * Diese Methode tätigt einen Schreibvorgang in die Synchronisationstabelle "article_provider_syncs
     * und stößt einen eventuellen Schreibvorgang in eine weitere Sync-Tabelle "article_provider_sync_deletions" an.
     *
     * @param [type] $article_id
     * @param [type] $provider_id
     * @param [type] $operation
     * @param [type] $priority
     * @param [type] $subject_class
     * @param [type] $subject_id
     * @param [type] $context
     * @param [type] $context_value
     * @return void
     */
    protected  function writeProviderSynchro($article_id,$provider_id,$operation,$priority,$subject_class=null,$subject_id=null,$context=null,$context_value=null,Model $model=null,$properties=[]){
        //Die Nummern holen für Operation und Priorität
        $operation_number=config("content-manager.operations.{$operation}");
        $priority_number=config("content-manager.priorities.{$priority}");

        $data=[
            'fk_article_id'=>$article_id,
            'fk_provider_id'=>$provider_id,
            'operation'=>$operation_number,
            'subject'=>$subject_class,
            'subject_id'=>$subject_id,
            'context'=>$context,
            'context_value'=>$context_value,
            'priority'=>$priority_number,
        ];
        $article_provider_sync=ArticleProviderSync::updateOrCreate($data);

        //Wenn die Operation ein Delete ist, müssen wir unseren Synchro-Datensatz mit einem
        //Lösch-Datensatz verknüpfen. Dieser Datensatz hält alles bereit für die anstehende
        //Synchronisation über die Lebensdauer des zu löschenden Objekts hinweg.



        if($operation=="delete"){

            //jetzt ermitteln wir noch das Objekt, das serialisiert werden muss
            /*
            $object_save=null;
            if($subject_class && $subject_id){
                $object_save2=
                $subject_class::query()->where('id',$subject_id)->first();
                if($object_save2){
                    $object_save=$object_save2;
                }

            }
            //Müssen wir Artikel speichern?
            if(!$object_save && $article_id){
                $object_save=Article::find($article_id);
            }
            */

            //Der code oben bei if beginnend ist momentan nur ein Relikt und steht einfach zur Verfügung, bis etwas besseres da ist als das folgende.
            $object_save=new stdClass();
            $object_save->id=$subject_id;
            foreach($properties as $property){
                $object_save->{$property}=$model->{$property};
            }


            //Nun lassen wir unsere Leiche begraben :-)
            if($article_provider_sync->wasRecentlyCreated && $object_save){

                $this->writeProviderSynchroDeletion($article_provider_sync->id,$object_save,$model,$properties);

            }




        }
    }

    protected  function writeProviderSynchroDeletion($sync_id,$value,Model $model=null,$properties=[]){



        $data=[
            'fk_sync_id'=>$sync_id,
            'value'=> json_encode($value),
        ];


        $sync_deletion=
        ArticleProviderSyncDeletion::create($data);
        $sync_deletion->save();



    }
    /**
     * Diese Methode liefert uns alle Provider eines Artikels zurück, mit denen es auch
     * über die Tabelle Article_Providers aktiv verknüpft ist.
     *
     * @param Article $article
     * @return []
     */
    protected  function getConnectedArticleProvider(Article $article){
        $provider_article=$article->provider()->where('active','=',1)->select('fk_provider_id')
                    ->get()->pluck('fk_provider_id')
                    ->toArray();

        $provider=[];

        if(!empty($provider_article)){
            $provider=Provider::query()->whereIn('id',$provider_article)
                        ->get();
        }
        else{
            //Vielleicht gibt es noch keine Provider, weil der Artikel neu angelegt worden ist.
            //Da neuangelegte Artikel standardmäßig mit allen Providern verknüpft werden, geben wir einfach alle Provider zurück!
            $query=Article::query()->where('id',$article->id)
                    ->whereDoesntHave('realProviders');
            $count=$query->count('*');

            if($count){
                $provider=Provider::all();
            }
        }



        return $provider;
    }
    /**
     * wirft eine Exception, wenn der angegebene Operationstyp nicht in
     * der Konfigurationsdatei content-manager aufgeführt ist.
     *
     * @param [type] $operation
     * @return void
     */
    private  function checkOperation($operation){
        $operations=config('content-manager.operations');
        $operations=array_keys($operations);

        if(!in_array($operation,$operations) ){
            throw new Exception("Der gewünschte Operator \"{$operation}\" wird nicht unterstützt!");
        }
    }
    private  function checkPriority($priority){
        $priorities=config('content-manager.priorities');
        $priorities=array_keys($priorities);

        if(!in_array($priority,$priorities)){
            throw new Exception("Die angegebene Priorität \"{$priority}\" wird nicht unterstützt!");
        }
    }
    /*
        ####################################################################################################################################
        Ende der Methoden, die die Synchros schreiben!!!
        ####################################################################################################################################
    */
}
