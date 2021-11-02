<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWArticle;
use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWArticleVariation;
use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWBranchVariation;
use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWCategory;
use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWOrder;
use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWpropertyGroups;

use App\Tenant\Provider;
use App\Tenant\Provider_Config;
use App\Tenant\Provider_Type;

use App\Tenant\Article;
use App\Tenant\Article_Image;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Category;
use App\Tenant\BranchArticle_Variation;
use App\Tenant\ArticleProvider;

use App\Tenant\Article_Eigenschaften;
use App\Tenant\Article_Eigenschaften_Data;
use App\Tenant\Article_Eigenschaften_Articles;
use App\Tenant\Article_Eigenschaften_Categories;

use App\Tenant\Order;
use App\Tenant\Order_Attribute;
use App\Tenant\OrderArticle;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Price;
use App\Tenant\Payment;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use Log;
use Redirect,Response;

class ShopwareAPIController extends Controller {

    //general props
    private $shops = [];
    private $shopConfigs = [];

    public function __construct() {
        $type = Provider_Type::where('provider_key','=','shopware')->first();
        if($type) {
            $this->shops = Provider::where('fk_provider_type','=', $type->id)->get();
            if(!empty($this->shops)) {
                $this->http_client = new Client();
            }
        }

    }


    public function article_batch($customer = false,$Log = false)
    {
        $articles = Article::whereHas('images')->whereHas('attributes', function($query) {
            $query->where('name','=','sw_id')->whereNotNull('value');
        })->get();

        foreach($articles as $article)
        {   if($Log){echo "\n[A:".$article->id."]...";}
            $check = true;//$this->checkArticle($article);
            if($Log){echo "[active:".(($check)? "1":"0")."]";}
            if($check)
            {   $variations = $article->variations()->get();
                foreach($variations as $var){$check = $this->update_article_variation($var);}
            }
            $article->batch_nr=null;$article->save();
            if($Log){echo "[OK:".$check."]";}
        }
    }
    //Article
    public function log_article_list($shop = null,$limit=20,$start=0)
    {
        if(!empty($this->shops))
        {
            $resOrderDetails = $this->callAPI('GET', 'articles?limit='.$limit.'&start='.$start, [], $shop);
            if(!$resOrderDetails || !is_object($resOrderDetails['res']))
            {return;} if(!isset($resOrderDetails['res']->data)){return;}
            foreach($resOrderDetails['res']->data as $ShopArticle)
            {
                Log::info(' :sw_id:'. $ShopArticle->id." | ".$ShopArticle->name);
            }
        }
    }

    public function get_article_list($shop = null,$limit=20,$start=0)
    {
        if(!empty($this->shops))
        {
            $resOrderDetails = $this->callAPI('GET', 'articles?limit='.$limit.'&start='.$start, [], $shop);
            if(!$resOrderDetails || !is_object($resOrderDetails['res']))
            {return;} if(!isset($resOrderDetails['res']->data)){return;}
            foreach($resOrderDetails['res']->data as $ShopArticle)
            {   $thisNumber=substr($ShopArticle->mainDetail->number, 0, -2);
				$article = Article::where('number','=',$thisNumber)->first();
                if($article)
                {
					$sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
                    if(!$sw_id)
                    {	echo "[".$ShopArticle->name.' :sw_id:'. $ShopArticle->id."]";
                        $article->updateOrCreateAttribute('sw_id', $ShopArticle->id);

                        Log::info($ShopArticle->name.' :sw_id:'. $ShopArticle->id);

                    }
                }
            }
        }
    }
    public function get_article_swID_byNumber($article, $shop = null)
    {
        if(!empty($this->shops))
        {
            $resOrderDetails = $this->callAPI('GET', 'articles/'.$article->number.'.1?useNumberAsId=true', [], $shop);
			if(!$resOrderDetails)
			{
				$this->create_article($article);
			}else
			{
				if(!$resOrderDetails || !is_object($resOrderDetails['res']))
				{return;}

				if($resOrderDetails && is_object($resOrderDetails['res']))
				{
                    if(isset($resOrderDetails['res']->data)) {
                        if(isset($resOrderDetails['res']->data->id)) {
							$Internarticle = Article::where('id','=',"".$article->id)->first();
                            $Internarticle->updateOrCreateAttribute('sw_id', $resOrderDetails['res']->data->id);
                            //Log::info(json_encode($resOrderDetails['res']->data));
                            //mainDetail->name
                            echo "[update sw_id: ".$resOrderDetails['res']->data->id."]";
                        }
                    }
                }
			}

        }
    }
    public function kill_article($articleID, $shop = null)
    {
        if(!empty($this->shops))
        {
            $resOrderDetails = $this->callAPI('DELETE', 'articles/'.$articleID, [], $shop);
            if(!$resOrderDetails || !is_object($resOrderDetails['res']))
            {return;}
            Log::info($resOrderDetails['res']);
        }
    }
    public function update_article(Article $article, $shop = null)
    {
        if(!empty($this->shops) || $shop)
        {
            $resource = new SWArticle();
            //check if article has an sw_id already
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                $this->create_article($article);
            }
            else {
                if(!empty($article->filledName()))
                {
                    $resource->setRId($sw_id->value);
                    $resource->buildPut($article);
                    $res = $this->callAPI('PUT','articles/'.$resource->getRId(), $resource->getBody(), $shop, $article);
                }
            }
        }
    }
    public function create_article(Article $article, $shop = null,$Log=false)
    {

        if(!empty($this->shops))
        {
            if(!empty($article->filledName()) && $article->filledName() != "")
            {

                $articleNumber = str_replace('/','-',$article->number);
                $resOrderDetails = $this->callAPI('GET', 'articles/'.$articleNumber.'.1?useNumberAsId=true', [], $shop);
                //$resOrderDetails = $this->callAPI('GET', 'articles/'.'?useNumberAsId=true', [], $shop);
                if(!$resOrderDetails)
                {

                    $resource = new SWArticle();

                    $content = $resource->buildPost($article);

                    $res = $this->callAPI('POST','articles/', $content, $shop, $article);

                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                Log::info("sw_id ".$res['res']->data->id." set for ID: ".$article->id." number: ".$article->number);
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }

                }else
                {

                    // Artikel existiert bereits > Update
                    if(!$resOrderDetails || !is_object($resOrderDetails['res'])) {return;}
                    if($resOrderDetails && is_object($resOrderDetails['res']))
                    {
                        if(isset($resOrderDetails['res']->data)) {
                            if(isset($resOrderDetails['res']->data->id)) {
                                $resource = new SWArticle();
                                $Internarticle = Article::where('id','=',"".$article->id)->first();
                                $Internarticle->updateOrCreateAttribute('sw_id', $resOrderDetails['res']->data->id);
                                $resource->setRId($resOrderDetails['res']->data->id);
                                $resource->buildPut($article);

                                $res = $this->callAPI('PUT','articles/'.$resource->getRId(), $resource->getBody(), $shop, $article);

                            }
                        }
                    }
                }

                if($Log){echo " ".$article->id." [OK]";}
            }else{ Log::info("sw Article filledName empty for ID: ".$article->id." number: ".$article->number); }
        }
    }
    public function delete_article(Article $article, $shop = null) {
        if(!empty($this->shops))
        {
            $resource = new SWArticle();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if($sw_id)
            {
                $resource->setRId($sw_id->value);
                $resource->buildDelete($article);
                $this->callAPI('DELETE','articles/'.$resource->getRId(), [], $shop, $article);
            }

        }
    }

    public function active_article(Article $article, $shop = null) {
        if(!empty($this->shops))
        {
            $resource = new SWArticle();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                $this->create_article($article, $shop);
                return;
            }
            $resource->setRId($sw_id->value);
            $resource->buildActive($article);
            $res = $this->callAPI('PUT','articles/'.$resource->getRId(), $resource->getBody(), $shop);
        }
    }

    public function deactive_article(Article $article, $shop = null) {
        if(!empty($this->shops))
        {
            $resource = new SWArticle();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }
            $resource->setRId($sw_id->value);
            $resource->buildDeActive($article);
            $res = $this->callAPI('PUT','articles/'.$resource->getRId(), $resource->getBody(), $shop);
        }
    }
    //Article_Image
   /*public function update_article_image(Article_Image $article_image) {
        $article = $article_image->article()->first();
        if($article){ $this->update_article_images_by_article($article); }
    }*/
    public function create_article_image(Article_Image $article_image) {
        $article = $article_image->article()->first();
        if($article){ $this->update_article_images_by_article($article); }
    }
    public function delete_article_image(Article_Image $article_image) {
        $article = $article_image->article()->first();
        if($article){ $this->update_article_images_by_article($article); }
    }
    public function update_article_images_by_article(Article $article, $shop = null)
    {
        if(!empty($this->shops))
        {
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }
                }
            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    public function update_cron_article_image(Article_Image $article_image, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article = $article_image->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }
                }
            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    public function create_cron_article_image(Article_Image $article_image, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article = $article_image->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }
                }
            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    public function delete_cron_article_image(Article_Image $article_image, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article = $article_image->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }
                }
            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    //Article_Image_Attribute
    public function update_cron_article_image_attr(Article_Image_Attribute $article_image_attr, $shop = null)
    {
        if(!empty($this->shops))
        {
            $image = $article_image_attr->image()->first();
            $article = $image->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }
                }
            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    public function create_cron_article_image_attr(Article_Image_Attribute $article_image_attr, $shop = null)
    {
        if(!empty($this->shops))
        {
            $image = $article_image_attr->image()->first();
            $article = $image->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                }
            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    public function delete_cron_article_image_attr(Article_Image_Attribute $article_image_attr, $shop = null)
    {
        if(!empty($this->shops))
        {
            $image = $article_image_attr->image()->first();
            $article = $image->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            $resource = new SWArticle();
            if(!$sw_id)
            {
                if(!empty($article->filledName()) && $article->filledName() != "")
                {
                    $resource->buildPost($article);
                    $res = $this->callAPI('POST','articles', $resource->getBody(), $shop, $article);
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data)) {
                            if(isset($res['res']->data->id)) {
                                $article->updateOrCreateAttribute('sw_id', $res['res']->data->id);
                            }
                        }
                    }
                }

            }
            else {
                $resource->buildImage($article);
                $this->callAPI('PUT','articles/'.$sw_id->value, $resource->getBody(), $shop, $article);
            }
        }
    }
    //ArticleProvider
    public function create_articleprovider(ArticleProvider $articleprovider)
    {
        if(!empty($this->shops))
        {
            $article = $articleprovider->article()->first();
            $shop = $articleprovider->provider()->first();
            if($articleprovider->active == 1) {
                $this->active_article($article, $shop);
            }
            else {
                $this->deactive_article($article, $shop);
            }
        }
    }
    public function update_articleprovider(ArticleProvider $articleprovider)
    {
        if(!empty($this->shops))
        {
            $article = $articleprovider->article()->first();
            $shop = $articleprovider->provider()->first();
            if($articleprovider->active == 1) {
                $this->active_article($article, $shop);
            }
            else {
                $this->deactive_article($article, $shop);
            }
        }
    }
    public function delete_articleprovider(ArticleProvider $articleprovider)
    {
        if(!empty($this->shops))
        {
            $article = $articleprovider->article()->first();
            $shop = $articleprovider->provider()->first();
            $this->deactive_article($article, $shop);
        }
    }

    //Article_Variation
    public function update_article_variation(Article_Variation $article_variation, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article = $article_variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }

            $var_id = $article_variation->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
            $resource = new SWArticleVariation();
            if(!$var_id)
            {
                $resource->buildPost($article_variation);
                $this->callAPI('POST','variants?useNumberAsId=true', $resource->getBody(), $shop, $article);
            }
            else {
                $resource->buildPut($article_variation);
                $this->callAPI('PUT','variants/'.$var_id->value.'?useNumberAsId=true', $resource->getBody(), $shop, $article);
            }
        }
    }

    public function update_article_variation_price($price, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article_variation = $price->variation()->first();
            $article = $article_variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) { return; }

            $var_id = $article_variation->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
            $resource = new SWArticleVariation();
            if(!$var_id)
            {
                $resource->buildPost($article_variation);
                $this->callAPI('POST','variants?useNumberAsId=true', $resource->getBody(), $shop, $article);
            }
            else {
                $resource->buildPut($article_variation);
                $this->callAPI('PUT','variants/'.$var_id->value.'?useNumberAsId=true', $resource->getBody(), $shop, $article);
            }
        }
    }

    public function create_article_variation(Article_Variation $article_variation, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article = $article_variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }

            $resource = new SWArticleVariation();
            $resource->buildPost($article_variation);

            $this->callAPI('POST','variants?useNumberAsId=true', $resource->getBody(), $shop, $article);
        }
    }
    public function delete_article_variation(Article_Variation $article_variation, $shop = null)
    {
        if(!empty($this->shops))
        {
            $article = $article_variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }
            $var_id = $article_variation->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
            if(!$var_id) {
                return;
            }
            $resource = new SWArticleVariation();
            $resource->buildDelete($article_variation);
            $this->callAPI('DELETE','variants/'.$var_id->value.'?useNumberAsId=true', $resource->getBody(), $shop, $article);
        }
    }

    //Variation Branch Stock
    public function update_variation_branch_stock(BranchArticle_Variation $stock, $shop = null)
    {
        if(!empty($this->shops))
        {
            $variation = $stock->article_variation()->first();
            $article = $variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }
            $var_id = $variation->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
            if(!$var_id) {
                return;
            }
            $resource = new SWArticleVariation();
            $resource->buildStock($variation);
            $this->callAPI('PUT','variants/'.$var_id->value.'?useNumberAsId=true', $resource->getBody(), $shop, $article);
        }
    }
    public function create_variation_branch_stock(BranchArticle_Variation $stock, $shop = null)
    {
        if(!empty($this->shops))
        {
            $variation = $stock->article_variation()->first();
            $article = $variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }
            $var_id = $variation->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
            if(!$var_id) {
                return;
            }
            $resource = new SWArticleVariation();
            $resource->buildStock($variation);
            $this->callAPI('PUT','variants/'.$var_id->value.'?useNumberAsId=true', $resource->getBody(), $shop, $article);
        }
    }
    public function delete_variation_branch_stock(BranchArticle_Variation $stock, $shop = null)
    {
        if(!empty($this->shops))
        {
            $variation = $stock->article_variation()->first();
            $article = $variation->article()->first();
            $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
            if(!$sw_id) {
                return;
            }
            $var_id = $variation->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
            if(!$var_id) {
                return;
            }
            $resource = new SWArticleVariation();
            $resource->buildStock($variation);
            $this->callAPI('PUT','variants/'.$var_id->value.'?useNumberAsId=true', $resource->getBody(), $shop, $article);
        }
    }

    //Categories
    public function update_category(Category $category, $Log = false)
    {
        if(!empty($this->shops))
        {
            if($category->fk_wawi_id != null) { return; }

            if($category->wawi_number == '' || $category->wawi_number == null)
            { $this->create_category($category); }

            $updateEigenschaftenSet = $this->update_eigenschaftSet($category, $Log);

            $resource = new SWCategory();
            $resource->setRId($category->wawi_number);
            $resource->buildPut($category);
            $this->callAPI('PUT','categories/'.$resource->getRId(), $resource->getBody());
        }
    }
    public function create_category(Category $category, $Log = false)
    {
        if(!empty($this->shops))
        {
            $resource = new SWCategory();
            $resource->buildPost($category);
            $res = $this->callAPI('POST','categories', $resource->getBody());
            if($res && is_object($res['res'])) {
                if(isset($res['res']->data)) {
                    if(isset($res['res']->data->id)) {
                        $category->update([ 'wawi_number' => $res['res']->data->id ]);
                        $updateEigenschaftenSet = $this->update_eigenschaftSet($category, $Log);
                    }
                }
            }
        }
    }
    public function delete_category(Category $category,$Log=false)
    {
        if(!empty($this->shops))
        {
            if($category->fk_wawi_id != null) {
                return;
            }
            if($category->wawi_number == '' || $category->wawi_number == null) {
                return;
            }
            if(!empty($category->sw_id))
            {$deleteEigenschaftenSet = $this->delete_eigenschaftSet($category->sw_id, $Log);}

            $this->callAPI('DELETE','categories/'.$category->wawi_number);
        }
    }

    //Orders
    public function getOrders($shop = null)
    {
        if(!empty($this->shops))
        {
            $pf = 'sw_';
            $body = [];
            $latestOrder = Order_Attribute::where('name', '=', $pf.'orderTime')->latest('value')->first();
            if($latestOrder) {
                $body['filter'] = [];
                $body['filter'][] = [
                    'property' => 'orderTime',
                    'expression' => '>',
                    'value' => $latestOrder->value
                ];
            }
            //For testing only
            else {
                $body['filter'] = [];
                $body['filter'][] = [
                    'property' => 'orderTime',
                    'expression' => '>',
                    'value' => '2020-08-05'
                ];
            }
            $res = $this->callAPI('GET', 'orders', $body);
            if(!$res) {
                return;
            }
            if(!is_object($res['res'])) {
                return;
            }
            if(!isset($res['res']->data)) {
                return;
            }
            foreach($res['res']->data as $swOrder) {
                $resOrderDetails = $this->callAPI('GET', 'orders/'.$swOrder->id, [], $shop);
                if(!$resOrderDetails) {
                    continue;
                }
                if(!is_object($resOrderDetails['res'])) {
                    continue;
                }

                $orderDetails = $resOrderDetails['res']->data;
                $order = Order::whereHas('attributes', function($query) use($swOrder) {
                    $query->where('name','=','sw_id')->where('value','=', $swOrder->id);
                })->first();
                if(!$order) {
                    $orderCount = Order::count();
                    $order = Order::create([
                        'fk_provider_id' => $res['shop']->id,
                        'number' => ++$orderCount,
                        'fk_order_status_id' => 1,
                    ]);
                }

                //Order Foreign Ids
                $paymentId = 2;
                $shipmentId = 1;
                $orderStatusId = 2;

                //Shopware Foreign Names
                $swPayment = $orderDetails->payment->name;
                $swPaymentStatus = $orderDetails->paymentStatus->name;
                $swOrderStatus = $orderDetails->orderStatus->name;
                $swDispatch = $orderDetails->dispatch->name;

                //Shipment Switch
                switch($swDispatch) {
                    case 'DHL':
                        $shipmentId = 1;
                    break;
                    default:
                        $shipmentId = 1;
                    break;
                }

                //Payment Switch
                switch($swPayment) {
                    case 'paypal':
                        $paymentId = 1;
                    break;
                    case 'prepayment':
                        $paymentId = 2;
                    break;
                    default:
                    break;
                }

                //Payment Status Switch
                switch($swPaymentStatus) {
                    case 'completely_paid':
                        $orderStatusId = 3;
                        Payment::updateOrCreate([
                            'fk_order_id' => $order->id,
                            'fk_config_payment_id' => $paymentId,
                        ], [
                            'payment_date' => date('Y-m-d'),
                            'payment_amount' => $orderDetails->invoiceAmount
                        ]);
                    break;
                    case 'open':
                        $orderStatusId = 2;
                    break;
                    default:
                    break;
                }

                //Order Status Switch
                switch($swOrderStatus) {
                    case 'cancelled_rejected':
                        $orderStatusId = 5;
                    break;
                    case 'open':
                    break;
                    default:
                        $orderStatusId = 10;
                    break;
                }

                //Set Foreign Ids in order
                $order->update([
                    'fk_config_payment_id' => $paymentId,
                    'fk_order_status_id' => $orderStatusId,
                    'fk_config_shipment_id' => $shipmentId,
                    'shipment_price' => $orderDetails->invoiceShipping * 100
                ]);

                $order->updateOrCreateAttribute($pf.'id', $swOrder->id);
                $order->updateOrCreateAttribute($pf.'orderTime', $swOrder->orderTime);
                $order->updateOrCreateAttribute($pf.'customerId', $orderDetails->customerId);
                $order->updateOrCreateAttribute($pf.'paymentId', $orderDetails->paymentId);
                $order->updateOrCreateAttribute($pf.'dispatchId', $orderDetails->dispatchId);
                $order->updateOrCreateAttribute($pf.'partnerId', $orderDetails->partnerId);
                $order->updateOrCreateAttribute($pf.'shopId', $orderDetails->shopId);
                $order->updateOrCreateAttribute($pf.'invoiceAmount', $orderDetails->invoiceAmount);
                $order->updateOrCreateAttribute($pf.'invoiceAmountNet', $orderDetails->invoiceAmountNet);
                $order->updateOrCreateAttribute($pf.'invoiceShipping', $orderDetails->invoiceShipping);
                $order->updateOrCreateAttribute($pf.'invoiceShippingNet', $orderDetails->invoiceShippingNet);
                $order->updateOrCreateAttribute($pf.'transactionId', $orderDetails->transactionId);
                $order->updateOrCreateAttribute($pf.'comment', $orderDetails->comment);
                $order->updateOrCreateAttribute($pf.'customerComment', $orderDetails->customerComment);
                $order->updateOrCreateAttribute($pf.'internalComment', $orderDetails->internalComment);
                $order->updateOrCreateAttribute($pf.'net', $orderDetails->net);
                $order->updateOrCreateAttribute($pf.'taxFree', $orderDetails->taxFree);
                $order->updateOrCreateAttribute($pf.'temporaryId', $orderDetails->temporaryId);
                $order->updateOrCreateAttribute($pf.'referer', $orderDetails->referer);
                $order->updateOrCreateAttribute($pf.'clearedDate', $orderDetails->clearedDate);
                $order->updateOrCreateAttribute($pf.'trackingCode', $orderDetails->trackingCode);
                $order->updateOrCreateAttribute($pf.'languageIso', $orderDetails->languageIso);
                $order->updateOrCreateAttribute($pf.'currency', $orderDetails->currency);
                $order->updateOrCreateAttribute($pf.'currencyFactor', $orderDetails->currencyFactor);
                $order->updateOrCreateAttribute($pf.'remoteAddress', $orderDetails->remoteAddress);
                $order->updateOrCreateAttribute($pf.'deviceType', $orderDetails->deviceType);
                $order->updateOrCreateAttribute($pf.'isProportionalCalculation', $orderDetails->isProportionalCalculation);
                if(is_array($orderDetails->details)) {
                    foreach($orderDetails->details as $position) {
                        $ean = $position->ean;
                        $variation = Article_Variation::where('vstcl_identifier', '=', 'vstcl-'.$ean)->first();
                        if(!$variation) {
                            continue;
                        }

                        $orderArticle = OrderArticle::updateOrCreate([
                            'fk_article_id' => $variation->article()->first()->id,
                            'fk_article_variation_id' => $variation->id,
                            'fk_order_id' => $order->id,
                        ],[
                            'fk_orderarticle_status_id' => (($position->shipped == 0) ? 1 : 2),
                            'quantity' => $position->quantity,
                            'tax' => $position->taxRate,
                            'price' => $position->price * 100
                        ]);
                    }
                }

                //Order address fields
                $adressFields = [
                    'vorname' => 'firstName',
                    'nachname' => 'lastName',
                    'gender' => 'salutation',
                    'street' => 'street',
                    'postcode' => 'zipCode',
                    'city' => 'city',
                    'region' => null,
                ];

                $addresstypes = [
                    'shippingaddress_' => $orderDetails->shipping,
                    'billingaddress_' => $orderDetails->billing
                ];
                $swCustomer = $orderDetails->customer;

                foreach($addresstypes as $adrpf => $swData) {
                    foreach($adressFields as $adrField => $adrVal) {
                        if(!$adrVal) {
                            $val = '';
                        }
                        else {
                            $val = $swData->{$adrVal};
                        }
                        Order_Attribute::updateOrCreate(
                            [
                                'fk_order_id' => $order->id,
                                'name' => $adrpf.$adrField
                            ],
                            [
                                'value' => $val
                            ]
                        );
                    }
                }
                if(is_object($swCustomer)) {
                    //Email
                    Order_Attribute::updateOrCreate(
                        [
                            'fk_order_id' => $order->id,
                            'name' => $adrpf.'email'
                        ],
                        [
                            'value' => $swCustomer->email
                        ]
                    );
                    //Tel
                    Order_Attribute::updateOrCreate(
                        [
                            'fk_order_id' => $order->id,
                            'name' => $adrpf.'tel'
                        ],
                        [
                            'value' => ''
                        ]
                    );
                }
            }
        }
    }

    //propertyGroups > Eigenschaften
    public function set_eigenschaften_sw_ids($Log=false)
    {   $SW_EigenschaftenSets = $this->get_eigenschaften();
        if($SW_EigenschaftenSets)
        {   foreach($SW_EigenschaftenSets as $EigenschaftenSet)
            {
                /*
                $VisticleCat = Category::whereNull('fk_wawi_id')->
                where('name','=',$EigenschaftenSet->name)->first();
                */


                /**
                 * Tanju Özsoy 16.03.2021 Anpassung wegen unterschiedlichen Kategoriebäumen
                 */
                $standard_provider=Category::getSystemStandardProvider()['provider'];
                $VisticleCat = Category::categoriesOfProvider($standard_provider)->whereNull('fk_wawi_id')->
                where('name','=',$EigenschaftenSet->name)->first();

                if($VisticleCat)
                {   // save Set ID
                    if($VisticleCat->sw_id!=$EigenschaftenSet->id){$VisticleCat->sw_id=$EigenschaftenSet->id;$VisticleCat->save();}
                    // Eigenschaften der Sets erforschen
                    if(isset($EigenschaftenSet->options))
                    {
                        $VisticleCatEigenschaftenIDs = $VisticleCat->eigenschaften_cat()->get()->pluck('fk_eigenschaft_id')->toArray();
                        $VisticleCatEigenschaften = Article_Eigenschaften::whereIn('id',$VisticleCatEigenschaftenIDs)->get();
                        if($VisticleCatEigenschaften && count($VisticleCatEigenschaften)>0 && is_object($VisticleCatEigenschaften))
                        {   foreach($VisticleCatEigenschaften as $VisticleCatEigenschaft)
                            {   foreach($EigenschaftenSet->options as $Option)
                                {   if($VisticleCatEigenschaft->name == $Option->name)
                                    {   // save Eigenschaft ID
                                        $update_saveItems = Article_Eigenschaften_Categories::where('fk_category_id','=',$VisticleCat->id)->where('fk_eigenschaft_id','=',$VisticleCatEigenschaft->id)->get();
                                        foreach($update_saveItems as $update_saveItem)
                                        {
                                            $update_saveItem->update(['sw_id' => $Option->id]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return true;
        }else{return false;}
    }

    public function get_eigenschaften($Log=false)
    {   $SW_EigenschaftenSets = [];
        if(!empty($this->shops))
        {   $resource = new SWpropertyGroups();$resource->buildGetAll();
            $res = $this->callAPI('GET','propertyGroups/', $resource->getBody());
            if($res && is_object($res['res'])) {
                if(isset($res['res']->data) && isset($res['res']->success)&& ($res['res']->success == 1))
                {   if($Log){echo "\n".print_r($res['res']->data,true);}
                    foreach($res['res']->data as $Index => $EigenschaftenSet)
                    {   $SW_EigenschaftenSets[] = $EigenschaftenSet;
                        if($Log){echo "\n".json_encode($EigenschaftenSet);}
                    }
                }else{return false;}
            }else{return false;}
        }else{return false;}
        return $SW_EigenschaftenSets;
    }
    public function update_eigenschaftSet(Category $category, $Log = false)
    {
        if(!empty($this->shops))
        {   $sw_id = $category->sw_id;
            if($sw_id == "" || !$sw_id)
            { $sw_id = $this->create_eigenschaftSet($category,$Log); return $sw_id; }
            else
            {
                $resource = new SWpropertyGroups();
                $resource->setRId($sw_id);


                $propertyGroups = [];
                $ThisFilterCategory = Category::where('sw_id','=',$sw_id)->first();
                if($ThisFilterCategory)
                {
                    // Filtergruppen Optionen für die Variation sammeln
                    $CatEigenschaften = Article_Eigenschaften_Categories::where('fk_category_id','=',$ThisFilterCategory->id)->get();
                    if($CatEigenschaften)
                    {
                        foreach($CatEigenschaften as $CatEigenschaft)
                        {   $Eigenschaft = Article_Eigenschaften::where('id','=',$CatEigenschaft->fk_eigenschaft_id)->first();
                            if($Eigenschaft)
                            {
                                if(!in_array($Eigenschaft->name,$propertyGroups)){$propertyGroups[]=$Eigenschaft->name;}
                            }
                        }
                    }
                }
                $resource->setData($propertyGroups);


                $resource->buildPost($category);
                $res = $this->callAPI('PUT','propertyGroups/'.$resource->getRId(), $resource->getBody());
                if($res && is_object($res['res'])) {
                    if(isset($res['res']->data) && isset($res['res']->success)&& ($res['res']->success == 1))
                    {   if($Log){echo "\n".$category->name." > SW_ID > ".$res['res']->data->id;}
                        return $res['res']->data->id;
                    }else{return false;}
                }else{return false;}
            }
        }else{return false;}
    }
    public function create_eigenschaftSet(Category $category, $Log = false)
    {
        if(!empty($this->shops))
        {
            // erst vorhandene Abrufen und Daten aufnehmen
            $SW_EigenschaftenSets = $this->get_eigenschaften();
            $exist = 0; $highestID = 0;
            if($SW_EigenschaftenSets)
            {
                foreach($SW_EigenschaftenSets as $SW_EigenschaftenSet)
                {   if($SW_EigenschaftenSet->id > $highestID){$highestID = $SW_EigenschaftenSet->id; }
                    if($SW_EigenschaftenSet->name == $category->name)
                    {   // check ob sw_id bereits vergeben ist an Kategorie mit gleichem Namen
                        $checkCat = Category::where('sw_id','=',$SW_EigenschaftenSet->id)->first();
                        if(!$checkCat){$exist = $SW_EigenschaftenSet->id;}
                    }
                }

                if($highestID > 0){$highestID=$highestID+1;}
                if($exist != 0){ // existiert bereits, ID setzen
                    $category->sw_id = $exist;
                    $category->save();
                    return $category->sw_id;
                }
            }
            if($exist == 0)
            {
                    $resource = new SWpropertyGroups();
                    $resource->setRId($highestID);
                    $resource->buildPost($category);
                    $res = $this->callAPI('POST','propertyGroups/'.$resource->getRId(), $resource->getBody());
                    if($res && is_object($res['res'])) {
                        if(isset($res['res']->data) && isset($res['res']->success)&& ($res['res']->success == 1))
                        {   if($Log){echo "\n".$category->name." > SW_ID > ".$res['res']->data->id;}
                            $category->sw_id = $res['res']->data->id;
                            $category->save();
                            return $category->sw_id;
                        }else{return false;}
                    }else{return false;}
            }else
            { Log::info("(SW - create_eigenschaft) Ein Fehler ist aufgetreten!"); return false; }
        }else{return false;}
    }
    public function delete_eigenschaftSet($sw_id= false, $Log = false)
    {   if($sw_id){
            $Cat = Category::where('sw_id','=',$sw_id)->first();
            if(!empty($this->shops))
            {   if($sw_id != "")
                {   $resource = new SWpropertyGroups();
                    $resource->setRId($sw_id);
                    $res = $this->callAPI('DELETE','propertyGroups/'.$resource->getRId(), []);
                    if($res && is_object($res['res']) && isset($res['res']->success)&& ($res['res']->success == 1))
                    {  if($Log){echo "\npropertyGroup [sw_ID:".$sw_id."] gelöscht";}
                        if($Cat){$Cat->sw_id = null; $Cat->save();}
                        return true;
                    }else{return false;}
                }else{return false;}
            }else{return false;}
        }
    }

    public function checkArticle($article = null)
    {
        if($article)
        {   // Bedingungen :: //Preis //Artikelname //Bild //Bestand
            $articleVariations = $article->variations()->get();
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
            ){ $this->deactive_article($article); return false; }
            return true;
        }
        return false;
    }

    private function callAPI($method, $path, $body = [], $shop = null, $article = null)
    {

        if($shop != null) {
            if($shop->type()->first()->provider_key != 'shopware') { return; }
            $shops = [$shop];
        }
        else { $shops = $this->shops; }

        foreach($shops as $shop) {
            if(!isset($this->shopConfigs[$shop->id])) {
                $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $shop->id]);
                $apiKey = $providerConfig->attributes()->where('name', '=', 'api_key')->first();
                $shopUrl = $providerConfig->attributes()->where('name', '=', 'shop_url')->first();
                $shopUser = $providerConfig->attributes()->where('name', '=', 'shop_user')->first();
                if(!$shopUrl || !$shopUser || !$apiKey) {
                    continue;
                }
                if($shopUrl->value == "") { continue; }
                if($article != null) {
                    if(!$shop->articles()->where('fk_article_id', $article->id)->exists()) { continue; }
                }
                /* Testumgebung
                $this->shopConfigs[$shop->id] = [];
                $this->shopConfigs[$shop->id]['shop_url'] = "https://www.stilfaktor.de/emcgn";
                $this->shopConfigs[$shop->id]['shop_user'] = "s.schulz";
                $this->shopConfigs[$shop->id]['api_key'] = "oMhowNqkxybV0ihXeTUrpyxMloWgSfU9ApirqNVT";
                */
                /* Live Server */
                $this->shopConfigs[$shop->id] = [];
                $this->shopConfigs[$shop->id]['shop_url'] = $shopUrl->value;
                $this->shopConfigs[$shop->id]['shop_user'] = $shopUser->value;
                $this->shopConfigs[$shop->id]['api_key'] = $apiKey->value;


                /* Testumgebung
                $this->shopConfigs[$shop->id]['standard_headers'] = [
                    'Authorization' => 'Basic '.base64_encode(utf8_encode('s.schulz:oMhowNqkxybV0ihXeTUrpyxMloWgSfU9ApirqNVT')),
                    'Accept' => 'application/json',
                ]; */

                /* Live Server */
                $this->shopConfigs[$shop->id]['standard_headers'] = [
                    'Authorization' => 'Basic '.base64_encode(utf8_encode($shopUser->value.':'.$apiKey->value)),
                    'Accept' => 'application/json',
                ];

            }

            $client = new Client();
            try
            {
                $res = $client->request($method, $this->shopConfigs[$shop->id]['shop_url'].'/api/'.$path, ['headers' => $this->shopConfigs[$shop->id]['standard_headers'],'body' => json_encode($body)]);
                $status = $res->getStatusCode();
                if($status != 200 && $status !=  201)
                { Log::info($this->shopConfigs[$shop->id]['shop_url'].'/api/'.$path.' - Typ: '.$method.' - Status: '.$status); }
            }
            catch(BadResponseException $e) {
                if ($e->hasResponse()) {
                    if(($e->getCode()==429)||($e->getCode()==500)||($e->getCode()==404)||($e->getCode()==400))
                    {
                        $thisErrorResponse = json_decode($e->getResponse()->getBody());
                        if(isset($thisErrorResponse->message))
                        {
                            if(strpos($thisErrorResponse->message,'Product by id') !== false
                            && strpos($thisErrorResponse->message,'not found') !== false)
                            {
                                Log::info("rufe SW_ID ab...");
                                $this->get_article_swID_byNumber($article);
                            } else if(strpos($thisErrorResponse->message,'Order by id') !== false
                            && strpos($thisErrorResponse->message,'not found') !== false)
                            {
                                Log::info("Order by id nicht gefunden...");
                            }else{ Log::info('Typ: '.$method.' - Status: '.$e->getCode()." Message: ".$e->getResponse()->getBody()." - ".$this->shopConfigs[$shop->id]['shop_url'].'/api/'.$path );
                                Log::info($thisErrorResponse->message);
                            }

                        }else{
                            Log::info($this->shopConfigs[$shop->id]['shop_url'].'/api/'.$path.' - Typ: '.$method.' - Status: '.$e->getCode()." Message: ".$e->getResponse()->getBody() );
                        }

                    }else{Log::error($e->getMessage()); }

                }
                return false;

            }

            return [
                'shop' => $shop,
                'res' => json_decode($res->getBody())
            ];
        }

    }

    public function getShops() { return $this->shops; }

}
