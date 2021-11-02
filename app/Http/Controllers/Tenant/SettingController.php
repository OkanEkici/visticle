<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

use App\Tenant\Setting;
use App\Tenant\Settings_Attribute;
use App\Tenant\Settings_Type;
use App\Tenant\Article_Attribute;
use App\Tenant\Branch;
use App\Tenant\Synchro;

use App\Tenant\PaymentConditions;
use App\Tenant\PaymentConditionsCustomers;
use App\Tenant\Customer;

use Illuminate\Http\Request;
use Redirect,Response;
use App\Traits\UploadTrait;
use Auth;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    use UploadTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }
    
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  String  $part
     * @return \Illuminate\Http\Response
     */
    public function show($part)
    {  
        $configArray = [];
        switch($part) {
            case 'general':
                $values = Setting::where('fk_settings_type_id','=','1')->first()->attributes();
                $configArray = $this->generalConfigFormArray($values);
            break;
            case 'number':
                $configArray = $this->numberConfigFormArray();
            break;
            case 'receipt':
                $configArray = $this->receiptConfigArray();
            break;
            case 'communication':
                $configArray = $this->communicationConfigArray();
            break;
            case 'partner':
                $configArray = $this->partnerConfigArray();
            break;
            case 'payment':
            break;
            case 'payment_conditions':

                if(request()->ajax()) 
                {
                    $response = datatables()->of(PaymentConditions::select([
                        'id', 'name', 'condition', 'created_at'
                    ]))
                    ->addColumn('action', 'action_button')
                    ->rawColumns(['action'])
                    ->addIndexColumn()
                    ->make(true);
                    $data = $response->getData(true);
                    $data['columnconfig'] = Auth::user()->getTableColumnConfig('payment_conditions');
                   
                    return response()->json($data);
                }

                $configArray = $this->payment_conditionsConfigArray();
            break;
            case 'shipping':
                $configArray = $this->shippingConfigArray();
            break;
        }
        
        return view('tenant.user.settings', [
            'part' => $part,
            'configArray' => $configArray,
            'sideNavConfig' => Setting::sidenavConfig($part)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function edit(Setting $setting)
    {
        //
    }

       
    
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $part)
    {
        switch($part) {
            case 'general':
                $this->updateGeneralConfig($request);
            break;
            case 'number':
                $this->updateNumberRanges($request);
            break;
            case 'receipt':
                $this->updateReceiptConfig($request);
            break;
            case 'communication':
                $this->updateCommunicationConfig($request);
            break;
            case 'partner':
                $this->updatePartnerConfig($request);
            break;
            case 'payment':
            break;
            case 'shipping':
                $this->updateShippingConfig($request);
            break;
        }
        
        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Setting  $setting
     * @return \Illuminate\Http\Response
     */
    public function destroy(Setting $setting)
    {
        //
    }

    protected function getGeneralConfig() {

    }

    protected function generalConfigFormArray($values) {
        $attributeKeys = ['brandname', 'company', 'street', 'postcode', 'city', 'tel', 'fax', 'email', 'domain', 'konto_inhaber', 'iban', 'bic','ust_id'];
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
                    'label' => 'Fax',
                    'name' => 'fax',
                    'type' => 'text',
                    'value' => $arrVals['fax']
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
                [
                    'label' => 'USt-ID',
                    'name' => 'ust_id',
                    'type' => 'text',
                    'value' => $arrVals['ust_id']
                ],
                
            ],
        ];

        $fieldsets[] = [
            'legend' => 'Bankverbindung',
            'form-group' => [
                [
                    'label' => 'Kontoinhaber',
                    'name' => 'konto_inhaber',
                    'type' => 'text',
                    'value' => $arrVals['konto_inhaber']
                ],
                [
                    'label' => 'IBAN',
                    'name' => 'iban',
                    'type' => 'text',
                    'value' => $arrVals['iban']
                ],
                [
                    'label' => 'BIC',
                    'name' => 'bic',
                    'type' => 'text',
                    'value' => $arrVals['bic']
                ],
            ]
        ];
        
        return $fieldsets;
    }

    protected function updateGeneralConfig(Request $request) {
        // Form validation
        $request->validate([
            'ust_id' => 'nullable',
            'company' => 'required',
            'street' => 'required',
            'postcode' => 'required',
            'city' => 'required',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $fillable = ['brandname', 'company', 'street', 'postcode', 'city', 'tel', 'fax', 'email', 'domain', 'logo', 'konto_inhaber', 'iban', 'bic','ust_id'];
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;

        foreach($request->all() as $key => $value) {
            if($key == 'logo' && !is_null($value)) {
                // Get image file
                $image = $request->file('logo');
                // Image name
                $name = 'logo';
                $folder = '/'.$request->attributes->get('identifier').'/img/';
                $filePath = $folder.$name. '.' . $image->getClientOriginalExtension();
                
                $this->uploadOne($image, $folder, 'public', $name);
                $value = $filePath;

            }
            if(in_array($key, $fillable)) {
                Settings_Attribute::updateOrCreate(
                    [
                        'fk_setting_id' => $settingId,
                        'name' => $key,
                    ],
                    [
                        'value' => is_null($value) ? '' : $value,
                    ]
                );
            }
        }
    }

    protected function numberConfigFormArray() 
    {   $Tenant_type = config()->get('tenant.tenant_type');
        
        $attributeKeys = ['invoice', 'credit', 'delivery_note', 'commission', 'packing', 'order', 'order_confirmation', 'supplier', 'partner', 'article', 'sku'];
        $fieldsets = []; $arrVals = [];
        $values = Setting::where('fk_settings_type_id','=','1')->first()->attributes();
        foreach($attributeKeys as $attributeKey) 
        { $arrVals[$attributeKey] = ($values->get()->where('name', '=', 'number_'.$attributeKey)->first()) ? $values->get()->where('name', '=', 'number_'.$attributeKey)->first()->value : ''; }
        
        $fieldsets[] = [ 'legend' => 'Nummernkreise', 'form-group' => [] ];
        $thisIndex = count($fieldsets)-1;

        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Rechnung', 'name' => 'number[invoice]', 'prependText' => 'RE-', 'readonly' => true, 'type' => 'number', 'value' => $arrVals['invoice'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Gutschrift', 'name' => 'number[credit]', 'prependText' => 'GS-', 'readonly' => true, 'type' => 'number', 'value' => $arrVals['credit'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Lieferschein', 'name' => 'number[delivery_note]', 'prependText' => 'LS-', 'required' => true, 'type' => 'number', 'value' => $arrVals['delivery_note'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Kommissionierliste', 'name' => 'number[commission]', 'prependText' => 'KL-', 'required' => true, 'type' => 'number', 'value' => $arrVals['commission'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Packschein', 'name' => 'number[packing]', 'prependText' => 'PS-', 'required' => true, 'type' => 'number', 'value' => $arrVals['packing'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Auftragsnummer', 'name' => 'number[order]', 'prependText' => 'AN-', 'required' => true, 'type' => 'number', 'value' => $arrVals['order'] ];

        if($Tenant_type=='vstcl-industry')
        {   $fieldsets[$thisIndex]['form-group'][]=
            [ 'label' => 'Auftragsbestätigungsnummer', 'name' => 'number[order_confirmation]', 'prependText' => 'AB-', 'required' => true, 'type' => 'number', 'value' => $arrVals['order_confirmation'] ];
        }
        
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'SKU', 'name' => 'number[sku]', 'type' => 'number', 'value' => $arrVals['sku'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Lieferantennummer', 'name' => 'number[supplier]', 'type' => 'number', 'value' => $arrVals['supplier'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Partnernummer', 'name' => 'number[partner]', 'type' => 'number', 'value' => $arrVals['partner'] ];
        $fieldsets[$thisIndex]['form-group'][]=
        [ 'label' => 'Artikelnummer','name' => 'number[article]','type' => 'number','value' => $arrVals['article'] ];

        return $fieldsets;
    }

    protected function updateNumberRanges(Request $request) {
        $fillable = ['delivery_note', 'commission', 'packing', 'order', 'supplier', 'partner', 'article', 'sku'];
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;

        foreach($request->number as $key => $value) {
            if(in_array($key, $fillable)) {
                Settings_Attribute::updateOrCreate(
                    [
                        'fk_setting_id' => $settingId,
                        'name' => 'number_'.$key,
                    ],
                    [
                        'value' => is_null($value) ? '' : $value,
                    ]
                );
            }
        }
    }

    protected function receiptConfigArray() {
        $fieldsets = [];
        $settingType = Settings_Type::where('name','=', 'receipt')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $values = $setting->first()->attributes();
        $fields = [
            'receipt_titles' => [
                'prefix' => 'rec_title',
                'attributes' => [
                    'invoice',
                    'delivery_note',
                    'retoure'
                ]
            ],
            'payment_texts' => [
                'prefix' => 'pay_texts',
                'attributes' => [
                    'vorkasse',
                    'paypal',
                    'rechnung'
                ]
            ],
            'pdf_footer' => [
                'prefix' => 'pdf_footer',
                'attributes' => [
                    'footer_1',
                    'footer_2',
                    'footer_3'
                ]
            ]
        ];

        $arrVals = [];
        foreach($fields as $key => $part) {
            $prefix = $part['prefix'];
            
            $arrVals[$key] = [];
            foreach($part['attributes'] as $attributeKey) {
                $arrVals[$key][$attributeKey] = ($values->get()->where('name', '=', $prefix.'_'.$attributeKey)->first()) ? $values->get()->where('name', '=', $prefix.'_'.$attributeKey)->first()->value : '';
            }
        }

        $fieldsets[] = [
            'legend' => 'Texte für Belege',
            'form-group' => [
                [
                    'label' => 'Rechnung',
                    'type' => 'text',
                    'value' => $arrVals['receipt_titles']['invoice'] ?? '',
                    'name' => 'receipt[receipt_titles][invoice]'
                ],
                [
                    'label' => 'Lieferschein',
                    'type' => 'text',
                    'value' => $arrVals['receipt_titles']['delivery_note'] ?? '',
                    'name' => 'receipt[receipt_titles][delivery_note]'
                ],
                [
                    'label' => 'Retoure',
                    'type' => 'text',
                    'value' => $arrVals['receipt_titles']['retoure'] ?? '',
                    'name' => 'receipt[receipt_titles][retoure]'
                ]
            ],
        ];

        $fieldsets[] = [
            'legend' => 'Zahlungsbedingungen',
            'form-group' => [
                [
                    'label' => 'Vorkasse',
                    'type' => 'text',
                    'value' => $arrVals['payment_texts']['vorkasse'] ?? '',
                    'name' => 'receipt[payment_texts][vorkasse]'
                ],
                [
                    'label' => 'PayPal',
                    'type' => 'text',
                    'value' => $arrVals['payment_texts']['paypal'] ?? '',
                    'name' => 'receipt[payment_texts][paypal]'
                ],
                [
                    'label' => 'Kauf auf Rechnung',
                    'type' => 'text',
                    'value' => $arrVals['payment_texts']['rechnung'] ?? '',
                    'name' => 'receipt[payment_texts][rechnung]'
                ]
            ],
        ];

        $fieldsets[] = [
            'legend' => 'Fußzeile',
            'form-group' => [
                [
                    'label' => 'Footer Spalte 1',
                    'type' => 'text',
                    'value' => $arrVals['pdf_footer']['footer_1'] ?? '',
                    'name' => 'receipt[pdf_footer][footer_1]'
                ],
                [
                    'label' => 'Footer Spalte 2',
                    'type' => 'text',
                    'value' => $arrVals['pdf_footer']['footer_2'] ?? '',
                    'name' => 'receipt[pdf_footer][footer_2]'
                ],
                [
                    'label' => 'Footer Spalte 3',
                    'type' => 'text',
                    'value' => $arrVals['pdf_footer']['footer_3'] ?? '',
                    'name' => 'receipt[pdf_footer][footer_3]'
                ]
            ],
        ];

        return $fieldsets;
    }

    protected function updateReceiptConfig(Request $request) {
        $fields = [
            'receipt_titles' => [
                'prefix' => 'rec_title',
                'attributes' => [
                    'invoice',
                    'delivery_note',
                    'retoure'
                ]
            ],
            'payment_texts' => [
                'prefix' => 'pay_texts',
                'attributes' => [
                    'vorkasse',
                    'paypal',
                    'rechnung'
                ]
            ],
            'pdf_footer' => [
                'prefix' => 'pdf_footer',
                'attributes' => [
                    'footer_1',
                    'footer_2',
                    'footer_3'
                ]
            ]
        ];
        $requestComm = $request->receipt;
        $settingType = Settings_Type::where('name','=', 'receipt')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;
        
        foreach($requestComm as $commKey => $commVal) {
            if(!isset($fields[$commKey])) {
                continue;
            }
            if(is_array($commVal)) {
                foreach($commVal as $settKey => $settVal) {
                    if(in_array($settKey, $fields[$commKey]['attributes'])) {
                        Settings_Attribute::updateOrCreate(
                            [
                                'fk_setting_id' => $settingId,
                                'name' => $fields[$commKey]['prefix'].'_'.$settKey,
                            ],
                            [
                                'value' => is_null($settVal) ? '' : $settVal,
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function communicationConfigArray() {
        $fieldsets = [];
        $settingType = Settings_Type::where('name','=', 'receipt')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $values = $setting->first()->attributes();
        $fields = [
            'email' => [
                'prefix' => 'email',
                'attributes' => [
                    'footer_1',
                    'footer_2',
                    'footer_3'
                ]
            ]
        ];

        $arrVals = [];
        foreach($fields as $key => $part) {
            $prefix = $part['prefix'];
            
            $arrVals[$key] = [];
            foreach($part['attributes'] as $attributeKey) {
                $arrVals[$key][$attributeKey] = ($values->get()->where('name', '=', $prefix.'_'.$attributeKey)->first()) ? $values->get()->where('name', '=', $prefix.'_'.$attributeKey)->first()->value : '';
            }
        }

        //Email
        $fieldsets[] = [
            'legend' => 'Emaileinstellungen',
            'form-group' => [
                [
                    'label' => 'Footer Spalte 1',
                    'type' => 'text',
                    'value' => $arrVals['email']['footer_1'] ?? '',
                    'name' => 'comm[email][footer_1]',
                    'escape' => true,
                ],
                [
                    'label' => 'Footer Spalte 2',
                    'type' => 'text',
                    'value' => $arrVals['email']['footer_2'] ?? '',
                    'name' => 'comm[email][footer_2]',
                    'escape' => true,
                ],
                [
                    'label' => 'Footer Spalte 3',
                    'type' => 'text',
                    'value' => $arrVals['email']['footer_3'] ?? '',
                    'name' => 'comm[email][footer_3]',
                    'escape' => true,
                ]
            ]
        ];

        return $fieldsets;

    }

    protected function updateCommunicationConfig(Request $request) {
        $fields = [
            'email' => [
                'prefix' => 'email',
                'attributes' => [
                    'footer_1',
                    'footer_2',
                    'footer_3'
                ]
            ]
        ];
        $requestComm = $request->comm;
        $settingType = Settings_Type::where('name','=', 'receipt')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;
        
        foreach($requestComm as $commKey => $commVal) {
            if(!isset($fields[$commKey])) {
                continue;
            }
            if(is_array($commVal)) {
                foreach($commVal as $settKey => $settVal) {
                    if(in_array($settKey, $fields[$commKey]['attributes'])) {
                        Settings_Attribute::updateOrCreate(
                            [
                                'fk_setting_id' => $settingId,
                                'name' => $fields[$commKey]['prefix'].'_'.$settKey,
                            ],
                            [
                                'value' => is_null($settVal) ? '' : $settVal,
                            ]
                        );
                    }
                }
            }
        }
    }
    
    protected function payment_conditionsConfigArray() 
    {
        $Payment_Conditions = PaymentConditions::all();
        $content = [];
        foreach($Payment_Conditions as $Payment_Condition) {
            $content[] = [
                $Payment_Condition->id
                ,$Payment_Condition->name
                ,$Payment_Condition->condition
                ,$Payment_Condition->created_at
            ];
        }

        $fieldsets = [
            'type' => 'payment_conditions_datatable',
            'easyTable' => true,
            'firstColumnWidth' => 200,
            'title' => 'Übersicht',
            'search' => ['placeholder' => 'Zahlungsbedingung suchen'],        
            'tableId' => 'payment_conditionsTable',
            'content' => $content,
            'columns' => [
              ' ',
              'Name',
              'Konditionen'
              ,'erstellt am'
            ],
          ];

        return $fieldsets; 
    }

    protected function partnerConfigArray() {
        $fieldsets = [
            'type' => 'bigcards',
            'cards' => [
                [
                    'cardTitle' => 'Fashion Cloud',
                    'cardLink' => route('tenant.user.settings.partner', [config()->get('tenant.identifier'), 'fashioncloud', 'settings']),
                    'cardImgUrl' => '/assets/img/partner/fashion-cloud.png',
                    'description' => 'Aktuelle und hochwertige Marketingmaterialien sowie Produktdaten direkt vom Lieferanten.'
                ],
                [
                    'cardTitle' => 'FEE Warenwirtschaft',
                    'cardLink' => route('tenant.user.settings.partner', [config()->get('tenant.identifier'), 'fee', 'settings']),
                    'cardImgUrl' => '/assets/img/partner/fee.jpg',
                    'description' => 'Die EDV-Lösung für den Textil- und Modehandel'
                ],
                [
                    'cardTitle' => 'Zalando Connected Retail',
                    'cardLink' => route('tenant.user.settings.partner', [config()->get('tenant.identifier'), 'zalando', 'settings']),
                    'cardImgUrl' => '/assets/img/partner/zalando.png',
                    'description' => 'Zeigen Sie Ihre Produkte den Millionen von KundInnen, die jeden Tag auf Zalando einkaufen.'
                ],
            ]
        ];

        return $fieldsets; 
    }

    public function showPartner($partner, $part) {
        $configArray = [];
        $title = '';
        switch($partner) {
            case 'zalando':
                $title = 'Zalando Connected Retail';
                $configArray = $this->partnerConfigZalando($part);
            break;
            case 'fee':
                $title = 'FEE Warenwirtschaft';
                $configArray = $this->partnerConfigFEE($part);
            break;
            case 'fashioncloud':
                $title = 'Fashion Cloud';
                $configArray = $this->partnerConfigFashioncloud($part);
            break;
            default:
            break;
        }
        return view('tenant.user.partner', [
            'partner' => $partner,
            'title' => $title,
            'part' => $part,
            'configArray' => $configArray,
            'sideNavConfig' => Setting::sidenavConfig('partner')
        ]);
    }

    private function partnerConfigZalando($part) {
        $fieldsets = [];
        $arrVals = [];
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $values = $setting->first()->attributes();
        $config = [
            'prefix' => 'za',
            'attributes' => [
                'client_id',
                'client_secret',
                'cr_customer',
                'cr_client_id',
                'cr_api_key',
                'cr_branches',
                'cr_min_qty',
                'cr_brands',
                'cr_min_article_price'
            ]
        ];

        switch($part) {
            case 'settings':
                foreach($config['attributes'] as $attributeKey) {
                    $arrVals[$attributeKey] = ($values->get()->where('name', '=', $config['prefix'].'_'.$attributeKey)->first()) ? $values->get()->where('name', '=', $config['prefix'].'_'.$attributeKey)->first()->value : '';
                }
        
                $fieldsets[] = [
                    'legend' => 'Einstellungen',
                    'form-group' => [
                        [
                            'label' => 'Connected Retail Partner',
                            'type' => 'checkbox',
                            'options' => [
                                ['name' => 'partner[zalando][cr_customer]', 'label' => 'Aktiv', 'checked' => ($arrVals['cr_customer'] == 'on')]
                            ]
                        ],
                        [
                            'label' => 'Connected Retail Client ID',
                            'type' => 'text',
                            'name' => 'partner[zalando][cr_client_id]',
                            'value' => $arrVals['cr_client_id'] ?? ''
                        ],
                        [
                            'label' => 'Connected Retail Api Key',
                            'type' => 'text',
                            'name' => 'partner[zalando][cr_api_key]',
                            'value' => $arrVals['cr_api_key'] ?? ''
                        ],
                        [
                            'label' => 'Connected Retail Filialen',
                            'type' => 'checkbox',
                            'options' => []
                        ],
                        [
                            'label' => 'Mindestbestand pro Artikel',
                            'type' => 'number',
                            'name' => 'partner[zalando][cr_min_qty]',
                            'value' => $arrVals['cr_min_qty'] ?? ''
                        ],
                    ]
                ];
                $fieldsets[] = [
                    'legend' => 'Filter',
                    'form-group' => [
                        [
                            'label' => 'Marken',
                            'type' => 'checkbox',
                            'options' => []
                        ],
                        [
                            'label' => 'Mindestpreis pro Artikel',
                            'type' => 'number',
                            'name' => 'partner[zalando][cr_min_article_price]',
                            'value' => $arrVals['cr_min_article_price'] ?? ''
                        ]
                    ]
                ];

                
                $configFilialen = $arrVals['cr_branches'] ? unserialize($arrVals['cr_branches']) : [];
        
                $filialen = Branch::all();
                foreach($filialen as $filiale) {
                    $fieldsets[0]['form-group'][3]['options'][] = [
                        'name' => 'partner[zalando][cr_branches]['.$filiale->id.']',
                        'label' => $filiale->name,
                        'checked' => (isset($configFilialen[$filiale->id]))
                    ];
                }

                //Filter
                $tenant = config()->get('tenant.identifier');
                $aktiveTenants = [/*"fischer-stegmaier",*/'basic'];
                if(in_array($tenant,$aktiveTenants) )
                {
                    $fieldsets[] = [ 'legend' => 'Hersteller min. Preise', 'form-group' => [] ];
                    $fieldsets[] = [ 'legend' => 'Hersteller Rabatte', 'form-group' => [] ];
                }

                $configBrands = $arrVals['cr_brands'] ? unserialize($arrVals['cr_brands']) : [];
                $brands = Article_Attribute::where('name','=','hersteller')->distinct('value')->orderBy('value')->pluck('value');
                $brandC = 0;
                foreach($brands as $brand) {
                    $fieldsets[1]['form-group'][0]['options'][] = [
                        'name' => 'partner[zalando][cr_brands]['.$brandC.']',
                        'label' => $brand,
                        'checked' => (in_array($brand, $configBrands)),
                        'value' => $brand
                    ];

                    if( (in_array($brand, $configBrands)) && in_array($tenant,$aktiveTenants) )
                    {
                        $ThisSlug = Str::slug($brand, '_');
                        $thisVal = Settings_Attribute::where('fk_setting_id', '=', $setting->first()->id)
                        ->where('name', '=', $config['prefix'].'_'.'cr_min_price_'.$ThisSlug)->first();

                        $fieldsets[2]['form-group'][count($fieldsets[2]['form-group'])] = [
                            'label' => $brand,'type' => 'number',
                            'name' => 'partner[zalando][cr_min_price_'.$ThisSlug.']',
                            'value' => (($thisVal)? $thisVal->value : '')
                        ];

                        $thisVal = Settings_Attribute::where('fk_setting_id', '=', $setting->first()->id)
                        ->where('name', '=', $config['prefix'].'_'.'cr_sale_percent_'.$ThisSlug)->first();
                        $fieldsets[3]['form-group'][count($fieldsets[3]['form-group'])] = [
                                'label' => $brand,'type' => 'number',
                                'name' => 'partner[zalando][cr_sale_percent_'.$ThisSlug.']',
                                'value' => (($thisVal)? $thisVal->value : '')
                        ];
                    }

                    $brandC++;
                }

            break;
            case 'synchro':
                $content = Synchro::basicTableDataByType('zalando');
                $fieldsets[] = Synchro::basicTable($content);
            break;
            default:
            break;
        }

        return $fieldsets;
    }

    private function partnerConfigFEE($part) {
        $fieldsets = [];
        $arrVals = [];
        $config = [];
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $values = $setting->first()->attributes();
        $config = [
            'prefix' => 'fee',
            'attributes' => [
                'online_branch_id'
            ]
        ];
        switch($part) {
            case 'settings':
                foreach($config['attributes'] as $attributeKey) {
                    $arrVals[$attributeKey] = ($values->get()->where('name', '=', $config['prefix'].'_'.$attributeKey)->first()) ? $values->get()->where('name', '=', $config['prefix'].'_'.$attributeKey)->first()->value : '';
                }
        
                $fieldsets[] = [
                    'legend' => 'Online-Shop - Konfiguration',
                    'form-group' => [
                        [
                            'label' => 'Online Filialnummer',
                            'name' => 'partner[fee][online_branch_id]',
                            'type' => 'text',
                            'value' => $arrVals['online_branch_id'] ?? ''
                        ],
                    ],
                ];
            break;
            case 'synchro':
                $content = Synchro::basicTableDataByType('fee');
                $fieldsets[] = Synchro::basicTable($content);
            break;
            default:
            break;
        }

        return $fieldsets;
    }

    private function partnerConfigFashioncloud($part) {
        $fieldsets = [];
        $arrVals = [];
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $values = $setting->first()->attributes();
        $config = [
            'prefix' => 'fc',
            'attributes' => [
                'api_key'
            ]
        ];

        switch($part) {
            case 'settings':
                foreach($config['attributes'] as $attributeKey) {
                    $arrVals[$attributeKey] = ($values->get()->where('name', '=', $config['prefix'].'_'.$attributeKey)->first()) ? $values->get()->where('name', '=', $config['prefix'].'_'.$attributeKey)->first()->value : '';
                }
        
                $fieldsets[] = [
                    'legend' => 'Fashion Cloud',
                    'form-group' => [
                        [
                            'label' => 'API Key',
                            'name' => 'partner[fashioncloud][api_key]',
                            'type' => 'text',
                            'value' => $arrVals['api_key'] ?? ''
                        ],
                    ],
                ];
            break;
            case 'synchro':
                $content = Synchro::basicTableDataByType('fashioncloud');
                $fieldsets[] = Synchro::basicTable($content);
            break;
            default:
            break;
        }

        return $fieldsets;
    }

    
    

    public function updatePartner(Request $request, $partner, $part) {
        switch($partner) {
            case 'zalando':
                $this->updatePartnerZalando($request, $part);
            break;
            case 'fee':
                $this->updatePartnerFee($request, $part);
            break;
            case 'fashioncloud':
                $this->updatePartnerFashioncloud($request, $part);
            break;
            default:
            break;
        }

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    private function updatePartnerFee(Request $request, $part) {
        $config = [
            'prefix' => 'fee',
            'attributes' => [
                'online_branch_id'
            ]
        ];
        
        $requestPartner = $request->partner;
        $partner = $requestPartner['fee'];
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;

        if(is_array($partner)) {
            foreach($partner as $settKey => $settVal) {
                if(in_array($settKey, $config['attributes'])) {
                    Settings_Attribute::updateOrCreate(
                        [
                            'fk_setting_id' => $settingId,
                            'name' => $config['prefix'].'_'.$settKey,
                        ],
                        [
                            'value' => is_null($settVal) ? '' : $settVal,
                        ]
                    );
                }
            }
        }
    }

    private function updatePartnerZalando(Request $request, $part) {
        $config = [
            'prefix' => 'za',
            'attributes' => [
                'client_id',
                'client_secret',
                'cr_customer',
                'cr_client_id',
                'cr_api_key',
                'cr_branches',
                'cr_min_qty',
                'cr_brands',
                'cr_min_article_price'
            ]
        ];

        $requestPartner = $request->partner;
        $partner = $requestPartner['zalando'];
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;
        
        if(!isset($partner['cr_branches'])) {
            $partner['cr_branches'] = [];
        }
        $partner['cr_branches'] = serialize($partner['cr_branches']);
        if(!isset($partner['cr_brands'])) {
            $partner['cr_brands'] = [];
        }
        $partner['cr_brands'] = serialize($partner['cr_brands']);
        if(!isset($partner['cr_customer'])) {
            $partner['cr_customer'] = 'off';
        }
        
        // Hersteller Min Preis + Sale Prozent speichern
        $tenant = config()->get('tenant.identifier');
    $aktiveTenants = [/*"fischer-stegmaier",*/'basic'];
        if(in_array($tenant,$aktiveTenants) )
        {   $brands = Article_Attribute::where('name','=','hersteller')->distinct('value')->orderBy('value')->pluck('value');            
            foreach($brands as $brand) 
            {   $ThisSlug = Str::slug($brand, '_');
                if( isset($partner['cr_min_price_'.$ThisSlug]) )
                {   Settings_Attribute::updateOrCreate(
                    [ 'fk_setting_id' => $settingId, 'name' => $config['prefix'].'_'.'cr_min_price_'.$ThisSlug ],
                    [ 'value' => is_null($partner['cr_min_price_'.$ThisSlug]) ? '' : $partner['cr_min_price_'.$ThisSlug] ]);
                }
                if( isset($partner['cr_sale_percent_'.$ThisSlug]) )
                {   Settings_Attribute::updateOrCreate(
                    [ 'fk_setting_id' => $settingId, 'name' => $config['prefix'].'_'.'cr_sale_percent_'.$ThisSlug ],
                    [ 'value' => is_null($partner['cr_sale_percent_'.$ThisSlug]) ? '' : $partner['cr_sale_percent_'.$ThisSlug] ]);
                }
            }
        }


        if(is_array($partner)) {
            foreach($partner as $settKey => $settVal) 
            {   if(in_array($settKey, $config['attributes'])) 
                {   Settings_Attribute::updateOrCreate(
                    [ 'fk_setting_id' => $settingId, 'name' => $config['prefix'].'_'.$settKey ],
                    [ 'value' => is_null($settVal) ? '' : $settVal ]);
                }
            }
        }

    }

    private function updatePartnerFashioncloud(Request $request, $part) {
        $config = [
            'prefix' => 'fc',
            'attributes' => [
                'api_key'
            ]
        ];
        
        $requestPartner = $request->partner;
        $partner = $requestPartner['fashioncloud'];
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;

        if(is_array($partner)) {
            foreach($partner as $settKey => $settVal) {
                if(in_array($settKey, $config['attributes'])) {
                    Settings_Attribute::updateOrCreate(
                        [
                            'fk_setting_id' => $settingId,
                            'name' => $config['prefix'].'_'.$settKey,
                        ],
                        [
                            'value' => is_null($settVal) ? '' : $settVal,
                        ]
                    );
                }
            }
        }
    }

    protected function updatePartnerConfig(Request $request) {
        $partner = [
            'fashioncloud' => [
                'prefix' => 'fc',
                'attributes' => [
                    'api_key'
                ]
            ],
            'zalando' => [
                'prefix' => 'za',
                'attributes' => [
                    'client_id',
                    'client_secret',
                    'cr_customer',
                    'cr_client_id',
                    'cr_api_key',
                    'cr_branches',
                    'cr_min_qty'
                ]
            ]
        ];
        $requestPartner = $request->partner;
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingId = $setting->first()->id;
        
        if(!isset($requestPartner['zalando']['cr_branches'])) {
            $requestPartner['zalando']['cr_branches'] = [];
        }
        $requestPartner['zalando']['cr_branches'] = serialize($requestPartner['zalando']['cr_branches']);
        if(!isset($requestPartner['zalando']['cr_customer'])) {
            $requestPartner['zalando']['cr_customer'] = 'off';
        }
        foreach($requestPartner as $partnerKey => $partnerVal) {
            if(!isset($partner[$partnerKey])) {
                continue;
            }
            if(is_array($partnerVal)) {
                foreach($partnerVal as $settKey => $settVal) {
                    if(in_array($settKey, $partner[$partnerKey]['attributes'])) {
                        Settings_Attribute::updateOrCreate(
                            [
                                'fk_setting_id' => $settingId,
                                'name' => $partner[$partnerKey]['prefix'].'_'.$settKey,
                            ],
                            [
                                'value' => is_null($settVal) ? '' : $settVal,
                            ]
                        );
                    }
                }
            }
        }
    }

    public function shippingConfigArray() {
        $fieldsets = [];
        $branches = Branch::all();
        $content = [];

        foreach($branches as $branch) {
            $cols = [
                str_replace('fee-','',$branch->wawi_ident),
                $branch->wawi_number,
                $branch->name
            ];
            //if(Setting::isActiveZalandoCRParnter()) {
                $cols[] = '<input class="form-control" name="branches['.$branch->id.'][zalando_id]" value="'.(($branch->zalando_id || $branch->zalando_id == "0") ? $branch->zalando_id : '').'">';
            //}
            $content[] = $cols;
        }

        $fieldsets[] = [
                'legend' => 'Filialen',
                'form-group' => [
                    [
                        'type' => 'table',
                        'tableData' => [
                            'firstColumnWidth' => 10,
                            'easyTable' => true,
                            'tableId' => 'colorsTable',
                            'columns' => ['Filiale-ID', 'Filiale', 'Filiale-Bezeichnung'],
                            'content' => $content
                        ]     
                    ]
                  ],
        ];

        //if(Setting::isActiveZalandoCRParnter()) {
            $fieldsets[0]['form-group'][0]['tableData']['columns'][] = 'Zalando ID';
        //}

        return $fieldsets;
    }

    protected function updateShippingConfig(Request $request) {
        
        if(!$request->exists('branches')) {
            return;
        }
        $branches = $request->branches;

        foreach($branches as $branchId => $branchVal) {
            $br = Branch::find($branchId);
            $br->zalando_id = $branchVal['zalando_id'];
            $br->save();
        }
    }

    public function create_payment_condition(Request $request)
    {  
        if(request()->ajax()) 
        {
            if(isset($request->all()['condition']))
            { 
                $condition = PaymentConditions::create([
                    'name' => $request->all()['condition']['name']
                    ,'condition' => $request->all()['condition']['condition']
                ]);            
            }
            return response()->json(['success'=>'Erfolgreich gespeichert!']);
        }
        $configArray = [];
        $configArray =[
            'type' => 'payment_conditions_form',
                           
                    'form-group' => 
                    [                        
                        [
                            'label' => 'Name',
                            'name' => 'condition[name]',
                            'type' => 'text'
                        ],
                        
                        [
                            'label' => 'Kondition',
                            'name' => 'condition[condition]',
                            'id' => 'condition_text',
                            'type' => 'textarea'
                        ]                        
                    ]  
        ];
        
        return view('tenant.user.settings', [
            'part' => 'payment_conditions',
            'configArray' => $configArray,
            'sideNavConfig' => Setting::sidenavConfig('payment_conditions')
        ]);
    }

    public function edit_payment_condition(Request $request, $condition_id)
    {

        if(request()->ajax()) 
        {
            if(isset($request->all()['condition']))
            { 
                $condition = PaymentConditions::updateOrCreate(['id' => $condition_id],
                [
                    'name' => $request->all()['condition']['name']
                    ,'condition' => $request->all()['condition']['condition']
                ]);            
            }
            return response()->json(['success'=>'Erfolgreich gespeichert!']);
        }

        $Condition = PaymentConditions::find([$condition_id])->first();
        
        $configArray = [];
        $configArray =[
            'type' => 'edit_payment_conditions_form',
                           
                    'form-group' => 
                    [                        
                        [
                            'label' => 'Name',
                            'name' => 'condition[name]',
                            'type' => 'text'
                            ,'value' => ''.$Condition->name.''

                        ],
                        
                        [
                            'label' => 'Kondition',
                            'name' => 'condition[condition]',
                            'id' => 'condition_text',
                            'type' => 'textarea'
                            ,'value' => ''.$Condition->condition.''
                        ]                        
                    ]  
        ];
        
        return view('tenant.user.settings', [
            'condition_id' => $condition_id,
            'part' => 'payment_conditions',
            'configArray' => $configArray,
            'sideNavConfig' => Setting::sidenavConfig('payment_conditions')
        ]);
    }

    protected function delete_payment_condition(Request $request, $condition_id) 
    {        
        $response = PaymentConditionsCustomers::where('fk_pcondition_id','=',$condition_id)->delete();

        $response = PaymentConditions::where('id','=',$condition_id)->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
    }

    public function show_payment_customer_conditions(Request $request, $condition_id)
    {
        
        if(request()->ajax()) 
        {
            //$payment_customer_conditions = PaymentConditions::find([$condition_id])->first()->customers()->get();

            $response = datatables()->of(Customer::select([
                        'id', 'knr', 'anrede',
                        'anrede', 'vorname', 'nachname',
                        'firma', 'steuernummer', 'email', 'created_at'
                    ]))
                    ->addColumn('status', function(Customer $customer) use ($condition_id) {
                        $paymentcondition = $customer->payment_conditions()->where('fk_pcondition_id','=',$condition_id)->exists();
                        return ($paymentcondition)? "1" : "0";
                    })
                    ->addColumn('action', 'action_button')
                    ->rawColumns(['action'])
                    ->addIndexColumn()
                    ->make(true);
                    $data = $response->getData(true);
                    $data['columnconfig'] = Auth::user()->getTableColumnConfig('customer_conditions');
                   
                    return response()->json($data);
        }

        $Customers = Customer::all();
        $content = [];
        foreach($Customers as $Customer) {
            $content[] = [
                $Customer->id
                ,$Customer->knr
                ,$Customer->anrede
                ,$Customer->vorname
                ,$Customer->nachname
                ,$Customer->firma
                ,$Customer->steuernummer
                ,$Customer->email 
                ,$Customer->created_at
            ];
        }

        $configArray = [
            'type' => 'edit_payment_customer_conditions',
            'easyTable' => true,
            'firstColumnWidth' => 200,
            'title' => 'Übersicht',
            'search' => ['placeholder' => 'Kunde suchen'],        
            'tableId' => 'payment_customer_conditionsTable',
            'content' => $content,
            'columns' => [
              ' ',
              'Kundennummer'
              ,'Anrede'
              ,'Vorname'
              ,'Nachname'
              ,'Firma'
              ,'Steuernummer'
              ,'E-Mail'
              ,'erstellt am'
            ],
          ];

        
        return view('tenant.user.settings', [
            'condition_id' => $condition_id,
            'part' => 'payment_conditions',
            'configArray' => $configArray,
            'sideNavConfig' => Setting::sidenavConfig('payment_conditions')
        ]);
    }

    public function update_payment_customer_conditions(Request $request, $condition_id, $customer_id)
    {
        if(request()->ajax()) 
        {
            $response = PaymentConditionsCustomers::updateOrCreate(
                [
                    'fk_pcondition_id' => $condition_id,
                    'fk_customer_id' => $customer_id
                ]
            );
            $response->status = 1;
            return response()->json($response);
        }
    }

    public function delete_payment_customer_conditions(Request $request, $condition_id, $customer_id)
    {
        if(request()->ajax()) 
        {   $response = PaymentConditionsCustomers::where('fk_pcondition_id','=',$condition_id)
            ->where('fk_customer_id','=',$customer_id)
            ->delete();
            if(!is_object($response)){$response = (object)array("status"=>"1");}            
            return response()->json($response);
        }
    }
    
    

    
}
