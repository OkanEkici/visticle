<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWResource;
use App\Tenant\Article;
use App\Tenant\Article_Eigenschaften;
use App\Tenant\Article_Eigenschaften_Categories;
use App\Tenant\Category; use Log;
use App\Tenant\Article_Marketing;

use Illuminate\Database\Eloquent\Model;

class SWArticleVariation extends SWResource {
    public function buildGet($model = null) {

    }
    public function buildGetAll() {

    }
    public function buildPost(Model $model = null) 
    {   
        $article = $model->article()->first();
        $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
        if(!$sw_id) {
            return;
        }
        $varCount = $article->variations()->count();
        $mainNumber = $this->replaceUmlauts(str_replace(' ','-',$article->number));
        $number = $mainNumber.'.'.$varCount;
        $isUsed = true;
        $checkCount = $varCount;
        while($isUsed) {
            $isUsed = $this->checkVariationNumber($article, $number);
            if($isUsed) {
                $checkCount++;
                $number = $mainNumber.'.'.$checkCount;
            }
        }
        // Preise ermitteln
        $thisWebStandardPrice = (float)str_replace (",",".",$model->getWebStandardPrice());
        $thisWebDiscountPrice = (float)str_replace (",",".",$model->getWebDiscountPrice());

        $thisStandardPrice = (float)str_replace (",",".",$model->getStandardPrice());
        $thisDiscountPrice = $thisStandardPrice;

        if($thisWebStandardPrice != false && $thisWebStandardPrice != ""){$thisStandardPrice = $thisWebStandardPrice;}
        if($thisWebDiscountPrice != false && $thisWebDiscountPrice != "")
        {   // Marketing Prüfung
            $ActiveDiscountRange=Article_Marketing::where('fk_article_id' ,'=', $article->id)
            ->where('name' ,'=', 'activate_discount')->where('active' ,'=', '1')
            ->where('from' ,'!=', '')->where('until' ,'!=', '')->first();
            if($ActiveDiscountRange)
            {   $fromStamp = strtotime($ActiveDiscountRange->from);
                $untilStamp = strtotime($ActiveDiscountRange->until);
                $NowStamp = strtotime(date("d.m.Y"));
                if($fromStamp<=$NowStamp && $NowStamp <= $untilStamp)
                {
                    $thisDiscountPrice = $thisWebDiscountPrice;
                }
            }else
            { // normale Live red. Preise übergeben wenn kein Discount aktiv und Zeitraum gesetzt
                $thisDiscountPrice = $thisWebDiscountPrice;
            }  
        }

        $this_pseudoPrice = false; $this_basePrice = false; $this_price = false;
        $priceArr = [];
        if($thisDiscountPrice < $thisStandardPrice)
        {   
            $this_pseudoPrice = $thisStandardPrice;
            $this_basePrice = $thisDiscountPrice;
            $priceArr = [
                    'customerGroupKey' => 'EK',
                    'basePrice' => $this_basePrice,
                    'pseudoPrice' => $this_pseudoPrice,
                    'price' => $this_basePrice
            ];

        }else{
            $this_price = $thisStandardPrice;
            $priceArr = [ 'customerGroupKey' => 'EK', 'price' => $this_price ];
        }
        // ENDE Preise ermitteln

        $ThisSize = $model->getAttributeValueByKey('fee-size');
        $configuratorOptions = [];
        $configuratorOptions =[  [ 'group' => 'Größe', 'option' => $ThisSize ] ];

        // MainVariante ermitteln
        $variations = $article->variations()->get();
        //PreCheck isMain get first MainAble
        $firstMainAble = false; $highestStock = 0;
        foreach($variations as $variation) 
        {
            if($variation->getStock()>0 && $variation->getStock()>$highestStock){$highestStock=$variation->getStock();$firstMainAble = $variation->id;}
        }

    
        $body = [
            'articleId' => $sw_id->value,
            'id' => $number,
            'number' => $number,
            'active' => (($model->getStock()>0)? $model->active : 0 ),//(($model->getStock() == 0) ? 0 : 1),
            'inStock' => $model->getStock(),
            'lastStock' => ((isset($model->min_stock) && $model->min_stock>0)? 1 : 0),
            'stockMin' => ((isset($model->min_stock) && $model->min_stock>0)? $model->min_stock : 0),
            'ean' => $model->getEan(),
            'isMain' => (($firstMainAble && $firstMainAble == $model->id)?1:0),
            'isMainVariant' => (($firstMainAble && $firstMainAble == $model->id)?1:0),
            'kind' => (($firstMainAble && $firstMainAble == $model->id)?1:2),
            'configuratorOptions' => $configuratorOptions,
            'mainDetail' => [
                'attribute' => [
                    'emcgn_highlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ,'emcgnHighlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ,'fields' =>
                    [
                        'emcgn_highlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                        ,'emcgnHighlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ]
                 ],
            ],
            'prices' => [$priceArr]
         
        ];

        $model->updateOrCreateAttribute('sw_variantid', $number);

        $swFilterGroupId = false;
        if($article->has('categories')) {
            $categories = $article->categories()->whereNull('fk_wawi_id')->get();
            foreach($categories as $category) { if($category->sw_id && $category->sw_id != null)
            {$swFilterGroupId = $category->sw_id;} }
        }
        if($swFilterGroupId)
        {   $body['filterGroupId'] = $swFilterGroupId;}

        $this->setBody($body);
    }
    public function buildPut(Model $model = null) 
    {
        
        $var_id = $model->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
        if(!$var_id) {
            return;
        }
        $article = $model->article()->first();
        $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
        // Preise ermitteln
        $thisWebStandardPrice = (float)str_replace (",",".",$model->getWebStandardPrice());
        $thisWebDiscountPrice = (float)str_replace (",",".",$model->getWebDiscountPrice());

        $thisStandardPrice = (float)str_replace (",",".",$model->getStandardPrice());
        $thisDiscountPrice = $thisStandardPrice;

        if($thisWebStandardPrice != false && $thisWebStandardPrice != ""){$thisStandardPrice = $thisWebStandardPrice;}
        if($thisWebDiscountPrice != false && $thisWebDiscountPrice != "")
        {
            // Marketing Prüfung
            $ActiveDiscountRange=Article_Marketing::where('fk_article_id' ,'=', $article->id)
            ->where('name' ,'=', 'activate_discount')->where('active' ,'=', '1')
            ->where('from' ,'!=', '')->where('until' ,'!=', '')->first();
            if($ActiveDiscountRange)
            {   $fromStamp = strtotime($ActiveDiscountRange->from);
                $untilStamp = strtotime($ActiveDiscountRange->until);
                $NowStamp = strtotime(date("d.m.Y"));
                if($fromStamp<=$NowStamp && $NowStamp <= $untilStamp)
                {
                    $thisDiscountPrice = $thisWebDiscountPrice;
                }
            }else
            { // normale Live red. Preise übergeben wenn kein Discount aktiv und Zeitraum gesetzt
                $thisDiscountPrice = $thisWebDiscountPrice;
            } 
        }

        $this_pseudoPrice = false; $this_basePrice = false; $this_price = false;
        $priceArr = [];
        if($thisDiscountPrice < $thisStandardPrice)
        {   
            $this_pseudoPrice = $thisStandardPrice;
            $this_basePrice = $thisDiscountPrice;
            $priceArr = [
                    'customerGroupKey' => 'EK',
                    'basePrice' => $this_basePrice,
                    'pseudoPrice' => $this_pseudoPrice,
                    'price' => $this_basePrice
            ];

        }else{
            $this_price = $thisStandardPrice;
            $priceArr = [ 'customerGroupKey' => 'EK', 'price' => $this_price ];
        }
        // ENDE Preise ermitteln

        $ThisSize = $model->getAttributeValueByKey('fee-size');
        $configuratorOptions = [];
        $configuratorOptions =[  [ 'group' => 'Größe', 'option' => $ThisSize ] ]; 
        
        // MainVariante ermitteln
        $variations = $article->variations()->get();
        //PreCheck isMain get first MainAble
        $firstMainAble = false; $highestStock = 0;
        foreach($variations as $variation) 
        {
            if($variation->getStock()>0 && $variation->getStock()>$highestStock){$highestStock=$variation->getStock();$firstMainAble = $variation->id;}
        }

        $body = [
            'articleId' => $sw_id->value,
            'number' => $var_id->value,
            'active' => (($model->getStock()>0)? $model->active : 0 ), //(($model->getStock() == 0) ? 0 : 1),
            'inStock' => $model->getStock(),
            'lastStock' => ((isset($model->min_stock) && $model->min_stock>0)? 1 : 0),
            'stockMin' => ((isset($model->min_stock) && $model->min_stock>0)? $model->min_stock : 0),
            'ean' => $model->getEan(),
            'isMain' => (($firstMainAble && $firstMainAble == $model->id)?1:0),
            'isMainVariant' => (($firstMainAble && $firstMainAble == $model->id)?1:0),
            'kind' => (($firstMainAble && $firstMainAble == $model->id)?1:2),
            'mainDetail' => [                
                'attribute' => [
                    'emcgn_highlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ,'emcgnHighlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ,'fields' =>
                    [
                        'emcgn_highlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                        ,'emcgnHighlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ]
                 ],
            ],
            'configuratorOptions' => $configuratorOptions,
            'prices' => [$priceArr]
        ];

        $swFilterGroupId = false;
        if($article->has('categories')) {
            $categories = $article->categories()->whereNull('fk_wawi_id')->get();
            foreach($categories as $category) { if($category->sw_id && $category->sw_id != null){$swFilterGroupId = $category->sw_id;} }
        }
        if($swFilterGroupId)
        {   $body['filterGroupId'] = $swFilterGroupId;}

        
        $this->setBody($body);
    }
    public function buildDelete(Model $model = null) {

    }

    public function buildStock(Model $model = null) {
        $var_id = $model->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
        if(!$var_id) { return; }
        
        $article = $model->article()->first();
        $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();

        $body = [
            'articleId' => $sw_id->value,
            'inStock' => $model->getStock(),
            'active' => (($model->getStock()>0)? $model->active : 0 ) //(($model->isActiveForShop()) ? 1 : 0)
        ];

        $this->setBody($body);
    }

    private function checkVariationNumber(Article $article, $number) {
        $isUsed = $article->variations()->whereHas('attributes', function($query) use($number) {
            $query->where('name','=','sw_variantid')->where('value','=', $number);
        })->first();

        if(!$isUsed) {
            return false;
        }
        else {
            return true;
        }
    }
}