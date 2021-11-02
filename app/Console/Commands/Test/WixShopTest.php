<?php
/*

    Class WixShopTest


    Description
        The Wix Class is for Create, Edit, Update and Delete Article from Wix.


    Functions



*/

namespace App\Console\Commands\Test;

use Illuminate\Console\Command;

use App\Tenant;
use App\Tenant\Article;
use App\Tenant\Article_Attribute;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Price;
use App\Tenant\Order;
use App\Tenant\Order_Status;
use App\Tenant\Order_Attribute;
use App\Tenant\OrderArticle;
use App\Tenant\OrderArticle_Status;
use App\Tenant\BranchArticle_Variation;
use Illuminate\Support\Facades\Log;
use App\Tenant\CategoryArticle;
use App\Tenant\Category;
use App\Tenant\Collection;
use App\Manager\Content\ContentManager;
use Config;
use App\Http\Controllers\Tenant\Providers\Wix\WixController;
use App\Tenant\Article_Image;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Variation_Image_Attribute;
use stdClass;

use function GuzzleHttp\json_decode;

class WixShopTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:wix_shop_test {customer} {befehl}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test, ob der Wix-Shop angesprochen werden kann.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
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

        \DB::purge('tenant');
            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);
            \DB::connection('tenant');

        $command=$this->argument("befehl");

        if($command=="update_pictures"){
            echo $this->updatePicturesToWix();
        }
        if($command=="update_pictures_immediate"){
            echo $this->updatePicturesToWixImmediate();
        }
        elseif($command=="update_stocks") {
            echo $this->updateStockToWix();
        }
        elseif($command=="update_article") {
            echo $this->updateWixArticles();
        }

        //getProviderSynchro(wix_id,'update oder insert',$priority="immediate",$subject_class=BranchArticleVariation::class,$subject_id="no matter",$context="stock",$context_value="no matter")
        /*
        $articles = Article::query()->where('active','=','1')->
        whereDoesntHave('attributes',function($query){
            $query->where('name','wix_produkt_id');
        })->get();

        foreach($articles as $article) {
            echo "####### article_id = " . $article->id . " ###########";
            $articleAllData = $this->getAllArticleDataFromVisticle($article->id);
            $this->createArticleToWix($articleAllData);
            echo $article->id;
        }

        */

    }


    public function updateWixArticles() {
        $contentManager = new ContentManager();
        $vars = $contentManager->getProviderSynchro(2,'update',"scheduled",null,null,null,null)->orderBy('created_at')->get();

        foreach($vars as $var) {
            $articleData = $this->getAllArticleDataFromVisticle($var->fk_article_id);
            $this->updateArticleToWix($articleData);
        }

        $ids=$vars->pluck('id')->all();
        $contentManager->deleteProviderSynchro_IDs($ids);

    }

    public function updatePicturesToWix() {
        $contentManager = new ContentManager();
        $articlePictures = $contentManager->getProviderSynchro(2,'update',"scheduled",Article_Image::class,"no matter",null,null)->orderBy('created_at')->get();

        foreach($articlePictures as $articlePicture) {

            $article=Article::find( $articlePicture->fk_article_id);
            $count=$article->attributes()->where('name','wix_produkt_id')->count('*');
            if(!$count){
                //vielleicht loggen
                continue;
            }

            $wix_produkt_id = $this->getWixProductId($articlePicture->fk_article_id);
            $picture = Article_Image::query()->where('id', '=', $articlePicture->subject_id)->first();

            if($picture == null) {
                continue;
            }

            $controller= new WixController();
            $setImagesUrl = "https://www.wixapis.com/stores/v1/products/" . $wix_produkt_id . "/media";
            $setImagesMethod = "post";

            $setImagesArticleDataObject = new StdClass();

            $articleImageClass = new StdClass();
            $articleImageClass->url = "https://visticle.online/storage" . $picture->location;
            $articleImagesArray[] = $articleImageClass;


            $setImagesArticleDataObject->media = $articleImagesArray;
            $controller->getDataFromWixShop($setImagesUrl,$setImagesArticleDataObject,$setImagesMethod);

            //insertArticleImageAtrributeMediaId($picture_id,$mediaId)

        }

        $ids=$articlePictures->pluck('id')->all();
        $contentManager->deleteProviderSynchro_IDs($ids);


    }

    public function updatePicturesToWixImmediate() {
        $contentManager = new ContentManager();
        $articlePictures = $contentManager->getProviderSynchro(2,'update',"immediate",Article_Image::class,"no matter",null,null)->orderBy('created_at')->get();

        foreach($articlePictures as $articlePicture) {

            $article=Article::find( $articlePicture->fk_article_id);
            $count=$article->attributes()->where('name','wix_produkt_id')->count('*');
            if(!$count){
                //vielleicht loggen
                continue;
            }

            $wix_produkt_id = $this->getWixProductId($articlePicture->fk_article_id);
            $picture = Article_Image::query()->where('id', '=', $articlePicture->subject_id)->first();

            if($picture == null) {
                continue;
            }

            $controller= new WixController();
            $setImagesUrl = "https://www.wixapis.com/stores/v1/products/" . $wix_produkt_id . "/media";
            $setImagesMethod = "post";

            $setImagesArticleDataObject = new StdClass();

            $articleImageClass = new StdClass();
            $articleImageClass->url = "https://visticle.online/storage" . $picture->location;
            $articleImagesArray[] = $articleImageClass;


            $setImagesArticleDataObject->media = $articleImagesArray;
            $controller->getDataFromWixShop($setImagesUrl,$setImagesArticleDataObject,$setImagesMethod);

            //insertArticleImageAtrributeMediaId($picture_id,$mediaId)

        }

        $ids=$articlePictures->pluck('id')->all();
        $contentManager->deleteProviderSynchro_IDs($ids);


    }

    public function updateStockToWix() {
        $contentManager = new ContentManager();
        $articleStocks = $contentManager->getProviderSynchro(2,'update',"immediate",Article_Variation::class,"no matter","stock","no matter")->orderBy('created_at')->get();

        foreach($articleStocks as $articleStock) {
            $stock=$articleStock->context_value;
            $stock=json_decode($stock);
            $stock=$stock->stock;

            $fk_inventoryWixId = $this->getWixInventoryId($articleStock->fk_article_id);
            $fk_article_variation_id = $this->getFkArticleVariationid($articleStock->subject_id);

            if($fk_article_variation_id == "false") {
                continue;
            }

            if($fk_inventoryWixId == "false") {
                continue;
            }

            $controller= new WixController();
            $setUrl = "https://www.wixapis.com/stores/v2/inventoryItems/" . $fk_inventoryWixId;
            $setMethod = "patch";

            $setArticleDataObject = new StdClass();

            $setArticleDataObject->inventoryItem = new StdClass();
            $setArticleDataObject->inventoryItem->trackQuantity = true;

            $dataStd = new StdClass();
            $dataStd->variantId = $fk_article_variation_id;
            $dataStd->stock = $stock;
            $data_array = array($dataStd);

            $setArticleDataObject->inventoryItem->variants = $data_array;

            $controller->getDataFromWixShop($setUrl,$setArticleDataObject,$setMethod);

        }

        // Löschen der Syncro Tabelle WICHTIG NICHT VERGESSEN
        $ids=$articleStocks->pluck('id')->all();
        $contentManager->deleteProviderSynchro_IDs($ids);

    }

    public function getAllArticleDataFromVisticle($article_id) {

        //All Article Data in Array
        $articleAllData = new StdClass();
        //Articles Table
        $articles_table = Article::query()->where('id', '=', $article_id)->first();
        $articleAllData->article_table = new StdClass();

        // All Article Information Array
        $articleAllData->article_table->id = $article_id;
        $articleAllData->article_table->vstcl_identifier = $articles_table->vstcl_identifier;
        $articleAllData->article_table->name = $articles_table->name;
        $articleAllData->article_table->short_description = $articles_table->short_description;
        $articleAllData->article_table->description = $articles_table->description;
        $articleAllData->article_table->active = $articles_table->active;
        $articleAllData->article_table->number = $articles_table->number;
        $articleAllData->article_table->webname = $articles_table->webname;
        $articleAllData->article_table->metatitle = $articles_table->metatitle;
        $articleAllData->article_table->keywords = $articles_table->keywords;
        $articleAllData->article_table->tax = $articles_table->tax;


        //Article Attributes Table
        $article__attributes_table = Article_Attribute::query()->where('fk_article_id', '=', $article_id)->get();
        $articleAllData->article_attributes = array();

        // Insert All Article Attributes
        foreach($article__attributes_table as $article_attributes) {
            $articleAllData->article_attributes[$article_attributes->name] = $article_attributes->value;

        }

        //Article Images Table
        $article_images = Article_Image::query()->where('fk_article_id', '=', $article_id)->get();


        // Check if Article has a picture
        if(count($article_images) > 0) {
            $articleAllData->article_images = array();
            foreach($article_images as $article_image) {
                $articleAllData->article_images[] = "https://visticle.online/storage" . $article_image->location;
            }
        }
        // Else Article Image not exist NULL
        else {
            $articleAllData->article_images = "NULL";
        }


        //Article Variations
        $article_variations = Article_Variation::query()->where('fk_article_id', '=', $article_id)->get();


        if(count($article_variations) > 0) {
            $articleAllData->article_variations = array();
            foreach($article_variations as $article_variation) {
                $variation_data = new StdClass();
                $variation_data->id = $article_variation->id;
                $variation_data->fk_article_id = $article_variation->fk_article_id;
                $variation_data->vstcl_identifier = $article_variation->vstcl_identifier;
                $articleAllData->article_variations[] = $variation_data;
            }
        }
        else {
            $articleAllData->article_variations = "NULL";
        }



        //Article Variation Attributes
        $article_variation_attributes_array = array();
        if(count($articleAllData->article_variations) > 0) {
            $articleAllData->article_variations_attributes = array();
            foreach($articleAllData->article_variations as $article_variation) {
                $article_variation_attributes = Article_Variation_Attribute::query()->where('fk_article_variation_id', '=', $article_variation->id)->get();

                foreach($article_variation_attributes as $article_variation_attribute) {
                    $article_variation_attributes_array[$article_variation_attribute->name] = $article_variation_attribute->value;
                }
                $article_variation_attributes_array["variation_id"] = $article_variation->id;
                $tmp_attribute_data_array[$article_variation->id] = $article_variation_attributes_array;
                $articleAllData->article_variations_attributes = $tmp_attribute_data_array;
            }
        }
        else {
            $articleAllData->article_variations_attributes = NULL;
        }



        //Article Variation Image
        $article_variation_images_array = array();
        if(count($articleAllData->article_variations) > 0) {

            foreach($articleAllData->article_variations as $article_variation) {
                $article_variation_images = Article_Variation_Image::query()->where('fk_article_variation_id', '=', $article_variation->id)->get();
                if(count($article_variation_images) > 0) {
                    foreach($article_variation_images as $article_variation_image) {
                        if(strpos($article_variation_image->location,"1024")) {
                            $article_variation_images_array[] = "https://visticle.online/storage" .$article_variation_image->location;

                        }
                    }
                    $tmp_images_array[$article_variation->id] = array_unique($article_variation_images_array);
                    $articleAllData->article_variations_images = $tmp_images_array;
                }
                else {
                    $articleAllData->article_variations_images = "NULL";
                }
            }
        }
        else {
            $articleAllData->article_variations_images = "NULL";
        }


        //Article Variation Price
        $article_variation_prices_array = array();
        $tmpPrice = "";
        if(count($articleAllData->article_variations) > 0) {
            foreach($articleAllData->article_variations as $article_variation) {
                $article_variation_prices = Article_Variation_Price::query()->where('fk_article_variation_id', '=', $article_variation->id)->get();
                foreach($article_variation_prices as $article_variation_price) {
                    $article_variation_prices_array[$article_variation_price->name] = $article_variation_price->value;
                    $tmpPrice = $article_variation_price->value;
                }
                $tmp_price_array[$article_variation->id] = $article_variation_prices_array;
                $articleAllData->article_variations_prices = $tmp_price_array;
            }
        }
        else {
            $articleAllData->article_variation_prices = "NULL";
        }

        // Article Price
        $articleAllData->article_price = $tmpPrice;


        //Article Variation Stock
        if(count($articleAllData->article_variations) > 0) {
            foreach($articleAllData->article_variations as $article_variation) {
                $article_variation_stocks = BranchArticle_Variation::query()->where('fk_article_variation_id', '=', $article_variation->id)->first();
                $tmp_stock_array[$article_variation->id] = $article_variation_stocks->stock;
            }

            $articleAllData->article_variations_stock = $tmp_stock_array;

        }
        else {
            $articleAllData["article_variation_stocks"] = "NULL";
        }


        // Article Categories
        $allArticleCategoriesArray = array();
        $allArticleCategories = CategoryArticle::query()->where('article_id', '=', $article_id)->get();

        if(count($allArticleCategories) > 0) {
            foreach($allArticleCategories as $allArticleCategory) {
                $category_name = Category::query()->where('id', '=', $allArticleCategory->category_id)->first();
                if(strlen($category_name->name) > 3) {

                    $category_collection = \DB::connection('tenant')->table('collection')->where('name', '=', $category_name->name)->first();
                    if($category_collection == null) {
                        $articleAllData->article_categories = "NULL";
                    }
                    else {
                        $allArticleCategoriesArray[] = $category_collection->wix_id;
                    }
                }
                else {
                    continue;
                }
            }
            if(count($allArticleCategoriesArray) > 0) {
                $articleAllData->article_categories = $allArticleCategoriesArray;
            }
            else {
                $articleAllData->article_categories = "NULL";
            }
        }
        else {
            $articleAllData->article_categories = "NULL";
        }

        return $articleAllData;
    }

    // Create Article
    public function createArticleToWix($article) {

        // New Article Object
        $articleDataObject = new StdClass();
        $articleDataObject->product = new StdClass();

        // Article Name
        if($article->article_table->name == "") {
            $articleDataObject->product->name = $article->article_table->number;
        }
        else {
            $articleDataObject->product->name = $article->article_table->name;
        }

        // Article Type Standard = physical
        $articleDataObject->product->productType = "physical";

        // Article Price
        $articleDataObject->product->priceData = new StdClass();

        $articlePrice = str_replace(",",".",$article->article_price);
        $articleDataObject->product->priceData->price = $articlePrice;

        // Article Description
        if($article->article_table->description == "") {
            if($article->article_table->short_description == "") {
                $articleDataObject->product->description = "";
            }
            else {
                $articleDataObject->product->description = $article->article_table->short_description;
            }
        }
        else {
            $articleDataObject->product->description = $article->article_table->description;
        }

        // Article Sku
        $articleDataObject->product->sku = $article->article_table->vstcl_identifier;

        $articleDataObject->product->visible = true;

        // Article Visible
        if($article->article_images == "NULL") {
            $articleDataObject->product->visible = false;
        }

        if($article->article_variations_images == "NULL") {
            $articleDataObject->product->visible = false;
        }


        // Article Managevariants
        if(count($article->article_variations) > 0) {
            $articleDataObject->product->manageVariants = true;
        }
        else {
            $articleDataObject->product->manageVariants = false;
        }

        // Managevariant True
        if($articleDataObject->product->manageVariants == true) {

            $articleDataObject->product->productOptions = array();
            $articleDataObject->product->productOptions[] = new StdClass();
            $articleDataObject->product->productOptions[0]->name = "Size";

            $optionsSizeChoices = array();

            foreach($article->article_variations_attributes as $article_variation_ids) {
                $optionsSizeChoices[] = $article_variation_ids["fee-size"];
            }

            $tmpOptionsSizeChoices = array_unique($optionsSizeChoices);

            foreach($tmpOptionsSizeChoices as $tmpOptionSizeChoice) {
                $option_sets = new StdClass();
                $option_sets->value = $tmpOptionSizeChoice;
                $option_sets->description = $tmpOptionSizeChoice;
                $originalOptionsSizeChoices[] = $option_sets;
            }

            $articleDataObject->product->productOptions[0]->choices = $originalOptionsSizeChoices;

            $optionsColorChoices = array();
            $colorChoices = array();
            foreach($article->article_variations_attributes as $article_variation_ids) {
                if($article_variation_ids["fee-info1"] == "") {
                    if(ctype_alpha($article_variation_ids["fee-color"])) {
                        $colorChoices[] = $article_variation_ids["fee-color"];
                    }
                }
                else {
                    $colorChoices[] = $article_variation_ids["fee-info1"];
                }
            }

            if(count($colorChoices) > 0) {

                $tmpColorChoices = array_unique($colorChoices);

                foreach($tmpColorChoices as $tmpColorChoice) {
                    $colorOption_set = new StdClass();
                    $colorOption_set->value = $tmpColorChoice;
                    $colorOption_set->description = $tmpColorChoice;
                    $optionsColorChoices[] = $colorOption_set;
                }
                $articleDataObject->product->productOptions[] = new StdClass();
                $articleDataObject->product->productOptions[1]->name = "Color";
                $articleDataObject->product->productOptions[1]->choices = $optionsColorChoices;

            }

        }

        if($article->article_categories != "NULL") {
            if(is_array($article->article_categories)) {
                $allArticleCategories = array();
                foreach($article->article_categories as $art_art_cat) {
                    if($art_art_cat != "") {
                        $allArticleCategories[] = $art_art_cat;
                    }
                }
            }
        }

        $controller= new WixController();
        $url = "https://www.wixapis.com/stores/v1/products";
        $method = "post";
        $productResult = $controller->getDataFromWixShop($url,$articleDataObject,$method);

        $new_article_id = "";
        if(is_array($article->article_table->id)) {
            $new_article_id = $article->article_table->id[0];
        }
        else {
            $new_article_id = $article->article_table->id;
        }
        Article_Attribute::insert([[
            'fk_article_id' => $new_article_id,
            'name' => 'wix_inventoryItem_id',
            'value' => $productResult->product->inventoryItemId],
            ['fk_article_id' => $new_article_id,
            'name' => 'wix_produkt_id',
            'value' => $productResult->product->id]
        ]);

        //Collection IDs update


        if($article->article_categories != "NULL") {
            foreach($allArticleCategories as $allCategories) {
                if(strlen($allCategories) > 3) {
                    $catUrl = "https://www.wixapis.com/stores/v1/collections/".$allCategories."/productIds";
                    $catMethod = "post";
                    $articleCatDataObject = new StdClass();
                    $articleCatDataObject->productIds = array($productResult->product->id);
                    $controller->getDataFromWixShop($catUrl,$articleCatDataObject,$catMethod);
                }
                else {
                    continue;
                }
            }
        }


        // Article Variations Identification save
        $updateVariantsDatas = array();

        foreach($article->article_variations_attributes as $article_variation_attribute) {
            foreach($productResult->product->variants as $productResult->product->variant) {
                if(property_exists($productResult->product->variant->choices,"Color") == true) {
                    if($productResult->product->variant->choices->Size === $article_variation_attribute["fee-size"] && $productResult->product->variant->choices->Color === $article_variation_attribute["fee-info1"]) {
                       Article_Variation_Attribute::query()->insert([
                            'fk_article_variation_id' => $article_variation_attribute["variation_id"],
                            'name' => "wix_variant_id",
                            'value' => $productResult->product->variant->id
                        ]);
                        $updateVariantsDatas[] = array(
                            "fk_article_variant_id" => $article_variation_attribute["variation_id"],
                            "wix_variant_id" => $productResult->product->variant->id
                        );
                    }
                }
                else {
                    if($productResult->product->variant->choices->Size === $article_variation_attribute["fee-size"]) {
                        Article_Variation_Attribute::query()->insert([
                            'fk_article_variation_id' => $article_variation_attribute["variation_id"],
                            'name' => "wix_variant_id",
                            'value' => $productResult->product->variant->id
                        ]);
                    }
                }
            }

        }

        $controller= new WixController();
        $updateVariantUrl = "https://www.wixapis.com/stores/v1/products/".$productResult->product->id."/variants";
        $updateVariantMethod = "patch";

        $updateTmpData = array();
        foreach($article->article_variations as $article_var) {
            $updateTmpData[$article_var->id] = $article_var->vstcl_identifier;
        }

        $tmpTmpArray = array();
        foreach($updateVariantsDatas as $updateVariantsData) {

            $updateVariantsObjectDatas = new StdClass();
            $updateVariantsObjectDatas->variants = $updateVariantsData["wix_variant_id"];
            $updateVariantsObjectDatas->variant = new StdClass();
            $updateVariantsObjectDatas->variant->priceData = new StdClass();
            $updateVariantsObjectDatas->variant->priceData->currency = "EUR";
            $updateVariantsObjectDatas->variant->priceData->price = $article->article_variations_prices[$updateVariantsData["fk_article_variant_id"]]["standard"];
            $updateVariantsObjectDatas->variant->priceData->discountedPrice = $article->article_variations_prices[$updateVariantsData["fk_article_variant_id"]]["discount"];
            $updateVariantsObjectDatas->sku = $updateTmpData[$updateVariantsData["fk_article_variant_id"]];
            $updateVariantsObjectDatas->visible = true;
            $tmpTmpArray[] = $updateVariantsObjectDatas;

        }
        $updateVariantArticleDataObject = new StdClass();
        $updateVariantArticleDataObject->variants = $tmpTmpArray;

        $updateVariantResult = $controller->sendDataToWixShop($updateVariantUrl,$updateVariantArticleDataObject,$updateVariantMethod);

        $updateInventoryUrl = "https://www.wixapis.com/stores/v2/inventoryItems/" . $productResult->product->inventoryItemId;
        $updateInventoryMethod = "patch";

        $updateInventoryArticleDataObject = new StdClass();
        $updateInventoryArticleDataObject->inventoryItem = new StdClass();
        $updateInventoryArticleDataObject->inventoryItem->trackQuantity = true;

        $inventoryTmpData = array();
        foreach($updateVariantsDatas as $updateInventoryData) {
            $branches = BranchArticle_Variation::query()->where('fk_article_variation_id', '=', $updateInventoryData["fk_article_variant_id"])->first();
            $tmpInventory = new StdClass();
            $tmpInventory->variantId =  $updateInventoryData["wix_variant_id"];
            if($branches->stock > 0) {
                $tmpInventory->inStock = true;
            }
            else {
                $tmpInventory->inStock = false;
            }
            $tmpInventory->quantity = $branches->stock;
            $inventoryTmpData[] = $tmpInventory;

        }
        $updateInventoryArticleDataObject->inventoryItem->variants = $inventoryTmpData;
        $controller->sendDataToWixShop($updateInventoryUrl,$updateInventoryArticleDataObject,$updateInventoryMethod);


        if($article->article_variations_images != "NULL") {
            $controller= new WixController();
            $setImagesUrl = "https://www.wixapis.com/stores/v1/products/" . $productResult->product->id . "/media";
            $setImagesMethod = "post";

            $setImagesArticleDataObject = new StdClass();

            $articleImagesArray = array();
            $arc_var_img = array();
            foreach($article->article_variations_images as $article_variation_image) {
                //$arc_var_img["url"] = $article_variation_image;
                foreach($article_variation_image as $arc_var_img) {
                    $articleImageClass = new StdClass();
                    $articleImageClass->url = $arc_var_img;
                    $articleImagesArray[] = $articleImageClass;
                }
                break;

            }
            $setImagesArticleDataObject->media = $articleImagesArray;
            $controller->getDataFromWixShop($setImagesUrl,$setImagesArticleDataObject,$setImagesMethod);

        }

        return $productResult;

    }

    // Update Article
    public function updateArticleToWix($article) {

        $article_wix = Article_Attribute::query()->where('fk_article_id','=', $article->article_table->id)->where('name', '=', 'wix_produkt_id')->first();
        $article_wix_id = $article_wix->value;

        if($article_wix_id != "") {

            // New Article Object
            $articleDataObject = new StdClass();
            $articleDataObject->product = new StdClass();

            // Article Name
            if($article->article_table->name == "") {
                $articleDataObject->product->name = $article->article_table->number;
            }
            else {
                $articleDataObject->product->name = $article->article_table->name;
            }

            // Article Type Standard = physical
            $articleDataObject->product->productType = "physical";

            // Article Price
            $articleDataObject->product->priceData = new StdClass();

            $articlePrice = str_replace(",",".",$article->article_price);
            $articleDataObject->product->priceData->price = $articlePrice;

            // Article Description
            if($article->article_table->description == "") {
                if($article->article_table->short_description == "") {
                    $articleDataObject->product->description = "";
                }
                else {
                    $articleDataObject->product->description = $article->article_table->short_description;
                }
            }
            else {
                $articleDataObject->product->description = $article->article_table->description;
            }

            // Article Sku
            $articleDataObject->product->sku = $article->article_table->vstcl_identifier;

            $articleDataObject->product->visible = true;

            // Article Visible
            if($article->article_images == "NULL") {
                $articleDataObject->product->visible = false;
            }

            if($article->article_variations_images == "NULL") {
                $articleDataObject->product->visible = false;
            }

            if($article->article_categories != "NULL") {
                if(is_array($article->article_categories)) {
                    $allArticleCategories = array();
                    foreach($article->article_categories as $art_art_cat) {
                        if($art_art_cat != "") {
                            $allArticleCategories[] = $art_art_cat;
                        }
                    }
                }
            }

            $controller= new WixController();
            $url = "https://www.wixapis.com/stores/v1/products/".$article_wix_id;
            $method = "patch";
            $productResult = $controller->getDataFromWixShop($url,$articleDataObject,$method);


            //Collection IDs update


            if($article->article_categories != "NULL") {
                foreach($allArticleCategories as $allCategories) {
                    if(strlen($allCategories) > 3) {
                        $catUrl = "https://www.wixapis.com/stores/v1/collections/".$allCategories."/productIds";
                        $catMethod = "post";
                        $articleCatDataObject = new StdClass();
                        $articleCatDataObject->productIds = array($productResult->product->id);
                        $controller->getDataFromWixShop($catUrl,$articleCatDataObject,$catMethod);
                    }
                    else {
                        continue;
                    }
                }
            }
            return $productResult;
        }

    }

    public function updateWixStock() {
        $branch_article__variations = BranchArticle_Variation::query()->where('updated_at' > NOW())->get();

        foreach($branch_article__variations as $branch_article_variation) {
            $wixInventoryItemId = $this->getWixInventoryId($branch_article_variation->fk_article_variation_id);
        }
        $articles = Article::query()-> whereHave('attributes',function($query){
            $query->where('name','wix_produkt_id');
        })->get();
    }



    public function getWixProductId($article_id) {
        $articles = Article_Attribute::query()->where('fk_article_id', '=', $article_id)->where('name','=','wix_produkt_id')->first();
        $articleValue = $articles->value;
        return $articleValue;
    }

    public function getWixInventoryId($article_id) {
        $articles = Article_Attribute::query()->where('fk_article_id', '=', $article_id)->where('name','=','wix_inventoryItem_id')->first();
        if($articles != "") {
            $articleValue = $articles->value;
            return $articleValue;
        }
        return "false";

    }

    public function insertArticleImageAtrributeMediaId($picture_id,$mediaId) {
        Article_Image_Attribute::insert([
            'fk_article_image_id' => $picture_id,
            'name' => 'wixMediaId',
            'value' => $mediaId
        ]);
    }


    public function getFkArticleVariationid($id) {
        $fk_id = Article_Variation_Attribute::query()->where('id','=', $id)->first();
        if($fk_id == NULL) {
        $fk_variation_attribute_id = $fk_id->fk_article_variation_id;
        $fk_variation = Article_Variation_Attribute::query()->where('fk_article_variation_id', '=', $fk_variation_attribute_id)->where('name','wix_variant_id')->first();
        return $fk_variation->value;
        }
        return "false";
    }


}
