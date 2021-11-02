<?php

namespace App\Console\Commands\Test;

use App\Helpers\Miscellaneous;
use App\Manager\Content\ContentManager;
use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\Article_Attribute;
use App\Tenant\Article_Image;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Marketing;
use App\Tenant\Article_Price;
use App\Tenant\Article_Shipment;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Article_Variation_Price;
use App\Tenant\ArticleProvider;
use App\Tenant\ArticleProviderSync;
use App\Tenant\Attribute_Group;
use App\Tenant\Attribute_Set;
use App\Tenant\Attribute_Sets_Attribute_Group;
use App\Tenant\Category;
use App\Tenant\CategoryArticle;
use App\Tenant\Provider;
use App\Tenant\Setting;
use App\Tenant\Brand;
use App\Tenant\BrandsSuppliers;
use App\Tenant\Provider_Config;
use App\Tenant\Provider_Config_Attribute;
use App\Tenant\Branch;
use Illuminate\Support\Facades\DB;
use Config;


class ContentManagerTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * function ==> derzeit filter
     *
     * @var string
     */
    protected $signature = 'test:content-manager {customer} {function}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'diverse Tests zum Contentmanager.';

    protected $functions=['filter','delete','vsshop_syncro'];
    /**
     * Create a new command instance.
     *
     * @return void
     */

    protected $function=null;
    protected $domain=null;
    protected $tenant=null;
    protected $manager=null;

    public function __construct()
    {
        parent::__construct();
    }

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
            $this->error("Der Tenant \"{$domain}\" ist nicht verzeichnet.");
            return;
        }

        $function = $this->argument('function');

        if(!in_array($function,$this->functions)){
            $this->error("Die Funktion \"{$function}\" wird nicht unterstützt");
            return;
        }

        Miscellaneous::loadTenantDBConnection($domain);

        $manager=new ContentManager();
        $this->manager=$manager;

        $this->function=$function;
        $this->domain=$domain;
        $this->tenant=$tenant;


        if($this->function=='filter'){
            //Wir machen jetzt mal paar tests lokal
            $this->do_test_filter();
        }
        elseif($this->function=='delete'){
            $this->do_test_delete();
        }
        elseif($this->function=='vsshop_syncro'){
            $this->do_vsshop_syncro();
        }

    }
    protected $instances_to_delete=[];
    protected function do_vsshop_syncro(){

        $this->instances_to_delete=[];


        ArticleProviderSync::query()->delete();

        //Wir holen den shop provider
        $provider=Provider::query()->whereHas('type',function($query){
            $query->where('provider_key','shop');
        })->first();

        if(!$provider){
            $this->warn("Wir haben keinen VSShop angelegt für den Kunden \"{$this->domain}\".");
            return;
        }

        //Article-Neuanlage
        $article=Article::create([
            'vstcl_identifier' => 'absolut eindeutig!',
            'ean' => 'beste ean nummer der welt',
            'sku' => 'sku rocks',
            'name' => 'neuer Artikel',
            'short_description' => 'shorty its your birthday',
            'description' => 'long description',
            'slug' => 'webtext',
            'min_stock' => 5,
            'active' => 1,
            'number' => 'number for article',
            'webname' =>'webname',
            'metatitle' => 'metatitle',
            'keywords' => 'keywords',
        ]);
        //Article-Update!
        $article->name='ultra neuer Artikel';
        $article->save();
         //Article-Update mit provider!
         $article->realProviders()->attach($provider->id);
         $article->save();
        //zum löschen anmelden
        $this->instances_to_delete[]=$article;
        //article_provider auch löschen
        DB::connection('tenant')->table('article_providers')->where('fk_article_id',$article->id)->where('fk_provider_id',$provider->id)->delete();

        $this->instances_to_delete[]=

        //Artikel-Attribut
        //insert
        $article_attribute=Article_Attribute::create([
            'name' => 'farbe',
            'vale' => 'blau',
            'fk_article_id' => $article->id,
        ]);
        //update
        $article_attribute->value='rot';
        $article_attribute->save();
        //delete
        $this->instances_to_delete[]=$article_attribute;

        //Article-Image
        //insert
        $article_image=Article_Image::create([
            'fk_article_id' => $article->id,
            'location' => 'scheiss auf den pfad',
        ]);
        //update
        $article_image->location='beim test unwichtig';
        $article_image->save();
        //delete
        $this->instances_to_delete[]=$article_image;

        //Artikel-Price
        //insert
        $article_price=Article_Price::create([
            'fk_article_id' => $article->id,
            'name' => 'standardooo',
            'value' => '10000000'
        ]);
        //update
        $article_price->value='10';
        $article_price->save();
        //delete
        $this->instances_to_delete[]=$article_price;

        //Artikel-Image-Attribute
        //insert
        $article_image_attribute=Article_Image_Attribute::create([
            'fk_article_image_id' => $article_image->id,
            'name' => 'tolles Bild',
            'value' => 'tolle beschreibung'
        ]);
        //update
        $article_image_attribute->value='noch besser';
        $article_image_attribute->save();
        //delete
        $this->instances_to_delete[]=$article_image_attribute;

        //Article_Marketing
        //insert
        $article_marketing=Article_Marketing::create([
            'fk_article_id' => $article->id,
            'name' => 'discountiii',
        ]);
        //update
        $article_marketing->name='discooo';
        $article_marketing->save();
        //delete
        $this->instances_to_delete[]=$article_marketing;

        //Article-Variation
        //insert
        $article_variation=Article_Variation::create([
            'fk_article_id' => $article->id,
            'vstcl_identifier' => 'identioniii',
            'ean' => 'am Ende immer Satans 666',
            'active' => 1,
            'name' =>'wonderland',
            'stock' => 0,
            'fk_attributeset_id' =>1,
            'type' => 'article',

        ]);
        //update
        $article_variation->name='top top';
        $article_variation->save();
        //delete
        $this->instances_to_delete[]=$article_variation;

        //Article-Variation-Image
        //insert
        $article_variation_image=Article_Variation_Image::create([
            'fk_article_variation_id' => $article_variation->id,
            'location' => 'irgendwo',
        ]);
        //update
        $article_variation_image->location='besserer ort';
        $article_variation_image->save();
        //delete
        $this->instances_to_delete[]=$article_variation_image;

        //Article_Variation_Image_Attribute
        //insert
        $article_variation_image_attribute=Article_Variation_Image_Attribute::create([
            'fk_article_variation_image_id' => $article_variation_image->id,
            'name' => 'attributeee',
            'value' => 'toller wert',
        ]);
        //update
        $article_variation_image_attribute->value='noch besser';
        $article_variation_image_attribute->save();
        //delete
        $this->instances_to_delete[]=$article_variation_image_attribute;


        //Article-Variation-Attribute
        //insert
        $article_variation_attribute=Article_Variation_Attribute::create([
            'fk_article_variation_id' => $article_variation->id,
            'name' => 'coler',
            'value' => 'blue',
        ]);
        //update
        $article_variation_attribute->value='rot';
        $article_variation_attribute->save();
        //delete
        $this->instances_to_delete[]=$article_variation_attribute;


        //Article-Variation-Price
        //insert
        $article_variation_price=Article_Variation_Price::create([
            'fk_article_variation_id' => $article_variation->id,
            'name' => 'standard',
            'value' => '50',
        ]);
        //update
        $article_variation_price->value='100';
        $article_variation_price->save();
        //delete
        $this->instances_to_delete[]=$article_variation_price;

        //Category
        //insert
        $category=Category::create([
            'name' => 'new world order',

        ]);
        //update
        $category->name='new world order corona';
        $category->save();
        //delete
        $this->instances_to_delete[]=$category;

        //Category-Article
        //insert
        $category_article=CategoryArticle::create([
            'article_id' => $article->id,
            'category_id' => $category->id
        ]);
        //update
        $category_article->category_id=$category->id;
        $category->save();
        //delete
        $this->instances_to_delete[]=$category_article;


        //Attribute_Group
        //insert
        $attribute_group=Attribute_Group::create([
            'name' => 'grün',
            'description' => 'tolle farbe',
            'position' => 2,
            'main_group' => 1,
            'is_filterable' => true,
            'active' => 1,
        ]);
        //update
        $attribute_group->position=5;
        $attribute_group->save();
        //delete
        $this->instances_to_delete[]=$attribute_group;


        //Attribute-Set
        //insert
        $attribute_set=Attribute_Set::create([
            'name' => 'textilooo',
            'description' => 'descriptioneeee'
        ]);
        //update
        $attribute_set->name='textiliii';
        $attribute_set->save();
        //delete
        $this->instances_to_delete[]=$attribute_set;

        //Attribute_sets_attribute_group
        //insert
        $attribute_set_attribute_group=Attribute_Sets_Attribute_Group::create([
            'fk_attributeset_id' => $attribute_set->id,
            'fk_attributegroup_id' => $attribute_group->id,
        ]);
        //update
        $attribute_set_attribute_group->fk_attributegroup_id=$attribute_group->id;
        $attribute_set_attribute_group->save();
        //delete
        $this->instances_to_delete[]=$attribute_set_attribute_group;

        //Brands
        //insert
        $brand=Brand::create([
            'name' => 'torontoo',
            'description' => 'toronto new',
            'slug' => 'sluggiiii'
        ]);
        //update
        $brand->slug='sluggmagg';
        $brand->save();
        //delete
        $this->instances_to_delete[]=$brand;


        //BrandSupplier
        //insert
        $brand_supplier=BrandsSuppliers::create([
            'fk_brand_id' => $brand->id,
            'hersteller-nr' => '0010101',
            'hersteller_name' => 'mr. modul',
        ]);
        //update
        $brand_supplier->hersteller_name='das Modul';
        $brand_supplier->save();
        //delete
        $this->instances_to_delete[]=$brand_supplier;

        //Article_Shipment
        //insert
        $article_shipment=Article_Shipment::create([
            'fk_article_id' => $article->id,
            'price' => '1000',
            'time' => 'shittiy',
            'description' => 'einfach so',
        ]);
        //update
        $article_shipment->price='1000000';
        $article_shipment->save();
        //delete
        $this->instances_to_delete[]=$article_shipment;



        //Shop-Sorting!!!!!
        $provider_config=Provider_Config::query()->where('fk_provider_id',$provider->id)->first();
        //insert
        $provider_config_attribute=Provider_Config_Attribute::create([
            'fk_provider_config_id' => $provider_config->id,
            'name' => 'shop_sorting',
            'value' => json_encode([]),
        ]);
        //update
        $provider_config_attribute->value=json_encode([]);
        $provider_config_attribute->save();
        //delete
        $this->instances_to_delete[]=$provider_config_attribute;




        //Bestandsupdate!!
        $branch=Branch::updateOrCreate(
            [
                'wawi_ident' => 'wawi-dawi',
            ],
            [

            'name' => 'cooles Geschäft',
            'active' => 1,
            'wawi_number' => '009',
          ]);

        //Jetzt ein erst Bestand setzen!!
        $article_variation->updateOrCreateStockInBranch($branch,100);

        //Dann auf 300 erhöhen
        $article_variation->updateOrCreateStockInBranch($branch,300);

        //Bestandsdaten zum Löschen anmelden
        $this->instances_to_delete[]=$branch;
        $this->instances_to_delete[]=$article_variation->branches->first();


        //Am Ende alles löschen!!
        $this->delete_instances();
    }
    protected function delete_instances(){
        $this->instances_to_delete= array_reverse($this->instances_to_delete);

        foreach($this->instances_to_delete as $instance_to_delete){
            $instance_to_delete->delete();
        }
    }
    protected function do_test_filter(){
        //$query=$this->manager->getProviderSynchro(1,"update","scheduled");
        $query=$this->manager->getProviderSynchroChronological(2,'scheduled');

        dd($query->get()->toArray());
    }
    protected function do_test_delete(){
        $this->manager->deleteProviderSynchro(2,"update",[],"scheduled",'no matter',null);


    }
}
