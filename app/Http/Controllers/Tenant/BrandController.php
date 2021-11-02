<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Tenant\Brand;
use Illuminate\Http\Request;

use App\Tenant\Article;
use App\Tenant\Article_Attribute;
use App\Tenant\BrandsSuppliers;
use Str, Auth;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(request()->ajax()) {
            $response = datatables()->of(Brand::select('*'))
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            $data['columnconfig'] = Auth::user()->getTableColumnConfig('brands');
           
            return response()->json($data);
        }
        return view('tenant.modules.article.index.brand', ['sideNavConfig' => Article::sidenavConfig('lieferantenverwaltung')]);
    }

    public function updateParentAjax(Request $request) {
        $articleId = $request->articleId;
        $categoryId = $request->categoryId;
        $state = ($request->state == 'true');
        if($categoryId == null || $articleId == null) {
            return response()->json(['success' => 1]);
        }
        $brandsupp = BrandsSuppliers::where('fk_brand_id','=', $categoryId)->where('hersteller-nr','=', $articleId)->first();
        if(!$brandsupp) {
            BrandsSuppliers::where('hersteller-nr', '=', $articleId)->delete();
            BrandsSuppliers::create([
                'fk_brand_id' => $categoryId,
                'hersteller-nr' => $articleId
            ]);
        }

        return response()->json(['success' => 1]);
    }

    public function suppliersIndex() {

        $supplierNrs = Article_Attribute::where('name', '=', 'hersteller-nr')->distinct('value')->whereNotNull('value')->where('value','!=','')->pluck('fk_article_id','value');
        $suppliers = [];
        $suppCount = 0;
        $brands = Brand::all();


        foreach($supplierNrs as $supplierNr => $articleId) {
            $supplierName = Article_Attribute::where('name', '=', 'hersteller')->where('fk_article_id','=',$articleId)->whereNotNull('value')->where('value','!=','')->first();
            
            $brandsSelect = '<form><div class="form-group">
            <select class="brandSelect" id="bss'.$supplierNr.'" data-id="'.$supplierNr.'" data-toggle="selectpicker" data-width="100%" title="Wählen Sie eine Marke...">';
            foreach($brands as $brand) {
                $supplierBrand = $brand->suppliers()->where('hersteller-nr','=',$supplierNr)->first();
                $selected = false;
                if($supplierBrand) {
                    $selected = true;
                }
                $brandsSelect .= '<option value="'.$brand->id.'" '.(($selected) ? 'selected' : '').'>'.$brand->name.'</opion>';
            }
            $brandsSelect .= '</select></div></form>';

            if($supplierName) {
                $suppCount++;
                $suppliers[] = [
                    'name' => $supplierName->value,
                    'nr' => $supplierNr,
                    'brands' => $brandsSelect
                ];
            }
        }

        asort($suppliers,);

        return view('tenant.modules.article.index.supplier', ['sideNavConfig' => Article::sidenavConfig('lieferantenverwaltung'), 'content' => $suppliers]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('tenant.modules.article.create.brand', ['sideNavConfig' => Article::sidenavConfig('lieferantenverwaltung')]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $brandName = $request->name;
        $checkBrand = Brand::where('name','=',$brandName)->first();
        if($checkBrand) {
            return redirect()->back()->withError('Die Marke '.$brandName.' ist schon vorhanden.');
        }

        $imagePath = ''; //TODO: Save image properly
        $description = $request->description; //TODO: Transform description

        $brand = Brand::create([
            'name' => $brandName,
            'slug' => Str::slug($brandName, '-'),
            'description' => $description ?? '',
            'image' => $imagePath,
            'link' => $request->link,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords
        ]);

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Brand  $brand
     * @return \Illuminate\Http\Response
     */
    public function show($brandId)
    {
        $brand = Brand::find($brandId);
        return view('tenant.modules.article.show.brand', ['brand' => $brand,  'sideNavConfig' => Article::sidenavConfig('lieferantenverwaltung')]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Brand  $brand
     * @return \Illuminate\Http\Response
     */
    public function edit($brandId)
    {
        $brand = Brand::find($brandId);
        return view('tenant.modules.article.edit.brand', ['brand' => $brand, 'sideNavConfig' => Article::sidenavConfig('lieferantenverwaltung')]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Brand  $brand
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $brandId)
    {
        $brand = Brand::find($brandId);
        if(!$brand) {
            return redirect()->back()->withError('Die Marke wurde nicht gefunden.');
        }

        $brandName = $request->name;
        $checkBrand = Brand::where('name','=',$brandName)->first();
        if($checkBrand && $brandName != $brand->name) {
            return redirect()->back()->withError('Die Marke '.$brandName.' ist schon vorhanden.');
        }

        $imagePath = ''; //TODO: Save image properly
        $description = $request->description; //TODO: Transform description

        $brand->update([
            'name' => $brandName,
            'slug' => Str::slug($brandName, '-'),
            'description' => $description ?? '',
            'image' => $imagePath,
            'link' => $request->link,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords
        ]);

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Brand  $brand
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $brandId)
    {
        Brand::find($brandId)->delete();
        return redirect()->back()->withSuccess('Erfolgreich gelöscht!');
    }
}
