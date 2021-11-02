<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Redirect,Response;
use App\Tenant\Category;
use App\Tenant\Article;
use App\Tenant\Article_Eigenschaften;
use App\Tenant\Article_Eigenschaften_Categories;
use App\Tenant\Provider;
use App\Tenant\Provider_Type;
use App\Tenant\CategoryProvider;
use Illuminate\Support\Str;
use stdClass;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(request()->ajax()) {
            return datatables()->of()
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }

        //$categories = Category::where('fk_wawi_id', '=', null)->get();
        $categories = Category::categoriesOfProvider()->get();



        return view('tenant.modules.article.index.vstclcategory', ['categories' => $categories, 'sideNavConfig' => Article::sidenavConfig('kategorien')]);
    }

    public function indexWaregroups() {
        $mainCategories = Category::with(['shopcategories'])->where('fk_wawi_id', '=', null)
        ->whereHas('providers',function($query){
            $query->where('providers.id',1);
        })
        ->get();

        /**
         * Nun holen wir alle Provider, die einen Kategoriebaum besitzen
         */
        $providers_with_categories=Provider::providersWithCategories()->get();


        /**
        *  Wenn $providers_with_categories leer ist, so müssen wir improvisieren
         */
        $improvisieren=false;
        if($providers_with_categories->count()==0){
            $improvisieren=true;
            $provider_with_categories_object=new stdClass();
            $provider_with_categories_object->name="Standard";
            $provider_with_categories_object->id=0;


            $providers_with_categories=[
                $provider_with_categories_object,
            ];
        }


        //dd($providers_with_categories);

        //Wir bauen jetzt eine Liste auf für unsere View und für das JavaScripting, um dynamisch alle Kategoriebäume anzeigen zu lassen
        //für die View
        $view_provider_categories=null;
        if(!$improvisieren){
            $view_provider_categories=$providers_with_categories->toArray();
        }
        else{
            $view_provider_categories=$providers_with_categories;
        }

        //Jetzt passen wir noch den Anzeigenamen an
        for($index=0;$index < count($view_provider_categories);$index++){
            if($improvisieren){
                $list=$view_provider_categories[$index];
                $list->category_tree_show_name="Kategorien {$list->name}";
                $list->category_tree_internal_name="categories_{$list->name}";
                $view_provider_categories[$index]=$list;
            }
            else{
                $list=$view_provider_categories[$index];
                $list['category_tree_show_name']="Kategorien {$list['name']}";
                $list['category_tree_internal_name']="categories_{$list['name']}";
                $view_provider_categories[$index]=$list;
            }

        }

        //jetzt bauen wir eine Baum-Liste für das Javascripting
        $javascript_provider_categories=[];
        foreach($providers_with_categories as $provider_with_categories){
            $javascript_provider_categories["categories_{$provider_with_categories->name}"]=[
                //Auf jeden Fall speichern wir den Namen und die Id des Providers und den Javascript-internen
                //Namen für den Kategoriebaum
                'provider_name'=>$provider_with_categories->name,
                'provider_id'=>$provider_with_categories->id,
                'category_tree_internal_name'=>"categories_{$provider_with_categories->name}",
                'category_tree_show_name'=>"Kategorien {$provider_with_categories->name}",
            ];
        }






       if(request()->ajax()) {
            $table=datatables()->of(Category::select('*')->where('fk_wawi_id', '!=', null))
            ->addColumn('action', 'action_button')
            /*
            ->addColumn('mainCategories', $mainCategories)
            ->addColumn('waregroupCategories', function(Category $category) {
                return $category->shopcategories()->get();
            })
            */
            //->rawColumns(['action', 'mainCategories', 'waregroupCategories'])
            ->addIndexColumn();

            //Wir erzeugen jetzt dynamisch unsere Kategorie-Bäume
            $raw_columns=[];
            foreach($javascript_provider_categories as $category_entry){

                //Provider holen
                $provider=Provider::find($category_entry['provider_id']);

                $provider_categories=null;
                //Provider-Kategorien holen
                if(!$provider){
                    $provider_categories=null;
                    $provider_categories=Category::query()->whereNull('fk_wawi_id')->whereDoesntHave('providers')->get();
                }
                else{
                    $provider_categories=$provider->realCategories;
                }


                /*
                if($provider->name=='ModeMai'){
                    dd($provider_categories);
                }
                */

                $table->addColumn("{$category_entry['category_tree_internal_name']}", $provider_categories);
                $table->addColumn("waregroupCategories_{$category_entry['category_tree_internal_name']}", function(Category $category)use($provider) {
                    //return $category->shopcategories($provider)->get();
                    if($provider){
                        return $category->providerCategories($provider)->get();
                    }
                    else{
                        return $category->shopcategories()->get();
                    }

                });
                $raw_columns[]="{$category_entry['category_tree_internal_name']}";
                $raw_columns[]="waregroupCategories_{$category_entry['category_tree_internal_name']}";
            }
            $table->rawColumns($raw_columns);


            $table=$table->make(true);


            return $table;
       }

        return view('tenant.modules.article.index.category',
                    [
                        'sideNavConfig' => Article::sidenavConfig('warengruppen'),
                        'view_provider_categories'=>$view_provider_categories,
                        'javascript_provider_categories'=>$javascript_provider_categories,
                        'improvisieren' => $improvisieren,
                    ]
                );
    }

    public function test(){

        //###################################
        //Category@categoriesOfProvider
        //###################################
        //Mit Provider
        //für shop
        $provider_types=Provider_Type::typesForProviderKey('shop')->get();
        $provider_type=$provider_types->first();
         //provider holen
         $provider=$provider_type->providers->first();
         $categories_of_provider=Category::categoriesOfProvider($provider)->get();
         //dd($categories_of_provider);
         //ohne Provider
         $categories_of_provider=Category::categoriesOfProvider()->get();
         dd($categories_of_provider);




        //###################################
        //Article@Kategorien-Test
        $article=Article::find(4146);
        /*

        $article_categories=$article->categories()->get();
        //dd($article_categories);
        */
        //Mit Provider
        //für check24
        $provider_types=Provider_Type::typesForProviderKey('shop')->get();
        $provider_type=$provider_types->first();
         //provider holen
         $provider=$provider_type->providers->first();
         $article_categories=$article->categories($provider)->get();
        //dd($article_categories);
        //###################################


        //###################################
        //Provider@providerWithCategories - Test
        $providers_with_categories=Provider::providersWithCategories()->get();
        //dd($providers_with_categories);
        //###################################



        //###################################
        //Kategorie@providerCategories Test
        $category=Category::query()->where('wawi_number','00001300')->first();
        //für check24
        $provider_types=Provider_Type::typesForProviderKey('check24')->get();
        $provider_type=$provider_types->first();
        //provider holen
        $provider=$provider_type->providers->first();
        //$provider_categories
        $provider_categories=$category->providerCategories($provider)->get();
        dd($provider_categories);
        //###################################


        //###################################
        //Kategorie-shopcategorie Test
        $category=Category::query()->where('wawi_number','00001300')->first();
        $shop_categories=$category->shopcategories;
        dd($shop_categories);
        //###################################


        //###################################
        //Standard-Provider Test
        $standar_provider=Category::getSystemStandardProvider();
        dd($standar_provider);
        //###################################
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //$categories = Category::where('fk_wawi_id', '=', null)->get();
        $categories=Category::categoriesOfProvider()->where('fk_wawi_id', '=', null)->get();

        return view('tenant.modules.article.create.category', ['categories' => $categories, 'sideNavConfig' => Article::sidenavConfig('kategorien')]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $request->merge([
            'fk_parent_category_id' => $request->parent == 'false' ? null :  $request->parent ,
            'slug' => Str::slug($request->slug, '-'),
        ]);

        $category = Category::create($request->except(['_token', 'parent']));

        //Wir holen uns jetzt den Standardprovider
        $standard_config=Category::getSystemStandardProvider();
        $standard_provider=$standard_config['provider'];

        //Wenn es einen Standardprovider gibt, so weisen wir die neu erstellte Kategorie diesem zu!! YEEEEEHHAAAAAAAAA
        if($standard_provider){
            $category_provider=CategoryProvider::create([
                'fk_provider_id'=>$standard_provider->id,
                'fk_category_id'=>$category->id,
            ]);
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $category = Category::where('id', $id)->get()->first();
        return view('tenant.modules.article.show.category', ['category' => $category, 'sideNavConfig' => Article::sidenavConfig('kategorien')]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //$categories = Category::where('fk_wawi_id', '=', null)->where('id','!=',$id)->get();
        $categories=Category::categoriesOfProvider()->where('fk_wawi_id', '=', null)->where('id','!=',$id)->get();
        $category = Category::where('id', $id)->get()->first();

        $Category_EigenschaftenIDs = $category->eigenschaften_cat()->get()->pluck("fk_eigenschaft_id")->toArray();
        $weitereEigenschaften = Article_Eigenschaften::whereNotIn('id',$Category_EigenschaftenIDs)->get();
        $weitereEigenschaftenContent = [];
        foreach($weitereEigenschaften as $weitereEigenschaft)
        {
            $this_Attrs = $weitereEigenschaft->eigenschaften()->get();
            $EigenschaftenOptionenOutput="";
            $EigenschaftenOptionen="";
            foreach($this_Attrs as $this_Attr)
            { $EigenschaftenOptionenOutput.='<span class="badge badge-secondary p-1 mr-2">'.$this_Attr->value.'</span>';
                $EigenschaftenOptionen.= '<span class="d-inline-block p-1 mx-1 mb-1 bg-secondary border">'.$this_Attr->value.'</span>';
            }

            $weitereEigenschaftenContent[] = [
                '<div class="custom-control custom-checkbox mb-1"><input data-category_id="'.$category->id.'" data-is_filterable="'.((isset($weitereEigenschaft->is_filterable) && $weitereEigenschaft->is_filterable)?"filterbar":"").'" data-name="'.((isset($weitereEigenschaft->name))?$weitereEigenschaft->name:"").'" data-active="'.((isset($weitereEigenschaft->active) && $weitereEigenschaft->active)?"Aktiv":"").'" data-id="'.$weitereEigenschaft->id.'" type="checkbox" class="custom-control-input wahl-attribut" id="attribut_'.$weitereEigenschaft->id.'"><label class="custom-control-label" for="attribut_'.$weitereEigenschaft->id.'"></label></div>',
                ''.((isset($weitereEigenschaft->active) && $weitereEigenschaft->active)?"Aktiv":"Inaktiv"),
                ''.((isset($weitereEigenschaft->name))?$weitereEigenschaft->name:"")
                .'<ul class="h-auto p-0 border-0"><li class="list-group-item p-0 border-0">'
                .'<li class="d-inline-block p-0 border-0">'
                .$EigenschaftenOptionenOutput.'</li></ul><div id="options_'.$weitereEigenschaft->id.'" class="d-none">'.$EigenschaftenOptionen.'</div>',
                ''.((isset($weitereEigenschaft->is_filterable) && $weitereEigenschaft->is_filterable)?"filterbar":"-")

            ];
        }

        return view('tenant.modules.article.edit.category',
        [ 'category' => $category
        , 'categories' => $categories
        , 'weitereEigenschaftenContent' => $weitereEigenschaftenContent
        , 'sideNavConfig' => Article::sidenavConfig('kategorien')
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->merge([
            'fk_parent_category_id' => $request->parent == 'false' ? null :  $request->parent ,
            'slug' => Str::slug($request->slug, '-'),
        ]);
        $thisCategory = Category::where('id','=',$id)->first();
        $thisCategory->update($request->except(['_token', 'parent']));

        // alte Kategorie Eigenschaften-Gruppierungen löschen
        $thisCategory->eigenschaften_cat()->delete();
        if(isset($request->eigenschaft) && is_array($request->eigenschaft))
        {
            $thisCategory->eigenschaften()->attach($request->eigenschaft);
        }

        $providerC = new ProviderController();
        $providerC->updatedContentByType($thisCategory, 'category');

        return redirect()->to('/categories/show/'.$id)->withSuccess('Erfolgreich gespeichert!');
    }

    public function updateAjax(Request $request) {
        $data = $request->data;
        if(!is_array($data)) {
            return Response::json(['error' => 'No data']);
        }
        foreach($data as $cat) {
            $catId = $cat['id'];
            $category = Category::find($catId);
            $category->fk_parent_category_id = null;
            $category->save();
            if(isset($cat['children']) && is_array($cat['children'])) {
                foreach($cat['children'] as $subcategory) {
                    $subcat = Category::find($subcategory['id']);
                    $subcat->fk_parent_category_id = $category->id;
                    $subcat->save();
                    if(isset($subcategory['children']) && is_array($subcategory['children'])) {
                        foreach($subcategory['children'] as $supersubcategory) {
                            $supersubcat = Category::find($supersubcategory['id']);
                            $supersubcat->fk_parent_category_id = $subcat->id;
                            $supersubcat->save();
                        }
                    }
                }
            }
        }
        return Response::json(['success' => 1]);
    }

    public function updateParentAjax(Request $request) {
        $childId = $request->childId;
        $parentId = $request->parentId;
        if($parentId == 'root') {
            return response()->json();
        }
        $state = ($request->state == "true");
        $childCat = Category::findOrFail($childId);
        if($state) {
            $childCat->add_shopcategory($parentId);
        }
        else {
            $childCat->remove_shopcategory($parentId);
        }

        return response()->json(['success' => 1]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $category = Category::find($id);
        //provider löschen
        CategoryProvider::query()->where('fk_category_id',$category->id)->delete();
        $category->delete();
        return redirect()->back()->withSuccess('Erfolgreich gelöscht!');
    }
}
