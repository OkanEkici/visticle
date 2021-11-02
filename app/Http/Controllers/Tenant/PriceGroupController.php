<?php

namespace App\Http\Controllers\Tenant;

use Auth;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Tenant\Setting;
use App\Tenant\Article;
use App\Tenant\Category;
use App\Tenant\Customer;
use App\Tenant\Price_Groups;
use App\Tenant\Price_Groups_Articles;
use App\Tenant\Price_Groups_Categories;
use App\Tenant\Price_Groups_Customers;
use Redirect,Response;
use Validator, Session;
use Illuminate\Support\Facades\Log;


class PriceGroupController extends Controller
{
    
    public function __construct()
    {
        //$this->middleware('auth');
    }

    
    public function index()
    {
        if(request()->ajax()) {
            return $this->dataTablesPriceGroupData([ 'id','name','description','val_type','value','active','created_at' ]);
        }
       
        $priceGroups = Price_Groups::all();
        $content = [];
        foreach($priceGroups as $priceGroup) {
            $content[] = [
                $priceGroup->name
                ,$priceGroup->description
                ,$priceGroup->val_type
                ,( (($priceGroup->val_type == "percent")? $priceGroup->value."%" 
                    : (($priceGroup->val_type == "solid")? $priceGroup->value."€" 
                    : ($priceGroup->val_type == "indi")? "" : '' ) )
                )
                ,$priceGroup->active                
                ,$priceGroup->created_at  
            ];
        }
        return view('tenant.modules.pricegroups.index.pricegroups',  ['content' => $content,'sideNavConfig' => Price_Groups::sidenavConfig()] );
    }

    private function dataTablesPriceGroupData($selection = "*", $columnconfig = 'pricegroups') {
        $response = datatables()->of(
            Price_Groups::select($selection)
        )
        ->addColumn('action', 'action_button') 
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);

        $data = $response->getData(true);
        
        $data['columnconfig'] = Auth::user()->getTableColumnConfig($columnconfig);
       
        return json_encode($data);
    }

    public function create(){
        return view('tenant.modules.pricegroups.create.pricegroups', ['sideNavConfig' => Price_Groups::sidenavConfig()]);
    }

    public function show($id) {
       
    }
    
    public function store(Request $request) 
    {
        if(isset($request->all()['pricegroup']))
        {
            if($request->all()['pricegroup']['name'] !== null)
            {   
                //Pre Check Name unique bereits vergeben
                $pricegroup_name_count = Price_Groups::all()->where('name','=',$request->all()['pricegroup']['name'])->count();
                if($pricegroup_name_count == 0)
                {
                    $pricegroup = Price_Groups::create([
                        'name' => $request->all()['pricegroup']['name'],
                        'description' => $request->all()['pricegroup']['description'],
                        'val_type' => $request->all()['pricegroup']['val_type'],
                        'value' => str_replace(',', '.', $request->all()['pricegroup']['value']),
                        'active' => $request->all()['pricegroup']['active'],
                    ]);    
                    
                }else{return '"error":"true","message":"Gruppen Name bereits vergeben !"';}
                
            }else{return '"error":"true","message":"Gruppen Name ist ein Pflichtfeld !"';}
            
        }

        return '"success":"true","message":"Erfolgreich gespeichert!"';
    }

    public function destroy($id) {
        $pricegroup_articles = Price_Groups::find($id)->articles()->delete();
        $pricegroup_categories = Price_Groups::find($id)->categories()->delete();
        $pricegroup_customers = Price_Groups::find($id)->customers()->delete();
        $pricegroup = Price_Groups::find($id)->delete();

        return Response::json($pricegroup);
    }

    public function edit($id) {
        $pricegroup = Price_Groups::where('id', $id)->get()->first();
        
        return view('tenant.modules.pricegroups.edit.pricegroups', 
        ['pricegroup' => $pricegroup
        ,'sideNavConfig' => Price_Groups::sidenavConfig()
        ]);
    }

    public function update(Request $request, $id) {
        $pricegroup = Price_Groups::where('id', $id)->get()->first();
        
        if(isset($request->all()['pricegroup']))
        { 
            if($request->all()['pricegroup']['name'] !== null)
            {   
                //Pre Check Name unique bereits vergeben
                $pricegroup_name_count = Price_Groups::all()->where('name','=',$request->all()['pricegroup']['name'])
                ->where('id','!=',$id)->count();
                if($pricegroup_name_count == 0)
                {
                    $pricegroup = Price_Groups::updateOrCreate(['id' => $id]
                        ,[                  
                        'name' => $request->all()['pricegroup']['name'],
                        'description' => $request->all()['pricegroup']['description'],
                        'val_type' => $request->all()['pricegroup']['val_type'],
                        'value' => str_replace(',', '.', $request->all()['pricegroup']['value']),
                        'active' => $request->all()['pricegroup']['active'],
                    ]);

                    // Preise der Einträge an das neue Value der Preisgruppe anpassen wenn prozent oder festwert
                    $pricegroup_articles = Price_Groups::find($id)->articles()->get();
                    

                    foreach($pricegroup_articles as $pricegroup_article)
                    {
                        $discount = false;
                        $web_discount = Article::with(['prices','variations'])->where('id','=',$pricegroup_article->article_id)->first()->variations()->first()->prices()->where('name','=','web_discount')->first();
                        if($web_discount && $web_discount->value > 0) {
                            $discount = str_replace(',', '.', $web_discount->value);
                        }
                        else {
                            $this_discount = Article::with(['prices','variations'])->where('id','=',$pricegroup_article->article_id)->first()->variations()->first()->prices()->where('name','=','discount')->first();
                            if($this_discount && $this_discount->value > 0) {
                                $discount = str_replace(',', '.', $this_discount->value);
                            }
                        }

                        $standard = false;
                        $web_standard = Article::with(['prices','variations'])->where('id','=',$pricegroup_article->article_id)->first()->variations()->first()->prices()->where('name','=','web_standard')->first();
                        if($web_standard && $web_standard->value > 0) {
                            $standard = str_replace(',', '.', $web_standard->value);
                        }
                        else {
                            $this_standard = Article::with(['prices','variations'])->where('id','=',$pricegroup_article->article_id)->first()->variations()->first()->prices()->where('name','=','standard')->first();
                            if($this_standard && $this_standard->value > 0) {
                                $standard = str_replace(',', '.', $this_standard->value);
                            }
                        }

                        // Berechnungen der Preise wenn Prozent oder Festwert eingestellt ist
                        switch($request->all()['pricegroup']['val_type'])
                        {             
                            case "percent":  
                                $thisPriceGroupWert = (int)str_replace(',', '.', $request->all()['pricegroup']['value']);           
                                $discount = (float)$standard+ (( (  ( ($standard*100) / 100)  ) * $thisPriceGroupWert )/100);
                                //$discount = (float)$discount+ (( (  ( ($discount*100) / 100)  ) * $thisPriceGroupWert )/100);
                                //$standard = (float)$standard+ (( (  ( ($standard*100) / 100)  ) * $thisPriceGroupWert )/100);
                                
                            break;
                            case "solid": 
                                $thisPriceGroupWert = (float)str_replace(',', '.', $request->all()['pricegroup']['value']);
                                $discount = ($discount+$thisPriceGroupWert > 0)? $discount+$thisPriceGroupWert : "0.00";
                                $standard = ($standard+$thisPriceGroupWert > 0)? $standard+$thisPriceGroupWert : "0.00";                
                            break;
                        }

                        $PriceGroupArticle = Price_Groups_Articles::updateOrCreate(
                            [
                                'id' => $pricegroup_article->id
                            ]
                            ,[
                                'standard'  => $standard,
                                'discount'  => $discount,
                            ]
                        );
                        
                        // TO DO !!
                        //$pricegroup_categories = Price_Groups::find($id)->categories()->get();
                        //$pricegroup_customers = Price_Groups::find($id)->customers()->get();

                    }

                }else{return '"error":"true","message":"Name bereits vergeben!"';}
                
            }else{return '"error":"true","message":"Name darf nicht leer sein!"';}
            
        }
        return '"success":"true","message":"Erfolgreich gespeichert!"';
    }

    public function articles() {        

            if(request()->ajax()) {

                $mainPriceGroups = Price_Groups::where('active','=','1')->get();

                $response = datatables()->of(Article::with(['attributes', 'variations'])
                ->select([ 'articles.id', 'articles.number', 'articles.name' ]))
                ->addColumn('systemnumber', function(Article $article) {
                    if($article->id != null) {
                        return Setting::getReceiptNameWithNumberByKey('article', $article->id);
                    }
                })
                ->addColumn('mainPriceGroups', $mainPriceGroups)
                ->addColumn('articleMainPriceGroups', function(Article $article) {
                    $articlePriceGroups = $article->pricegroups()->get();
                    return $articlePriceGroups;
                })
                ->addColumn('var_eans', function(Article $article) {
                    $vars = $article->variations()->get();
                    $eans = '';
                    foreach($vars as $var) {
                        $eans .= str_replace('vstcl-','',$var->vstcl_identifier).' ';
                    };
                    return $eans;
                })
                ->addColumn('action', 'action_button')
                ->rawColumns(['action'])
                ->addIndexColumn()
                ->make(true);
                $data = $response->getData(true);
                $data['columnconfig'] = Auth::user()->getTableColumnConfig('pricegroups_articles');
            
                return response()->json($data);
            }

        return view('tenant.modules.pricegroups.index.pricegroups_articles', ['sideNavConfig' => Price_Groups::sidenavConfig()]);
    }

    public function pricegroups_articles_UpdateAjax(Request $request) {
        $articleId = $request->articleId;
        $PriceGroupId = $request->pricegroupId;

        $discount = false;
        $web_discount = Article::with(['prices','variations'])->where('id','=',$articleId)->first()->variations()->first()->prices()->where('name','=','web_discount')->first();
        if($web_discount && $web_discount->value > 0) {
            $discount = str_replace(',', '.', $web_discount->value);
        }
        else {
            $this_discount = Article::with(['prices','variations'])->where('id','=',$articleId)->first()->variations()->first()->prices()->where('name','=','discount')->first();
            if($this_discount && $this_discount->value > 0) {
                $discount = str_replace(',', '.', $this_discount->value);
            }
        }

        $standard = false;
        $web_standard = Article::with(['prices','variations'])->where('id','=',$articleId)->first()->variations()->first()->prices()->where('name','=','web_standard')->first();
        if($web_standard && $web_standard->value > 0) {
            $standard = str_replace(',', '.', $web_standard->value);
        }
        else {
            $this_standard = Article::with(['prices','variations'])->where('id','=',$articleId)->first()->variations()->first()->prices()->where('name','=','standard')->first();
            if($this_standard && $this_standard->value > 0) {
                $standard = str_replace(',', '.', $this_standard->value);
            }
        }

        // Berechnungen der Preise wenn Prozent oder Festwert eingestellt ist
        $thisPriceGroup = Price_Groups::where('id','=',$PriceGroupId)->first();
        switch($thisPriceGroup->val_type)
        {
            case "percent":  
                $thisPriceGroupWert = (int)str_replace(',', '.', $thisPriceGroup->value);
                
                $discount = (float)$standard+ (( (  ( ($standard*100) / 100)  ) * $thisPriceGroupWert )/100);
                
                //$discount = (float)$discount+ (( (  ( ($discount*100) / 100)  ) * $thisPriceGroupWert )/100);
                //$standard = (float)$standard+ (( (  ( ($standard*100) / 100)  ) * $thisPriceGroupWert )/100);
                
            break;
            case "solid": 
                $thisPriceGroupWert = (float)str_replace(',', '.', $thisPriceGroup->value);  
                $discount = ($discount+$thisPriceGroupWert > 0)? $discount+$thisPriceGroupWert : "0.00";
                $standard = ($standard+$thisPriceGroupWert > 0)? $standard+$thisPriceGroupWert : "0.00";                
            break;
        }

        $state = ($request->state == 'true');
        if($state) {
            if(($standard||$discount))
            {
                $PriceGroupArticle = Price_Groups_Articles::updateOrCreate(
                    [
                        'article_id' => $articleId,                            
                        'group_id'  => $PriceGroupId,
                        'standard'  => $standard,
                        'discount'  => $discount,
                    ]
                );
            }else
            {
                return response()->json(['error' => 'Keine Artikelpreise vorhanden']);
            }
            
        } else {
            Price_Groups_Articles::where('article_id', $articleId)->where('group_id', $PriceGroupId)->get()->first()->delete();
        }

        return response()->json(['success' => 1]);
    }

    public function categories() {        

        if(request()->ajax()) {

            $mainPriceGroups = Price_Groups::where('active','=','1')->get();

            $response = datatables()->of(Category::
            select([ 'categories.id', 'categories.name' ]))            
            ->addColumn('mainPriceGroups', $mainPriceGroups)
            ->addColumn('categoryMainPriceGroups', function(Category $category) {
                $categoryPriceGroups = $category->pricegroups()->get();
                return $categoryPriceGroups;
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('pricegroups_categories');
        
            return response()->json($data);
        }

        return view('tenant.modules.pricegroups.index.pricegroups_categories', ['sideNavConfig' => Price_Groups::sidenavConfig()]);
    }

    public function pricegroups_categories_UpdateAjax(Request $request) {
        $categoryId = $request->categoryId;
        $PriceGroupId = $request->pricegroupId;
        $state = ($request->state == 'true');
        if($state) {
            $PriceGroupCategory = Price_Groups_Categories::updateOrCreate(
                [
                    'category_id' => $categoryId          
                    ,'group_id'  => $PriceGroupId
                ]
            );
            return response()->json(['success' => $PriceGroupId." <G-C> ".$categoryId]);
        } else {
            Price_Groups_Categories::where('category_id', $categoryId)->where('group_id', $PriceGroupId)->get()->first()->delete();
            return response()->json(['success' => 1]);
        }

        
    }

    public function customers() {        

        if(request()->ajax()) {

            $mainPriceGroups = Price_Groups::where('active','=','1')->get();

            $response = datatables()->of(Customer::
            select([ 'customers.id', 'customers.knr', 'customers.anrede', 'customers.vorname', 'customers.nachname', 'customers.email', 'customers.firma' ]))            
            ->addColumn('mainPriceGroups', $mainPriceGroups)
            ->addColumn('customerMainPriceGroups', function(Customer $customer) {
                $customerPriceGroups = $customer->pricegroups()->get();
                return $customerPriceGroups;
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('pricegroups_customers');
        
            return response()->json($data);
        }

        return view('tenant.modules.pricegroups.index.pricegroups_customers', ['sideNavConfig' => Price_Groups::sidenavConfig()]);
    }

    public function pricegroups_customers_UpdateAjax(Request $request) {
        $customerId = $request->customerId;
        $PriceGroupId = $request->pricegroupId;
        $state = ($request->state == 'true');
        if($state) {
            $PriceGroupCustomer = Price_Groups_Customers::updateOrCreate(
                [
                    'customer_id' => $customerId          
                    ,'group_id'  => $PriceGroupId
                ]
            );
            return response()->json(['success' => $PriceGroupId." <G-C> ".$customerId]);
        } else {
            Price_Groups_Customers::where('customer_id', $customerId)->where('group_id', $PriceGroupId)->get()->first()->delete();
            return response()->json(['success' => 1]);
        }

        
    }

    
}
