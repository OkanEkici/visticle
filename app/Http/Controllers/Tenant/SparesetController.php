<?php

namespace App\Http\Controllers\Tenant;

use App\Tenant\Article
, App\Tenant\Sparesets
, App\Tenant\Sparesets_SpareArticles
, App\Tenant\Sparesets_Articles
, App\Tenant\Sparesets_Categories
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

class SparesetController extends Controller
{

    use UploadTrait;

    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index()
    {
        if(request()->ajax()) {
            
            $response = datatables()->of(Sparesets::select([
                'sparesets.id', 'sparesets.id', 'sparesets.name', 'sparesets.description', 'sparesets.updated_at', 'sparesets.created_at','sparesets.id'
            ]))            
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('sparesets');
           
            return response()->json($data);
        }
        return view('tenant.modules.article.sparesets.index.sparesets', ['sideNavConfig' => Article::sidenavConfig('ersatzteilverwaltung')]);
    }

    public function show($id, $part) {
        $configArray = [];
        $Spareset = Sparesets::find($id);
        switch($part) {
            case 'general':
                $configArray = $this->generalConfigFormArray($Spareset);
            break;
            case 'spareset_spare_articles': 
               return $this->show_spareset_spare_articles($Spareset, $part);
            break;
            case 'spareset_articles':
               return $this->show_spareset_articles($Spareset, $part);
            break;
            case 'spareset_categories':
                return $this->show_spareset_categories($Spareset, $part);
            break;
        } 
        return view('tenant.modules.article.sparesets.show.spareset', [
            'spareset' => $Spareset,
            'part' => $part,
            'configArray' => $configArray,
            'sideNavConfig' => Article::sidenavConfig('ersatzteilverwaltung')
        ]);
    }

    protected function show_spareset_categories(Sparesets $spareset, $part) 
    {
        $categories = Category::where('fk_wawi_id', '=', null)->get();

        $SetCategories = Category::with(['sparesets_categories' => function($query) use ($spareset)
        {   $query->where('fk_spareset_id','=',$spareset->id); }])    
        ->whereHas('sparesets_categories')    
        ->where('fk_wawi_id', '=', null)->get()->pluck('slug')->toArray();
       
        return view('tenant.modules.article.sparesets.edit.spareset_categories',  
        [    'categories' => $categories
            ,'SetCategories' => $SetCategories
            ,'spareset' => $spareset
            ,'part' => $part
            ,'sideNavConfig' => Article::sidenavConfig('ersatzteilverwaltung')
        ] );
    }

    protected function show_spareset_spare_articles(Sparesets $spareset, $part) 
    {
        if(request()->ajax()) 
        {
            $mainCategories = Category::where('fk_wawi_id', '=', null)->get();

            $response = datatables()->of(Article::with(['categories' => function($query) {
                $query->where('fk_wawi_id','!=',null);
            }, 'attributes', 'variations'])            
            ->select([
                'articles.id', 'articles.name', 'articles.updated_at', 'articles.created_at', 'articles.number', 'articles.webname'
            ])
            ->where('type','like','%spare%') 
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
            ->addColumn('status', function(Article $article) use ($spareset) {
                $articleSparesets = $article->sparesets_spare_article()->where('fk_spareset_id','=',$spareset->id)->exists();
                return ($articleSparesets)? "1" : "0";
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action', 'mainCategories', 'articleMainCategories'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('spareset_spare_articles');
           
            return response()->json($data);
        }

        // selected Articles (SpareSet zugewiesen)
        $selArticles = Article::with(['attributes', 'variations','categories' => function($query) {$query->where('fk_wawi_id','!=',null);}])
        ->where('type','like','%spare%') 
        ->where('number','!=','') 
        ->whereHas('sparesets_spare_article', function($query) use ($spareset) 
        { $query->where('fk_spareset_id','=',$spareset->id); })
        ->get();

        return view('tenant.modules.article.sparesets.edit.spareset_spare_articles', [
            'spareset' => $spareset,
            'selArticles' => $selArticles,
            'part' => $part,
            'sideNavConfig' => Article::sidenavConfig('ersatzteilverwaltung')
        ]);
    }

    protected function show_spareset_articles(Sparesets $spareset, $part) 
    {
        if(request()->ajax()) 
        {
            $mainCategories = Category::where('fk_wawi_id', '=', null)->get();

            $response = datatables()->of(Article::with(['categories' => function($query) {
                $query->where('fk_wawi_id','!=',null);
            }, 'attributes', 'variations'])            
            ->select([
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
            ->addColumn('status', function(Article $article) use ($spareset) {
                $articleSparesets = $article->sparesets_article()->where('fk_spareset_id','=',$spareset->id)->exists();
                return ($articleSparesets)? "1" : "0";
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action', 'mainCategories', 'articleMainCategories'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('spareset_articles');
           
            return response()->json($data);
        }

        // selected Articles (SpareSet zugewiesen)
        $selArticles = Article::with(['attributes', 'variations','categories' => function($query) {$query->where('fk_wawi_id','!=',null);}])
            ->where('type','like','%article%') 
            ->where('type','not like','%spare%') 
            ->where('type','not like','%equipment%') 
            ->where('number','!=','') 
            ->whereHas('sparesets_article', function($query) use ($spareset) 
            { $query->where('fk_spareset_id','=',$spareset->id); })
            ->get();

        return view('tenant.modules.article.sparesets.edit.spareset_articles', [
            'spareset' => $spareset,
            'selArticles' => $selArticles,
            'part' => $part,
            'sideNavConfig' => Article::sidenavConfig('ersatzteilverwaltung')
        ]);
    }

    protected function generalConfigFormArray(Sparesets $spareset) 
    {        
        $fieldsets = [
            [
                'legend' => 'Stammdaten',
                  'form-group' => [
                        [
                            'label' => 'Name',
                            'name' => 'name',
                            'type' => 'text',
                            'value' => ($spareset->name != null) ? $spareset->name : '',
                        ],
                        [
                            'label' => 'Beschreibung',
                            'name' => 'description',
                            'type' => 'text',
                            'value' => ($spareset->description != null) ? $spareset->description : '',
                        ],
                  ]
                ],
                [
                    'legend' => 'Systeminfos',
                    'form-group' => [
                        ['label' => 'Angelegt', 'value' => date('d.m.Y H:i:s', strtotime($spareset->created_at)), 'type' => 'info'],
                        ['label' => 'Geändert', 'value' => date('d.m.Y H:i:s', strtotime($spareset->updated_at)), 'type' => 'info']
                    ]
                ]
        ];

        return $fieldsets;
    }

    public function update(Request $request, $id, $part, $article_id = false) {
        $spareset = Sparesets::find($id);
        switch($part) {
            case 'general':
                $this->updateGeneral($request, $spareset);
            break;
            case 'spareset_spare_articles':
                if($article_id){return $this->update_sparearticle($request, $id, $part, $article_id);}
             break;
             case 'spareset_articles':
                if($article_id){return $this->update_article($request, $id, $part, $article_id);}
             break;
             case 'spareset_categories':
                if($article_id){return $this->update_category($request, $id, $part, $article_id);}
             break;            
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function update_sparearticle(Request $request, $id, $part, $article_id) {
        $spareset = Sparesets::find($id);
        if(request()->ajax()) 
        {
            $response = Sparesets_SpareArticles::updateOrCreate(
                [
                    'fk_article_id' => $article_id,
                    'fk_spareset_id' => $id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }
    public function update_article(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $response = Sparesets_Articles::updateOrCreate(
                [
                    'fk_article_id' => $article_id,
                    'fk_spareset_id' => $id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }

    public function update_category(Request $request, $id, $part, $category_id) {
        if(request()->ajax()) 
        {
            $response = Sparesets_Categories::updateOrCreate(
                [
                    'fk_category_id' => $category_id,
                    'fk_spareset_id' => $id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }

    

    protected function updateGeneral(Request $request, Sparesets $spareset) {
        $spareset->update([
            'name' => $request->name
            ,'description' => $request->description
        ]);
    }

    public function create(){
        return view('tenant.modules.article.sparesets.create.spareset', ['sideNavConfig' => Article::sidenavConfig('ersatzteilverwaltung')]);
    }

    public function store(Request $request) {
        $spareset = Sparesets::create([
            'name' => $request->name,
            'description' => $request->description
        ]);
        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function delete(Request $request, $id, $part, $article_id = false) {        
        switch($part) {
            case 'spareset_spare_articles':
                if($article_id){return $this->delete_sparearticle($request, $id, $part, $article_id);}
             break;
             case 'spareset_articles':
                if($article_id){return $this->delete_article($request, $id, $part, $article_id);}
             break;
             case 'spareset_categories':
                if($article_id){return $this->delete_category($request, $id, $part, $article_id);}
             break;            
        }
        if(!$part)
        {
            
            $Spareset_articles = Sparesets_SpareArticles::where('fk_spareset_id','=',$id)->get();
            foreach($Spareset_articles as $Spareset_article)
            {$shopController = new VSShopController(); $shopController->delete_spareset_article($Spareset_article); }            
            $response = Sparesets_Articles::where('fk_spareset_id','=',$id)->delete();
            

            $Spareset_categories = Sparesets_Categories::where('fk_spareset_id','=',$id)->get();
            foreach($Spareset_categories as $Spareset_category)
            {$shopController = new VSShopController();$shopController->delete_spareset_category($Spareset_category);}
            $response = Sparesets_Categories::where('fk_spareset_id','=',$id)->delete();

            $Spareset_spare_articles = Sparesets_SpareArticles::where('fk_spareset_id','=',$id)->get();
            foreach($Spareset_spare_articles as $Spareset_spare_article)
            {$shopController = new VSShopController();$shopController->delete_spareset_spare_article($Spareset_spare_article);}        
            $response = Sparesets_SpareArticles::where('fk_spareset_id','=',$id)->delete();
            
            $Spareset = Sparesets::where('id','=',$id)->first();
            $shopController = new VSShopController();
            $shopController->delete_spareset($Spareset);

            $response = Sparesets::where('id','=',$id)->delete();


            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function delete_sparearticle(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $Spareset_spare_articles = Sparesets_SpareArticles::where('fk_article_id','=',$article_id)
            ->where('fk_spareset_id','=',$id)->get();
            foreach($Spareset_spare_articles as $Spareset_spare_article)
            {$shopController = new VSShopController();$shopController->delete_spareset_spare_article($Spareset_spare_article);}

            $response = Sparesets_SpareArticles::
              where('fk_article_id','=',$article_id)
            ->where('fk_spareset_id','=',$id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }
    
    public function delete_article(Request $request, $id, $part, $article_id) {
        if(request()->ajax()) 
        {
            $Spareset_articles = Sparesets_Articles::where('fk_article_id','=',$article_id)
            ->where('fk_spareset_id','=',$id)->get();
            foreach($Spareset_articles as $Spareset_article)
            {$shopController = new VSShopController(); $shopController->delete_spareset_article($Spareset_article); }  

            $response = Sparesets_Articles::
              where('fk_article_id','=',$article_id)
            ->where('fk_spareset_id','=',$id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }

    public function delete_category(Request $request, $id, $part, $category_id) {
        if(request()->ajax()) 
        {
            $Spareset_categories = Sparesets_Categories::where('fk_category_id','=',$category_id)
            ->where('fk_spareset_id','=',$id)->get();
            foreach($Spareset_categories as $Spareset_category)
            {$shopController = new VSShopController();$shopController->delete_spareset_category($Spareset_category);}

            $response = Sparesets_Categories::
              where('fk_category_id','=',$category_id)
            ->where('fk_spareset_id','=',$id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }

}
