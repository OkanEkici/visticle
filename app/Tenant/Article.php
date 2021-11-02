<?php

namespace App\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use Illuminate\Database\Eloquent\Model;
use App\Tenant\BrandsSuppliers;
use App\Tenant;
use App\Tenant\Provider;
use App\Tenant\Category;
use Carbon\Carbon;
use App\Manager\Content\ContentManager;
use Illuminate\Support\Facades\Log;

class Article extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name','description', 'slug', 'vstcl_identifier', 'min_stock', 'active', 'number', 'ean',
        'sku', 'fk_wawi_id', 'fk_brand_id', 'short_description',
        'fashioncloud_updated_at', 'batch_nr','webname','metatitle',
        'keywords','shopware_id','tax', 'type', 'fk_attributeset_id'
    ];

    //protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article)use($content_manager) {
                $content_manager->registrateOperation($article,'insert','scheduled');
            });

            self::updated(function($article)use($content_manager) {
                $content_manager->registrateOperation($article,'update','scheduled');
            });

            self::deleting(function($article)use($content_manager) {
                $content_manager->registrateOperation($article,'delete','scheduled');
            });
        }
        catch(\Exception $e){
            Log::channel('content_manager')->error($e->getMessage());

        }
    }

    /**
     * @author Tanju Özsoy
     * 12.01.2012
     * Wir erstellen jetzt einen Getter für ein Attribut, das es nicht gibt.
     * Dieses Attribut heiss "vsshop_active".
     * Ein Artikel muss beim Shop inaktiv gesetzt sein, wenn eines folgender Bedingungen zutrifft:
     *
     */
    protected $appends=['vsshop_active',
                        'vsshop_sale',
                        'vsshop_new',
                        'vsshop_brand',//16.01.2021 Tanju Özsoy
                    ];
    public function getVsshopBrandAttribute(){
        return $this->getAttributeValueByKey('hersteller');
        //return $this->brandNameToShow();
    }
    public function getVsshopActiveAttribute(){

        $article=$this;

        // Bedingungen :: //Preis //Artikelname //Bild //Bestand
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
        || $imageCount == 0
        || $GesamtStock == 0
        ){  return false; }

        return true;
    }
    public function getVsshopSaleAttribute(){
        $date=Carbon::now()->format('Y-m-d');
        $count_discount=
        Article_Marketing::query()
            ->where('fk_article_id','=',$this->id)
            ->where('name','=','activate_discount')
            ->where('active','=',1)
            ->whereRaw('case when `from` is null then \'?\' else date(`from`) end <= \'?\'',[$date,$date])
            ->whereRaw('case when until is null then \'?\' else date(until) end >= \'?\'',[$date,$date])
            ->exists();

        if(!$count_discount){
            $count_discount=
            $this->isOnSale();
        }
        else{
            $count_discount=
            $this->isOnSale();
        }

        if($count_discount){
            return true;
        }
        else{
            return false;
        }
    }
    public function isOnSale() {
        $StandardPrice_nr=false;$DiscountPrice_nr=false;$WebDiscountPrice_nr=false;$WebStandardPrice_nr=false;
        $StandardPrice=false;$DiscountPrice=false;$WebDiscountPrice=false;$WebStandardPrice=false;
        //$marketing = $this->marketing()->where('name', '=', 'activate_discount')->where('active', '=', '1')->first();
        //if($marketing){ return $marketing; }
        //else{
            $thisVars = $this->variations()->where('stock', '>=',1)->get();
            foreach($thisVars as $thisVar)
            {
                //Standard-Preis
                $StandardPrice=$thisVar->getStandardPrice();
                if($StandardPrice){$StandardPrice_nr=(float)str_replace(',','.', $StandardPrice);}
                //Discount-Preis
                $DiscountPrice=$thisVar->getDiscountPrice();
                if($DiscountPrice){$DiscountPrice_nr=(float)str_replace(',','.', $DiscountPrice);}
                //Web-StandardPreis
                $WebStandardPrice=$thisVar->getWebStandardPrice();
                if($WebStandardPrice){$WebStandardPrice_nr=(float)str_replace(',','.', $WebStandardPrice);}
                //Web-Discount
                $WebDiscountPrice=$thisVar->getWebDiscountPrice();
                if($WebDiscountPrice){$WebDiscountPrice_nr=(float)str_replace(',','.', $WebDiscountPrice);}

                if(!$DiscountPrice){
                    $DiscountPrice=$StandardPrice;
                    $DiscountPrice_nr=$StandardPrice_nr;
                }
                if(!$WebStandardPrice){
                    $WebStandardPrice=$StandardPrice;
                    $WebDiscountPrice_nr=$StandardPrice_nr;
                }
                if(!$WebDiscountPrice){
                    $WebDiscountPrice=$StandardPrice;
                    $WebDiscountPrice_nr=$StandardPrice_nr;
                }

                if( ($StandardPrice_nr>$WebStandardPrice_nr) ||
                    ($StandardPrice_nr>$WebDiscountPrice_nr) ||
                    ($StandardPrice_nr>$DiscountPrice_nr)
                )
                {
                    return true;
                }
                else{
                    return false;
                }
            }

        //}
        return false;
    }
    public function getVsshopNewAttribute(){
        $date=Carbon::now()->format('Y-m-d');
        $count_discount=
        Article_Marketing::query()
            ->where('fk_article_id','=',$this->id)
            ->where('name','=','mark_as_new')
            ->where('active','=',1)
            ->whereRaw('case when `from` is null then \'?\' else date(`from`) end <= \'?\'',[$date,$date])
            ->whereRaw('case when until is null then \'?\' else date(until) end >= \'?\'',[$date,$date])
            ->count();

        if($count_discount){
            return true;
        }
        else{
            return false;
        }
    }



    /* Abhängigkeiten */
    public function provider() {return $this->hasMany(ArticleProvider::class, 'fk_article_id');}
    public function realProviders(){
        return $this->belongsToMany(Provider::class,'article_providers','fk_article_id','fk_provider_id');
    }

    public function variations() {return $this->hasMany(Article_Variation::class, 'fk_article_id');}

    public function attribute_set() {return $this->belongsTo(Attribute_Set::class, 'fk_attributeset_id');}
    public function attributes() { return $this->hasMany(Article_Attribute::class, 'fk_article_id'); }
    public function eigenschaften() { return $this->hasMany(Article_Eigenschaften_Articles::class,'fk_article_id'); }

    public function images() {return $this->hasMany(Article_Image::class, 'fk_article_id');}

    public function sparesets_spare_article() {return $this->hasMany(Sparesets_SpareArticles::class, 'fk_article_id');}
    public function sparesets_article() {return $this->hasMany(Sparesets_Articles::class, 'fk_article_id');}

    public function equipmentsets_equipment_article() {return $this->hasMany(Equipmentsets_EquipmentArticles::class, 'fk_article_id'); }
    public function equipmentsets_article() {return $this->hasMany(Equipmentsets_Articles::class, 'fk_article_id');}

    public function prices() {return $this->hasMany(Article_Price::class, 'fk_article_id');}
    public function article_prices() {return $this->hasMany(Price_Customer_Articles::class, 'fk_article_id');}
    public function pricegroups() {return $this->hasMany(Price_Groups_Articles::class, 'article_id');}
    public function customerPrices($customerID) {return $this->hasMany(Price_Customer_Articles::class, 'fk_article_id')->where('fk_customer_id', '=', $customerID); }

    public function categories(Provider $provider=null)
    {

        //ist der Optionale Parameter $provider gesetzt, so selektieren wir gleich nach Provider!
        if($provider){

            $query=
            $this->belongsToMany(Category::class, 'category_article','fk_article_id','fk_category_id')
            ->whereHas('providers',function($query)use($provider){
                $query->where('providers.id',$provider->id);
            });


            return $query;
        }


        //Ohne den optionalen Parameter $provider machen wir so weiter
        $standard_provider=Category::getSystemStandardProvider();

        //gibt es Kategorien ohne Provider?
        $query=null;
        if($standard_provider['categoriesWithoutProvider']>0){
            $query=
            $this->belongsToMany(Category::class, 'category_article','article_id','category_id')
                ->whereDoesntHave('providers');
        }

        //ansonsten berücksichtigen wir einen Provider!
        if($standard_provider['provider']){
            $provider=$standard_provider['provider'];
            $query=
            $this->belongsToMany(Category::class, 'category_article','article_id','category_id')
            ->whereHas('providers',function($query)use($provider){
                $query->where('providers.id',$provider->id);
            });
        }
        else{
            $query=
            $this->belongsToMany(Category::class, 'category_article','article_id','category_id');
        }

        return $query;


        //return $this->belongsToMany(Category::class, 'category_article');
    }

    public function brand() { return $this->belongsTo(Brand::class, 'fk_brand_id'); }
    public function wawi() { return $this->belongsTo(WaWi::class, 'fk_wawi_id'); }
    public function marketing() {return $this->hasMany(Article_Marketing::class, 'fk_article_id');}
    public function upsellFor() {return $this->belongsToMany(Article_Upsell::class, 'fk_upsell_article_id');}
    public function upsells() {return $this->hasMany(Article_Upsell::class, 'fk_main_article_id');}
    public function shipments() {return $this->hasMany(Article_Shipment::class, 'fk_article_id');}

    public function providerMappings() {return $this->hasMany(ArticleProvider::class, 'fk_article_id');}


    /* Funktionsbereich */

    public function getStandardPrice() { $price = $this->prices()->where('name', '=', 'standard')->first(); return ($price) ? $price->value : null ; }
    public function getDiscountPrice() { $price = $this->prices()->where('name', '=', 'discount')->first(); return ($price) ? $price->value : null ; }
    public function getWebStandardPrice() { $price = $this->prices()->where('name', '=', 'web_standard')->first(); return ($price) ? $price->value : null ; }
    public function getWebDiscountPrice() { $price = $this->prices()->where('name', '=', 'web_discount')->first(); return ($price) ? $price->value : null ; }

    public function getAttrByName($name)
    {   return $this->attributes()->where('name', '=', $name)->first() ? $this->attributes()->where('name', '=', $name)->first()->value : ''; }

    public function filledName()
    {   return (($this->webname != '' && $this->webname != null) ? $this->webname : $this->name); }

    public function getAttributeValueByKey($key) {
        $attribute = $this->attributes()->where('name','=',$key)->first();
        return (($attribute) ? $attribute->value : '');
    }

    public function updateOrCreateAttribute($name, $value, $group_id = null)
    {   return Article_Attribute::updateOrCreate(
        ['fk_article_id' => $this->id,'name' => $name],
        ['value' => $value,'fk_attributegroup_id' => $group_id]);
    }

    public static function getFormattedPrice($price)
    {return str_replace('.',',', number_format((float)str_replace(',','.',$price), 2, '.', '')) . '€';}

    public function updateOrCreatePrice($name, $value)
    {   return Article_Price::updateOrCreate(
        ['fk_article_id' => $this->id,'name' => $name],
        ['value' => $value]);
    }

    public function getProducerName()
    {   $producer = $this->attributes()->where('name', '=', 'hersteller')->first();
        return ($producer) ? $producer->value : '';
    }

    public function isActiveForShops()
    {   $isActive = false; $hasImages = $this->images()->exists();
        if($hasImages) {$isActive = true;}
        return $isActive;
    }

    public function brandNameToShow() {
        $supplierNr = $this->getAttributeValueByKey('hersteller-nr');
        if($supplierNr == '')
        {$brandName = $this->getAttributeValueByKey('hersteller');}
        else {
            $brandSupp = BrandsSuppliers::where('hersteller-nr', '=', $supplierNr)->first();
            if($brandSupp) {
                $brand = $brandSupp->brand()->first();
                $brandName = $brand->name;
            }
            else { $brandName = $this->getAttributeValueByKey('hersteller');}
        }
        return $brandName;
    }

    public static function sidenavConfig($active = null)
    {
        $config['artikelverwaltung'] = [
            'name' => 'Artikelverwaltung',
            'route' => '/articles',
            'iconClass' => 'fas fa-store'
        ];

        $Tenant_type = config()->get('tenant.tenant_type');
        $identifier = config()->get('tenant.identifier');
        if($Tenant_type=='vstcl-industry')
        {
            $config['ersatzteilverwaltung'] = [
                'name' => 'Ersatzteilverwaltung',
                'route' => '/sparesets',
                'iconClass' => 'fas fa-store'
            ];
            $config['zubehoerverwaltung'] =
            [
                'name' => 'Zubehörverwaltung',
                'route' => '/equipmentsets',
                'iconClass' => 'fas fa-store'
            ];
            $config['attributverwaltung'] =
            [
                'name' => 'Attributverwaltung',
                'route' => '/article_attributes',
                'iconClass' => 'fas fa-list'
            ];
        }
        if($Tenant_type=='vstcl-textile' && ($identifier == 'stilfaktor' || $identifier == 'olgasmodewelt'))
        {
            $config['eigenschaftenverwaltung'] =
            [
                'name' => 'Eigenschaftenverwaltung',
                'route' => '/article_eigenschaften',
                'iconClass' => 'fas fa-list'
            ];
        }


        $config['lieferantenverwaltung'] =
            [
                'name' => 'Lieferantenverwaltung',
                'route' => '/suppliers',
                'iconClass' => 'fas fa-dolly'
            ];
        $config['kategorien'] =
            [
                'name' => 'Kategorien',
                'route' => '/categories',
                'iconClass' => 'fas fa-grip-horizontal'
            ];
        $config['warengruppen'] =
            [
                'name' => 'Warengruppen',
                'route' => '/warengruppen',
                'iconClass' => 'fas fa-cubes'
            ];
        $config['zusatz-ean'] =
            [
                'name' => 'Zusatz-EAN',
                'route' => '/extra-ean',
                'iconClass' => 'fas fa-barcode'
            ];

        if($active && isset($config[$active])) {
            $config[$active]['isActive'] = true;
        }

        return $config;
    }


/*
    public function getProducerName() {
        $producer = $this->attributes()->where('name', '=', 'hersteller')->first();
        return ($producer) ? $producer->value : '';
    }


    public static function getFormattedPrice($price) {
        return str_replace('.',',', number_format((float)str_replace(',','.',$price), 2, '.', '')) . '€';
    }

    public function getAttributeValueByKey($key) {
        $attribute = $this->attributes()->where('name','=',$key)->first();
        return (($attribute) ? $attribute->value : '');
    }



    public function isActiveForShops() {
        $isActive = false;
        $hasImages = $this->images()->exists();

        if($hasImages) {
            $isActive = true;
        }

        return $isActive;
    }

    public function brandNameToShow() {
        $supplierNr = $this->getAttributeValueByKey('hersteller-nr');
        if($supplierNr == '') {
            $brandName = $this->getAttributeValueByKey('hersteller');
        }
        else {
            $brandSupp = BrandsSuppliers::where('hersteller-nr', '=', $supplierNr)->first();
            if($brandSupp) {
                $brand = $brandSupp->brand()->first();
                $brandName = $brand->name;
            }
            else {
                $brandName = $this->getAttributeValueByKey('hersteller');
            }
        }
        return $brandName;
    }

    public function sparesets_spare_article() {
        return $this->hasMany(Sparesets_SpareArticles::class, 'fk_article_id');
    }
    public function sparesets_article() {
        return $this->hasMany(Sparesets_Articles::class, 'fk_article_id');
    }

    public function equipmentsets_equipment_article() {
        return $this->hasMany(Equipmentsets_EquipmentArticles::class, 'fk_article_id');
    }
    public function equipmentsets_article() {
        return $this->hasMany(Equipmentsets_Articles::class, 'fk_article_id');
    }

    public function article_prices() {
        return $this->hasMany(Price_Customer_Articles::class, 'fk_article_id');
    }
*/


}
