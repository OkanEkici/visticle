<?php

namespace App\Http\Controllers\Tenant;

use App\Tenant\Article
, App\Tenant\Equipmentsets
, App\Tenant\Equipmentsets_EquipmentArticles
, App\Tenant\Equipmentsets_Articles
, App\Tenant\Equipmentsets_Categories
, App\Tenant\Article_Attribute
, App\Tenant\Article_Image
, App\Tenant\Article_Image_Attribute
, App\Tenant\Article_Price;
use App\Tenant\Article_Marketing;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Redirect,Response;
use Illuminate\Support\Str;
use App\Traits\UploadTrait;
use Storage;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Article_Variation, App\Tenant\Article_Variation_Attribute, App\Tenant\Article_Variation_Price, App\Tenant\Article_Variation_Image, App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Article_Shipment;
use App\Tenant\Category;
use App\Tenant\Setting;
use Auth;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use Log;
use Image;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;

class EquipmentsetController extends Controller
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

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        if(request()->ajax()) {
            
            $response = datatables()->of(Equipmentsets::select([
                'equipmentsets.id', 'equipmentsets.id', 'equipmentsets.name', 'equipmentsets.description', 'equipmentsets.updated_at', 'equipmentsets.created_at','equipmentsets.id'
            ]))            
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('equipmentsets');
           
            return response()->json($data);
        }
        return view('tenant.modules.article.equipmentsets.index.equipmentsets', ['sideNavConfig' => Article::sidenavConfig('zubehoerverwaltung')]);
    }

    public function show($id, $part) {
        $configArray = [];
        $Equipmentset = Equipmentsets::find($id);
        switch($part) {
            case 'general':
                $configArray = $this->generalConfigFormArray($Equipmentset);
            break;
            case 'eqset_eq_articles': 
                return $this->show_equipmentset_equipment_articles($Equipmentset, $part);
            break;
            case 'eqset_articles':
                return $this->show_equipmentset_articles($Equipmentset, $part);
            break;
            case 'eqset_categories':
                return $this->show_equipmentset_categories($Equipmentset, $part);
            break;
        } 
        return view('tenant.modules.article.equipmentsets.show.equipmentset', [
            'equipmentset' => $Equipmentset,
            'part' => $part,
            'configArray' => $configArray,
            'sideNavConfig' => Article::sidenavConfig('zubehoerverwaltung')
        ]);
    }

    protected function show_equipmentset_categories(Equipmentsets $equipmentset, $part) 
    {
        $categories = Category::where('fk_wawi_id', '=', null)->get();

        $SetCategories = Category::with(['equipmentsets_categories' => function($query) use ($equipmentset)
        {   $query->where('fk_eqset_id','=',$equipmentset->id); }])    
        ->whereHas('equipmentsets_categories')    
        ->where('fk_wawi_id', '=', null)->get()->pluck('slug')->toArray();
       
        return view('tenant.modules.article.equipmentsets.edit.eqset_categories',  
        [    'categories' => $categories
            ,'SetCategories' => $SetCategories
            ,'equipmentset' => $equipmentset
            ,'part' => $part
            ,'sideNavConfig' => Article::sidenavConfig('zubehoerverwaltung')
        ] );

    }
    protected function show_equipmentset_equipment_articles(Equipmentsets $equipmentset, $part) 
    {
        if(request()->ajax()) 
        {
            $mainCategories = Category::where('fk_wawi_id', '=', null)->get();

            $response = datatables()->of(Article::with(['categories' => function($query) {
                $query->where('fk_wawi_id','!=',null);
            }, 'attributes', 'variations'])->select([
                'articles.id', 'articles.name', 'articles.updated_at', 'articles.created_at', 'articles.number', 'articles.webname'
            ])
            ->where('type','like','%equipment%') )
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
            ->addColumn('var_eans', function(Article $article) {
                $vars = $article->variations()->get();
                $eans = '';
                foreach($vars as $var) {
                    $eans .= str_replace('vstcl-','',$var->vstcl_identifier).' ';
                };
                return $eans;
            })
            ->addColumn('status', function(Article $article) use ($equipmentset) {
                $articleEquipmentsets = $article->equipmentsets_equipment_article()->where('fk_eqset_id','=',$equipmentset->id)->exists();
                return ($articleEquipmentsets)? "1" : "0";
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action', 'mainCategories', 'articleMainCategories'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('eqset_eq_articles');
           
            return response()->json($data);
        }

        // selected Articles (SpareSet zugewiesen)
        $selArticles = Article::with(['attributes', 'variations','categories' => function($query) {$query->where('fk_wawi_id','!=',null);}])
        ->where('type','like','%equipment%') 
        ->where('number','!=','') 
        ->whereHas('equipmentsets_equipment_article', function($query) use ($equipmentset) 
        { $query->where('fk_eqset_id','=',$equipmentset->id); })
        ->get();

        return view('tenant.modules.article.equipmentsets.edit.eqset_eq_articles', [
            'equipmentset' => $equipmentset,
            'selArticles' => $selArticles,
            'part' => $part,
            'sideNavConfig' => Article::sidenavConfig('zubehoerverwaltung')
        ]);
    }

    protected function show_equipmentset_articles(Equipmentsets $equipmentset, $part) 
    {
        if(request()->ajax()) 
        {
            $mainCategories = Category::where('fk_wawi_id', '=', null)->get();

            $response = datatables()->of(Article::with(['categories' => function($query) {
                $query->where('fk_wawi_id','!=',null);
            }, 'attributes', 'variations'])->select([
                'articles.id', 'articles.name', 'articles.updated_at', 'articles.created_at', 'articles.number', 'articles.webname'
            ])
            ->where('type','like','%article%') 
            ->where('type','not like','%spare%') 
            ->where('type','not like','%equipment%') 
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
            ->addColumn('var_eans', function(Article $article) {
                $vars = $article->variations()->get();
                $eans = '';
                foreach($vars as $var) {
                    $eans .= str_replace('vstcl-','',$var->vstcl_identifier).' ';
                };
                return $eans;
            })
            ->addColumn('status', function(Article $article) use ($equipmentset) {
                $articleEquipmentsets = $article->equipmentsets_article()->where('fk_eqset_id','=',$equipmentset->id)->exists();
                return ($articleEquipmentsets)? "1" : "0";
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action', 'mainCategories', 'articleMainCategories'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('eqset_articles');
           
            return response()->json($data);
        }

        // selected Articles (SpareSet zugewiesen)
        $selArticles = Article::with(['attributes', 'variations','categories' => function($query) {$query->where('fk_wawi_id','!=',null);}])
        ->where('type','like','%article%') 
        ->where('type','not like','%spare%') 
        ->where('type','not like','%equipment%') 
        ->where('number','!=','') 
        ->whereHas('equipmentsets_article', function($query) use ($equipmentset) 
        { $query->where('fk_eqset_id','=',$equipmentset->id); })
        ->get();

        return view('tenant.modules.article.equipmentsets.edit.eqset_articles', [
            'equipmentset' => $equipmentset,
            'selArticles' => $selArticles,
            'part' => $part,
            'sideNavConfig' => Article::sidenavConfig('zubehoerverwaltung')
        ]);
    }

    protected function generalConfigFormArray(Equipmentsets $equipmentset) 
    {        
        $fieldsets = [
            [
                'legend' => 'Stammdaten',
                  'form-group' => [
                        [
                            'label' => 'Name',
                            'name' => 'name',
                            'type' => 'text',
                            'value' => ($equipmentset->name != null) ? $equipmentset->name : '',
                        ],
                        [
                            'label' => 'Beschreibung',
                            'name' => 'description',
                            'type' => 'text',
                            'value' => ($equipmentset->description != null) ? $equipmentset->description : '',
                        ],
                  ]
                ],
                [
                    'legend' => 'Systeminfos',
                    'form-group' => [
                        ['label' => 'Angelegt', 'value' => date('d.m.Y H:i:s', strtotime($equipmentset->created_at)), 'type' => 'info'],
                        ['label' => 'Geändert', 'value' => date('d.m.Y H:i:s', strtotime($equipmentset->updated_at)), 'type' => 'info']
                    ]
                ]
        ];

        return $fieldsets;
    }

    public function update(Request $request, $id, $part, $article_id = false) {
        $equipmentset = Equipmentsets::find($id);
        switch($part) {
            case 'general':
                $this->updateGeneral($request, $equipmentset);
            break;
            case 'eqset_eq_articles':
                if($article_id){return $this->update_equipmentarticle($request, $id, $part, $article_id);}                
             break;
             case 'eqset_articles':
                if($article_id){return $this->update_article($request, $id, $part, $article_id);}                
             break;
             case 'eqset_categories':
                if($article_id){return $this->update_category($request, $id, $part, $article_id);}                
             break;            
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function update_equipmentarticle(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $response = Equipmentsets_EquipmentArticles::updateOrCreate(
                [
                    'fk_article_id' => $article_id,
                    'fk_eqset_id' => $id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }

    public function update_article(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $response = Equipmentsets_Articles::updateOrCreate(
                [
                    'fk_article_id' => $article_id,
                    'fk_eqset_id' => $id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }

    public function update_category(Request $request, $id, $part, $category_id) {
        if(request()->ajax()) 
        {
            $response = Equipmentsets_Categories::updateOrCreate(
                [
                    'fk_category_id' => $category_id,
                    'fk_eqset_id' => $id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }

    

    protected function updateGeneral(Request $request, Equipmentsets $equipmentset) {
        $equipmentset->update([
            'name' => $request->name
            ,'description' => $request->description
        ]);
    }

    public function create(){
        return view('tenant.modules.article.equipmentsets.create.equipmentset', ['sideNavConfig' => Article::sidenavConfig('zubehoerverwaltung')]);
    }

    public function store(Request $request) {
        $equipmentset = Equipmentsets::create([
            'name' => $request->name,
            'description' => $request->description
        ]);
        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }


    public function delete(Request $request, $id, $part, $article_id = false) {
        $equipmentset = Equipmentsets::find($id);
        switch($part) {
            case 'eqset_eq_articles':
                if($article_id){return $this->delete_equipmentarticle($request, $id, $part, $article_id);}                
             break;
             case 'eqset_articles':
                if($article_id){return $this->delete_article($request, $id, $part, $article_id);}
             break;
             case 'eqset_categories':
                if($article_id){return $this->delete_category($request, $id, $part, $article_id);}
             break;            
        }
        if(!$part)
        {
            
            $Equipmentset_articles = Equipmentsets_Articles::where('fk_eqset_id','=',$id)->get();
            foreach($Equipmentset_articles as $Equipmentset_article)
            {$shopController = new VSShopController();$shopController->delete_eqset_article($Equipmentset_article);}
            $response = Equipmentsets_Articles::where('fk_eqset_id','=',$id)->delete();
            
            $Equipmentset_categories = Equipmentsets_Categories::where('fk_eqset_id','=',$id)->get();
            foreach($Equipmentset_categories as $Equipmentset_category)
            {$shopController = new VSShopController();$shopController->delete_eqset_category($Equipmentset_category);}
            $response = Equipmentsets_Categories::where('fk_eqset_id','=',$id)->delete();
            
            $Equipmentset_equipment_articles = Equipmentsets_EquipmentArticles::where('fk_eqset_id','=',$id)->get();
            foreach($Equipmentset_equipment_articles as $Equipmentset_equipment_article)
            {$shopController = new VSShopController();$shopController->delete_eqset_eq_article($Equipmentset_equipment_article);}
            $response = Equipmentsets_EquipmentArticles::where('fk_eqset_id','=',$id)->delete(); 
            
            $Equipmentset = Equipmentsets::where('id','=',$id)->first();
            $shopController = new VSShopController();
            $shopController->delete_eqset($Equipmentset);
            $response = Equipmentsets::where('id','=',$id)->delete();

            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function delete_equipmentarticle(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $Equipmentset_equipment_articles = Equipmentsets_EquipmentArticles::where('fk_article_id','=',$article_id)
            ->where('fk_eqset_id','=',$id)->get();
            foreach($Equipmentset_equipment_articles as $Equipmentset_equipment_article)
            {$shopController = new VSShopController();$shopController->delete_eqset_eq_article($Equipmentset_equipment_article);}

            $response = Equipmentsets_EquipmentArticles::
              where('fk_article_id','=',$article_id)
            ->where('fk_eqset_id','=',$id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }

    public function delete_article(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $Equipmentset_articles = Equipmentsets_Articles::where('fk_article_id','=',$article_id)
            ->where('fk_eqset_id','=',$id)->get();
            foreach($Equipmentset_articles as $Equipmentset_article)
            {$shopController = new VSShopController();$shopController->delete_eqset_article($Equipmentset_article);}

            $response = Equipmentsets_Articles::
              where('fk_article_id','=',$article_id)
            ->where('fk_eqset_id','=',$id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }

    public function delete_category(Request $request, $id, $part, $category_id) {
        if(request()->ajax()) 
        {
            $Equipmentset_categories = Equipmentsets_Categories::where('fk_category_id','=',$category_id)
            ->where('fk_eqset_id','=',$id)->get();
            foreach($Equipmentset_categories as $Equipmentset_category)
            {$shopController = new VSShopController();$shopController->delete_eqset_category($Equipmentset_category);}

            $response = Equipmentsets_Categories::
              where('fk_category_id','=',$category_id)
            ->where('fk_eqset_id','=',$id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }

}
