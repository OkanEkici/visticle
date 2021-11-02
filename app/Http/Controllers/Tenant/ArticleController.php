<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant\Branch;
use App\Tenant\Article, App\Tenant\Article_Attribute, App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute, App\Tenant\Article_Price;
use App\Tenant\Article_Marketing;
use App\Tenant\Sparesets;
use App\Tenant\Sparesets_Articles;
use App\Tenant\Equipmentsets;
use App\Tenant\Equipmentsets_Articles;
use App\Tenant\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Redirect,Response;
use Illuminate\Support\Str;
use App\Traits\UploadTrait;
use Storage;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Article_Variation, App\Tenant\Article_Variation_Attribute, App\Tenant\Article_Variation_Price, App\Tenant\Article_Variation_Image, App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Article_Shipment;
use App\Tenant\Article_Eigenschaften, App\Tenant\Article_Eigenschaften_Data
, App\Tenant\Article_Eigenschaften_Articles, App\Tenant\Article_Eigenschaften_Categories;
use App\Tenant\Category;
use App\Tenant\Setting;
use App\Tenant\Attribute_Group;
use Auth;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use Log;
use Image;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Exception\RequestException;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopProviderController;

class ArticleController extends Controller
{

    use UploadTrait;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index_hauptartikel(){ return $this->index("article"); }
    public function index_ersatzteile(){ return $this->index("spare"); }
    public function index_zubehoerartikel(){ return $this->index("equipment"); }

    public function index($type = false)
    {
        $FilterType = "e";
        if($type){$FilterType = $type;}

        if(request()->ajax()) {

            $mainCategories = Category::where('fk_wawi_id', '=', null)->get();

            $response = datatables()->of(Article::with(['categories' => function($query) {
                $query->where('fk_wawi_id','!=',null);
            }, 'attributes', 'variations'])->select([
                'articles.id', 'articles.name', 'articles.active', 'articles.updated_at', 'articles.created_at', 'articles.number', 'articles.webname','articles.type'
            ])->where('articles.type','like',"%".$FilterType."%")
            )
            ->addColumn('systemnumber', function(Article $article) {
                if($article->id != null) {
                    return Setting::getReceiptNameWithNumberByKey('article', $article->id);
                }
            })
            ->addColumn('lieferant', function(Article $article) {
                $hersteller = $article->attributes()->where('name', '=', 'hersteller')->first();
                if($hersteller) {
                    return $hersteller->value;
                }
            })
            ->addColumn('lieferant-nr', function(Article $article) {
                $herstellerNr = $article->attributes()->where('name', '=', 'hersteller-nr')->first();
                if($herstellerNr) {
                    return $herstellerNr->value;
                }
            })
            ->addColumn('wg-eigenbez', function(Article $article) {
                $wgEigenBez = $article->attributes()->where('name', '=', 'eigene-artikelnr')->first();
                if($wgEigenBez) {
                    return $wgEigenBez->value;
                }
            })
            ->addColumn('mainCategories', $mainCategories)
            ->addColumn('articleMainCategories', function(Article $article) {
                $articleCategories = $article->categories()->whereNull('fk_wawi_id')->get();
                return $articleCategories;
            })
            ->addColumn('var_stock', function(Article $article) {
                $vars = $article->variations()->get();
                $ean_stock = [];
                $ean_stock_gesamt = 0;
                foreach($vars as $var)
                {   $this_ean_stock = array();
                    $this_ean_stock['ean'] = str_replace('vstcl-','',$var->vstcl_identifier);
                    $this_ean_stock['stock'] = $var->getStock();
                    $ean_stock_gesamt +=  $var->getStock();
                    array_push ($ean_stock, $this_ean_stock );
                };
                $ean_stock['gesamt'] = $ean_stock_gesamt;
                return $ean_stock;
            })
            ->addColumn('var_eans', function(Article $article) {
                $vars = $article->variations()->get();
                $eans = '';
                foreach($vars as $var) {
                    $eans .= str_replace('vstcl-','',$var->vstcl_identifier).' ';
                    if(isset($var->extra_ean)&&$var->extra_ean!=""){$eans .= $var->extra_ean.' ';}
                };
                return $eans;
            })
            ->addColumn('action', 'action_button')

            ->rawColumns(['action', 'mainCategories', 'articleMainCategories'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('articles');

            return response()->json($data);
        }
        return view('tenant.modules.article.index.article', ['typefilter'=>$FilterType,'sideNavConfig' => Article::sidenavConfig('artikelverwaltung'), 'isFashioncloudPartner' => (Setting::getFashionCloudApiKey() != null)]);
    }



    public function store(Request $request)
    {
        $doTheJob = false;
        $thisType = "";
        if(isset($request->type_main) && $request->type_main){ $thisType .= "article|"; }
        if(isset($request->type_spare) && $request->type_spare){ $thisType .= "spare|"; }
        if(isset($request->type_equipment) && $request->type_equipment){ $thisType .= "equipment|"; }
        if($thisType == ""){$thisType = "article|";}

        $article = Article::create([
            'active' => (isset($request->active)),
            'name' => $request->name,
            'ean' => $request->number,
            'number' => $request->number,
            'description' => $request->description,
            'short_description' => "",
            'slug' => Str::slug($request->name, '-'),
            'vstcl_identifier' => 'vstcl-'.$request->number,
            'type' => $thisType
        ]);


        //$doTheJob = VSShopController::create_article_job($article);
        $article->updateOrCreateAttribute("VPE", 1,1);
        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        {
            $article_variation = Article_Variation::create([
                'fk_article_id' => $article->id,
                'active' => (isset($request->active)),
                'ean' => $request->number.".".((int)(Article_Variation::where('ean','=',$request->number)->count())+ 1),
                'vstcl_identifier' => 'vstcl-'.$request->number,
                'type' => $thisType
            ]);
            //$doTheJob = VSShopController::create_article_variation_job($article_variation);
            $article_variation->updateOrCreateAttribute("VPE", 1,1);

            $article_variation_standard_price=Article_Variation_Price::updateOrCreate(
            [ 'fk_article_variation_id' => $article_variation->id,'name' => 'standard'],
            [ 'value' => "0.00" ]);
            $count=Article_Variation_Price::where('fk_article_variation_id','=', $article_variation->id)
            ->where('name' ,'=', 'standard')->count('*');
            if($count){
                //$doTheJob = VSShopController::update_article_variation_price_job($article_variation_standard_price);
            }
            else{
                //$doTheJob = VSShopController::create_article_variation_price_job($article_variation_standard_price);
            }

            $article_variation_discount_price=Article_Variation_Price::updateOrCreate(
            [ 'fk_article_variation_id' => $article_variation->id,
              'name' => 'discount'],
            [ 'value' => "0.00" ]);
            $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $article_variation->id)
            ->where( 'name' ,'=', 'discount')->count('*');
            if($count){
                //$doTheJob = VSShopController::update_article_variation_price_job($article_variation_discount_price);
            }
            else{
                //$doTheJob = VSShopController::create_article_variation_price_job($article_variation_discount_price);
            }
        }
        // Ausnahme für TS Cutting > Bestand 1000
        if(config()->get('tenant.identifier') == "ts-cutting")
        {   $branches = Branch::get();
            $variations = $article->variations()->get();
            foreach( $variations as $variation )
            {   foreach($branches as $branch)
                { $variation->updateOrCreateStockInBranch($branch, 1000, 111); }
            }
        }
        return response()->json(['success' => 'Erfolgreich gespeichert!']);
        //return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function edit($id) {
        $article = Article::find($id);
        return view('tenant.modules.article.edit.article', ['article' => $article, 'sideNavConfig' => Article::sidenavConfig('artikelverwaltung')]);
    }

    public function show($id, $part) {
        $configArray = [];
        $article = Article::find($id);
        switch($part) {
            case 'general':
                $configArray = $this->generalConfigFormArray($article);
            break;
            case 'provider':
                $configArray = $this->providerConfigFormArray($article);
            break;
            case 'description':
                $configArray = $this->descriptionConfigFormArray($article);
            break;
            case 'images':
                $configArray = $this->imagesConfigFormArray($article);
            break;
            case 'colors':
                $configArray = $this->colorsConfigFormArray($article);
            break;
            case 'sizes':
                $configArray = $this->sizesConfigFormArray($article);
            break;
            case 'eigenschaften':

                $ThisArticleCats = $article->categories()->get();
                $EigenschaftenUnique = [];

                foreach($ThisArticleCats as $ThisArticleCat)
                {
                    $CatEigenschaften = Article_Eigenschaften_Categories::where('fk_category_id','=',$ThisArticleCat->id)->get();
                    if($CatEigenschaften)
                    {
                        foreach($CatEigenschaften as $CatEigenschaft)
                        {   $Eigenschaft = Article_Eigenschaften::where('id','=',$CatEigenschaft->fk_eigenschaft_id)->first();
                            if($Eigenschaft)
                            {   if(!in_array($Eigenschaft->id,$EigenschaftenUnique)){
                                    $EigenschaftenUnique[$Eigenschaft->id]=[];
                                    $EigenschaftenUnique[$Eigenschaft->id]['active'] = $Eigenschaft->active;
                                    $EigenschaftenUnique[$Eigenschaft->id]['aktivText'] = ($Eigenschaft->active)? "<span class='text-green'>Aktiv</span>" : "<span class='text-red'>Inaktiv</span>";
                                    $EigenschaftenUnique[$Eigenschaft->id]['is_filterable'] = $Eigenschaft->is_filterable;
                                    $EigenschaftenUnique[$Eigenschaft->id]['filterText'] = ($Eigenschaft->is_filterable)? "<span class='text-blue'>*filterbar</span>" : "";
                                    $EigenschaftenUnique[$Eigenschaft->id]['type'] = 'text';
                                    $EigenschaftenUnique[$Eigenschaft->id]['EigenschaftText'] = "<b>".$Eigenschaft->name."</b>".'<input type="text" value="'.$Eigenschaft->id.'" name="eigenschaft[]" class="d-none">';
                                    $EigenschaftenUnique[$Eigenschaft->id]['deleteText'] = '';
                                    $EigenschaftenUnique[$Eigenschaft->id]['name'] = 'article['.$article->id.'][eigenschaft]['.$Eigenschaft->id.']';

                                    $EigenschaftenUnique[$Eigenschaft->id]['EigenschaftData'] = [];
                                }
                                $articleEigenschaftData=[]; $allEigenschaftDatas = $Eigenschaft->eigenschaften()->get();
                                if($allEigenschaftDatas)
                                {   foreach($allEigenschaftDatas as $allEigenschaftData)
                                    {   $thisIndex = count($articleEigenschaftData);
                                        $articleEigenschaftData[$thisIndex]['article']=$article->id;
                                        $articleEigenschaftData[$thisIndex]['eigenschaft']=$allEigenschaftData->fk_eigenschaft_id;

                                        $articleEigenschaftenDatas = $article->eigenschaften()->where('fk_eigenschaft_data_id','=',$allEigenschaftData->id)->first();

                                        $articleEigenschaftData[$thisIndex]['selected']=($articleEigenschaftenDatas)?1:0;
                                        $articleEigenschaftData[$thisIndex]['data'] = $allEigenschaftData;
                                    }
                                }
                                $EigenschaftenUnique[$Eigenschaft->id]['EigenschaftData'] = $articleEigenschaftData;
                            }
                        }
                    }
                }


                $configArray = $this->eigenschaftenConfigFormArray($article,$EigenschaftenUnique);

                return view('tenant.modules.article.show.article', [
                    'article' => $article,
                    'part' => $part,
                    'configArray' => $configArray,
                    'sideNavConfig' => Article::sidenavConfig('artikelverwaltung'),
                    'isFashioncloudPartner' => (Setting::getFashionCloudApiKey() != null)
                ]);
            break;
            case 'attributes':
                $configArray = $this->attributesConfigFormArray($article);
                $Article_AttributeGroupIDs = $article->attributes()->whereNotNull('fk_attributegroup_id')->get()->pluck("fk_attributegroup_id")->toArray();
                $attributeGroups = Attribute_Group::whereNotIn('id',$Article_AttributeGroupIDs)->get();
                $AttributContent = [];
                foreach($attributeGroups as $attributeGroup)
                {
                    $AttributContent[] = [
                        '<div class="custom-control custom-checkbox mb-1"><input data-article_id="'.$article->id.'" data-is_filterable="'.((isset($attributeGroup->is_filterable) && $attributeGroup->is_filterable)?"filterbar":"").'" data-unit_type="'.((isset($attributeGroup->unit_type))?$attributeGroup->unit_type:"").'" data-name="'.((isset($attributeGroup->name))?$attributeGroup->name:"").'" data-active="'.((isset($attributeGroup->active) && $attributeGroup->active)?"Aktiv":"").'" data-id="'.$attributeGroup->id.'" type="checkbox" class="custom-control-input wahl-attribut" id="attribut_'.$attributeGroup->id.'"><label class="custom-control-label" for="attribut_'.$attributeGroup->id.'"></label></div>',
                        ''.((isset($attributeGroup->active) && $attributeGroup->active)?"Aktiv":""),
                        ''.((isset($attributeGroup->name))?$attributeGroup->name:"").'',
                        ''.((isset($attributeGroup->description))?$attributeGroup->description:"").'',
                        ''.((isset($attributeGroup->unit_type))?$attributeGroup->unit_type:"").'',
                        ''.((isset($attributeGroup->is_filterable) && $attributeGroup->is_filterable)?"filterbar":"")

                    ];
                }
                return view('tenant.modules.article.show.article', [
                    'article' => $article,
                    'AttributContent' => $AttributContent,
                    'part' => $part,
                    'configArray' => $configArray,
                    'sideNavConfig' => Article::sidenavConfig('artikelverwaltung'),
                    'isFashioncloudPartner' => (Setting::getFashionCloudApiKey() != null)
                ]);
            break;
            case 'variations':
                $configArray = $this->variationsConfigFormArray($article);
            break;
            case 'prices':
                $configArray = $this->pricesConfigFormArray($article);
            break;
            case 'categories':
                $configArray = $this->categoriesConfigFormArray($article);
            break;
            case 'inventory':
                $configArray = $this->inventoryConfigFormArray($article);
            break;
            case 'shipping':
                $configArray = $this->shippingConfigFormArray($article);
            break;
            case 'marketing':
                $configArray =  $this->marketingConfigFormArray($article);
            break;
            case 'sparesets':
                return $this->sparesetsConfigFormArray($article, $part);
            break;
            case 'equipmentsets':
                return $this->equipmentsetsConfigFormArray($article, $part);
            break;
        }


        return view('tenant.modules.article.show.article', [
            'article' => $article,
            'part' => $part,
            'configArray' => $configArray,
            'sideNavConfig' => Article::sidenavConfig('artikelverwaltung'),
            'isFashioncloudPartner' => (Setting::getFashionCloudApiKey() != null)
        ]);
    }

    public function destroy($id)
    {
        $article = Article::find($id)->first();
        if($article)
        {
            $caVars=$article->variations()->get();
            foreach($caVars as $caVar)
            {	$caVarAttrs = $caVar->attributes()->get();
                foreach($caVarAttrs as $caVarAttr){$caVarAttr->delete();}
                $caVarPrices = $caVar->prices()->get();
                foreach($caVarPrices as $caVarPrice){$caVarPrice->delete();}
                $caVarImages = $caVar->images()->get();
                foreach($caVarImages as $caVarImage){
                    $caVarImageAttrs = $caVarImage->attributes()->get();
                    foreach($caVarImageAttrs as $caVarImageAttr){$caVarImageAttr->delete();}
                    $caVarImage->delete();
                }
                $BranchArticle_Variations = BranchArticle_Variation::where('fk_article_variation_id','=',$caVar->id)->get();
                foreach($BranchArticle_Variations as $BranchArticle_Variation){
                    VSShopController::delete_stock_job($BranchArticle_Variation);
                    $BranchArticle_Variation->delete();
                }
                $caVar->delete();
            }
            $checkArtikelAttrs = $article->attributes()->get();
            foreach($checkArtikelAttrs as $checkArtikelAttr)
            {
                VSShopController::delete_article_attr_job($checkArtikelAttr);
                $checkArtikelAttr->delete();
            }
            $checkArtikelPrices = $article->prices()->get();
            foreach($checkArtikelPrices as $checkArtikelPrice)
            {
                VSShopController::delete_article_variation_price_job($checkArtikelPrice);
                $checkArtikelPrice->delete();
            }
            $checkArtikelImages = $article->images()->get();
            foreach($checkArtikelImages as $checkArtikelImage){
                $caImageAttrs = $checkArtikelImage->attributes()->get();
                foreach($caImageAttrs as $caImageAttr)
                {
                    VSShopController::delete_article_image_attr_job($caImageAttr);
                    $caImageAttr->delete();
                }
                VSShopController::delete_article_image_job($checkArtikelImage);
                $checkArtikelImage->delete();
            }
            $ArticleProviders = ArticleProvider::where('fk_article_id','=',$article->id)->get();
            foreach($ArticleProviders as $ArticleProvider)
            {
                $ArticleProvider->delete();
            }
            $article->delete();
        }
        return Response::json($article);
    }

    public function update(Request $request, $id, $part) {
        $article = Article::find($id); $article->update([ 'batch_nr' => date("Ymdhis") ]);
        switch($part) {
            case 'general': $this->updateGeneral($request, $article);
                            VSShopController::update_article_job($article);
                            break;
            case 'provider':
                $articleProviders = $article->provider()->get();
                foreach($articleProviders as $articleProvider) {
                    $articleProvider->active = 0;
                    $articleProvider->save();
                }
                $checkActive=false;
                ArticleProvider::where('fk_article_id','=',$article->id)->update(['active' => 0]);
                if($request->exists('forprovider') && is_array($request->forprovider)) {
                    foreach($request->forprovider as $key => $value) {
                        $checkActive=true;
                        ArticleProvider::updateOrCreate(
                            [
                                'fk_provider_id' => $key,
                                'fk_article_id' => $article->id
                            ],
                            [
                                'active' => 1
                            ]
                        );
                    }
                }
                if(!$checkActive)
                { $article->update([ 'active' => 0 ]); }
                else{$article->update([ 'active' => 1 ]);}
                //wenn kein Provider mehr aktiv ist, deaktiviere den artikel
                VSShopController::update_article_job($article);
            break;
            case 'description':

                if(isset($request->sw_emcgn_highlights))
                {
                    $update=false;
                    if(Article_Attribute::where('fk_article_id','=',$article->id)->where('name','=','sw_emcgn_highlights')->count('*'))
                    {
                        $update=true;
                    }
                    $article_attribute=
                    Article_Attribute::updateOrCreate(
                        [   'fk_article_id' => $article->id, 'name' => 'sw_emcgn_highlights' ], ['value' => $request->sw_emcgn_highlights]
                    );
                    if($update){
                        VSShopController::update_article_attr_job($article_attribute);
                    }
                    else{
                        VSShopController::create_article_attr_job($article_attribute);
                    }
                }else
                {
                    if(config()->get('tenant.identifier') == "stilfaktor")
                    //if($article->getAttrByName('sw_emcgn_highlights') != "")
                    {
                        $checkString = str_replace("<ul>","",str_replace("</ul>","",$request->description));
                        $checkString = str_replace('<div class="em--product--description-inner">',"",str_replace('</div>',"",str_replace('<div>',"",$checkString)));
                        $checkString_arr = explode("<li>",$checkString);
                        if(!empty(trim($checkString," ")))
                        {	$this_Highlights = "<ul>";
                            if(is_array($checkString_arr) && count($checkString_arr) > 0) {	$countLI = 0;
                                foreach($checkString_arr as $this_LI)  {
                                if(empty(trim($this_LI," ")) || str_replace(array("\r\n", "\r"), "", $this_LI) == ""){continue;}
                                if($this_LI=="" || $this_LI==" "){continue;}
                                $countLI++;  if($countLI>4){continue;}
                                $this_Highlights .= "<li>".str_replace("<li>","",str_replace("</li>","",$this_LI))."</li>"; }
                            }  $this_Highlights .= "</ul>";
                        }else{$this_Highlights = "<ul></ul>";}
                        $update=false;
                        if(Article_Attribute::where('fk_article_id','=',$article->id)->where('name','=','sw_emcgn_highlights')->count('*'))
                        {
                            $update=true;
                        }
                        $article_attribute=
                        Article_Attribute::updateOrCreate( [   'fk_article_id' => $article->id, 'name' => 'sw_emcgn_highlights' ], ['value' => $this_Highlights] );
                        if($update){
                            VSShopController::update_article_attr_job($article_attribute);
                        }
                        else{
                            VSShopController::create_article_attr_job($article_attribute);
                        }
                    }
                }

                $request->merge([
                    'description' => $this->processedSummernoteforDB($request->description, $request, $article)
                ]);
                Article::find($id)->update($request->except(['_token', 'files']));
            break;
            case 'images': $this->updateArticleImages($request, $article);break;
            case 'eigenschaften': $this->updateEigenschaften($request, $article); break;
            case 'attributes': $this->updateAttributes($request, $article); break;
            case 'colors': $this->updateColors($request, $article); break;
            case 'sizes': $this->updateSizes($request, $article); break;
            case 'variations': $this->updateVariations($request, $article); break;
            case 'prices': $this->updatePrices($request, $article); break;
            case 'categories': $this->updateCategories($request, $article); break;
            case 'inventory': $this->updateInventory($request, $article); break;
            case 'shipping': $this->updateShipping($request, $article); break;
            case 'marketing': $this->updateMarketing($request, $article); break;
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function updateCategoryAjax(Request $request) {
        $articleId = $request->articleId;
        $categoryId = $request->categoryId;
        $state = ($request->state == 'true');
        $article = Article::findOrFail($articleId);
        if($state) {
            $article->categories()->syncWithoutDetaching([$categoryId]);
        } else {
            $article->categories()->detach($categoryId);
        }

        return response()->json(['success' => 1]);
    }

    public function create(){
        return view('tenant.modules.article.create.article', ['sideNavConfig' => Article::sidenavConfig('artikelverwaltung')]);
    }

    public function destroyImage($artId, $imgId) {
        $image = Article_Image::find($imgId);
        if($image)
        {
            $imgPath = $image->location;
            $fcId = $image->fashioncloud_id;
            //delete all variations relations for image
            $varImgs = Article_Variation_Image::where('location','=',$imgPath)->get();
            foreach($varImgs as $varImg) {
                VSShopController::delete_article_variation_image_job($varImg);
                $varImg->delete();
            }
            VSShopController::delete_article_image_job($image);
            $deleted=$image->delete();
            if($deleted) {

                if($fcId == null) {
                    Storage::disk('public')->delete($imgPath);
                }

            }
        }

        return redirect()->back();
    }

    public function loadVariations($id) {
        if(request()->ajax()) {
            return datatables()->of(Article_Variation::select('*')->where('fk_article_id', '=', $id)->with(['attributes', 'prices']))
            ->addColumn('stock', function(Article_Variation $variation) {
                return $variation->getStock();
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }
    }

    public function deleteVariation($id, $vid){
        $article_variation=Article_Variation::find($vid);

        VSShopController::delete_article_variation_job($article_variation);
        $article_v=$article_variation->delete();

        return Response::json($article_v);
    }

    public function loadCategories($id, $part) {
        $categories = Category::where('fk_wawi_id','=',null)->get();
        $treeData = [];
        foreach($categories as $category) {
            $articleInCategory = false;
            if($part == 'categories') {
                $articleInCategory = $category->articles()->where('article_id', '=', $id)->first();
            }
            elseif ($part == 'marketing') {
                $articleInCategory = $category->upsells()->where('fk_main_article_id', '=', $id)->first();
            }
            $text = ($category->name == '') ? '-' : $category->name ;
            if($category->fk_wawi_id != null) {
              $text.= ' / Aus Wawi: '.$category->wawi()->first()->name . ' - '.$category->wawi_number.' - '.$category->wawi_name;
            }
            $treeData[] = [
                'id' => $category->id,
                'parent' => ($category->fk_parent_category_id == null) ? '#' : $category->fk_parent_category_id,
                'text' => $text,
                'icon' => 'fas fa-layer-group text-teal',
                'state' => ['opened' => false, 'selected' => ($articleInCategory) ? true : false]
            ];
        }
        return Response::json($treeData);
    }

    protected function generalConfigFormArray(Article $article) {
        $providerArticles = $article->providerMappings()->get()->groupBy('fk_provider_id');
        $provider = '';
        $i = 0;
        $numItems = count($providerArticles);
        foreach($providerArticles as $providerArticle) {
			$thisProvider = $providerArticle->first()->provider()->get()->first();
			if($thisProvider){$provider .= $thisProvider->name .((++$i !== $numItems && $numItems != 1) ? ',' : '');}
        }

        $is_Hauptartikel = (strpos($article->type, "article") !== false) ? true : false;
        $is_Ersatzteil = (strpos($article->type, "spare") !== false) ? true : false;
        $is_Zubehoer = (strpos($article->type, "equipment") !== false) ? true : false;

        $thisActivePs = ArticleProvider::where('fk_article_id','=',$article->id)->get();
        $checkedActiveP=false;$Pcounter=0;
        if($thisActivePs){ foreach($thisActivePs as $thisActiveP){if($thisActiveP->active){$Pcounter++;} if($thisActiveP->active && !$checkedActiveP){ $checkedActiveP=true; } } }

        $Tenant_type = config()->get('tenant.tenant_type');
        $formgroupTypArr = [
            ['label' => 'Artikelnummer', 'value' => ($article->number != null) ? $article->number : '-', 'type' => 'info'],
            ['label' => 'Lieferantennummer', 'value' => $article->getAttrByName('hersteller-nr'), 'type' => 'info'],
            ['label' => 'Lieferant', 'value' => $article->getAttrByName('hersteller'), 'type' => 'info'],
            ['label' => 'Verkaufsplattformen', 'value' => $provider, 'type' => 'info'],
            ['text' => 'Status', 'type' => 'label'],
            [
                'type' => 'checkbox',
                'options' => [
                    [
                        'addOn' => 'AktivCheckLeuchte',
                        'teilaktiv' => (($checkedActiveP && $Pcounter>1)? "1" : "0" ),
                        'class' => 'hidden',
                        'label' => 'Aktiv',
                        'name' => 'active',
                        'checked' => ($article->active)
                    ]
                ]
            ],
            ['text' => 'Artikeltyp', 'type' => 'label'],
            [
                'type' => 'checkbox',
                'options' => [
                    [
                        'label' => 'Hauptartikel',
                        'name' => 'type_main',
                        'checked' => $is_Hauptartikel
                    ]
                ]
            ]
        ];
        if($Tenant_type=='vstcl-industry')
        {$formgroupTypArr = [['label' => 'Artikelnummer', 'value' => ($article->number != null) ? $article->number : '-', 'type' => 'info'],
            ['label' => 'Lieferantennummer', 'value' => $article->getAttrByName('hersteller-nr'), 'type' => 'info'],
            ['label' => 'Lieferant', 'value' => $article->getAttrByName('hersteller'), 'type' => 'info'],
            ['label' => 'Verkaufsplattformen', 'value' => $provider, 'type' => 'info'],
            ['text' => 'Status', 'type' => 'label'],
            [
                'type' => 'checkbox',
                'options' => [
                    [
                        'addOn' => 'AktivCheckLeuchte',
                        'teilaktiv' => (($checkedActiveP && $Pcounter>1)? "1" : "0" ),
                        'class' => 'hidden',
                        'label' => 'Aktiv',
                        'name' => 'active',
                        'checked' => ($article->active)
                    ]
                ]
            ],
            ['text' => 'Artikeltyp', 'type' => 'label'],
            [
                'type' => 'checkbox',
                'options' => [
                    [
                        'label' => 'Hauptartikel',
                        'name' => 'type_main',
                        'checked' => $is_Hauptartikel
                    ]
                ]
            ],
            [
                'type' => 'checkbox',
                'options' => [
                    [
                        'label' => 'Ersatzteil',
                        'name' => 'type_spare',
                        'checked' => $is_Ersatzteil
                    ]
                ]
            ],
            [
                'type' => 'checkbox',
                'options' => [
                    [
                        'label' => 'Zubehörartikel',
                        'name' => 'type_equipment',
                        'checked' => $is_Zubehoer
                    ]
                ]
            ]];}

        $fieldsets = [
            [
                'legend' => 'Stammdaten',
                  'form-group' => $formgroupTypArr
                ],
                [
                    'legend' => 'Systeminfos',
                    'form-group' => [
                        ['label' => 'Angelegt', 'value' => date('d.m.Y H:i:s', strtotime($article->created_at)), 'type' => 'info'],
                        ['label' => 'Geändert', 'value' => date('d.m.Y H:i:s', strtotime($article->updated_at)), 'type' => 'info'],
                        ['label' => 'WaWi', 'value' => ($article->wawi()->first()) ? $article->wawi()->first()->name : '-', 'type' => 'info'],
                        ['label' => 'VISTICLEID', 'value' => $article->vstcl_identifier, 'type' => 'info']
                    ]
                ]
        ];

        return $fieldsets;
    }

    protected function providerConfigFormArray(Article $article) {
        $providers = Provider::all();
        $formFields = [
            [
                'legend' => 'Verkaufsplattformen',
                'form-group' => [
                    [
                        'type' => 'checkbox',
                        'options' => []
                    ]
                  ],
              ]
        ];
        $count = 0;
        foreach($providers as $provider) {
            $formFields[0]['form-group'][0]['options'][$count] = [
                'label' => $provider->name,
                'name' => 'forprovider['.$provider->id.']'
            ];

            if($provider->articles()->where('fk_article_id', '=', $article->id)->where('active', '=', '1')->first()) {
                $formFields[0]['form-group'][0]['options'][$count]['checked'] = true;
            }
            $count++;
        }
        return $formFields;
    }

    protected function descriptionConfigFormArray(Article $article)
    {

        $formgroupFields =
        [
            [
                'label' => 'Name',
                'name' => 'name',
                'type' => 'text',
                //'required' => true,
                'value' => $article->name,
            ],
            [
                'label' => 'Kurzbeschreibung (SEO - Metadescription)',
                'type' => 'text',
                'name' => 'short_description',
                'value' => $article->short_description
            ],
            [
                'label' => 'Beschreibung',
                'id' => 'descriptionEditor',
                'type' => 'wysiwyg',
                'name' => 'description',
                'value' => $article->description
            ],
            [
                'label' => 'Artikelname (Web)',
                'type' => 'text',
                'name' => 'webname',
                'value' => $article->webname
            ],
            [
                'label' => 'Metatitel (SEO)',
                'type' => 'text',
                'name' => 'metatitle',
                'value' => $article->metatitle
            ],
            [
                'label' => 'Keywords (SEO)',
                'type' => 'text',
                'name' => 'keywords',
                'value' => $article->keywords
            ]
        ];

        $thisHighlights = $article->getAttrByName('sw_emcgn_highlights');
        $thisHighlights_field = false;
        if($thisHighlights!="")
        {
            $formgroupFields=
            [
                [
                    'label' => 'Name',
                    'name' => 'name',
                    'type' => 'text',
                    //'required' => true,
                    'value' => $article->name,
                ],
                [
                    'label' => 'Kurzbeschreibung (SEO - Metadescription)',
                    'type' => 'text',
                    'name' => 'short_description',
                    'value' => $article->short_description
                ],
                [
                    'label' => 'Beschreibung',
                    'id' => 'descriptionEditor',
                    'type' => 'wysiwyg',
                    'name' => 'description',
                    'value' => $article->description
                ]/*,
                [
                    'label' => 'Highlights',
                    'id' => 'sw_emcgn_highlightsEditor',
                    'type' => 'wysiwyg',
                    'name' => 'sw_emcgn_highlights',
                    'value' => $thisHighlights
                ]*/,
                [
                    'label' => 'Artikelname (Web)',
                    'type' => 'text',
                    'name' => 'webname',
                    'value' => $article->webname
                ],
                [
                    'label' => 'Metatitel (SEO)',
                    'type' => 'text',
                    'name' => 'metatitle',
                    'value' => $article->metatitle
                ],
                [
                    'label' => 'Keywords (SEO)',
                    'type' => 'text',
                    'name' => 'keywords',
                    'value' => $article->keywords
                ]
            ];
        }
        $formFields = [
            [
                'legend' => 'Artikelname und Beschreibung',
                'form-group' => $formgroupFields,
            ],
        ];
        return $formFields;
    }

    protected function imagesConfigFormArray(Article $article)
    {
        $swController = new ShopwareAPIController();
        $swShops = $swController->getShops();

        $images = Article_Image::where('fk_article_id', '=', $article->id);
        $variations = $article->variations()->with(['images', 'attributes', 'images.attributes'])->get();
        $loadedImgs = [];
        $colors = [];
        foreach($variations as $variation) {
            $colorText = $variation->getColorText();
            if(!in_array($colorText, $colors)) {
                $colors[] = $colorText;
            }
            foreach($variation->images as $image) {
                if(isset($loadedImgs[$image->location])) {
                    if(!in_array($colorText, $loadedImgs[$image->location])) {
                        $loadedImgs[$image->location]['colors'][] = $colorText;
                    }
                    continue;
                }
                else {
                    $loadedImgs[$image->location] = [
                        'colors' => [$colorText],
                        'attributes' => $image->attributes
                    ];
                }
            }
        }
        /*
        $previewImgs = [];
        foreach($loadedImgs as $imgLocation => $loadedImg) {
            $imgAttrs = [
                'for_colors' => ['label' => 'Farbauswahl', 'type' => 'multiselect', 'options' => []],
                'is_thumbnail' => ['label' => 'Thumbnail', 'type' => 'checkbox'],
                'is_preview' => ['label' => 'Vorschaubild', 'type' => 'checkbox'],
                'is_base' => ['label' => 'Hauptbild', 'type' => 'checkbox'],
                'position' => ['label' => 'Position', 'type' => 'number'],
            ];
            foreach($loadedImg['colors'] as $color) {
                $imgAttrs['for_colors']['options'][] = [
                    'text' => $color,
                    'selected' => true
                ];
            }
            foreach($imgAttrs as $imgAttrKey => $imgAttrVal) {
                $attr = $loadedImg['attributes']->where('name', '=', $imgAttrKey)->first();
                if($attr) {
                    $imgAttrs[$imgAttrKey]['value'] = $attr->value;
                }
                $imgAttrs[$imgAttrKey]['name'] = 'img['.$image->id.']['.$imgAttrKey.']';
            }

            $previewImgs[] = [
                'path' => $imgLocation,
                'attrs' => $imgAttrs,
                'actions' => [['href' => route('tenant.articles.images.delete', [TENANT_IDENT, $article->id, $image->id]), 'icon' => 'fas fa-trash-alt', 'confirm' => true]]
            ];
        }*/


        //dd($loadedImgs);
        $previewImgs = [];


        foreach($images->get() as $image) {

            $imgAttrs = [
                'for_colors][' => ['label' => 'Farbauswahl', 'type' => 'multiselect', 'options' => [], 'title' => 'Wählen Sie eine oder mehrere Farben...'],
                'is_preview' => ['label' => 'Vorschaubild', 'type' => 'checkbox'],
                'is_preview_2' => ['label' => 'Vorschaubild 2', 'type' => 'checkbox'],
                'is_base' => ['label' => 'Hauptbild', 'type' => 'checkbox'],
            ];
            $Tenant_type = config()->get('tenant.tenant_type');
            if($Tenant_type=='vstcl-industry'){ $imgAttrs['is_pikto'] = ['label' => 'Piktogramm', 'type' => 'checkbox']; }
            if(!empty($swShops)) {$imgAttrs['sw_preview'] = ['label' => 'Shopware Vorschaubild', 'type' => 'checkbox', 'class' => 'sw_sw_sw'];}

            $selectedColors = [];
            if(isset($loadedImgs[$image->location])) {
                $selectedColors = $loadedImgs[$image->location]['colors'];
            }
            foreach($colors as $color) {
                $selected = in_array($color, $selectedColors);
                $imgAttrs['for_colors][']['options'][] = [
                    'text' => $color,
                    'value' => $color,
                    'selected' => $selected
                ];
            }

            foreach($imgAttrs as $imgAttrKey => $imgAttrVal) {
                $attr = $image->attributes()->where('name', '=', $imgAttrKey)->first();
                if($attr) {
                    $value = $attr->value;
                    $imgAttrs[$imgAttrKey]['value'] = (($value=="1")? "on" : $value);
                }
                $imgAttrs[$imgAttrKey]['name'] = 'img['.$image->id.']['.$imgAttrKey.']';
            }

            $previewImgs[] = [
                'path' => $image->location,
                'attrs' => $imgAttrs,
                'actions' => [['href' => route('tenant.articles.images.delete', [TENANT_IDENT, $article->id, $image->id]), 'icon' => 'fas fa-trash-alt', 'confirm' => true]]
            ];
        }
        $formFields = [
            [
                'legend' => 'Bilder',
                'form-group' => [
                      [
                          'type' => 'upload',
                          'value' => ['name' => 'images[]', 'placeholder' => 'Bilder auswählen', 'previewImgs' => $previewImgs, 'label' => 'Neue Bilder hochladen', 'helptext' => 'Hinweise: Laden Sie hier Ihre großen Artikelbilder hoch. Das System schneidet die Bilder automatisch zu.'],
                      ],
                  ],
              ]
        ];
        return $formFields;
    }

    protected function attributesConfigFormArray(Article $article)
    {
        $formFields = [ [ 'legend' => 'Attribute', 'form-group' => [], ] ];

        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        {
            $attributeGroups = Attribute_Group::all();
            foreach($attributeGroups as $attributeGroup)
            {
                $Article_Attribute = Article_Attribute::where('fk_article_id', '=', $article->id)->where('fk_attributegroup_id', '=', $attributeGroup->id)->first();
                if($Article_Attribute)
                {
                    $aktivText = ($attributeGroup->active)? "<span class='text-green'>Aktiv</span>" : "<span class='text-red'>Inaktiv</span>";
                    $is_filterableText = ($attributeGroup->is_filterable)? "<span class='text-blue'>*filterbar</span>" : "";
                    $formFields[0]['form-group'][] = [
                        'type' => 'text',
                        'filterText' => $is_filterableText,
                        'AttributText' => "<b>".$attributeGroup->name."</b>",
                        'value' => (($Article_Attribute)? $Article_Attribute->value : ""),
                        'appendText' => $attributeGroup->unit_type,
                        'aktivText' => $aktivText,
                        'deleteText' => '<a data-id="'.$attributeGroup->id.'" class="ml-3 btn btn-sm btn-icon btn-secondary text-red entferne-attribute" ><i class="far fa-trash-alt"></i></a>',
                        'name' => 'article['.$article->id.'][attribute_group]['.$attributeGroup->id.']'
                    ];
                }

            }

        }
        else
        {

            $attributes = Article_Attribute::where('fk_article_id', '=', $article->id)->get();
            foreach($attributes as $attribute) {
                $formFields[0]['form-group'][] = [
                    'type' => 'text',
                    'prependText' => $attribute->name,
                    'value' => $attribute->value,
                    'name' => 'article['.$article->id.'][attributes]['.$attribute->id.']'
                ];
            }
            $formFields[0]['form-group'][] = [
                'type' => 'footer',
                'actions' => [['icon' => 'fa fa-plus-circle', 'data-class' => 'article'.$article->id.'_attribute', 'data-name' => 'article['.$article->id.'][attributes][new]', 'text' => 'Attribut hinzufügen', 'class' => 'add_attribute_btn', 'id' => 'base_attr_add']]
            ];
            $variations = $article->variations();
            foreach($variations->get() as $variation) {
                $labelText = '';
                $varSize = $variation->attributes()->where('name', '=', 'fee-size')->first();
                $varColor = $variation->attributes()->where('name', '=', 'fee-color')->first();
                if($varSize)
                { $labelText .= 'Größe '.$varSize->value;}
                if($varColor)
                { $labelText .= ' | Farbe '.$varColor->value;}
                $labelText .= ' | EAN '.str_replace('vstcl-', '',$variation->vstcl_identifier);
                $formFields[0]['form-group'][] = [
                    'type' => 'label',
                    'text' =>  $labelText,
                ];
                $vAttributes = Article_Variation_Attribute::where('fk_article_variation_id', '=', $variation->id);
                foreach($vAttributes->get() as $vAttribute) {
                    $formFields[0]['form-group'][] = [
                        'type' => 'text',
                        'prependText' => $vAttribute->name,
                        'value' => $vAttribute->value,
                        'name' => 'variation['.$variation->id.'][attributes]['.$vAttribute->id.']'
                    ];
                }
                $formFields[0]['form-group'][] = [
                    'type' => 'footer',
                    'actions' => [['icon' => 'fa fa-plus-circle', 'text' => 'Attribut hinzufügen', 'data-class' => 'variation_'.$variation->id.'_attribute', 'data-name' => 'variation['.$variation->id.'][attributes][new]','class' => 'add_attribute_btn', 'id' => 'variation_'.$variation->id.'_attr_add']]
                ];
            }

            if(count($formFields[0]['form-group']) == 1)
            { $formFields[0]['form-group'][0]['text'] = 'Keine Attribute vorhanden';}
        }



        return $formFields;
    }

    protected function eigenschaftenConfigFormArray(Article $article, $EigenschaftenUnique = [])
    {
        $formFields = [ [ 'legend' => 'Eigenschaften', 'form-group' => [], ] ];

        foreach($EigenschaftenUnique as $EigenschaftID => $Eigenschaft)
        {
                $formFields[0]['form-group'][] =
                ['type' => $Eigenschaft['type']
                ,'aktivText' => $Eigenschaft['aktivText']
                ,'filterText' => $Eigenschaft['filterText']
                ,'EigenschaftData' => $Eigenschaft['EigenschaftData']
                ,'name' => $Eigenschaft['name']
                ,'EigenschaftText' => $Eigenschaft['EigenschaftText']
                ,'deleteText' =>  $Eigenschaft['deleteText']
                ,'id' => 'eigenschaft_'.$EigenschaftID
                ];
        }

        return $formFields;
    }

    protected function colorsConfigFormArray(Article $article) {
        $variations = $article->variations()->get();
        $content = [];
        foreach($variations as $variation) {
            $colorNr = $variation->attributes()->where('name','=','fee-color')->first();
            $colorWawi = $variation->attributes()->where('name', '=', 'fee-info1')->first();
            $ownColor = $variation->attributes()->where('name', '=', 'own-color')->first();
            $content[] = [
                $article->number,
                str_replace('vstcl-', '', $variation->vstcl_identifier),
                ($colorNr) ? $colorNr->value : '',
                ($colorWawi) ? $colorWawi->value : '',
                '<input class="form-control" name="colors['.$variation->id.'][own-color]" value="'.(($ownColor) ? $ownColor->value : '').'">'
            ];
        }
        $formFields = [
            [
                'legend' => 'Farben',
                'form-group' => [
                    [
                        'type' => 'table',
                        'tableData' => [
                            'firstColumnWidth' => 10,
                            'easyTable' => true,
                            'tableId' => 'colorsTable',
                            'columns' => ['ArtikelNr','EAN', 'FarbNr', 'FarbeWaWi', 'Farbbezeichnung'],
                            'content' => $content
                        ]
                    ]
                  ],
              ]
        ];
        return $formFields;
    }

    protected function sizesConfigFormArray(Article $article)
    {
        $Tenant = config()->get('tenant.identifier');
        $variations = $article->variations()->get();
        $content = [];
        foreach($variations as $variation) {
            $size = $variation->attributes()->where('name','=','fee-size')->first();
            $length = $variation->attributes()->where('name', '=', 'fee-formLaenge')->first();


            if($Tenant == 'olgasmodewelt')
            {
                $content[] = [
                    $article->number
                    , str_replace('vstcl-', '', $variation->vstcl_identifier)
                    , ($size) ? $size->value : ''
                    , '<input class="form-control" name="feeformLaenge['.$variation->id.']" value="'.(($length) ? $length->value : '').'">'
                ];
            }
            else
            { $content[] = [ $article->number , str_replace('vstcl-', '', $variation->vstcl_identifier) , ($size) ? $size->value : '' , ($length) ? $length->value : '' ]; }

        }
        $formFields = [
            [
                'legend' => 'Größen',
                'form-group' => [
                    [
                        'type' => 'table',
                        'tableData' => [
                            'easyTable' => true,
                            'firstColumnWidth' => 10,
                            'tableId' => 'sizesTable',
                            'columns' => ['ArtikelNr','EAN', 'Größe', 'Länge'],
                            'content' => $content
                        ]
                    ]
                  ],
              ]
        ];
        return $formFields;
    }

    protected function variationsConfigFormArray(Article $article) {
        $variations = $article->variations()->get();
        $content = [];
        foreach($variations as $variation) {
            $colorNr = $variation->attributes()->where('name','=','fee-info1')->first();
            $size = $variation->attributes()->where('name','=','fee-size')->first();
            $length = $variation->attributes()->where('name', '=', 'fee-formLaenge')->first();
            $content[] = [
                ($colorNr) ? $colorNr->value : '',
                ($size) ? $size->value : '',
                ($length) ? $length->value : '',
                str_replace('vstcl-', '', $variation->vstcl_identifier) .
                (($variation->extra_ean) ? ' <br>+ '.$variation->extra_ean. '<a title="Zusatz EAN entfernen" class="ml-2" href="/delete-extra-ean/'.$variation->id.'"><i class="fas fa-trash-alt"></i></a>' : ''),
                $variation->getStock(),
                '<input class="form-control" name="min_stock['.$variation->id.']" type="number" min="0" step="1" value="'.$variation->min_stock.'">',
                '<div class="custom-control custom-checkbox">
                <input type="checkbox" id="chkbactive'.$variation->id.'" class="custom-control-input" name="active['.$variation->id.']" '.(($variation->active) ? 'checked' : '').'>
                    <label class="custom-control-label" for="chkbactive'.$variation->id.'">Aktiv</label>
                </div>',
            ];
        }
        $formFields = [
            [
                'legend' => 'Variationen',
                'form-group' => [
                    [
                        'type' => 'table',
                        'tableData' => [
                            'firstColumnWidth' => 10,
                            'tableId' => 'articleVariationTable',
                            'easyTable' => true,
                            'columns' => ['Farbe', 'Größe', 'Länge', 'EAN', 'Bestand', 'Min. Bestand', ''],
                            'content' => $content
                        ]
                    ]
                  ],
              ]
        ];
        return $formFields;
    }


    protected function pricesConfigFormArray(Article $article) {
        $variations = $article->variations()->get();
        $content = [];
        foreach($variations as $variation) {
            $colorNr = $variation->attributes()->where('name','=','fee-info1')->first();
            $size =  $variation->attributes()->where('name','=','fee-size')->first();
            $length = $variation->attributes()->where('name', '=', 'fee-formLaenge')->first();
            $vStandardPrice = $variation->prices()->where('name','=','standard')->first() ? $variation->prices()->where('name','=','standard')->first()->value : '';
            $vSalePrice = $variation->prices()->where('name','=','discount')->first() ? $variation->prices()->where('name','=','discount')->first()->value : '';
            $vWebStandardPrice = $variation->prices()->where('name','=','web_standard')->first() ? $variation->prices()->where('name','=','web_standard')->first()->value : '';
            $vWebSalePrice = $variation->prices()->where('name','=','web_discount')->first() ? $variation->prices()->where('name','=','web_discount')->first()->value : '';
            $content[] = [
                ($colorNr) ? $colorNr->value : '',
                ($size) ? $size->value : '',
                ($length) ? $length->value : '',
                $variation->getStock(),
                str_replace('vstcl-', '', $variation->vstcl_identifier),
                $vStandardPrice,
                $vSalePrice,
                '<input class="form-control" name="web_price['.$variation->id.'][standard]" type="number" min="0" step="0.01" value="'.str_replace(',','.',$vWebStandardPrice).'">',
                '<input class="form-control" name="web_price['.$variation->id.'][discount]" type="number" min="0" step="0.01" value="'.str_replace(',','.',$vWebSalePrice).'">'
            ];
        }
        $formFields = [
            [
                'legend' => 'Preise',
                'form-group' => [
                    [
                        'type' => 'table',
                        'tableData' => [
                            'firstColumnWidth' => 10,
                            'tableId' => 'pricesTable',
                            'easyTable' => true,
                            'columns' => ['Farbe','Größe', 'Länge', 'Bestand', 'EAN', 'Preis aus Wawi', 'Reduzierter Preis aus Wawi', 'Web-Preis', 'Reduzierter Web-Preis'],
                            'content' => $content
                        ]
                    ]
                  ],
              ]
        ];
        return $formFields;


        $variations = $article->variations()->get();
        $standardPrice = $article->prices()->where('name','=','standard')->first() ? $article->prices()->where('name','=','standard')->first()->value : '';
        $salePrice = $article->prices()->where('name','=','discount')->first() ? $article->prices()->where('name','=','discount')->first()->value : '';

        $formFields = [
            [
                'legend' => 'Preise',
                'form-group' => [
                    ['type' => 'label', 'text' => 'Basis'],
                    ['type' => 'columns', 'count' => 2],
                    ['type' => 'number', 'step' => '0.01', 'label' => 'Standardpreis', 'value' => str_replace(',','.',$standardPrice), 'name' => 'base[standard]', 'prependText' => '€'],
                    ['type' => 'number', 'step' => '0.01', 'label' => 'Reduzierter Preis', 'value' => str_replace(',','.',$salePrice), 'name' => 'base[discount]', 'prependText' => '€'],
                    ['type' => 'endcolumns']
                  ],
            ]
        ];


        foreach($variations as $variation) {
            $labelText = '';
            $varSize = $variation->attributes()->where('name', '=', 'fee-size')->first();
            $varColor = $variation->attributes()->where('name', '=', 'fee-color')->first();
            if($varSize) {
                $labelText .= 'Größe '.$varSize->value;
            }
            if($varColor) {
                $labelText .= ' | Farbe '.$varColor->value;
            }
            $labelText .= ' | EAN '.str_replace('vstcl-', '',$variation->vstcl_identifier);
            $vStandardPrice = $variation->prices()->where('name','=','standard')->first() ? $variation->prices()->where('name','=','standard')->first()->value : '';
            $vSalePrice = $variation->prices()->where('name','=','discount')->first() ? $variation->prices()->where('name','=','discount')->first()->value : '';
            $formFields[0]['form-group'][] = ['type' => 'label', 'text' => $labelText];
            $formFields[0]['form-group'][] = ['type' => 'columns', 'count' => 2];
            $formFields[0]['form-group'][] = ['type' => 'number', 'step' => '0.01', 'label' => 'Standardpreis', 'value' => str_replace(',','.',$vStandardPrice), 'name' => 'variations['.$variation->id.'][standard]', 'prependText' => '€'];
            $formFields[0]['form-group'][] = ['type' => 'number', 'step' => '0.01', 'label' => 'Reduzierter Preis', 'value' => str_replace(',','.',$vSalePrice), 'name' => 'variations['.$variation->id.'][discount]', 'prependText' => '€'];
            $formFields[0]['form-group'][] = ['type' => 'endcolumns'];

        }

        return $formFields;
    }


    protected function categoriesConfigFormArray(Article $article) {
        $formFields = [
            [
                'legend' => 'Kategorien Onlineshop',
                'form-group' => [
                    [
                        'title' => '',
                        'type' => 'treeview',
                        'id' => 'categoryTree',
                        'options' => []
                    ]
                  ],
              ]
        ];
        $categories = Category::where('fk_wawi_id','=',null)->get();
        foreach($categories as $category) {
            $articleInCategory = $category->articles()->where('article_id', '=', $article->id)->first();
            $formFields[0]['form-group'][0]['options'][] = [
                'id' => 'category_input_'.$category->id,
                'name' => 'category['.$category->id.']',
                'value' => ($articleInCategory) ? true : false
            ] ;
        }
        return $formFields;
    }

    protected function inventoryConfigFormArray(Article $article) {
        $formFields = [
            [
                'legend' => 'Lager und Bestand',
                'form-group' => [
                    ['type' => 'number', 'step' => '1', 'label' => 'Minimale Stückzahl auf Lager', 'value' => $article->min_stock, 'name' => 'min_stock', 'prependText' => 'Einheiten'],
                  ],
              ]
        ];
        return $formFields;
    }

    protected function shippingConfigFormArray(Article $article) {
        $formFields = [];
        $shipments = $article->shipments();
        foreach($shipments->get() as $shipment) {
            $formFields[] = [
                'legend' => $shipment->description ?? '',
                'form-group' => [
                        [
                            'type' => 'number',
                            'label' => 'Preis',
                            'step' => '0.01',
                            'name' => 'shipment['.$shipment->id.'][price]',
                            'prependText' => '€',
                            'value' => $shipment->price ?? ''
                        ],
                        [
                            'type' => 'text',
                            'label' => 'Lieferzeit Text',
                            'name' => 'shipment['.$shipment->id.'][time]',
                            'value' => $shipment->time ?? ''
                        ],
                        [
                            'type' => 'text',
                            'label' => 'Beschreibung',
                            'name' => 'shipment['.$shipment->id.'][description]',
                            'value' => $shipment->description ?? ''
                        ]
                ]
            ];

        }
        $formFields[(count($formFields) > 0) ? (count($formFields) - 1) : 0]['form-group'][] = [
            'type' => 'footer',
            'actions' => [['icon' => 'fa fa-plus-circle', 'data-class' => 'article'.$article->id.'_attribute', 'data-name' => 'shipment[new]', 'text' => 'Versandart hinzufügen', 'class' => 'add_attribute_btn', 'id' => 'base_attr_add']]
        ];

        return $formFields;
    }

    protected function marketingConfigFormArray(Article $article) {
        $markedAsNew = $article->marketing()->where('name', '=', 'mark_as_new')->first();
        $activateDiscount = $article->marketing()->where('name', '=', 'activate_discount')->first();


        $markesAsNewDateRange = ($markedAsNew && $markedAsNew->from && $markedAsNew->until && $markedAsNew->active)
            ? date('d.m.Y', strtotime($markedAsNew->from)).' to '.date('d.m.Y', strtotime($markedAsNew->until))
            : '';


        $activatDiscontDateRange = ($activateDiscount && $activateDiscount->from && $activateDiscount->until && $activateDiscount->active)
        ? date('d.m.Y', strtotime($activateDiscount->from)).' to '.date('d.m.Y', strtotime($activateDiscount->until))
        : '';

        $formFields = [
            [
                'legend' => 'Marketing',
                'form-group' => [
                    ['type' => 'label', 'text' => 'Einstellungen'],
                    ['type' => 'columns', 'count' => 2],
                    [
                        'label' => ($markedAsNew && $markedAsNew->from && $markedAsNew->until && $markedAsNew->active)
                        ? 'Aktiv vom '.date('d.m.Y', strtotime($markedAsNew->from)).' bis '.date('d.m.Y', strtotime($markedAsNew->until))
                        : '',
                        'type' => 'checkbox',
                        'options' => [
                            [
                                'label' => 'Artikel als "neu" markieren',
                                'name' => 'mark_as_new',
                                'checked' => ($markedAsNew != null && $markedAsNew->active == 1)
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'id' => 'mark_as_new_range_field',
                        'name' => 'mark_as_new_range',
                        'value' => $markesAsNewDateRange,
                        'cssClasses' => 'flatpickr',
                        'label' => 'Zeitraum',
                        'attributes' => 'data-toggle=flatpickr data-mode=range data-date-format=d.m.Y readonly=readonly '
                    ],
                    ['type' => 'endcolumns'],
                    ['type' => 'divider'],
                    ['type' => 'columns', 'count' => 2],
                    [
                        'label' => ($activateDiscount && $activateDiscount->from && $activateDiscount->until && $activateDiscount->active)
                        ? 'Aktiv vom '.date('d.m.Y', strtotime($activateDiscount->from)).' bis '.date('d.m.Y', strtotime($activateDiscount->until))
                        : '',
                        'type' => 'checkbox',
                        'options' => [
                            [
                                'label' => 'Rabatte aktivieren',
                                'name' => 'activate_discount',
                                'checked' => ($activateDiscount != null && $activateDiscount->active == 1)
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'id' => 'activate_discount_range_field',
                        'name' => 'activate_discount_range',
                        'value' => $activatDiscontDateRange,
                        'label' => 'Zeitraum',
                        'attributes' => 'data-toggle=flatpickr data-mode=range data-date-format=d.m.Y readonly=readonly '
                    ],
                    ['type' => 'endcolumns'],
                    ['type' => 'divider'],
                    ['type' => 'label', 'text' => 'Upsell/Crosssell'],
                    ['type' => 'columns', 'count' => 2],
                    [
                        'title' => 'Kategorien',
                        'columnWidth' => 2,
                        'type' => 'treeview',
                        'id' => 'categoryTree',
                        'options' => []
                    ],
                    [
                        'type' => 'table',
                        'tableData' => [
                            'title' => 'Artikel',
                            'columnWidth' => 10,
                            'firstColumnWidth' => 200,
                            'tableId' => 'myTable',
                            'columns' =>[
                                'Artikel',
                                'System-ID',
                                'Angelegt am',
                              ],
                            'search' => ['placeholder' => 'Variation suchen']
                        ]

                    ],
                    ['type' => 'endcolumns']
                  ],
              ]
        ];
        $categories = Category::all();
        foreach($categories as $category) {
            $articleInCategory = $category->articles()->where('article_id', '=', $article->id)->first();
            $formFields[0]['form-group'][12]['options'][] = [
                'id' => 'category_input_'.$category->id,
                'name' => 'category['.$category->id.']',
                'value' => ($articleInCategory) ? true : false
            ];
        }
        return $formFields;
    }

    protected function sparesetsConfigFormArray(Article $article, $part) {

        if(request()->ajax()) {

            $response = datatables()->of(Sparesets::
            with(['articles' => function($query) use ($article) {
                $query->where('fk_article_id','=',$article->id);
            }])
            ->select([
                'sparesets.id', 'sparesets.id', 'sparesets.name', 'sparesets.description', 'sparesets.updated_at', 'sparesets.created_at','sparesets.id'
            ]))
            ->addColumn('status', function(Sparesets $spareset) use ($article) {
                $articleSparesets = $spareset->articles()->where('fk_article_id','=',$article->id)->exists();
                return ($articleSparesets)? "1" : "0";
            })
            ->addColumn('status_cats', function(Sparesets $spareset) use ($article) {
                $ArtCatsArray = $article->categories()->get()->pluck('id')->toArray();
                $haveactiveCatSparesets = 0;
                $CatSparesets = [];
                foreach($ArtCatsArray as $ArtCat)
                {
                    $SparesetsCats = $spareset->categories()->where('fk_category_id','=',$ArtCat)->exists();
                    if($SparesetsCats){$CatSparesets[]= Category::find($ArtCat)->name;$haveactiveCatSparesets++;}
                }
                return ($haveactiveCatSparesets > 0)? $CatSparesets : "0";
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('article_sparesets');

            return response()->json($data);
        }

        // selected Articles (SpareSet zugewiesen)
        $selArticles = Sparesets::with(['articles'])
        ->whereHas('articles', function($query) use ($article)
        { $query->where('fk_article_id','=',$article->id); })
        ->get();

        return view('tenant.modules.article.edit.article_sparesets'
        , [
            'sideNavConfig' => Article::sidenavConfig('artikelverwaltung')
            ,'article' => $article
            ,'selArticles' => $selArticles
            ,'part' => $part
        ]);
    }

    protected function equipmentsetsConfigFormArray(Article $article, $part) {

        if(request()->ajax()) {

            $response = datatables()->of(Equipmentsets::
            with(['articles' => function($query) use ($article) {
                $query->where('fk_article_id','=',$article->id);
            }])
            ->select([
                'equipmentsets.id', 'equipmentsets.id', 'equipmentsets.name', 'equipmentsets.description', 'equipmentsets.updated_at', 'equipmentsets.created_at','equipmentsets.id'
            ]))
            ->addColumn('status', function(Equipmentsets $equipmentset) use ($article) {
                $articleEquipmentsets = $equipmentset->articles()->where('fk_article_id','=',$article->id)->exists();
                return ($articleEquipmentsets)? "1" : "0";
            })
            ->addColumn('status_cats', function(Equipmentsets $equipmentset) use ($article) {
                $ArtCatsArray = $article->categories()->get()->pluck('id')->toArray();
                $haveactiveCatEquipmentsets = 0;
                $CatEquipmentsets = [];
                foreach($ArtCatsArray as $ArtCat)
                {
                    $EquipmentsetsCats = $equipmentset->categories()->where('fk_category_id','=',$ArtCat)->exists();
                    if($EquipmentsetsCats){$CatEquipmentsets[]= Category::find($ArtCat)->name;$haveactiveCatEquipmentsets++;}
                }
                return ($haveactiveCatEquipmentsets > 0)? $CatEquipmentsets : "0";
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('article_equipmentsets');

            return response()->json($data);
        }

        // selected Articles (SpareSet zugewiesen)
        $selArticles = Equipmentsets::with(['articles'])
        ->whereHas('articles', function($query) use ($article)
        { $query->where('fk_article_id','=',$article->id); })
        ->get();

        return view('tenant.modules.article.edit.article_equipmentsets'
        , [
            'sideNavConfig' => Article::sidenavConfig('artikelverwaltung')
            ,'article' => $article
            ,'selArticles' => $selArticles
            ,'part' => $part
        ]);
    }

    protected function processedSummernoteforDB($detail, Request $request, Article $article) {
        $dom = new \domdocument();
        libxml_use_internal_errors(true);
        if(empty($detail)){$detail = "&nbsp;";}
		$dom->loadHtml(mb_convert_encoding($detail, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$images = $dom->getelementsbytagname('img');

        //loop over img elements, decode their base64 src and save them to public folder,
        //and then replace base64 src with stored image URL.
		foreach($images as $k => $img){
			$data = $img->getattribute('src');
            if(strpos($data, 'base64') == false){
                continue;
            }
			list($type, $data) = explode(';', $data);
			list(, $data)      = explode(',', $data);

            $data = base64_decode($data);

            $name = $article->id.'_desc_'.$k;
            $folder = $request->attributes->get('identifier').'/img/products/';
            $filePath = $folder.$name. '.png';
            //dd($filePath);
            Storage::disk('public')->put($filePath, $data);

			$img->removeattribute('src');
			$img->setattribute('src', '/storage/'.$filePath);
		}

		return $dom->savehtml();
    }

    protected function updateGeneral(Request $request, Article $article)
    {
        $thisType = "";
        if(isset($request->type_main) && $request->type_main){ $thisType .= "article|"; }
        if(isset($request->type_spare) && $request->type_spare){ $thisType .= "spare|"; }
        if(isset($request->type_equipment) && $request->type_equipment){ $thisType .= "equipment|"; }
        $article->update([
            'active' => (isset($request->active))
            ,'type' => $thisType
        ]);
    }

    protected function updateArticleImages(Request $request, Article $article)
    {
        $Tenant_type = config()->get('tenant.tenant_type');
        $imgAttrs = $request->img;
        $firstColor = false;
        $checkAttrs = ['is_preview','is_preview_2', 'is_base', 'sw_preview', 'is_pikto'];
        if(is_array($imgAttrs))
        {

            foreach($imgAttrs as $imgAttrKey => $imgAttrVal)
            {
                $articleImg = Article_Image::find($imgAttrKey);
                if($articleImg)
                {
                    //Set all variation relations inactive
                    $avarImgs = Article_Variation_Image::where('location', '=', $articleImg->location)->get();
                    foreach($avarImgs as $avarImg) { $avarImg->delete(); }

                    foreach($checkAttrs as $attr)
                    {
                        $count=Article_Image_Attribute::where('fk_article_image_id','=',$imgAttrKey)
                                ->where('name','=',$attr)
                                ->count('*');
                        $article_image_attr=Article_Image_Attribute::updateOrCreate(
                            [ 'fk_article_image_id' => $imgAttrKey, 'name' => $attr ],
                            [ 'value' => 'off']
                        );
                        if($count){
                            VSShopController::update_article_image_attr_job($article_image_attr);
                        }
                        else{
                            VSShopController::create_article_image_attr_job($article_image_attr);
                        }

                    }
                    if(is_array($imgAttrVal)) {
                        foreach($imgAttrVal as $attrKey => $attrVal) {
                            if($attrKey == 'for_colors') {
                                if(is_array($attrVal)) {
                                    foreach($attrVal as $varColor) {  if($firstColor==false){$firstColor = $varColor;}
                                        $variations = $article->variations()->whereHas('attributes', function($query) use($varColor) {
                                            $query->whereIn('name', ['fee-info1', 'fee-color'])->where('value', '=', $varColor);
                                        })->get();
                                        foreach($variations as $variation) {
                                            $count=Article_Variation_Image::where('fk_article_variation_id','=',$variation->id)
                                                    ->where('location' ,'=', $articleImg->location)
                                                    ->count('*');
                                            $articleVarimage = Article_Variation_Image::updateOrCreate(
                                                [
                                                    'fk_article_variation_id' => $variation->id,
                                                    'location' => $articleImg->location
                                                ],
                                                [ 'loaded' => 1 ]
                                            );
                                            if($count){
                                                VSShopController::update_article_variation_image_job($articleVarimage);
                                            }
                                            else
                                            {
                                                VSShopController::create_article_variation_image_job($articleVarimage);
                                            }
                                            $versions = [ '200', '512', '1024' ];
                                            foreach($versions as $version) {
                                                $count=Article_Variation_Image_Attribute::where('fk_article_variation_image_id' ,'=', $articleVarimage->id)
                                                        ->where('name','=', 'imgType')
                                                        ->where('value' ,'=' , $version)
                                                        ->count('*');

                                                $article_variation_image_attribute=
                                                Article_Variation_Image_Attribute::updateOrCreate(
                                                    ['fk_article_variation_image_id' => $articleVarimage->id, 'name' => 'imgType','value' => $version], [] );
                                                if($count){
                                                    VSShopController::update_article_variation_image_attr_job($article_variation_image_attribute);
                                                }
                                                else{
                                                    VSShopController::create_article_variation_image_attr_job($article_variation_image_attribute);
                                                }
                                            }
                                            foreach($imgAttrVal as $varattrKey => $varattrVal)
                                            {
                                                if($varattrKey != 'for_colors')
                                                {
                                                    $count=Article_Variation_Image_Attribute::where('fk_article_variation_image_id' ,'=', $articleVarimage->id)
                                                            ->where('name' ,'=', $varattrKey)
                                                            ->count('*');
                                                    $article_variation_image_attribute=
                                                    Article_Variation_Image_Attribute::updateOrCreate(
                                                        ['fk_article_variation_image_id' => $articleVarimage->id, 'name' => $varattrKey]
                                                        ,['value' => ($varattrVal == null) ? '' : $varattrVal ]);
                                                    if($count){
                                                        VSShopController::update_article_variation_image_attr_job($article_variation_image_attribute);
                                                    }
                                                    else{
                                                        VSShopController::create_article_variation_image_attr_job($article_variation_image_attribute);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            else {
                                $count=Article_Image_Attribute::where('fk_article_image_id' ,'=', $imgAttrKey)
                                    ->where('name' ,'=', $attrKey)
                                    ->count('*');
                                $article_image_attribute=
                                Article_Image_Attribute::updateOrCreate(
                                    [ 'fk_article_image_id' => $imgAttrKey, 'name' => $attrKey]
                                    , [ 'value' => ($attrVal == null) ? '' : $attrVal ] );
                                if($count){
                                    VSShopController::update_article_image_attr_job($article_image_attribute);
                                }
                                else{
                                    VSShopController::create_article_image_attr_job($article_image_attribute);
                                }
                            }
                        }
                    }
                }

            }
        }

        if($Tenant_type=='vstcl-industry')
        {
            $articleImgs = $article->images()->get();
            $variations = $article->variations()->get();
            foreach($variations as $variation)
            {   // del old Var Images
                $varImgs = $variation->images()->get();
                foreach($varImgs as $varImg) {
                    $imgAttrs = $varImg->attributes()->get();
                    foreach($imgAttrs as $imgAttr) {$imgAttr->delete();}
                    $varImg->delete();
                }
                // recreate var images
                foreach($articleImgs as $articleImg)
                {
                    $count=Article_Variation_Image::where('fk_article_variation_id' ,'=', $variation->id)
                        ->where('location' ,'=', $articleImg->location)
                        ->count('*');
                    $articleVarimage = Article_Variation_Image::updateOrCreate(
                        [   'fk_article_variation_id' => $variation->id,
                            'location' => $articleImg->location
                        ],[ 'loaded' => 1 ]
                    );
                    if($count){
                        VSShopController::update_article_variation_image_job($articleVarimage);
                    }
                    else{
                        VSShopController::create_article_variation_image_job($articleVarimage);
                    }

                    $articleImgAttrs = $articleImg->attributes()->get();
                    foreach($articleImgAttrs as $articleImgAttr)
                    {
                        $count=Article_Variation_Image_Attribute::where('fk_article_variation_image_id' ,'=', $articleVarimage->id)
                            ->where('name' ,'=', $articleImgAttr->name)
                            ->count('*');
                        $article_variation_image_attribute=
                        Article_Variation_Image_Attribute::updateOrCreate(
                        [   'fk_article_variation_image_id' => $articleVarimage->id
                            , 'name' => $articleImgAttr->name
                        ],['value' => $articleImgAttr->value]);
                        if($count){
                            VSShopController::update_article_variation_image_attr_job($article_variation_image_attribute);
                        }
                        else{
                            VSShopController::create_article_variation_image_attr_job($article_variation_image_attribute);
                        }
                    }
                }
            }
        }

        $images = $request->images;
        if(is_array($images)) {

            if(count($images)<=0)
            {   //clean Images
                // TO DO WENN KEINE BILDER VORHANDEN ALLES Müll löschen
            }

            foreach($images as $image) {
                // Image name
                $name = $article->id.'_base_';
                $folder = '/'.$request->attributes->get('identifier').'/img/products/';
                $filePath = $folder.$name;
                $versions = [
                    '200' => [ 'height' => 200 ],
                    '512' => [ 'height' => 512 ],
                    '1024' => [ 'height' => 1024 ]
                ];


                $articleImage = Article_Image::create([
                    'fk_article_id' => $article->id,
                ]);

                $articleImage->location = $filePath.$articleImage->id. '.' . $image->getClientOriginalExtension();
                $articleImage->update();

                $this->uploadOne($image, $folder, 'public', $name.$articleImage->id);

                VSShopController::create_article_image_job($articleImage);
                /*if($firstColor!==false)
                {
                    $variations = $article->variations()->whereHas('attributes', function($query) use($firstColor)
                    { $query->whereIn('name', ['fee-info1', 'fee-color'])->where('value', '=', $firstColor); })->get();
                    foreach($variations as $variation) {
                        $articleVarimage = Article_Variation_Image::updateOrCreate(
                            [
                                'fk_article_variation_id' => $variation->id,
                                'location' => $articleImage->location
                            ],
                            [
                                'loaded' => 1
                            ]
                        );
                        $varversions = [ '200', '512', '1024' ];
                        foreach($varversions as $varversion) {
                            Article_Variation_Image_Attribute::updateOrCreate(
                                ['fk_article_variation_image_id' => $articleVarimage->id, 'name' => 'imgType','value' => $varversion],
                                []
                            );
                        }
                    }
                }*/

                foreach($versions as $versionKey => $versionVal) {

                    Storage::disk('public')->makeDirectory($request->attributes->get('identifier').'/img/products/'.$versionKey);

                    $img = Image::make('storage'.$articleImage->location)->resize(null, $versionVal['height'], function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->save('storage'.$folder.$versionKey.'/'.$name.$articleImage->id.'.png');

                    $count=Article_Image_Attribute::where('fk_article_image_id' ,'=', $articleImage->id)
                            ->where('name' ,'=', 'imgType')
                            ->where('value' ,'=', $versionKey)
                            ->count('*');
                    $article_image_attribute=
                    Article_Image_Attribute::updateOrCreate(
                        ['fk_article_image_id' => $articleImage->id, 'name' => 'imgType','value' => $versionKey],
                        []
                    );
                    if($count){
                        VSShopController::update_article_image_attr_job($article_image_attribute);
                    }
                    else{
                        VSShopController::create_article_image_attr_job($article_image_attribute);
                    }
                }
            }
        }
    }

    protected function updateEigenschaften(Request $request, Article $article)
    {   // alte Artikel Eigenschaften löschen
        $article->eigenschaften()->delete(); // alle var Eigenschaften auf einmal löschen
        if(isset($request->eigenschaftValues) && is_array($request->eigenschaftValues))
        {
            foreach($request->eigenschaftValues as $EigenschaftIDValue)
            {
                $ThisDataIDs = explode( ',', $EigenschaftIDValue );
                foreach($ThisDataIDs as $ThisID)
                {   if($ThisID && $ThisID != "" && $ThisID != null)
                    {
                        $count=Article_Eigenschaften_Articles::where('fk_article_id','=',$article->id)
                            ->where('fk_eigenschaft_data_id','=', $ThisID)
                            ->where('active','=',1)
                            ->count('*');
                        $article_eigenschaften_articles=
                        Article_Eigenschaften_Articles::updateOrCreate(
                        [ 'fk_article_id' => $article->id, 'fk_eigenschaft_data_id'=> $ThisID, 'active'=>1 ]);

                    }

                }
            }
        }
        $providerC = new ProviderController();
        $providerC->updatedContentByType($article, 'article');
    }
    protected function updateAttributes(Request $request, Article $article)
    {   $providerC = new ProviderController();
        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        {
            foreach($request->article as $articlePOST)
            {
                if(isset($articlePOST['attribute_group']))
                {
                    // alte Attributierung löschen
                    $old_aas = $article->attributes()->get();
                    foreach($old_aas as $old_aa){$providerC->deletedContentByType($old_aa, 'article_attr');$old_aa->delete();}
                    // bei den Variationen ebenfalls
                    $variations = $article->variations()->get();
                    foreach($variations as $variation)
                    {   $old_varas = $variation->attributes()->get();
                        foreach($old_varas as $old_vara){$providerC->deletedContentByType($old_vara, 'article_variation_attr');$old_vara->delete();}
                    }

                    foreach($articlePOST['attribute_group'] as $GroupID => $GroupValue)
                    {
                        $thisGroup =  Attribute_Group::where('id', '=', $GroupID)->first();
                        if($thisGroup)
                        {
                            $count=Article_Attribute::where('fk_article_id','=', $article->id)
                                    ->where('fk_attributegroup_id','=', $GroupID)
                                    ->count('*');
                            $aa = Article_Attribute::updateOrCreate(
                                [  'fk_article_id' => $article->id,'fk_attributegroup_id'=> $GroupID],
                                [   'value' => (($GroupValue)?$GroupValue:""),'name' => $thisGroup->name]);
                            if($count){
                                VSShopController::update_article_attr_job($aa);
                            }
                            else{
                                VSShopController::create_article_attr_job($aa);
                            }
                            $providerC->updatedContentByType($aa, 'article_attr',true);
                            // Variationen ebenfalls ändern
                            foreach($variations as $variation)
                            {
                                $count=Article_Variation_Attribute::where('fk_article_variation_id' ,'=', $variation->id)
                                        ->where('fk_attributegroup_id','=', $GroupID)
                                        ->count('*');
                                $ava = Article_Variation_Attribute::updateOrCreate(
                                    [    'fk_article_variation_id' => $variation->id,'fk_attributegroup_id'=> $GroupID],
                                    [   'value' => (($GroupValue)?$GroupValue:""),'name' => $thisGroup->name]);
                                if($count){
                                    VSShopController::update_article_variation_attr_job($ava);
                                }
                                else{
                                    VSShopController::create_article_variation_attr_job($ava);
                                }
                                $providerC->updatedContentByType($ava, 'article_variation_attr',true);
                            }
                        }

                    }
                }
            }



        }else
        {
            $articles = $request->article;
            $variations = $request->variation;
            if(is_array($articles)) {
                foreach($articles as $articleId => $article) {
                    if(is_array($article['attributes'])) {
                        foreach($article['attributes'] as $articleAttrId => $articleAttrVal) {
                            if($articleAttrId == 'new') {
                                if(is_array($articleAttrVal)) {
                                    foreach($articleAttrVal as $newArticleAttr) {
                                        $count=Article_Attribute::where('fk_article_id' ,'=', $articleId)
                                            ->where('name' ,'=', $newArticleAttr['name'])
                                            ->count('*');
                                        $aa = Article_Attribute::updateOrCreate(
                                        ['fk_article_id' => $articleId,'name' => $newArticleAttr['name']],
                                        ['value' => $newArticleAttr['value']]);
                                        $providerC->updatedContentByType($aa, 'article_attr',true);

                                        if($count){
                                            VSShopController::update_article_attr_job($aa);
                                        }
                                        else{
                                            VSShopController::create_article_attr_job($aa);
                                        }
                                    }
                                }
                            }
                            else {
                                $count=Article_Attribute::where('id' ,'=', $articleAttrId)
                                    ->where('fk_article_id' ,'=', $articleId)
                                    ->count('*');
                                $aa = Article_Attribute::updateOrCreate(
                                ['id' => $articleAttrId,'fk_article_id' => $articleId],
                                ['value' => $articleAttrVal]);
                                $providerC->updatedContentByType($aa, 'article_attr',true);

                                if($count){
                                    VSShopController::update_article_attr_job($aa);
                                }
                                else{
                                    VSShopController::create_article_attr_job($aa);
                                }
                            }
                        }
                    }
                }
            }
            if(is_array($variations)) {
                foreach($variations as $variationId => $variationVal) {
                    if(is_array($variationVal['attributes'])) {
                        foreach($variationVal['attributes'] as $vAttrID => $vAttrVal) {
                            if($vAttrID == 'new') {
                                if(is_array($vAttrVal)) {
                                    foreach($vAttrVal as $newVAttr) {
                                        $count=Article_Variation_Attribute::where('fk_article_variation_id' ,'=', $variationId)
                                                ->where('name' ,'=', $newVAttr['name'])
                                                ->count('*');
                                        $ava = Article_Variation_Attribute::updateOrCreate(
                                        ['fk_article_variation_id' => $variationId,'name' => $newVAttr['name']],
                                        ['value' => $newVAttr['value']]);
                                        $providerC->updatedContentByType($ava, 'article_variation_attr',true);
                                        if($count){
                                            VSShopController::update_article_variation_attr_job($ava);
                                        }
                                        else{
                                            VSShopController::create_article_variation_attr_job($ava);
                                        }
                                    }
                                }
                            }
                            else {
                                $count=Article_Variation_Attribute::where('id' ,'=', $vAttrID)
                                        ->where('fk_article_variation_id' ,'=', $variationId)
                                        ->count('*');
                                $ava = Article_Variation_Attribute::updateOrCreate(
                                ['id' => $vAttrID,'fk_article_variation_id' => $variationId],
                                ['value' => $vAttrVal]);
                                $providerC->updatedContentByType($ava, 'article_variation_attr',true);
                                if($count){
                                    VSShopController::update_article_variation_attr_job($ava);
                                }
                                else{
                                    VSShopController::create_article_variation_attr_job($ava);
                                }
                            }

                        }
                    }
                }
            }
        }
    }

    public function updateColors(Request $request, Article $article) {
        if(!$request->exists('colors')) {
            return;
        }
        $colors = $request->colors;

        foreach($colors as $varId => $color) {
            $count=Article_Variation_Attribute::where('fk_article_variation_id' ,'=', $varId)
                    ->where( 'name' ,'=','own-color')
                    ->count('*');
            $article_variation_attribute=
            Article_Variation_Attribute::updateOrCreate(
                [
                    'fk_article_variation_id' => $varId,
                    'name' => 'own-color'
                ],
                [
                    'value' => $color['own-color'] ?? ''
                ]
            );
            if($count){
                VSShopController::update_article_variation_attr_job($article_variation_attribute);
            }
            else{
                VSShopController::create_article_variation_attr_job($article_variation_attribute);
            }
        }
    }

    public function updateSizes(Request $request, Article $article)
    {   if(!$request->exists('feeformLaenge')) { return; }
        $feeformLaengen = $request->feeformLaenge;

        foreach($feeformLaengen as $varId => $feeformLaenge) {
            $count=Article_Variation_Attribute::where('fk_article_variation_id' ,'=', $varId)->where( 'name' ,'=','fee-formLaenge')->count('*');
            $article_variation_attribute = Article_Variation_Attribute::updateOrCreate(
            [ 'fk_article_variation_id' => $varId, 'name' => 'fee-formLaenge' ],
            [ 'value' => $feeformLaenge ?? '' ]);
            if($count){ VSShopController::update_article_variation_attr_job($article_variation_attribute); }
            else{ VSShopController::create_article_variation_attr_job($article_variation_attribute); }
        }
    }

    public function updateVariations(Request $request, Article $article) {
        $fields = ['active', 'min_stock'];
        $variations = $article->variations()->get();
        foreach($variations as $vari) {
            $vari->active = false;
            $vari->save();
        }
        foreach($request->all() as $key => $value) {
            if(!in_array($key, $fields)) {
                continue;
            }
            if(is_array($value)) {
                foreach($value as $var_id => $var_val) {
                    $variation = Article_Variation::find($var_id);
                    if($var_val == 'on') {
                        $var_val = true;
                    }
                    $variation->{$key} = $var_val;
                    $variation->save();

                    VSShopController::update_article_variation_job($variation);
                }
            }
        }
    }

    public function updatePrices(Request $request, Article $article)
    {
        if(!$request->exists('web_price')) { return;}

        $providerC = new ProviderController();

        $web_prices = $request->web_price;

        foreach($web_prices as $varId => $webprice) {
            $count=Article_Variation_Price::where( 'fk_article_variation_id' ,'=', $varId)
                    ->where('name' ,'=', 'web_standard')
                    ->count('*');
            $varPrice = Article_Variation_Price::updateOrCreate(
                [
                    'fk_article_variation_id' => $varId,
                    'name' => 'web_standard'
                ],
                ['value' => ($webprice['standard'] == null) ? '' : str_replace('.',',', $webprice['standard'])]
            );
            $providerC->updatedContentByType($varPrice, 'article_variation_price');
            if($count){
                VSShopController::update_article_variation_price_job($varPrice);
            }
            else
            {
                VSShopController::create_article_variation_price_job($varPrice);
            }

            $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $varId)
                    ->where('name' ,'=', 'web_discount')
                    ->count('*');
            $varPrice = Article_Variation_Price::updateOrCreate(
                [
                    'fk_article_variation_id' => $varId,
                    'name' => 'web_discount'
                ],
                ['value' => ($webprice['discount'] == null) ? '' : str_replace('.',',',$webprice['discount'])]
            );
            $providerC->updatedContentByType($varPrice, 'article_variation_price');
            if($count){
                VSShopController::update_article_variation_price_job($varPrice);
            }
            else
            {
                VSShopController::create_article_variation_price_job($varPrice);
            }
        }


    }

    public function updateCategories(Request $request, Article $article) {
        $categories = $request->category;
        $relatedCats = [];
        if(is_array($categories)) {
            foreach($categories as $catId => $catVal) {
                if($catVal == "1" || $catVal == "true") {
                    $relatedCats[] = $catId;
                }
            }
        }
        $article->categories()->sync($relatedCats);
        VSShopController::update_article_job($article);
    }

    public function updateInventory(Request $request, Article $article) {
        $article->update(['min_stock' => intval($request->min_stock)]);
        VSShopController::update_article_job($article);
    }

    public function updateMarketing(Request $request, Article $article) {
        $marketingOptions = ['mark_as_new', 'activate_discount'];
        $upSellOptions = ['category'];
        $marketings = Article_Marketing::where('fk_article_id','=',$article->id)->get();
        foreach($marketings as $marketing) {
            $article_marketing=Article_Marketing::find($marketing->id);
            $article_marketing->update(['active' => 0]);
            VSShopController::update_article_marketing_job($article_marketing);
        }

        foreach($request->all() as $key => $value)
        {
            if(in_array($key, $marketingOptions))
            {   $from = ''; $until = ''; $jumpOne=false;
                /*if($request->exists($key.'_range') && $request->{$key.'_range'} != null)
                {   if($request->{$key.'_range'} != "")
                    {   if(strpos($request->{$key.'_range'}, ' to ')  !== false)
                        {
                            $range = explode(" to ",$request->{$key.'_range'});
                            $from = $range[0]; $until = $range[1];
                        } else { $from = date("d.m.Y"); $until = $request->{$key.'_range'}; }
                    }else{$jumpOne=true;}
                }*/
                if($request->exists('activate_discount_range') && $request->activate_discount_range != null)
                {   if($request->activate_discount_range != "")
                    {   if(strpos($request->activate_discount_range, ' to ')  !== false)
                        {
                            $range = explode(" to ",$request->activate_discount_range);
                            $from = $range[0]; $until = $range[1];
                        } else { $from = date("d.m.Y"); $until = $request->activate_discount_range; }
                    }else{$jumpOne=true;}
                }




                if(!$jumpOne)
                {   $count=Article_Marketing::where('fk_article_id' ,'=', $article->id)->where('name' ,'=', $key)->count('*');
                    $article_marketing = Article_Marketing::updateOrCreate(
                    [   'fk_article_id' => $article->id, 'name' => $key ],
                    [   'active' => 1,
                        'from' => ($from != '') ? date('Y-m-d', strtotime($from)) : null,
                        'until' => ($until != '') ? date('Y-m-d', strtotime($until)) : null
                    ]);
                    if($count){ VSShopController::update_article_marketing_job($article_marketing); }
                    else{ VSShopController::create_article_marketing_job($article_marketing); }
                }
            }
            else if(in_array($key, $upSellOptions)) {
                if($key == 'category' && is_array($request->{$key})) {
                    foreach($request->{$key} as $key => $value) {
                        //TODO: Sync Article_Upsell Relations
                    }
                }
            }

        }
    }

    public function updateShipping(Request $request, Article $article) {
        $shipments = $request->shipment;
        if(is_array($shipments)) {
            foreach($shipments as $shipmentId => $shipment) {
                if($shipmentId == 'new') {
                    if(is_array($shipment)) {
                        foreach($shipment as $newShip) {
                            Article_Shipment::updateOrCreate(
                                [
                                    'fk_article_id' => $article->id,
                                    'description' => $newShip['description']
                                ],
                                [
                                    'price' => $newShip['price'],
                                    'time' => $newShip['time']
                                ]
                            );
                        }
                    }
                }
                else {
                    Article_Shipment::updateOrCreate(
                        [
                            'id' => $shipmentId
                        ],
                        [
                            'fk_article_id' => $article->id,
                            'description' => $shipment['description'],
                            'price' => $shipment['price'],
                            'time' => $shipment['time']
                        ]
                    );
                }
            }
        }
    }

    public function getArticle(Request $request, $id) {
        $provider = $request->attributes->get('provider');
        $article = $provider->articles()->with([
                'article' => function($query) {
                    $query->where('active', '=', 1);
                },
                'article.attributes',
                'article.images',
                'article.categories',
                'article.prices',
                'article.marketing',
                'article.shipments'
            ])
            ->where('fk_article_id', '=', $id)
            ->where('active', '=', 1)
            ->get();

        if($article) {
            return response()->json([
                'data' => [
                    'type' => 'articles',
                    'message' => 'Success',
                    'attributes' => $article
                ]
            ], 200);
        }
        else {
            return response()->json([
                'type' => 'articles',
                'message' => 'Not Found'
            ], 404);
        }

    }

    public function sendArticlesToShop(Request $request, $shop_id,$EchologAktiv=false,$account_type='',$file_output=false,$customer='') {
        $shop = Provider::find($shop_id);
        if(!$shop || $shop->url == null || $shop->apikey == null) {
            return false; //redirect()->back()->withError('Fehlerhafte Shopzugangsdaten');
        }
        $Tenant_type = config()->get('tenant.tenant_type');
        $client = new Client();
        $promises = [];
        $count = $shop->articles()->count();
        $limit = 200;
        $limit = 10;
        $offset = 0;
        $failed = false;
        $iterations = round($count / $limit);
        //$categories = Category::with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();
        $categories = Category::with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();
        $customers = [];//Customer::with(['payment_conditions','customer_article_prices','customer_category_vouchers'])->get();


        $folder_path=$customer . config('content-manager.controller.article_controller.send_articles_to_shop.file_output_path');
        $file_name=config('content-manager.controller.article_controller.send_articles_to_shop.file_output');

        Storage::disk("customers")->makeDirectory($folder_path);


        $file_counter=0;
        for($i = 0; $i <= $iterations; $i++) {
            $file_counter++;

            if($account_type=='vstcl-industry')
            {
                $articles = Article::with([
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
                ])->whereHas('provider', function($query) use($shop_id) {
                    $query->where('fk_provider_id', $shop_id);
                })
                ->where('active', '=', 1)
                ->offset($offset)
                ->limit($limit)->get();
            }
            else
            {
                $articles = Article::with([
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
                ])->whereHas('provider', function($query) use($shop_id) {
                    $query->where('fk_provider_id', $shop_id);
                })
                ->where('active', '=', 1)
                ->offset($offset)
                ->limit($limit)->get();
            }

            $offset += $limit;



            /*
            $promises[] = $client->postAsync($shop->url.'/api/v1/init_data?apikey='.$shop->apikey,
            ['body' => json_encode([ 'articles' => $articles, 'categories' => $categories,'customers' => $customers ])
            ])->then(function($response) use (&$EchologAktiv,&$offset) {
                $status = $response->getStatusCode();
                if($status != 200) { if($EchologAktiv){echo "[fail]";}$failed = true;}
                else{if($EchologAktiv){echo " [OK:".$offset."]";} }
            }, function($e) use (&$EchologAktiv,&$shop) { $statusmeldung = "";
                    switch($e->getCode()) {
                        case 401:
                            $statusmeldung = 'Status: '.$e->getCode()." VSShop API Key konnte sich nicht authentifizieren! URL: ".$shop->url.'/api/v1/init_data?apikey='.$shop->apikey;
                        break;
                        case 429:
                        case 500:

                            $statusmeldung = 'Status: '.$e->getCode()." URL: ".$shop->url.'/api/v1/init_data?apikey='.$shop->apikey . '   Message:  ' . $e->getMessage();
                        break;
                        default:
                            $statusmeldung =$e->getMessage();
                        break;
                    }
                if($EchologAktiv){echo "\n[#e-fail:".$statusmeldung."]";}$failed = true;
            })->wait(); self::schedulePromise([$promises[count($promises)-1], 'wait'],false);
            */


            //#########


            $response=null;

            try{
                //var_dump(json_decode(json_encode($articles)) );

                if($file_output && $customer!=''){
                    $data=json_encode( ['body' => [ 'articles' => $articles, 'categories' => $categories,'customers' => $customers ]]);

                    $file_path=Storage::disk("customers")->path($folder_path . "/" . $file_counter . '_' . $file_name);

                    file_put_contents($file_path,$data);

                    continue;
                }


                $response=$client->post($shop->url.'/api/v1/init_data?apikey='.$shop->apikey,
                ['body' => json_encode([ 'articles' => $articles, 'categories' => $categories,'customers' => $customers ])
                ]);

                $status_code=$response->getStatusCode();

                if($status_code != 200)
                {
                    if($EchologAktiv)
                    {
                        echo "[fail]";
                    }
                    $failed = true;
                }
                else
                {
                    if($EchologAktiv)
                    {
                        echo " [OK:".$offset."]";
                    }
                }
            }
            catch(RequestException $e)
            {
                $failed = true;
                $code=$e->getCode();
                switch($code){
                    case 401:
                        $statusmeldung = 'Status: '.$e->getCode()." VSShop API Key konnte sich nicht authentifizieren! URL: ".$shop->url.'/api/v1/init_data?apikey='.$shop->apikey;
                    break;
                    case 429:
                    case 500:

                        $statusmeldung = 'Status: '.$e->getCode()." URL: ".$shop->url.'/api/v1/init_data?apikey='.$shop->apikey . '   Message:  ' . $e->getMessage();
                    break;
                    default:
                        $statusmeldung =$e->getMessage();
                    break;
                }
            }

            //##########

        }
        if($failed) {
            return false;//redirect()->back()->withError('Fehler bei der Datenübertragung');
        } else {
            return true;//redirect()->back()->withSuccess('Daten erfolgreich übertragen');
        }
    }
    public static function schedulePromise($callable, ...$args)
    {
        register_shutdown_function(function ($callable, ...$args) {
            @session_write_close();
            @ignore_user_abort(true);
            call_user_func($callable, ...$args);
        }, $callable, ...$args);
    }

    public function getArticles(Request $request) {
        $provider = $request->attributes->get('provider');
        $articles = $provider->articles()->with([
                'article' => function($query) {
                    $query->where('active', '=', 1);
                },
                'article.attributes',
                'article.images' => function($query) {
                    $query->with('attributes');
                },
                'article.categories' => function($query) {
                    $query->where('fk_wawi_id', '=', null);
                },
                'article.prices',
                'article.marketing',
                'article.shipments',
                'article.variations' => function($query) {
                    $query->with([
                        'prices',
                        'images' => function($query) {
                            $query->with('attributes');
                        },
                        'branches',
                        'attributes'
                        ,'sparesets_spare_article' => function($query) {
                            $query->with('spareset');
                        }
                        ,'sparesets_article' => function($query) {
                            $query->with('spareset');
                        }
                        ,'equipmentsets_equipment_article'  => function($query) {
                            $query->with('equipmentset');
                        }
                        ,'equipmentsets_article' => function($query) {
                            $query->with('equipmentset');
                        }
                    ]);
                }
                ,'sparesets_spare_article'  => function($query) {
                    $query->with('spareset');
                }
                ,'sparesets_article'  => function($query) {
                    $query->with('spareset');
                }
                ,'equipmentsets_equipment_article'  => function($query) {
                    $query->with('equipmentset');
                }
                ,'equipmentsets_article' => function($query) {
                    $query->with('equipmentset');
                }
            ])
            ->where('active', '=', 1)
            ->limit(200)
            ->get();

            $categories = Category::with(['category_vouchers','sparesets_categories','equipmentsets_categories'])->where('fk_wawi_id','=',null)->get();
            $customers = Customer::with(['payment_conditions','customer_article_prices','customer_category_vouchers'])->get();

        return response()->json([
            'articles' => $articles,
            'categories' => $categories,
            'customers' => $customers,
            'data' => [
                'message' => 'Successfully loaded articles',
            ]
        ], 200);
    }

    public function updateStock() {

    }

    public function getStock() {

    }

    public function index_attribute_groups()
    {
        $attributeGroups = Attribute_Group::all();
        $content = [];
        foreach($attributeGroups as $attributeGroup)
        {
            $content[] = [
                '<div class="custom-control custom-checkbox mb-1"><input '.((isset($attributeGroup->active) && $attributeGroup->active)?"checked":"").' data-id="'.$attributeGroup->id.'" type="checkbox" class="custom-control-input" id="attribut['.$attributeGroup->id.'][active]" name="attribut['.$attributeGroup->id.'][active]"><label class="custom-control-label" for="attribut['.$attributeGroup->id.'][active]"></label></div>',
                '<input name="attribut['.$attributeGroup->id.'][name]" value="'.((isset($attributeGroup->name))?$attributeGroup->name:"").'" class="form-control" >',
                '<input name="attribut['.$attributeGroup->id.'][description]" value="'.((isset($attributeGroup->description))?$attributeGroup->description:"").'" class="form-control" >',
                '<input name="attribut['.$attributeGroup->id.'][unit_type]" value="'.((isset($attributeGroup->unit_type))?$attributeGroup->unit_type:"").'" class="form-control" >',
                '<div class="custom-control custom-checkbox mb-1"><input '.((isset($attributeGroup->is_filterable) && $attributeGroup->is_filterable)?"checked":"").' data-id="'.$attributeGroup->id.'" type="checkbox" class="custom-control-input" id="attribut['.$attributeGroup->id.'][is_filterable]" name="attribut['.$attributeGroup->id.'][is_filterable]"><label class="custom-control-label" for="attribut['.$attributeGroup->id.'][is_filterable]"></label></div>',
                '<a data-id="'.$attributeGroup->id.'" class="btn btn-sm btn-secondary save-attribute mr-3" >Speichern</a>'
                .'<a data-id="'.$attributeGroup->id.'" class="btn btn-sm btn-icon btn-secondary text-red delete-attribute" ><i class="far fa-trash-alt"></i></a>'
            ];
        }
        $content[] = [
            '<div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="new_active" name="new_active" value="1"><label class="custom-control-label" for="new_active"></label></div>',
            '<input id="new_name" name="new_name" value="" class="form-control" >',
            '<input id="new_description" name="new_description" value="" class="form-control" >',
            '<input id="new_unit_type" name="new_unit_type" value="" class="form-control" >',
            '<div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="new_is_filterable" name="new_is_filterable" value="1"><label class="custom-control-label" for="new_is_filterable"></label></div>',
            '<a class="btn btn-sm btn-secondary new-attribute mr-3" >Attribut anlegen</a>'
        ];
        return view('tenant.modules.article.index.attributes', ['content'=>$content,'sideNavConfig' => Article::sidenavConfig('attributverwaltung')]);
    }
    public function create_attribute_groups(Request $request)
    {   $attributeGroup = false;
        $data = $request->all();
        $name = $data['name'];
        $description = $data['description'];
        $unit_type = $data['unit_type'];
        $is_filterable = $data['is_filterable'];
        $active = $data['active'];


        $count=Attribute_Group::where('name' ,'=', ((empty($name))? "" : $name))->count('*');
        $attributeGroup = Attribute_Group::updateOrCreate(
        [ 'name' => ((empty($name))? "" : $name)
        , 'description' => ((empty($description))? "" : $description)
        , 'unit_type' => ((empty($unit_type))? "" : $unit_type)
        , 'is_filterable' => (($is_filterable)? 1 : 0)
        , 'active' => (($active)? 1 : 0) ] );
        if($count){
            VSShopController::update_attribute_group_job($attributeGroup);
        }
        else{
            VSShopController::create_attribute_group_job($attributeGroup);
        }

        if($attributeGroup){
            $providerC = new ProviderController();
            $providerC->createdContentByType($attributeGroup, 'attribute_group');
            return json_encode(['success'=>'Erfolg!']);
        }
        else{return json_encode(['error'=>'Fehler!']);}
    }
    public function update_attribute_groups(Request $request)
    {
        $attributeGroup = false;
        $data = $request->all();
        $ID = $data['id'];

        $name = $data['name'];
        $description = $data['description'];
        $unit_type = $data['unit_type'];
        $is_filterable = $data['is_filterable'];
        $active = $data['active'];

        $count=0;
        if($ID != "" && $ID != false)
        {
            $count= Attribute_Group::where('id' ,'=', $ID)->count('*');
            $attributeGroup = Attribute_Group::updateOrCreate(
                ['id' => $ID],
                [ 'name' => ((empty($name))? "" : $name)
                , 'description' => ((empty($description))? "" : $description)
                , 'unit_type' => ((empty($unit_type))? "" : $unit_type)
                , 'is_filterable' => (($is_filterable)? 1 : 0)
                , 'active' => (($active)? 1 : 0) ] );
        }
        if($count){
            VSShopController::update_attribute_group_job($attributeGroup);
        }
        else{
            VSShopController::create_attribute_group_job($attributeGroup);
        }
        if($attributeGroup){
            $providerC = new ProviderController();
            $providerC->updatedContentByType($attributeGroup, 'attribute_group');
            return json_encode(['success'=>'Erfolg!']);
        }
        else{return json_encode(['error'=>'Fehler!']);}
    }

    public function delete_attribute_groups(Request $request)
    {
        $attributeGroup = false;
        $data = $request->all();
        $ID = $data['id'];

        if($ID != "" && $ID != false)
        {
            $attributeGroup = Attribute_Group::where('id', '=', $ID)->first();
            if($attributeGroup)
            { //trenne Sets von der Gruppe
                $thisSets = $attributeGroup->sets()->get();
                foreach($thisSets as $thisSet){$thisSet->groups()->detach($ID);}
                $thisArtAttrs = $attributeGroup->article_attributes()->get();
                foreach($thisArtAttrs as $thisArtAttr){$thisArtAttr->fk_attributegroup_id=null;$thisArtAttr->save();}
                $thisArtVarAttrs = $attributeGroup->variation_attributes()->get();
                foreach($thisArtVarAttrs as $thisArtVarAttr){$thisArtVarAttr->fk_attributegroup_id=null;$thisArtVarAttr->save();}
                $attributeGroup->delete();
            }
        }

        if($attributeGroup){
            VSShopController::delete_attribute_group_job($attributeGroup);
        }
        if($attributeGroup){
            $providerC = new ProviderController();
            $providerC->deletedContentByType($attributeGroup, 'attribute_group');
            return json_encode(['success'=>'Erfolg!']);
        }
        else{return json_encode(['error'=>'Fehler!']);}
    }


    public function indexExtraEAN() {
        return view('tenant.modules.article.index.ean', ['sideNavConfig' => Article::sidenavConfig('zusatz-ean')]);
    }

    public function saveExtraEAN(Request $request) {
        $eans = $request->ean;
        $submittedCount = 0;
        $notFoundCount = 0;
        $notFoundEANs = [];
        $savedEANs = [];
        $savedCount = 0;

        foreach($eans as $ean) {
            if($ean['vstcl'] == null || $ean['zusatz'] == null) {
                continue;
            }
            $submittedCount++;
            $variation = Article_Variation::where('vstcl_identifier','=', 'vstcl-'.$ean['vstcl'])->first();
            if(!$variation) {
                $notFoundEANs[] = $ean['vstcl'];
                $notFoundCount++;
                continue;
            }
            $variation->extra_ean = $ean['zusatz'];
            $variation->save();
            VSShopController::update_article_variation_job($variation);
            $savedEANs[] = $ean['vstcl'];
            $savedCount++;
        }
        $eanString = implode(' ', array_values($notFoundEANs));
        Log::info(config()->get('tenant.identifier').' - Zusatz EAN Upload: Insgesamt: '.$submittedCount.' ('.$savedCount.' saved. '.$notFoundCount.' not found.)', ['saved' => $savedEANs, 'failed' => $notFoundEANs]);
        return redirect()->back()->withSuccess('Erfolgreich gespeichert! Zusammenfassung: '.$submittedCount.' übertragen. '.$savedCount.' gespeichert. '.$notFoundCount.' Visticle EANs nicht gefunden: '.$eanString);
    }

    public function destroyExtraEAN(Request $request, $variation_id) {
        $variation = Article_Variation::find($variation_id);
        $variation->extra_ean = null;
        $variation->save();
        VSShopController::update_article_variation_job($variation);
        return redirect()->back();
    }

    public function update_sets(Request $request, $id, $part, $set_id)
    {
        switch($part)
        {
            case 'sparesets':
                return $this->update_sparesets($id, $set_id);
            break;
            case 'equipmentsets':
                return $this->update_equipmentsets($id, $set_id);
            break;
        }
    }

    public function update_sparesets($article_id, $set_id)
    {
        if(request()->ajax())
        {
            $count=Sparesets_Articles::where('fk_article_id' ,'=', $article_id)
                    ->where('fk_spareset_id' ,'=', $set_id)
                    ->count('*');
            $response = Sparesets_Articles::updateOrCreate(
                [ 'fk_article_id' => $article_id, 'fk_spareset_id' => $set_id ]
            );
            if($count){
                VSShopController::update_spareset_spare_article_job($response);
            }
            else{
                VSShopController::create_spareset_spare_article_job($response);
            }
            $response->status = 1;
            return response()->json($response);
        }
    }

    public function update_equipmentsets($article_id, $set_id)
    {
        if(request()->ajax())
        {
            $count=Equipmentsets_Articles::where('fk_article_id' ,'=', $article_id)
                    ->where('fk_eqset_id' ,'=', $set_id )
                    ->count('*');
            $response = Equipmentsets_Articles::updateOrCreate(
                [ 'fk_article_id' => $article_id, 'fk_eqset_id' => $set_id ]
            );
            if($count){
                VSShopController::update_eqset_job($response);
            }
            else{
                VSShopController::create_eqset_job($response);
            }
            $response->status = 1;
            return response()->json($response);
        }
    }

    public function delete_sets(Request $request, $id, $part, $set_id)
    {
        switch($part)
        {
            case 'sparesets': return $this->delete_sparesets($id, $set_id); break;
            case 'equipmentsets': return $this->delete_equipmentsets($id, $set_id); break;
        }
    }

    public function delete_sparesets($article_id, $set_id)
    {
        if(request()->ajax())
        {
            $Spareset_articles = Sparesets_Articles::where('fk_article_id','=',$article_id)
            ->where('fk_spareset_id','=',$set_id)->get();
            foreach($Spareset_articles as $Spareset_article)
            {$shopController = new VSShopController(); $shopController->delete_spareset_article($Spareset_article); }


            $response = Sparesets_Articles::
              where('fk_article_id','=',$article_id)
            ->where('fk_spareset_id','=',$set_id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}
            return response()->json($response);
        }
    }

    public function delete_equipmentsets($article_id, $set_id)
    {
        if(request()->ajax())
        {
            $Equipmentset_articles = Equipmentsets_Articles::where('fk_article_id','=',$article_id)
            ->where('fk_eqset_id','=',$set_id)->get();
            foreach($Equipmentset_articles as $Equipmentset_article)
            {$shopController = new VSShopController();$shopController->delete_eqset_article($Equipmentset_article);}

            $response = Equipmentsets_Articles::
              where('fk_article_id','=',$article_id)
            ->where('fk_eqset_id','=',$set_id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}
            return response()->json($response);
        }
    }

    // funktioniert nicht richtig
    public function setRabattSubCatPreis($checkSubCats,$checkSubCatsBedingungen,$thisAllDoneArticleVariationIds)
    {   // SubCat ArtikelPreise ebenfalls setzen
        $thisDoneArticleVariationIds = [];
        $allCats = Category::whereNull('fk_wawi_id')->get();
        $thisVon = false;$thisBis = false;$thisProzent = false; $priceSET=0;
        foreach($checkSubCats as $Index => $Subcategorys)
        {   $thisVon = $checkSubCatsBedingungen[$Index]['von'];
            $thisBis = $checkSubCatsBedingungen[$Index]['bis'];
            $thisProzent = $checkSubCatsBedingungen[$Index]['prozent'];
            foreach($Subcategorys as $Subcategory)
            {
                $thisCatArticleIDs = $Subcategory->articles()->get()->pluck('article_id')->toArray();
                $ThisArticles = Article::whereIn('id',$thisCatArticleIDs )->get();
                if($ThisArticles)
                {
                    foreach($ThisArticles as $ThisArticle)
                    {   $ThisVariations = $ThisArticle->variations()->get();
                        if($ThisVariations)
                        {
                            foreach($ThisVariations as $ThisVariation)
                            {   if($priceSET==0 && !in_array($ThisVariation->id,$thisAllDoneArticleVariationIds))
                                {
                                    $sPrice = false; $dPrice = false; $WsPrice = false; $WdPrice = false;
                                    $thisprice=false; $thisWdprice=false;$thisWsprice=false;$thisdprice=false;$thissprice=false;
                                    $prices = $ThisVariation->prices()->get();foreach($prices as $price)
                                    {
                                        if(($price->value!=null)&&($price->value!=""))
                                        {
                                            switch($price->name)
                                            {   case "standard":
                                                    $sPrice = (($price->value!=null)?$price->value:false);
                                                    $thissprice=$price;
                                                    if($sPrice){$sPrice = (float)(str_replace(',','.',str_replace('-','',$sPrice)));}
                                                break;
                                                case "discount":
                                                    $dPrice = (($price->value!=null)?$price->value:false);
                                                    $thisdprice=$price;
                                                    if($dPrice){$dPrice = (float)(str_replace(',','.',str_replace('-','',$dPrice)));}
                                                break;
                                                case "web_standard":
                                                    $WsPrice = (($price->value!=null)?$price->value:false);
                                                    $thisWsprice=$price;
                                                    if($WsPrice){$WsPrice = (float)(str_replace(',','.',str_replace('-','',$WsPrice)));}
                                                break;
                                                case "web_discount":
                                                    $WdPrice = (($price->value!=null)?$price->value:false);
                                                    $thisWdprice=$price;
                                                    if($WdPrice){$WdPrice = (float)(str_replace(',','.',str_replace('-','',$WdPrice)));}
                                                break;
                                            }
                                        }

                                    }
                                    // aktuellen Preis selektieren
                                    $currentPrice = false;
                                    if($WdPrice)
                                    {   $currentPrice = $WdPrice; $thisprice=$thisWdprice; }// wenn WebDiscount gesetzt, ändern
                                    else if($WsPrice && $sPrice && $dPrice)
                                    {   if($dPrice < $sPrice)
                                        {   // WebStandard gesetzt
                                            if($WsPrice<=$dPrice)
                                            { $currentPrice = $WsPrice; $thisprice=$thisWsprice; }
                                            // WebStandard am kleinsten
                                            else{ $currentPrice = $dPrice; $thisprice=$thisdprice; }
                                        }
                                        else // WebStandard gesetzt
                                        {    $currentPrice = $WsPrice; $thisprice=$thisWsprice; }
                                    }else if($sPrice && $dPrice)
                                    {   if($dPrice && $dPrice < $sPrice) { $currentPrice = $dPrice; $thisprice=$thisdprice; }
                                        else { $currentPrice = $sPrice; $thisprice=$thissprice; }
                                    }


                                    if($currentPrice && $currentPrice != "" && is_numeric($currentPrice) && strtotime(date('Y-m-d H:i:s')) >= strtotime($thisVon)
                                    && (strtotime(date('Y-m-d H:i:s')) <= strtotime($thisBis)))
                                    {   $currentPrice3 = number_format( (float)($currentPrice - (float)((float)($currentPrice / 100) * $thisProzent)) , 2, '.', '');

                                        if(is_numeric($currentPrice3)){

                                                $currentPrice3 = str_replace(".", ",",  (string)$currentPrice3 );
                                                $currentPrice3 = str_replace("-", "",  (string)$currentPrice3 );

                                                $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $ThisVariation->id)
                                                        ->where('name' ,'=', 'web_standard' )
                                                        ->count('*');
                                                $newPrice = Article_Variation_Price::updateOrCreate(
                                                    [ 'fk_article_variation_id' => $ThisVariation->id, 'name' => 'web_standard' ],
                                                    [ 'value' => $currentPrice3, 'batch_nr' => date("YmdHis") ]
                                                );
                                                if($count){
                                                    VSShopController::update_article_variation_price_job($newPrice);
                                                }
                                                else{
                                                    VSShopController::create_article_variation_price_job($newPrice);
                                                }
                                                echo "\nSub-Kategorie: [".$newPrice->id."] -".$thisProzent."% = ".$currentPrice3;
                                                $priceSET=1; $thisDoneArticleVariationIds[]=$ThisVariation->id;

                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $thisDoneArticleVariationIds;
    }
    public function setRabattPreis($currentPrice = false,$price = false, $variation = false, $article = false, $globalRabatt=[], $herstellerRabatt=[], $kategorieRabatt=[], $SALE=[],$DoneArticleVariationIds=[])
    {   $thisDoneArticleVariationIds = [];
        if(!in_array($variation->id,$DoneArticleVariationIds)) // doppelte Verarbeitung vermeiden
        {	// Sale Preise zuerst senken
            $priceSET = 0;
            if(count($SALE)>0)
            {	$ThisStandard = Article_Variation_Price::where('fk_article_variation_id','=',$variation->id)->where("name",'=','standard')->first();
                $ThisWebStandard = Article_Variation_Price::where('fk_article_variation_id','=',$variation->id)->where("name",'=','web_standard')->first();
                $ThisStandard = ($ThisStandard)? $ThisStandard->value : 0;
                $ThisWebStandard = ($ThisWebStandard)? $ThisWebStandard->value : 0;

                if( $price->name == "discount"  || $price->name == "web_discount" )
                {
                    if($priceSET==0)
                    {
                        foreach($SALE as $Rabatt)
                        {
                                if(strtotime(date('Y-m-d H:i:s')) >= strtotime($Rabatt['zeitraeume']['von'])
                                && (strtotime(date('Y-m-d H:i:s')) <= strtotime($Rabatt['zeitraeume']['bis'])))
                                {
                                    $currentPrice1 = number_format( (float)($currentPrice - (float)((float)($currentPrice / 100) * $Rabatt['zeitraeume']['prozent']) ) , 2, '.', '');

                                    if(is_numeric($currentPrice1)){
                                        $currentPrice1 = str_replace(".", ",",  (string)$currentPrice1 );
                                        $currentPrice1 = str_replace("-", "",  (string)$currentPrice1 );

                                        $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $variation->id)
                                                ->where('name' ,'=', 'web_discount')
                                                ->count('*');
                                        $newPrice = Article_Variation_Price::updateOrCreate(
                                            [ 'fk_article_variation_id' => $variation->id, 'name' => 'web_discount' ],
                                            [ 'value' => $currentPrice1, 'batch_nr' => date("YmdHis") ]
                                        ); $priceSET=1;
                                        if($count){
                                            VSShopController::update_article_variation_price_job($newPrice);
                                        }
                                        else{
                                            VSShopController::create_article_variation_price_job($newPrice);
                                        }
                                        echo "\nSALE: [".$newPrice->id."] ".$price->value." -".$Rabatt['zeitraeume']['prozent']."% = ".$currentPrice1;
                                    }
                                }

                        }
                    }
                }
            }

            if(count($kategorieRabatt)>0)
            {   $categories = $article->categories()->whereNull('fk_wawi_id')->get();
                $checkSubCats=[];
                $checkSubCatsBedingungen=[];
                foreach($kategorieRabatt as $Rabatt)
                {   foreach($categories as $category_parent)
                    {   if($category_parent->id == $Rabatt['kategorie'])
                        {
                            if($priceSET==0)
                            {
                                if(strtotime(date('Y-m-d H:i:s')) >= strtotime($Rabatt['zeitraeume']['von'])
                                && (strtotime(date('Y-m-d H:i:s')) <= strtotime($Rabatt['zeitraeume']['bis'])))
                                {   $currentPrice2 = number_format( (float)($currentPrice - (float)((float)($currentPrice / 100) * $Rabatt['zeitraeume']['prozent'])) , 2, '.', '');

                                    if(is_numeric($currentPrice2)){

                                            $currentPrice2 = str_replace(".", ",",  (string)$currentPrice2 );
                                            $currentPrice2 = str_replace("-", "",  (string)$currentPrice2 );
                                            $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $variation->id)
                                                    ->where('name' ,'=', 'web_discount')
                                                    ->count('*');
                                            $newPrice = Article_Variation_Price::updateOrCreate(
                                                [ 'fk_article_variation_id' => $variation->id, 'name' => 'web_discount' ],
                                                [ 'value' => $currentPrice2, 'batch_nr' => date("YmdHis") ]
                                            );
                                            if($count){
                                                VSShopController::update_article_variation_price_job($newPrice);
                                            }
                                            else{
                                                VSShopController::create_article_variation_price_job($newPrice);
                                            }
                                            echo "\nKategorie: [".$newPrice->id."] ".$price->value." -".$Rabatt['zeitraeume']['prozent']."% = ".$currentPrice2;
                                            $priceSET=1;


                                    }
                                }
                            }
                        }
                    }

                }
            }

            if(count($globalRabatt)>0)
            {   $currentPrice1 = number_format( (float)($currentPrice - (float)((float)($currentPrice / 100) * $globalRabatt[0] ) ) , 2, '.', '');

                if(is_numeric($currentPrice1)){
                    $currentPrice1 = str_replace(".", ",",  (string)$currentPrice1 );
                    $currentPrice1 = str_replace("-", "",  (string)$currentPrice1 );
                    $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $variation->id)
                            ->where('name' ,'=', 'web_discount')
                            ->count('*');
                    $newPrice = Article_Variation_Price::updateOrCreate(
                        [ 'fk_article_variation_id' => $variation->id, 'name' => 'web_discount' ],
                        [ 'value' => $currentPrice1, 'batch_nr' => date("YmdHis") ]
                    );
                    if($count){
                        VSShopController::update_article_variation_price_job($newPrice);
                    }
                    else{
                        VSShopController::create_article_variation_price_job($newPrice);
                    }
                    echo "\n[".$newPrice->id."] ".$price->value." - ".$globalRabatt[0]."% = ".$currentPrice1;
                }
            }
            if(count($herstellerRabatt)>0)
            {   echo "\ncheck Händler ".$price->value." ".$currentPrice;
                /*$checkHersteller = $article->getAttributeValueByKey('hersteller');
                foreach($herstellerRabatt as $Data){}*/
            }
        }

        $thisDoneArticleVariationIds[] = $variation->id;
        return $thisDoneArticleVariationIds;

    }

    // Preisrabatte Stapelverarbeitung
    public function setRabattPreise_kunde($customer = false,$globalRabatte = [], $herstellerRabatte = [], $kategorieRabatte = [], $SALE = [],$NoSaleChange=false)
    {
        $articles = Article::all(); $Rabatt = 0;
        $DoneArticleVariationIds = [];
        foreach($articles as $article)
        {   $variations =  $article->variations()->get();
            foreach($variations as $variation)
            {   $sPrice = false; $dPrice = false; $WsPrice = false; $WdPrice = false;
                $thisprice=false; $thisWdprice=false;$thisWsprice=false;$thisdprice=false;$thissprice=false;
                $prices = $variation->prices()->get();foreach($prices as $price)
                {
                    if(($price->value!=null)&&($price->value!=""))
                    {   $continue=false;
                        switch($price->name)
                        {   case "standard":
                                $sPrice = (($price->value!=null)?$price->value:false);
                                $thissprice=$price;
                                if($sPrice){$sPrice = (float)(str_replace(',','.',str_replace('-','',$sPrice)));}
                            break;
                            case "discount":
                                $dPrice = (($price->value!=null)?$price->value:false);
                                $thisdprice=$price;
                                if($dPrice){$dPrice = (float)(str_replace(',','.',str_replace('-','',$dPrice)));}
                            break;
                            /*case "web_standard":
                                $WsPrice = (($price->value!=null)?$price->value:false);
                                $thisWsprice=$price;
                                if($WsPrice){$WsPrice = (float)(str_replace(',','.',str_replace('-','',$WsPrice)));}
                            break;
                            case "web_discount":
                                $WdPrice = (($price->value!=null)?$price->value:false);
                                $thisWdprice=$price;
                                if($WdPrice){$WdPrice = (float)(str_replace(',','.',str_replace('-','',$WdPrice)));}
                            break;*/
                            default: $continue=true; break;
                        }
                        if($continue){continue;}
                    }

                }
                // aktuellen Preis selektieren
                $isSale = false;
                $currentPrice = false;
                if($WdPrice)
                {   $currentPrice = $WdPrice; $thisprice=$thisWdprice;}// wenn WebDiscount gesetzt, ändern
                else if($WsPrice && $sPrice && $dPrice)
                {   if($dPrice < $sPrice)
                    {   // WebStandard gesetzt
                        if($WsPrice<=$dPrice)
                        { $currentPrice = $WsPrice; $thisprice=$thisWsprice; }
                        // WebStandard am kleinsten
                        else{ $currentPrice = $dPrice; $thisprice=$thisdprice;  }
                    }
                    else // WebStandard gesetzt
                    {    $currentPrice = $WsPrice; $thisprice=$thisWsprice; }
                }else if($sPrice && $dPrice)
                {   if($dPrice && $dPrice < $sPrice) { $currentPrice = $dPrice; $thisprice=$thisdprice; if($NoSaleChange){$currentPrice = $sPrice; $thisprice=$thissprice;}  }
                    else { $currentPrice = $sPrice; $thisprice=$thissprice; }
                }else{ $currentPrice = $sPrice; $thisprice=$thissprice; }
                // Preis der Variation setzen
                if($currentPrice && $currentPrice != "" && is_numeric($currentPrice))
                {
                    $currentPrice = str_replace("-", "", (string)$currentPrice );
                    $currentPrice = (float)(str_replace(",", ".",  (string)$currentPrice ));
                    if($currentPrice>0)
                    {
                        $DoneArticleVariationIds[] = $this->setRabattPreis($currentPrice,$thisprice,$variation, $article, $globalRabatte, $herstellerRabatte,$kategorieRabatte, $SALE,$DoneArticleVariationIds); }
                    }

            }
        }
        echo "\nfertig";
    }

    // Preisrabatte Stapelverarbeitung
    public function checkPreise()
    {
        $articles = Article::all();
        foreach($articles as $article)
        {   $variations =  $article->variations()->get();
            foreach($variations as $variation)
            {   $prices = $variation->prices()->get();foreach($prices as $price)
                {   if($price->value != "" && strpos("-",$price->value)){echo "[KOR]"; $price->value = str_replace("-", "",  (string)$price->value ); $price->save(); }
                    if($price->value == ""){echo "[DEL]";$price->delete();}
                }
            }
        }
        echo "\nfertig";
    }

}
