<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Providers\Amazon\AmazonProviderController;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopProviderController;
use App\Http\Controllers\Tenant\Providers\Zalando\ZalandoProviderController;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareProviderController;
use App\Http\Controllers\Tenant\Providers\Check24\Check24ProviderController;
use App\Tenant\Provider;
use App\Tenant\Provider_Config,App\Tenant\Provider_Config_Attribute;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Category;
use App\Tenant\Setting;
use App\Tenant\Synchro;
use Auth;
use Illuminate\Http\Request;
use Log;

class ProviderController extends Controller
{

    private $registeredProviders = [
        Providers\VSShop\VSShopController::class,
        Providers\Shopware\ShopwareAPIController::class
    ];

    public function createdContentByType($content, string $type) {
        foreach($this->registeredProviders as $registeredProvider) {
            $providerC = new $registeredProvider();
            if(method_exists($providerC, 'create_'.$type)) {
                $providerC->{'create_'.$type}($content);
            }
        }
    }

    public function updatedContentByType($content, string $type, $ignoreBatchNr=false) {
        foreach($this->registeredProviders as $registeredProvider) {
            $providerC = new $registeredProvider();
            if($ignoreBatchNr){$type=$type."_manuell";}
            if(method_exists($providerC, 'update_'.$type)) {
                $providerC->{'update_'.$type}($content);
            }
        }
    }

    public function deletedContentByType($content, string $type) {
        foreach($this->registeredProviders as $registeredProvider) {
            $providerC = new $registeredProvider();
            if(method_exists($providerC, 'delete_'.$type)) {
                $providerC->{'delete_'.$type}($content);
            }
        }
    }

    public function index()
    {
        $providers = Provider::all();
        return view('tenant.modules.provider.index.provider', ['providers' => $providers]);
    }

    public function show($id, $part)
    {
        $provider = Provider::where('id', $id)->get()->first();
        $providerType = $provider->type()->first();
        $providerController = null;
        $configArray = [];
        $providers = Provider::all();
        if($providerType) {
            switch($providerType->provider_key) {
                case 'amzn':
                    $providerController = new AmazonProviderController();
                break;
                case 'shop':
                    $providerController = new VSShopProviderController();
                break;
                case 'zalando_cr':
                    $providerController = new ZalandoProviderController();
                break;
                case 'shopware':
                    $providerController = new ShopwareProviderController();
                break;
                case "check24":
                    $providerController = new Check24ProviderController();
                    break;
                default:
                break;
            }
        }
        if($providerController) {
            $configArray = $providerController->getConfigFormArray($provider, $part);
        }

        return view('tenant.modules.provider.show.provider',[
            'provider' => $provider,
            'part' => $part,
            'configArray' => $configArray,
            'providers' => $providers,
            'menu' => ($providerController) ? $providerController->getMenuStructure() : []
            ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, $part)
    {
        $provider = Provider::where('id', $id)->get()->first();
        $providerType = $provider->type()->first();
        $providerController = null;
        if($providerType) {
            switch($providerType->provider_key) {
                case 'amzn':
                    $providerController = new AmazonProviderController();
                break;
                case 'shop':
                    $providerController = new VSShopProviderController();
                break;
                case 'zalando_cr':
                    $providerController = new ZalandoProviderController();
                break;
                case 'shopware':
                    $providerController = new ShopwareProviderController();
                break;
                case "check24":
                    $providerController = new Check24ProviderController();
                    break;
                default:
                break;
            }
        }
        if($providerController) {
            $providerController->updateProvider($request, $provider, $part);
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function articles($id) {
        $provider = Provider::where('id', $id)->get()->first();
        if(request()->ajax()) {
            $response = datatables()->of($provider->articles()->groupBy('fk_article_id')->with(['article']))
            ->addColumn('number', function(ArticleProvider $articleP) {
                return Setting::getReceiptNameWithNumberByKey('article', $articleP->article()->first()->id);
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            //$data['columnconfig'] = Auth::user()->getTableColumnConfig('articles');

            return json_encode($data);
        }
    }

    public function loadArticles($id) {
        $provider = Provider::where('id', $id)->get()->first();
        if(request()->ajax()) {
            $response = datatables()->of($provider->articles()->groupBy('fk_article_id')->with(['article']))
            ->addColumn('number', function(ArticleProvider $articleP) {
                return Setting::getReceiptNameWithNumberByKey('article', $articleP->article()->first()->id);
            })
            ->addColumn('name', function(ArticleProvider $articleP) {
                return $articleP->name;
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
            $data = $response->getData(true);
            //$data['columnconfig'] = Auth::user()->getTableColumnConfig('articles');

            return json_encode($data);
        }

    }

    public function orders($id) {
        $provider = Provider::where('id', $id)->get()->first();
        return view('tenant.modules.provider.index.orders',['provider' => $provider]);
    }


    // Shop Sort Update + Delete
    public function ShopSort_UpdateAjax(Request $request)
    {
        $providerId = $request->providerId;
        $type = $request->type;
        $name = $request->name;
        $sai_id = $request->sai_id;
        $sai_nr = $request->sai_nr;

        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $providerId]);

        if($sai_id && $sai_id != "" && $providerConfig)
        {
            $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
            $shop_sorting_selected = [];
            $isSet=false;
            if($shop_sortingDB && $shop_sortingDB != "")
            {   $shop_sortingDB = json_decode($shop_sortingDB->value);
                if(is_array($shop_sortingDB))
                {   foreach($shop_sortingDB as $item)
                    {
                        if($item->sai_id == $sai_id){$isSet=true;}
                        $shop_sorting_selected[] = ['type' => $item->type, 'name' => $item->name, 'selected' => 1,'sai_id' =>$item->sai_id,'sai_nr' =>$item->sai_nr ];
                    }
                }
            }
            if(!$isSet){$shop_sorting_selected[] = ['type' => $type, 'name' => $name, 'selected' => 1,'sai_id' =>$sai_id,'sai_nr' =>$sai_nr ];}

            $Provider_Config_Attribute = Provider_Config_Attribute::updateOrCreate(
            ['fk_provider_config_id'  => $providerConfig->id,'name'  => 'shop_sorting'],[
            'value' => json_encode($shop_sorting_selected) ]);
            return response()->json(['success' => 1]);
        }else{return response()->json(['error' => 1 ]);}
    }
    public function ShopSort_deleteUpdateAjax($provider_id, $sai_id)
    {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider_id]);
        if($sai_id && $sai_id != "" && $providerConfig)
        {
            $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
            $shop_sorting_selected_new = [];
            $isSet=false;
            if($shop_sortingDB && $shop_sortingDB != "")
            {   $shop_sortingDB = json_decode($shop_sortingDB->value);
                if(is_array($shop_sortingDB))
                {   foreach($shop_sortingDB as $item)
                    {
                        if($item->sai_id == $sai_id){continue;}
                        $shop_sorting_selected_new[] = ['type' => $item->type, 'name' => $item->name, 'selected' => 1,'sai_id' =>$item->sai_id,'sai_nr' =>$item->sai_nr ];
                    }
                }
            }

            $Provider_Config_Attribute = Provider_Config_Attribute::updateOrCreate(
                ['fk_provider_config_id'  => $providerConfig->id,'name'  => 'shop_sorting'],[
                'value' => json_encode($shop_sorting_selected_new) ]);

            if($Provider_Config_Attribute)
            {
                return response()->json(['success' => 1 ]);
            }else{return response()->json(['error' => 1 ]);}
        }
        else{return response()->json(['error' => 1 ]);}
    }

    public function ShopSort_upUpdateAjax($provider_id, $sai_id)
    {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider_id]);
        if($sai_id && $sai_id != "" && $providerConfig)
        {
            $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
            $shop_sorting_selected_new = [];
            $isSet=false;
            if($shop_sortingDB && $shop_sortingDB != "")
            {   $shop_sortingDB = json_decode($shop_sortingDB->value);
                if(is_array($shop_sortingDB))
                {   foreach($shop_sortingDB as $item)
                    {
                        if($item->sai_id == $sai_id){$isSet=count($shop_sorting_selected_new);}
                        $shop_sorting_selected_new[] = ['type' => $item->type, 'name' => $item->name, 'selected' => 1,'sai_id' =>$item->sai_id,'sai_nr' =>$item->sai_nr ];
                    }
                }
            }

            if($isSet>0){
                $item = $shop_sorting_selected_new[ $isSet ];
                $shop_sorting_selected_new[ $isSet ] = $shop_sorting_selected_new[ $isSet - 1 ];
                $shop_sorting_selected_new[ $isSet - 1 ] = $item;
            }

            $Provider_Config_Attribute = Provider_Config_Attribute::updateOrCreate(
                ['fk_provider_config_id'  => $providerConfig->id,'name'  => 'shop_sorting'],[
                'value' => json_encode($shop_sorting_selected_new) ]);

            if($Provider_Config_Attribute)
            {
                return response()->json(['success' => json_encode($shop_sorting_selected_new) ]);
            }else{return response()->json(['error' => 1 ]);}
        }
        else{return response()->json(['error' => 1 ]);}
    }
    public function ShopSort_downUpdateAjax($provider_id, $sai_id)
    {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider_id]);
        if($sai_id && $sai_id != "" && $providerConfig)
        {
            $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
            $shop_sorting_selected_new = [];
            $isSet=false;
            if($shop_sortingDB && $shop_sortingDB != "")
            {   $shop_sortingDB = json_decode($shop_sortingDB->value);
                if(is_array($shop_sortingDB))
                {   foreach($shop_sortingDB as $item)
                    {
                        if($item->sai_id == $sai_id){$isSet=count($shop_sorting_selected_new);}
                        $shop_sorting_selected_new[] = ['type' => $item->type, 'name' => $item->name, 'selected' => 1,'sai_id' =>$item->sai_id,'sai_nr' =>$item->sai_nr ];
                    }
                }
            }

            if($isSet<count($shop_sorting_selected_new)){
                $item = $shop_sorting_selected_new[ $isSet ];
                $shop_sorting_selected_new[ $isSet ] = $shop_sorting_selected_new[ $isSet + 1 ];
                $shop_sorting_selected_new[ $isSet + 1 ] = $item;
            }

            $Provider_Config_Attribute = Provider_Config_Attribute::updateOrCreate(
                ['fk_provider_config_id'  => $providerConfig->id,'name'  => 'shop_sorting'],[
                'value' => json_encode($shop_sorting_selected_new) ]);

            if($Provider_Config_Attribute)
            {
                return response()->json(['success' => 1 ]);
            }else{return response()->json(['error' => 1 ]);}
        }
        else{return response()->json(['error' => 1 ]);}
    }

}
