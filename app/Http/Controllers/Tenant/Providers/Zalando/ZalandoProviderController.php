<?php

namespace App\Http\Controllers\Tenant\Providers\Zalando;

use App\Http\Controllers\Controller;
use App\Tenant\Setting;
use App\Tenant\Provider_Config;
use App\Tenant\Provider_Config_Attribute;
use App\Tenant\Branch;
use Illuminate\Http\Request;

class ZalandoProviderController extends Controller {

    private $menuStructure = [
        'general' => 'Stammdaten',
        'articles' => 'Artikelliste',
        'branches' => 'Filialverwaltung',
        'synchro' => 'Synchronisation',
        'settings' => 'Einstellungen'
    ];

    public function __construct() {

    }

    public function getConfigFormArray($provider, $part) {
        $configArray = [];
        switch($part) {
            case 'general':
                $configArray = $this->generalConfigFormArray($provider);
            break;
            case 'articles':
                $configArray = $this->articlesConfigFormArray($provider);
            break;
            case 'branches':
                $configArray = $this->branchesConfigFormArray($provider);
            break;
            case 'synchro':
            break;
            case 'settings':
                $configArray = $this->settingsConfigFormArray($provider);
            break;
            default:
            break;
        }
        return $configArray;
    }

    public function updateProvider(Request $request, $provider, $part) {
        switch($part) {
            case 'general':
            break;
            case 'articles':
            break;
            case 'branches':
                $this->updateBranchesConfig($request, $provider);
            break;
            case 'synchro':
            break;
            case 'settings':
                $this->updateSettingsConfig($request, $provider);
            break;
            default:
            break;
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

    protected function branchesConfigFormArray($provider) {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $branchesConfig = $providerConfig->attributes()->where('name', '=', 'branches_config')->first();
        $branchesMinQty = $providerConfig->attributes()->where('name', '=', 'branches_min_qty')->first();
        $bConfigArray = [];
        
        if($branchesConfig) {
            $bConfigArray = unserialize($branchesConfig->value);
        }


        $formFields = [
            [
                'legend' => 'Filialverwaltung',
                'form-group' => [
                    [
                        'label' => 'Filialen de-/aktivieren',
                        'type' => 'checkbox',
                        'options' => []
                    ],
                    [
                        'label' => 'Mindestbestand pro Artikel',
                        'type' => 'number',
                        'name' => 'branches_min_qty',
                        'value' => ($branchesMinQty) ? $branchesMinQty->value : ''
                    ],
                ]
            ]
        ];

        $branches = Branch::all();
        foreach($branches as $branch) {
            $formFields[0]['form-group'][0]['options'][] = [
                'name' => 'active_branches['.$branch->id.']',
                'label' => $branch->name,
                'checked' => (isset($bConfigArray[$branch->id]))
            ];
        }

        return $formFields;
    }

    protected function updateBranchesConfig(Request $request, $provider) {
        $configs = [
            'branches_config' => [],
            'branches_min_qty' => 0
        ];

        if($request->exists('active_branches')) {
            $configs['branches_config'] = $request->active_branches;
        }

        $configs['branches_config'] = serialize($configs['branches_config']);
        $configs['branches_min_qty'] = $request->branches_min_qty;

        $this->updateProviderConfig($configs, $provider);
    }

    protected function settingsConfigFormArray($provider) {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        $activeExport = $providerConfig->attributes()->where('name', '=', 'active_export')->first();
        $clientId = $providerConfig->attributes()->where('name', '=', 'cr_client_id')->first();
        $apiKey = $providerConfig->attributes()->where('name', '=', 'cr_api_key')->first();
        $formFields = [
            [
                'legend' => 'Einstellungen',
                'form-group' => [
                    [
                        'label' => 'Export De-/Aktivieren',
                        'type' => 'checkbox',
                        'options' => [
                            ['name' => 'active_export', 'label' => 'Aktiv', 'checked' => ($activeExport && $activeExport->value == "on")]
                        ]
                    ],
                    [
                        'label' => 'Connected Retail Client ID',
                        'type' => 'text',
                        'name' => 'cr_client_id',
                        'value' => $arrVals['cr_client_id'] ?? ''
                    ],
                    [
                        'label' => 'Connected Retail Api Key',
                        'type' => 'text',
                        'name' => 'cr_api_key',
                        'value' => $arrVals['cr_api_key'] ?? ''
                    ],
                ]
            ]
        ];

        return $formFields;
    }

    protected function updateSettingsConfig(Request $request, $provider) {
        $configs = [
            'active_export' => "off",
            'cr_client_id' => '',
            'cr_api_key' => ''
        ];

        if($request->exists('active_export')) {
            $configs['active_export'] = $request->active_export;
        }
        if($request->exists('cr_client_id')) {
            $configs['cr_client_id'] = $request->cr_client_id;
        }
        if($request->exists('cr_api_key')) {
            $configs['cr_api_key'] = $request->cr_api_key;
        }
        $this->updateProviderConfig($configs, $provider);
    }

    private function updateProviderConfig($configs, $provider) {
        $providerConfig = Provider_Config::firstOrCreate(['fk_provider_id' => $provider->id]);
        foreach($configs as $configKey => $configVal) {
            Provider_Config_Attribute::updateOrCreate(
                [
                    'fk_provider_config_id' => $providerConfig->id,
                    'name' => $configKey
                ],
                [
                    'value' =>  is_null($configVal) ? '' : $configVal
                ]
            );
        }
    }

    public function getMenuStructure() {
        return $this->menuStructure;
    }


}