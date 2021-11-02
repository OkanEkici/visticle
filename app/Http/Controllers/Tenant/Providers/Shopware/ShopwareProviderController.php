<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware;

use App\Http\Controllers\Controller;
use App\Tenant\Setting;
use App\Tenant\Provider;
use App\Tenant\Provider_Config;
use App\Tenant\Provider_Config_Attribute;
use App\Tenant\Branch;
use Illuminate\Http\Request;

class ShopwareProviderController extends Controller {

    private $menuStructure = [
        'general' => 'Stammdaten',
        'articles' => 'Artikelliste',
        'synchro' => 'Synchronisation',
        'settings' => 'Einstellungen'
    ];

    public function __construct() { }

    public function getConfigFormArray($provider, $part) {
        $configArray = [];
        switch($part) {
            case 'general': $configArray = $this->generalConfigFormArray($provider); break;
            case 'articles': $configArray = $this->articlesConfigFormArray($provider); break;
            case 'branches': break;
            case 'synchro': break;
            case 'settings': $configArray = $this->settingsConfigFormArray($provider); break;
            default: break;
        }
        return $configArray;
    }

    public function updateProvider(Request $request, $provider, $part) {
        switch($part) {
            case 'general': break;
            case 'articles': break;
            case 'branches': break;
            case 'synchro': break;
            case 'settings': $this->updateSettingsConfig($request, $provider); break;
            default: break;
        }
    }

    protected function generalConfigFormArray($provider) {
        $attributeKeys = ['brandname', 'company', 'street', 'postcode', 'city', 'tel', 'email', 'domain'];
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $values = $providerConfig->attributes();
        $arrVals = [];
        foreach($attributeKeys as $attributeKey) {
            $arrVals[$attributeKey] = ($values->get()->where('name', '=', $attributeKey)->first()) ? $values->get()->where('name', '=', $attributeKey)->first()->value : '';
        }
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
                    'helptext' => 'Ihr Firmenname',
                    'value' => $arrVals['company']
                ],
                [
                    'label' => 'Straße und Hausnummer',
                    'name' => 'street',
                    'type' => 'text',
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
                    'value' => $arrVals['postcode']
                ],
                [
                    'label' => 'Ort',
                    'name' => 'city',
                    'type' => 'text',
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

    protected function articlesConfigFormArray($provider) {
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

    private function settingsConfigFormArray($provider) {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $apiKey = $providerConfig->attributes()->where('name', '=', 'api_key')->first();
        $shopUrl = $providerConfig->attributes()->where('name', '=', 'shop_url')->first();
        $shopUser = $providerConfig->attributes()->where('name', '=', 'shop_user')->first();
        $formFields = [
            [
                'legend' => 'Einstellungen',
                'form-group' => [
                    [
                        'label' => 'Shopware URL',
                        'type' => 'text',
                        'name' => 'shop_url',
                        'value' => ($shopUrl) ? $shopUrl->value : ''
                    ],
                    [
                        'label' => 'Shopware Benutzername',
                        'type' => 'text',
                        'name' => 'shop_user',
                        'value' => ($shopUser) ? $shopUser->value : ''
                    ],
                    [
                        'label' => 'Shopware API Key',
                        'type' => 'text',
                        'name' => 'api_key',
                        'value' => ($apiKey) ? $apiKey->value : ''
                    ],
                ]
            ]
        ];

        return $formFields;
    }

    private function updateSettingsConfig(Request $request, $provider) {
        $configs = [
            'shop_url' => '',
            'shop_user' => '',
            'api_key' => '',
        ];

        if($request->exists('shop_url')) { $configs['shop_url'] = $request->shop_url; }
        if($request->exists('shop_user')) { $configs['shop_user'] = $request->shop_user; }
        if($request->exists('api_key')) { $configs['api_key'] = $request->api_key; }

        $this->updateProviderConfig($configs, $provider);
    }

    private function updateProviderConfig($configs, $provider) {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        foreach($configs as $configKey => $configVal) {
            Provider_Config_Attribute::updateOrCreate(
                [ 'fk_provider_config_id' => $providerConfig->id, 'name' => $configKey ],
                [ 'value' =>  is_null($configVal) ? '' : $configVal ]
            );
        }
    }

    public function getMenuStructure() { return $this->menuStructure; }


    public function testAPI($providerId) 
    {
        $provider = Provider::find($providerId);
        if($provider->type()->first()->provider_key != 'shopware') 
        { return; }

        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $apiKey = $providerConfig->attributes()->where('name', '=', 'api_key')->first();
        $shopUrl = $providerConfig->attributes()->where('name', '=', 'shop_url')->first();
        $shopUser = $providerConfig->attributes()->where('name', '=', 'shop_user')->first();

        if(!$shopUrl || !$shopUser || !$apiKey) 
        { return; }

        $apiC = new ShopwareAPIController($shopUrl->value, $shopUser->value ,$apiKey->value);
        $apiC->test();
    }

}
