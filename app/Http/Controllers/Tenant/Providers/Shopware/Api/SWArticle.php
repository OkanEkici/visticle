<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWResource;
use Illuminate\Database\Eloquent\Model;
use App\Tenant\ArticleProvider;
use App\Tenant\Category;
use App\Tenant\Provider;
use App\Tenant\Article_Eigenschaften;
use App\Tenant\Article_Eigenschaften_Categories;
use App\Tenant\Article_Marketing;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Tenant\Article_Image;
use Storage, Config; use Log;

class SWArticle extends SWResource {
    public function buildGet($model = null) {}
    public function buildGetAll() {}
    public function buildPost(Model $model = null)
    {

        $TaxIDs = [ '19%' => 1 ];
        $mainNumber = $this->replaceUmlauts(str_replace(' ','-',$model->number));
        $mainNumber = str_replace('/','-',$mainNumber);

        $thisActiveP = $model->provider()->where('active','=',1)->first();

        $contentBody = [
            'kind' => 0,
            'name' => $model->filledName(),
            //'description' => $model->short_description,
            'description' => $model->short_description,
            'descriptionLong' => $model->description,
            'keywords' => $model->keywords,
            'metaTitle' => $model->metatitle,
            'active' => (($model->isActiveForShops())? (($thisActiveP)? $model->active:0) : 0 ),
            'lastStock' => ((isset($model->min_stock) && $model->min_stock>0)? 1 : 0),
            'stockMin' => ((isset($model->min_stock) && $model->min_stock>0)? $model->min_stock : 0),
            'tax' => [
                //'id' => $TaxIDs["".$model->tax."%"],
                'id' => $TaxIDs["19%"],
                'name' => ($model->tax!="")?$model->tax:"19",
                'tax' => ($model->tax!="")?$model->tax:"19"
            ],

            'mainDetail' => [

                'number' => $mainNumber,
                'attribute' => [
                    'emcgn_highlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ,'emcgnHighlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ,'fields' =>
                    [
                        'emcgn_highlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                        ,'emcgnHighlights' => $model->getAttributeValueByKey('sw_emcgn_highlights')
                    ]

                 ],
                'active' => (($model->isActiveForShops())? (($thisActiveP)? $model->active:0) : 0 )
            ]

        ];
        /*
        $contentBody["mainDetail"] = new \StdClass();
        $contentBody["mainDetail"]->number = $mainNumber;
        $contentBody["mainDetail"]->attribute = new \StdClass();
        //$contentBody["mainDetail"]->attribute->emcgn_highlights = $model->getAttributeValueByKey('sw_emcgn_highlights');
        $contentBody["mainDetail"]->attribute->attr2 = $model->getAttributeValueByKey('sw_emcgn_highlights');
        $contentBody["mainDetail"]->active = (($model->isActiveForShops())? (($thisActiveP)? $model->active:0) : 0 );
*/




        $swFilterGroupActive = false;
        if($model->has('categories'))
        {
            $ShopController = new ShopwareAPIController();
            $eigenschaften_sw_ids_gesetzt = $ShopController->set_eigenschaften_sw_ids();

            $categories = $model->categories()->whereNull('fk_wawi_id')->get();
            $contentBody['categories'] = [];
            foreach($categories as $category)
            {   $contentBody['categories'][] = [ 'id' => $category->wawi_number ];

                if($category->sw_id && $category->sw_id != null){$swFilterGroupActive = $category->sw_id;}
                if($swFilterGroupActive)
                {   //
                    $contentBody['filterGroupId'] = $swFilterGroupActive;
                    // Filtergruppen Optionen für die Variation sammeln
                    $EigenschaftenIDs = Article_Eigenschaften_Categories::where('fk_category_id','=',$category->id)->get()->pluck('id','fk_eigenschaft_id')->toArray();
                    $CatEigenschaften = Article_Eigenschaften::whereIn('id',$EigenschaftenIDs)->get();
                    if($CatEigenschaften && count($CatEigenschaften)>0)
                    {   foreach($CatEigenschaften as $CatEigenschaft)
                        {   if(!in_array('propertyValues',$contentBody)){$contentBody['propertyValues'] = [];}
                            if(!in_array('propertyGroup',$contentBody)){$contentBody['propertyGroup'] = [];}
                            $getSwID = Article_Eigenschaften_Categories::where('fk_category_id','=',$category->id)->where('fk_eigenschaft_id','=',$CatEigenschaft->id)->first();
                            $swID = ($getSwID)? $getSwID->sw_id : null;

                            if($swID && $swID != null)
                            {$contentBody['propertyGroup'][] = ['id' => $swID,'name' => $CatEigenschaft->name];}
                            else{$contentBody['propertyGroup'][] = ['name' => $CatEigenschaft->name];}

                            $CatEigenschaftDatas = $CatEigenschaft->eigenschaften()->get();
                            if($CatEigenschaftDatas)
                            {   foreach($CatEigenschaftDatas as $CatEigenschaftData)
                                {   $checkVarEigenschaft = $model->eigenschaften()->where('fk_article_id','=',$model->id)->where('fk_eigenschaft_data_id','=',$CatEigenschaftData->id)->first();
                                    if($checkVarEigenschaft)
                                    {
                                        //$contentBody['propertyValues'][] =
                                        //[ "option" => [ "name" => $Eigenschaft->name ], "value" => $CatEigenschaftData->value ];
                                        //
                                        $contentBody['propertyValues'][] =
                                        [   'groupId' => $category->sw_id,
                                            'option'  => array( 'name' => $CatEigenschaft->name, 'filterable' => $CatEigenschaft->is_filterable ),
                                            'position' => $CatEigenschaftData->id,'value' => $CatEigenschaftData->value
                                        ];
                                       /* if($swID != "" && $swID != null ){
                                            $contentBody['propertyValues'][] =
                                            [   'groupId' => $category->sw_id,
                                                'option'  => array('optionId' => $swID,'name' => $CatEigenschaft->name, 'filterable' => $CatEigenschaft->is_filterable ),
                                                'position' => $CatEigenschaftData->id,'value' => $CatEigenschaftData->value
                                            ];
                                        }
                                        else{
                                            $contentBody['propertyValues'][] =
                                            [   'groupId' => $category->sw_id,
                                                'option'  => array( 'name' => $CatEigenschaft->name, 'filterable' => $CatEigenschaft->is_filterable ),
                                                'position' => $CatEigenschaftData->id,'value' => $CatEigenschaftData->value
                                            ];
                                        }   //*/
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }


        if($model->has('variations'))
        {
            $variations = $model->variations()->get();

            //PreCheck isMain get first MainAble
            $firstMainAble = false; $highestStock = 0;
            foreach($variations as $variation)
            { if($variation->getStock()>0 && $variation->getStock()>$highestStock){$highestStock=$variation->getStock();$firstMainAble = $variation->id;} }


            $contentBody['variants'] = [];
            $varCount = 0; $swSupplier = '';
            foreach($variations as $variation)
            {
                $isMain = false;
                if($firstMainAble){if($firstMainAble == $variation->id){$isMain = 1;}}
                else{ if($varCount == 0) { $isMain = 1; } }

                $swSupplier = $variation->getAttributeValueByKey('sw_supplier');

                // Preise ermitteln
                $thisWebStandardPrice = (float)str_replace (",",".",$variation->getWebStandardPrice());
                $thisWebDiscountPrice = (float)str_replace (",",".",$variation->getWebDiscountPrice());

                $thisStandardPrice = (float)str_replace (",",".",$variation->getStandardPrice());
                $thisDiscountPrice = $thisStandardPrice;

                if($thisWebStandardPrice != false && $thisWebStandardPrice != ""){$thisStandardPrice = $thisWebStandardPrice;}
                if($thisWebDiscountPrice != false && $thisWebDiscountPrice != "")
                {   // Marketing Prüfung
                    $ActiveDiscountRange=Article_Marketing::where('fk_article_id' ,'=', $model->id)
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

                $configuratorOptions = [];
                $ThisSize = $variation->getAttributeValueByKey('fee-size');
                $configuratorSizeOptions[] = $ThisSize;
                $configuratorOptions =[  [ 'group' => 'Größe', 'option' => $ThisSize ] ];

                $contentBody['variants'][] = [
                    'isMain' => $isMain,
                    'isMainVariant' => $isMain,
                    'kind' => (($isMain)? 1 : 2),
                    'number' => $mainNumber.'.'.($varCount + 1),
                    'inStock' => $variation->getStock(),
                    'lastStock' => ((isset($variation->min_stock) && $variation->min_stock>0)? 1 : 0),
                    'stockMin' => ((isset($variation->min_stock) && $variation->min_stock>0)? $variation->min_stock : 0),
                    'additionaltext' => $variation->getAttributeValueByKey('sw_additionalText'),
                    'ean' => $variation->getEan(),
                    'active' => (($model->isActiveForShops())? (($variation->getStock()>0&&$thisActiveP)?$variation->active:0) : 0 ),
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
                    'prices' => [ $priceArr ]

                ];
                /*
                $contentBody["mainDetail"] = new \StdClass();
                $contentBody["mainDetail"]->number = $mainNumber;
                $contentBody["mainDetail"]->attribute = new \StdClass();
        //$contentBody["mainDetail"]->attribute->emcgn_highlights = $model->getAttributeValueByKey('sw_emcgn_highlights');
        $contentBody["mainDetail"]->attribute->attr2 = $model->getAttributeValueByKey('sw_emcgn_highlights');
                $contentBody["mainDetail"]->prices = [ $priceArr ];
*/

                $variation->updateOrCreateAttribute('sw_variantid', $mainNumber.'.'.($varCount + 1));
                $varCount++;
            }
            $supplier = $model->brandNameToShow();
            if($supplier != '')
            { $contentBody['supplier'] = $supplier; }
            else
            { $contentBody['supplier'] = $swSupplier; }
        }





        if($model->has('images'))
        {   //$images = $model->images()->get();


            $images = $model->images()
            ->whereHas('attributes', function($q)
            { $q->where('name', '=', 'sw_uploaded')->where('value', '=', '0');})
            ->orWhereDoesntHave('attributes', function($q)
            { $q->where('name', '=', 'sw_uploaded');})
            ->get();

            if(sizeof($images) == 0) {
                $images = Article_Image::query()->where('fk_article_id','=',$model->id)->get();
            }

            $contentBody['images'] = [];
            $contentBody['__options_images'] = [ 'replace' => 0 ];

            foreach ($images as $image)
            {
               if(Storage::disk('public')->exists($image->location))
                {   $isMainImg = $image->attributes()->where('name', '=', 'sw_preview')->where('value','=', 'on')->first();
                    if($isMainImg) { $isMainImg = 1; }
                    else { $isMainImg = 2; }
                    $contentBody['images'][] = [
                        'mediaId' => $image->id,
                        'link' => 'https://visticle.online/storage'.$image->location,
                        'main' => $isMainImg,
                        'options' => [ 'replace' => 0 ]
                    ];
                    $image->updateOrCreateAttribute("sw_uploaded", "1");
                }

            }

        }


        /*
        // Alte Beginn Konfiguratorset

        // Sortierung der Größen nach Vorgabe in Eigenschaften wenn gegeben
        $alleEigenschaften=[]; $alleEigenschaften_visc = Article_Eigenschaften::get();
        if($alleEigenschaften)
        {   foreach($alleEigenschaften_visc as $Eigenschaft)
            {   if(!in_array($Eigenschaft->name,$alleEigenschaften)){$alleEigenschaften[$Eigenschaft->name] = [];}
                $EigenschaftDatas = $Eigenschaft->eigenschaften()->get();
                foreach($EigenschaftDatas as $EigenschaftData)
                {   if(!in_array($EigenschaftData->value,$alleEigenschaften[$Eigenschaft->name])) { $alleEigenschaften[$Eigenschaft->name][]=$EigenschaftData->value; }  }
            }
        }
        $sortConfiguratorGroessen=[]; $currentIndex=0;
        foreach($alleEigenschaften as $thisEigenschaftName -> $thisEigenschaftOptionen)
        {   foreach($configuratorSizeOptions as $configuratorSizeOption)
            {   if(in_array($configuratorSizeOption,$thisEigenschaftOptionen))
                {   if(!in_array($configuratorSizeOption,$sortConfiguratorGroessen))
                    {$sortConfiguratorGroessen[]=$configuratorSizeOption;}
                }
            }
        }
        foreach($configuratorSizeOptions as $configuratorSizeOption)
        {   if(!in_array($configuratorSizeOption,$sortConfiguratorGroessen))
            {$sortConfiguratorGroessen[]=$configuratorSizeOption;}
        }
        $contentBody['configuratorSet'] = [ 'groups' => [] ];
        $sizeConfigSet = [ 'name' => 'Größe', 'options' => [] ];
        foreach($sortConfiguratorGroessen as $configuratorSizeOption)
        { $sizeConfigSet['options'][] = [ 'name' => $configuratorSizeOption ]; }
        $contentBody['configuratorSet']['groups'][] = $sizeConfigSet;
        // Alte Ende Konfiguratorset
        */

        // Beginn Konfiguratorset
        $configuratorSizeOptions = [];
        if($model->has('variations'))
        {   $variations = $model->variations()->get();
            foreach($variations as $variation)
            {   $ThisSize = $variation->getAttributeValueByKey('fee-size');
                $configuratorSizeOptions[] = $ThisSize;
            }
        }
        // Sortierung der Größen nach Vorgabe in Eigenschaften wenn gegeben
        $alleEigenschaften=[]; $alleEigenschaften_visc = Article_Eigenschaften::get();
        if($alleEigenschaften)
        {   foreach($alleEigenschaften_visc as $Eigenschaft)
            {   if(!in_array($Eigenschaft->name,$alleEigenschaften)){$alleEigenschaften[$Eigenschaft->name] = [];}
                $EigenschaftDatas = $Eigenschaft->eigenschaften()->get();
                foreach($EigenschaftDatas as $EigenschaftData)
                {   if(!in_array($EigenschaftData->value,$alleEigenschaften[$Eigenschaft->name])) { $alleEigenschaften[$Eigenschaft->name][]=$EigenschaftData->value; }  }
            }
        }
        $sortConfiguratorGroessen=[]; $currentIndex=0;
        foreach($alleEigenschaften as $thisEigenschaftName -> $thisEigenschaftOptionen)
        {   foreach($configuratorSizeOptions as $configuratorSizeOption)
            {   if(in_array($configuratorSizeOption,$thisEigenschaftOptionen))
                {   if(!in_array($configuratorSizeOption,$sortConfiguratorGroessen))
                    {$sortConfiguratorGroessen[]=$configuratorSizeOption;}
                }
            }
        }
        foreach($configuratorSizeOptions as $configuratorSizeOption)
        {   if(!in_array($configuratorSizeOption,$sortConfiguratorGroessen))
            {$sortConfiguratorGroessen[]=$configuratorSizeOption;}
        }
        $contentBody['configuratorSet'] = [ 'groups' => [] ];
        $sizeConfigSet = [ 'name' => 'Größe', 'options' => [] ];
        foreach($sortConfiguratorGroessen as $configuratorSizeOption)
		{ $sizeConfigSet['options'][] = [ 'name' => $configuratorSizeOption ]; }
        $contentBody['configuratorSet']['groups'][] = $sizeConfigSet;

        $this->setBody($contentBody);
        return $contentBody;
    }

    public function buildPut(Model $model = null)
    {
        ///$TaxIDs = [ '19%' => 1 ,'7%' => 2 ,'0%' => 3 ,'16%' => 4,'%' => 4 ];
        $TaxIDs = [ '19%' => 1 ];
        $mainNumber = $this->replaceUmlauts(str_replace(' ','-',$model->number));

        $thisActiveP = $model->provider()->where('active','=',1)->first();

        $contentBody = [
            'name' => $model->filledName(),
            'description' => $model->short_description,
            'descriptionLong' => $model->description,
            'keywords' => $model->keywords,
            'metaTitle' => $model->metatitle,
            'active' => (($model->isActiveForShops())? (($thisActiveP)?$model->active:0) : 0 ),
            'lastStock' => ((isset($model->min_stock) && $model->min_stock>0)? 1 : 0),
            'stockMin' => ((isset($model->min_stock) && $model->min_stock>0)? $model->min_stock : 0),
            'tax' => [
                //'id' => $TaxIDs[$model->tax."%"],
                'id' => $TaxIDs["19%"],
                'name' => ($model->tax!="")?$model->tax:"19",
                'tax' => ($model->tax!="")?$model->tax:"19"
            ],

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
                'active' => (($model->isActiveForShops())? (($thisActiveP)?$model->active:0) : 0 )
            ]

        ];
        /*
        $contentBody["mainDetail"] = new \StdClass();
        $contentBody["mainDetail"]->number = $mainNumber;
        $contentBody["mainDetail"]->attribute = new \StdClass();
        //$contentBody["mainDetail"]->attribute->emcgn_highlights = $model->getAttributeValueByKey('sw_emcgn_highlights');
        $contentBody["mainDetail"]->attribute->attr2 = $model->getAttributeValueByKey('sw_emcgn_highlights');
        $contentBody["mainDetail"]->active = (($model->isActiveForShops())? (($thisActiveP)? $model->active:0) : 0 );
*/


        $contentBody['supplier'] = $model->brandNameToShow();

        $swFilterGroupActive = false;
        if($model->has('categories'))
        {
            $ShopController = new ShopwareAPIController();
            $eigenschaften_sw_ids_gesetzt = $ShopController->set_eigenschaften_sw_ids();

            $categories = $model->categories()->whereNull('fk_wawi_id')->get();
            $contentBody['categories'] = [];
            foreach($categories as $category)
            {   $contentBody['categories'][] = [ 'id' => $category->wawi_number ];

                if($category->sw_id && $category->sw_id != null){$swFilterGroupActive = $category->sw_id;}
                if($swFilterGroupActive)
                {   //
					$contentBody['filterGroupId'] = $swFilterGroupActive;
                    // Filtergruppen Optionen für die Variation sammeln
                    $EigenschaftenIDs = Article_Eigenschaften_Categories::where('fk_category_id','=',$category->id)->get()->pluck('fk_eigenschaft_id')->toArray();
                    $CatEigenschaften = Article_Eigenschaften::whereIn('id',$EigenschaftenIDs)->get();

                    if($CatEigenschaften && count($CatEigenschaften)>0)
                    {   foreach($CatEigenschaften as $CatEigenschaft)
                        {   if(!in_array('propertyValues',$contentBody)){$contentBody['propertyValues'] = [];}
                            if(!in_array('propertyGroup',$contentBody)){$contentBody['propertyGroup'] = [];}
                            $getSwID = Article_Eigenschaften_Categories::where('fk_category_id','=',$category->id)->where('fk_eigenschaft_id','=',$CatEigenschaft->id)->first();
                            $swID = ($getSwID)? $getSwID->sw_id : null;

							//$contentBody['propertyGroup'][] = ['name' => $CatEigenschaft->name];
                            //if($swID && $swID != null){$contentBody['propertyGroup'][] = ['groupId' => $category->sw_id."-".$CatEigenschaft->id,'id' => $swID,'name' => $CatEigenschaft->name];}
                            //else{$contentBody['propertyGroup'][] = ['groupId' => $category->sw_id."-".$CatEigenschaft->id,'optionId' => $CatEigenschaft->id, 'name' => $CatEigenschaft->name];}

                            $CatEigenschaftDatas = $CatEigenschaft->eigenschaften()->get();
                            if($CatEigenschaftDatas)
                            {   foreach($CatEigenschaftDatas as $CatEigenschaftData)
                                {   $checkVarEigenschaft = $model->eigenschaften()->where('fk_article_id','=',$model->id)->where('fk_eigenschaft_data_id','=',$CatEigenschaftData->id)->first();
                                    if($checkVarEigenschaft)
                                    {
                                        //$contentBody['propertyValues'][] = [ "option" => [ "name" => $CatEigenschaftData->name, 'filterable' => $CatEigenschaft->is_filterable ],'position' => $CatEigenschaftData->id, "value" => $CatEigenschaftData->value ];

                                         $contentBody['propertyValues'][] =
                                         [   'groupId' => $category->sw_id,
                                             'option'  => array('name' => $CatEigenschaft->name, 'filterable' => $CatEigenschaft->is_filterable ),
                                             'position' => $CatEigenschaftData->id,'value' => $CatEigenschaftData->value
                                         ];
                                         /*
                                        if($swID != "" && $swID != null ){
                                            $contentBody['propertyValues'][] =
                                            [   'groupId' => $category->sw_id,
												'optionId' => $swID,
												'option'  => array('name' => $CatEigenschaft->name, 'filterable' => $CatEigenschaft->is_filterable ),
                                                'position' => $CatEigenschaftData->id,'value' => $CatEigenschaftData->value
                                            ];
                                        }
                                        else{
                                            $contentBody['propertyValues'][] =
                                            [   'groupId' => $category->sw_id,
												'option'  => array('name' => $CatEigenschaft->name, 'filterable' => $CatEigenschaft->is_filterable ),
                                                'position' => $CatEigenschaftData->id,'value' => $CatEigenschaftData->value
                                            ];
                                        }         //*/
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }


        // Beginn Konfiguratorset
        $configuratorSizeOptions = [];
        if($model->has('variations'))
        {   $variations = $model->variations()->get();
            foreach($variations as $variation)
            {   $ThisSize = $variation->getAttributeValueByKey('fee-size');
                $configuratorSizeOptions[] = $ThisSize;
            }
        }
        // Sortierung der Größen nach Vorgabe in Eigenschaften wenn gegeben
        $alleEigenschaften=[]; $alleEigenschaften_visc = Article_Eigenschaften::get();
        if($alleEigenschaften)
        {   foreach($alleEigenschaften_visc as $Eigenschaft)
            {   if(!in_array($Eigenschaft->name,$alleEigenschaften)){$alleEigenschaften[$Eigenschaft->name] = [];}
                $EigenschaftDatas = $Eigenschaft->eigenschaften()->get();
                foreach($EigenschaftDatas as $EigenschaftData)
                {   if(!in_array($EigenschaftData->value,$alleEigenschaften[$Eigenschaft->name])) { $alleEigenschaften[$Eigenschaft->name][]=$EigenschaftData->value; }  }
            }
        }
        $sortConfiguratorGroessen=[]; $currentIndex=0;
        foreach($alleEigenschaften as $thisEigenschaftName -> $thisEigenschaftOptionen)
        {   foreach($configuratorSizeOptions as $configuratorSizeOption)
            {   if(in_array($configuratorSizeOption,$thisEigenschaftOptionen))
                {   if(!in_array($configuratorSizeOption,$sortConfiguratorGroessen))
                    {$sortConfiguratorGroessen[]=$configuratorSizeOption;}
                }
            }
        }
        foreach($configuratorSizeOptions as $configuratorSizeOption)
        {   if(!in_array($configuratorSizeOption,$sortConfiguratorGroessen))
            {$sortConfiguratorGroessen[]=$configuratorSizeOption;}
        }
        $contentBody['configuratorSet'] = [ 'groups' => [] ];
        $sizeConfigSet = [ 'name' => 'Größe', 'options' => [] ];
        foreach($sortConfiguratorGroessen as $configuratorSizeOption)
		{ $sizeConfigSet['options'][] = [ 'name' => $configuratorSizeOption ]; }
        $contentBody['configuratorSet']['groups'][] = $sizeConfigSet;
        // Ende Konfiguratorset

        $this->setBody($contentBody);
    }

    // CREATE Shopware Image
    public function buildImage(Model $model = null) {
        $contentBody = [];
        $mainNumber = $this->replaceUmlauts(str_replace(' ','-',$model->number));
        $images = $model->images()->get();

        $thisActiveP = $model->provider()->where('active','=',1)->first();

        $contentBody['active'] = (($model->isActiveForShops())? (($thisActiveP)?$model->active:0) : 0 );
        $contentBody['mainDetail'] = [ 'active' => (($model->isActiveForShops())? (($thisActiveP)?$model->active:0) : 0 ) ];
        $contentBody['images'] = [];
        $contentBody['__options_images'] = [ 'replace' => 1 ];

        foreach ($images as $image)
        {
            if(Storage::disk('public')->exists($image->location))
            {
                $isMainImg = $image->attributes()->where('name', '=', 'sw_preview')->where('value','=', 'on')->first();
                if($isMainImg) { $isMainImg = 1; }
                else { $isMainImg = 2; }
                $contentBody['images'][] = [
                    'mediaId' => $image->id,
                    'link' => 'https://visticle.online/storage'.$image->location,
                    'main' => $isMainImg,
                    'options' => [ 'replace' => 1 ]
                ];
                $image->updateOrCreateAttribute("sw_uploaded", "1");
            }
        }

        if(count($contentBody['images'])<=0){$contentBody['active'] = 0;$contentBody['mainDetail'] = [ 'active' => 0];}

        $categories = $model->categories()->whereNull('fk_wawi_id')->get();
        $contentBody['categories'] = [];
        foreach($categories as $category) {
            $contentBody['categories'][] = [ 'id' => $category->wawi_number ];
        }

        $this->setBody($contentBody);
        return $contentBody;
    }

    public function buildActive(Model $model = null) {
        $contentBody = [ 'active' => 1, 'mainDetail' => [ 'active' => 1 ] ];
        $this->setBody($contentBody);
        return $contentBody;
    }
    public function buildDeActive(Model $model = null) {
        $contentBody = [ 'active' => 0,'mainDetail' => [ 'active' => 0 ] ];
        $this->setBody($contentBody);
        return $contentBody;
    }

    public function buildDelete(Model $model = null) { }

}
