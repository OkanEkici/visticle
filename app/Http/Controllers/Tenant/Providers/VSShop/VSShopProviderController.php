<?php

namespace App\Http\Controllers\Tenant\Providers\VSShop;

use App\Http\Controllers\Controller;
use App\Tenant\Provider;
use App\Tenant\Provider_Config;
use App\Tenant\Article,App\Tenant\Article_Attribute;
use App\Tenant\ArticleProvider;
use App\Tenant\Category;
use App\Tenant\Setting;
use App\Tenant\Synchro;
use Auth;
use Illuminate\Http\Request;

class VSShopProviderController extends Controller {


    private $menuStructure = [
        'general' => 'Stammdaten',
        'articles' => 'Artikelliste',
        'shipping' => 'Versand',
        'payment' => 'Zahlungsarten',
        'marketing' => 'Marketing',
        'categorytree' => 'Kategoriebaum',
        'menutree' => 'Menübaum',
        'synchro' => 'Synchronisation',
        'shop_sort' => 'Grundsortierung'
    ];

    public function __construct() {}

    public function getConfigFormArray($provider, $part) {
        $configArray = [];
        switch($part) {
            case 'general': $configArray = $this->generalConfigFormArray($provider); break;
            case 'articles': $configArray = $this->articlesConfigFormArray($provider); break;
            case 'orders': $configArray = $this->ordersConfigFormArray($provider); break;
            case 'shipping': $configArray = $this->shippingConfigFormArray($provider); break;
            case 'payment': $configArray = $this->paymentConfigFormArray($provider); break;
            case 'marketing': $configArray = $this->marketingConfigFormArray($provider); break;
            case 'categorytree': $configArray = $this->categorytreeConfigFormArray($provider); break;
            case 'menutree': $configArray = $this->menutreeConfigFormArray($provider); break;
            case 'synchro': $configArray = $this->synchroConfigFormArray($provider); break;
            case 'shop_sort': $configArray = $this->shop_sortConfigFormArray($provider); break;            
        }
        return $configArray;
    }

    public function updateProvider(Request $request, $provider, $part) 
    {}

    protected function generalConfigFormArray(Provider $provider) {
        $attributeKeys = ['brandname', 'company', 'street', 'postcode', 'city', 'tel', 'email', 'domain'];
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $values = $providerConfig->attributes();
        $arrVals = [];foreach($attributeKeys as $attributeKey) 
        {$arrVals[$attributeKey] = ($values->get()->where('name', '=', $attributeKey)->first()) ? $values->get()->where('name', '=', $attributeKey)->first()->value : '';}
        $fieldsets = [];
        $fieldsets[] = [
            'legend' => 'Stammdaten',
            'form-group' => [
                [
                    'label' => 'Markenname',
                    'name' => 'brandname',
                    'type' => 'text',
                    'helptext' => 'Der Name Ihres Geschäfts',
                    'value' => $arrVals['brandname'],
                ],
                [
                    'type' => 'upload',
                    'value' => ['name' => 'logo', 'placeholder' => 'Logo auswählen', 'label' => 'Logo', 'helptext' => 'Laden Sie hier Ihr Logo hoch, welches standardmäßig angezeigt wird.'],
                ],
                [
                    'label' => 'Firma',
                    'name' => 'company',
                    'type' => 'text',
                    'required' => true,
                    'helptext' => 'Ihr Firmenname',
                    'value' => $arrVals['company']
                ],
                [
                    'label' => 'Straße und Hausnummer',
                    'name' => 'street',
                    'type' => 'text',
                    'required' => true,
                    'value' => $arrVals['street']
                ],
                [
                    'type' => 'columns',
                    'count' => 2
                ],
                [
                    'label' => 'PLZ',
                    'name' => 'postcode',
                    'type' => 'text',
                    'required' => true,
                    'value' => $arrVals['postcode']
                ],
                [
                    'label' => 'Ort',
                    'name' => 'city',
                    'type' => 'text',
                    'required' => true,
                    'value' => $arrVals['city']
                ],
                [
                    'type' => 'endcolumns',
                ],
                [
                    'label' => 'Telefonnummer',
                    'name' => 'tel',
                    'type' => 'text',
                    'value' => $arrVals['tel']
                ],
                [
                    'label' => 'E-Mail Adresse',
                    'name' => 'email',
                    'type' => 'text',
                    'value' => $arrVals['email']
                ],
                [
                    'label' => 'Web',
                    'name' => 'domain',
                    'type' => 'text',
                    'value' => $arrVals['domain']
                ],
            ],
        ];
        
        return $fieldsets;
    }

    protected function updateGeneralConfig() {

    }

    protected function articlesConfigFormArray(Provider $provider) {
        $bulkOptions = [];
        if(Setting::getFashionCloudApiKey() != null) {
          $bulkOptions[] = ['id' => 'syncArticlesFashionCloud', 'text' => 'Mit Fashioncloud synchronisieren'];
        }
        $formFields = [
            [
                'legend' => 'Artikelliste',
                'form-group' => [
                    [
                        'type' => 'table',
                        'tableData' => [
                            'title' => '',
                            'firstColumnWidth' => 10,
                            'tableId' => 'myTable',
                            'bulkOptions' => $bulkOptions,
                            'columns' => [
                                'id',
                                'Artikelnummer',
                                'Artikelname',
                                'Aktiv'
                              ],
                            'search' => ['placeholder' => 'Artikel suchen']
                        ]     
                    ]
                  ],
              ]
        ];
        return $formFields;
    }

    protected function ordersConfigFormArray(Provider $provider) {
        $formFields = [
            [
                'legend' => 'Aufträge',
                'form-group' => [

                  ],
              ]
        ];
        return $formFields;
    }

    protected function shippingConfigFormArray(Provider $provider) {
        $formFields = [
            [
                'legend' => 'Versand',
                'form-group' => [

                  ],
              ]
        ];
        return $formFields;
    }

    protected function paymentConfigFormArray(Provider $provider) {
        $formFields = [
            [
                'legend' => 'Zahlungsarten',
                'form-group' => [

                  ],
              ]
        ];
        return $formFields;
    }

    protected function marketingConfigFormArray(Provider $provider) {
        $formFields = [
            [
                'legend' => 'Marketing',
                'form-group' => [

                  ],
              ]
        ];
        return $formFields;
    }

    protected function categorytreeConfigFormArray(Provider $provider) {
        $formFields = [
            [
                'legend' => 'Kategoriebaum',
                'form-group' => [
                    [
                        'label' => 'Aktive Kategorien',
                        'type' => 'checkbox',
                        'options' => []
                    ],
                  ],
              ]
        ];
        $categories = Category::all();
        foreach($categories as $category) {
            $articleInCategory = false;
            $formFields[0]['form-group'][0]['options'][] = [
                'label' => $category->name,
                'name' => 'category['.$category->id.']',
                'checked' => ($articleInCategory) ? true : false
            ] ;
        }
        return $formFields;
    }

    protected function menutreeConfigFormArray(Provider $provider) {
        $formFields = [
            [
                'legend' => 'Menübaum',
                'form-group' => [

                  ],
              ]
        ];
        return $formFields;
    }

    public function getConfig() {
        
    }

    
    

    public function synchroConfigFormArray(Provider $provider) {
        $fieldsets = [];
        $content = Synchro::basicTableDataByType('shop');
        $fieldsets[] = Synchro::basicTable($content);
        return $fieldsets;
    }

    public function getMenuStructure() {
        return $this->menuStructure;
    }

    public function shop_sortConfigFormArray(Provider $provider) 
    {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $shop_sortingDB = $providerConfig->attributes()->where('name','=','shop_sorting')->first();
        $shop_sorting_selected = [];
        $shop_sorting_unselected = [];
        $selNames=[];
        $RandomCheck=false;$letzterWeCheck=false;
        if($shop_sortingDB && $shop_sortingDB != "")
        {   $shop_sortingDB = json_decode($shop_sortingDB->value);
            if(is_array($shop_sortingDB))
            {   foreach($shop_sortingDB as $item)
                {   $shop_sorting_selected[] = ['type' => $item->type, 'name' => $item->name, 'selected' => 1,'sai_id' =>$item->sai_id,'sai_nr' =>$item->sai_nr ]; 
                    if($item->type == "Random"){$RandomCheck=true;}
                    if($item->type == "letzterWe"){$letzterWeCheck=true;} 
                    if(!in_array($item->name,$selNames)){$selNames[]=$item->name;}
                }
            }
        }
        if(!$RandomCheck){$shop_sorting_unselected[] = ['type' => "Random", 'name' => "Random", 'selected' => 0,'sai_id' =>"Random",'sai_nr' =>"" ]; }
        if(!$letzterWeCheck){$shop_sorting_unselected[] = ['type' => "letzterWe", 'name' => "letzter Wareneingang", 'selected' => 0,'sai_id' =>"letzterWe",'sai_nr' =>"" ]; }
          
        //Fee Saisons der Artikel abfragen
        $ArticleIDsDB = Article::get()->pluck('id')->toArray();
        $SaisonIDsDB = Article_Attribute::where('name', '=', 'fee-sai_id')->whereIn('fk_article_id', $ArticleIDsDB)->distinct('value')->pluck('id','value');
        foreach($SaisonIDsDB as $AttrID)
        {   if($AttrID != "")
            {   $thisAttr = Article_Attribute::where('id', '=', $AttrID)->first();
                if($thisAttr)
                {   
                    $thisArtSaison = $thisAttr->article()->first()->attributes()->where('name', '=', 'fee-saison')->first();
                    if($thisArtSaison){$thisArtSaison = $thisArtSaison->value;}
                    $thisArtSaisonBezeichnung = $thisAttr->article()->first()->attributes()->where('name', '=', 'fee-saisonbezeichnung')->first();
                    if($thisArtSaisonBezeichnung){$thisArtSaisonBezeichnung = $thisArtSaisonBezeichnung->value;}
                    $thisArtSaisonID = $thisAttr->article()->first()->attributes()->where('name', '=', 'fee-sai_id')->first();
                    if($thisArtSaisonID){$thisArtSaisonID = $thisArtSaisonID->value;}
                    if(in_array($thisArtSaisonBezeichnung,$selNames)){continue;}
                    $shop_sorting_unselected[] = ['type' => "Saison", 'name' => $thisArtSaisonBezeichnung, 'selected' => 0,'sai_id' =>$thisArtSaisonID,'sai_nr' =>$thisArtSaison ];
                }

            }
        }

        // Advarics Saisons der Artikel abfragen
        $ArticleIDsDB = Article::get()->pluck('id')->toArray();
        $SaisonIDsDB = Article_Attribute::where('name', '=', 'adv-lastIncomeSeasonNo')->whereIn('fk_article_id', $ArticleIDsDB)->distinct('value')->pluck('id','value');
        foreach($SaisonIDsDB as $AttrID)
        {   if($AttrID != "")
            {   $thisAttr = Article_Attribute::where('id', '=', $AttrID)->first();
                if($thisAttr)
                {   
                    $thisArtSaison = $thisAttr->article()->first()->attributes()->where('name', '=', 'adv-lastIncomeSeasonNo')->first();
                    if($thisArtSaison){$thisArtSaison = $thisArtSaison->value;}
                    $thisArtSaisonBezeichnung = $thisAttr->article()->first()->attributes()->where('name', '=', 'adv-lastIncomeSeasonName')->first();
                    if($thisArtSaisonBezeichnung){$thisArtSaisonBezeichnung = $thisArtSaisonBezeichnung->value;}
                    $thisArtSaisonID = $thisAttr->article()->first()->attributes()->where('name', '=', 'adv-lastIncomeSeasonNo')->first();
                    if($thisArtSaisonID){$thisArtSaisonID = $thisArtSaisonID->value;}
                    if(in_array($thisArtSaisonBezeichnung,$selNames)){continue;}
                    $shop_sorting_unselected[] = ['type' => "Saison", 'name' => $thisArtSaisonBezeichnung, 'selected' => 0,'sai_id' =>$thisArtSaisonID,'sai_nr' =>$thisArtSaison ];
                }

            }
        }

        // Kollektionen der Artikel abfragen
       /// $KollektionValuesDB = Article_Attribute::where('name', '=', 'fee-kollektion')->whereIn('fk_article_id', $ArticleIDsDB)->distinct('value')->pluck('value');
       /// foreach($KollektionValuesDB as $Kollektion)
       /// {   if($Kollektion != ""){ $shop_sorting_unselected[] = ['type' => "Kollektion", 'name' => $Kollektion, 'selected' => 0,'sai_id' =>$Kollektion,'sai_nr' =>"" ]; } }
        
        $fieldsets = ['shop_sorting_selected'=>$shop_sorting_selected,'shop_sorting_unselected'=>$shop_sorting_unselected,'provider_id'=>$provider->id]; 
        //dd($fieldsets);
        return $fieldsets;
    }

}