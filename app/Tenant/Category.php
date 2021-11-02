<?php

namespace App\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use Illuminate\Database\Eloquent\Model;
use App\Tenant\Article;
use App\Tenant\CategoryAttribute;
use App\Tenant\Provider;
use App\Tenant\CategoryProvider;
use App\Manager\Content\ContentManager;

class Category extends Model
{

    protected $connection = 'tenant';

    protected $fillable = ['name', 'description', 'slug', 'fk_parent_category_id', 'fk_wawi_id', 'wawi_number', 'wawi_description', 'wawi_name', 'sw_id'];
    //protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($category)use($content_manager) {
                $content_manager->registrateOperation($category,'insert','scheduled');
            });

            self::updated(function($category)use($content_manager) {
                $content_manager->registrateOperation($category,'update','scheduled');
            });

            self::deleting(function($category)use($content_manager) {
                $content_manager->registrateOperation($category,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function providers(){
        return $this->belongsToMany(Provider::class,'category_providers','fk_category_id','fk_provider_id');
    }
    public function attributes(){
        return $this->hasMany(CategoryAttribute::class,'fk_category_id');
    }
    public function articles() {
        return $this->belongsToMany(Article::class, 'category_article');
    }

    public function eigenschaften() {
        return $this->belongsToMany(Category::class, 'article__eigenschaften__categories','fk_category_id','fk_eigenschaft_id');
    }

    public function eigenschaften_cat() {
        return $this->hasMany(Article_Eigenschaften_Categories::class,'fk_category_id');
    }

    public function upsells() {
        return $this->hasMany(Article_Upsell::class, 'fk_upsell_category_id');
    }

    public function parentCategory() {
        return $this->belongsTo(Category::class, 'fk_parent_category_id');
    }

    public function allParentCategories() {
        return $this->parentCategory()->with('allParentCategories');
    }

    public function subcategories() {
        return $this->hasMany(Category::class, 'fk_parent_category_id');
    }

    public function allSubCategories() {
        return $this->subcategories()->with('allSubCategories');
    }

    public function wawi() {
        return $this->belongsTo(WaWi::class, 'fk_wawi_id');
    }

    /**
         * Die Methode shopcategories() liefert für eine Warengruppe ( technisch gesehen eine Kategorie ) eine Liste von Kategorien zurück.
         * Wir merken hier an,
         * dass versehentlich in der tabelle waregroups_category die id der Warengruppe in der spalte category_id gelandet ist und
         * die ID der Kategory in der Spalte Waregroup_id.
         * Das lassen wir jetzt mal so, weil sonst vieles nicht stimmt.
         * Wir statten jetzt diese Funktion mit zusätzlicher Funktionalität aus!Es wird eine standardmäßige Routinge durchgeführt.
         * Wir schauen, ob es in der gesamten Kategorietabelle Kategorien gibt, die keinem Provider zugeordnet sind.
         * Wenn ja, werden bei der Verknüpfung von Warengruppe und Kategorie nur Kategorien in Betracht gezogen,
         * die keinem Provider zugeordnet sind. Gibt es keine Kategorie ohne Provider, müssen wir  ermitteln, welches unser Standardshop ist (vsshop, shopware).
         *
         *
         * !!! Diese Methode sollte in Zukunft vielleicht prüfen, ob die Instanz auch eine Warengruppe ist  !!!
    */
    public function shopcategories() {
        $standard_provider=self::getSystemStandardProvider();

        //gibt es Kategorien ohne Provider?
        $query=null;
        if($standard_provider['categoriesWithoutProvider']>0){
            $query=
            $this->belongsToMany(Category::class, 'waregroups_category', 'category_id', 'waregroup_id')
                ->whereDoesntHave('providers');
        }

        //ansonsten berücksichtigen wir einen Provider!
        if($standard_provider['provider']){
            $provider=$standard_provider['provider'];
            $query=
            $this->belongsToMany(Category::class, 'waregroups_category', 'category_id', 'waregroup_id')
            ->whereHas('providers',function($query)use($provider){
                $query->where('providers.id',$provider->id);
            });
        }
        else{
            $query=
            $this->belongsToMany(Category::class, 'waregroups_category', 'category_id', 'waregroup_id');
        }


        return $query;


        //return $this->belongsToMany(Category::class, 'waregroups_category', 'category_id', 'waregroup_id');
    }
    public function waregroups(){
        return $this->belongsToMany(Category::class,'waregroups_category','category_id','waregroup_id');
    }
    /**
     * Die Methode soll zu einer Warengruppe alle Kategorien eines übergebenen Providers zurückliefern.
     *
     * !!! Diese Methode sollte in Zukunft vielleicht prüfen, ob die Instanz auch eine Warengruppe ist  !!!
     *
     * @param Provider $provider
     * @return void
     */
    public function providerCategories(Provider $provider){
        $query=
        $this->belongsToMany(Category::class, 'waregroups_category', 'category_id', 'waregroup_id')
        ->whereHas('providers',function($query)use($provider){
            $query->where('providers.id',$provider->id);
        });

        return $query;
    }

    public function add_shopcategory($wg_id) {
        $this->shopcategories()->syncWithoutDetaching($wg_id);
        $wg = Category::find($wg_id);
        $wg->shopcategories()->syncWithoutDetaching($this->id);
    }

    public function remove_shopcategory($wg_id) {
        $this->shopcategories()->detach($wg_id);
        $wg = Category::find($wg_id);
        $wg->shopcategories()->detach($this->id);
    }


    public function sparesets_categories() {
        return $this->hasMany(Sparesets_Categories::class, 'fk_category_id');
    }
    public function equipmentsets_categories() {
        return $this->hasMany(Equipmentsets_Categories::class, 'fk_category_id');
    }

    public function category_vouchers() {
        return $this->hasMany(Price_Customer_Categories::class, 'fk_category_id');
    }

	public function price_groups() {
        return $this->hasMany(Price_Groups_Categories::class, 'category_id');
    }

    public static function categoriesOfProvider(Provider $provider=null){
        //Wenn Provider gegeben ist, brauchen wir nicht viel
        if($provider){
            $query=
            self::query()->whereHas('providers',function($query)use($provider){
                $query->where('providers.id',$provider->id);
            });
            return $query;
        }

        //Ansonsten schauen wir uns mal den "Standard-Provider an!!"
        $standard_config=self::getSystemStandardProvider();

        //Gibt es Kategorien ohne Provider?
        if($standard_config['categoriesWithoutProvider']>0){
            return
            self::query()->whereDoesntHave('providers')->whereNull('wawi_number');
        }

        //Gibt es einen Standardprovider
        if($standard_config['provider']){
            $provider=$standard_config['provider'];
            return
            self::query()->whereHas('providers',function($query)use($provider){
                $query->where('providers.id',$provider->id);
            });
        }

        //ansonsten den rest
        return self::query()->whereNull('fk_wawi_id');
    }
    /**
     * Wir erweitern die Category-klasse um eine weitere statische Methode, die ermittelt, ob es
     * "Standardkategorien" gibt ohne Providerverknüpfung. Wenn nicht, ermittelt sie den Standardprovider.
     * Diese Funktion können wir dann schon mal in den Methoden Article@categories und Category@shopcategories verwenden.
     * Die funktion soll heissen "getSystemStandardProvider" und liefert eine Liste mit folgenden Schlüsseln
     * "categoriesWithoutProvider" => (ja | nein), "provider" => (null | Provider-Instanz).
     */
    public static function getSystemStandardProvider(){
        //gibt es Kategorien ohne Providerzuordnung
        $category_count_without_provider=self::query()->whereDoesntHave('providers')
                                        ->whereNull('fk_wawi_id')
                                        ->count('*')
                                        ;

        //Wenn es Kategorien gibt ohne Provider, liefertn wir hier schon einmal das Ergebnis zurück
        $return_list=[
            'categoriesWithoutProvider'=>$category_count_without_provider,
            'provider'=>null,
        ];
        /*
        if($category_count_without_provider>0){
            return $return_list;
        }
        */

        //Jetzt ermitteln wir unseren Standard-Provider.
        //Wir gehen einfach nur durch, ob es Provider vom Typ shop oder shopware gibt! Lets rock baby...
        $provider=null;
        $provider_types=['shop','shopware'];
        foreach($provider_types as $provider_type){
            $provider_query=Provider::query()
            ->whereHas('type',function($query)use($provider_type){
                $query->where('provider_key',$provider_type);
            });

            $count=$provider_query->count('*');

            //Wenn es Provider gibt, die den Provider-Typen entsprechen und auch einen Kategoriebaum besitzen
            if($count){
                $provider=$provider_query->first();
            }
        }

        $return_list['provider']=$provider;

        return $return_list;
    }
    public function getCategoryAttributeValuePath($attribute_name){
        $parent=Category::find($this->fk_parent_category_id);
        $path="{$this->attributes()->where('name',$attribute_name)->first()->value}";

        while($parent){
            $path="{$parent->attributes()->where('name',$attribute_name)->first()->value}/{$path}";
            $parent=Category::find($parent->fk_parent_category_id);
        }

        return $path;
    }
    public function getCategoryNamePath(){
        $parent=Category::find($this->fk_parent_category_id);
        $path="{$this->name}";

        while($parent){
            $path="{$parent->name}/{$path}";
            $parent=Category::find($parent->fk_parent_category_id);
        }

        return $path;
    }
}
