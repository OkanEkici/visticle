<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Exception;

class Setting extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_settings_type_id'];

    public function type() {
        return $this->belongsTo(Settings_Type::class, 'fk_settings_type_id');
    }

    public function attributes() {
        return $this->hasMany(Settings_Attribute::class, 'fk_setting_id');
    }

    /**
     * @author Özsoy Tanju
     * 01.02.2021
     * Hier folgen zwei Methoden, um die client-seitigen Zugangsdaten für das Wix-System zu setzen und zu erfragen.
     * Dem Setter übergibt man ein assoziatives Array und der Getter liefert einem ein assoziatives Array mit folgenden
     * Schlüsseln
     * - refresh_token
     * - instance_id
     */
    public static function setWixCredentials($key_values){
        if(!isset($key_values['refresh_token']) || !isset($key_values['instance_id'])){
            throw new Exception('Die Wix-Credentials sind unvollständig!!');
        }
         //Wir holen uns ein Setting
         $settingType = Settings_Type::where('name','=', 'partner')->first();
         $setting = Setting::where('fk_settings_type_id','=',$settingType->id)->first();

         //Dann aktualisieren oder erstellen wir das Schlüssel-Wertpaar
         //wix_authorization_code
         $keys= [
                 'fk_setting_id'=>$setting->id,
                 'name'=>'wix_refresh_token'
         ];
         $values=['value'=>$key_values['refresh_token'],'updated_at'=>\date('Y-m-d H:i:s')];
         $setting_attribute=
         Settings_Attribute::query()
                 ->updateOrCreate($keys,$values);
        
        //Dann aktualisieren oder erstellen wir das Schlüssel-Wertpaar
         //wix_instance_id
         $keys= [
            'fk_setting_id'=>$setting->id,
            'name'=>'wix_instance_id'
            ];
        $values=['value'=>$key_values['instance_id'],'updated_at'=>\date('Y-m-d H:i:s')];
        $setting_attribute=
        Settings_Attribute::query()
                ->updateOrCreate($keys,$values);

        
    }
    public static function getWixCredentials(){
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);

        //Wir holen jetzt den authorisierungscode
        $setting_authorization_code = $setting->first()->attributes()
                            ->where('name','=','wix_refresh_token')->first();

        //Wir holen jetzt die Instanz-ID
        $setting_instance_id = $setting->first()->attributes()
                            ->where('name','=','wix_instance_id')->first();

        $authorization_code=($setting_authorization_code ? $setting_authorization_code->value : null);
        $instance_id=($setting_instance_id ? $setting_instance_id->value : null);

        $key_values=[
            'refresh_token'=>$authorization_code,
            'instance_id'=>$instance_id
        ];

        $return_value=null;
        if(isset($authorization_code) && isset($instance_id)){
            $return_value=$key_values;
        }

        return $return_value;
    }



    /**
     * @author  Tanju Özsoy
     * 10.02.2021
     * Hier Kommen neue Methoden für Adverics-Einstellungen
     * Es soll der höchste Wert des Updatedatums aus dem letzten Import zurückgegeben werden
     * und abgespeichert werden können
     *
     * @return [null|Carbon\Carbon]
     *
     */
    public static function getAdvericsLastChangedAt() {
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $last_changed_at = $setting->first()->attributes()
                            ->where('name','=','adv-lastChangedAt')->first();

        //hat es kein Datum, so geben wir Null zurück, ansonsten geben wir eine Instanz von
        //Carbon zurück
        if(!$last_changed_at){
            return null;
        }
        $last_changed_at=Carbon::createFromFormat('Y-m-d H:i:s.u',$last_changed_at->value);
        return $last_changed_at;
    }
    /**
     * Undocumented function
     *
     * @param [string] $datetime
     * @return void
     */
    public static function setAdvericsLastChangedAt($datetime){
        //Wir holen uns ein Setting
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id)->first();

        //Dann aktualisieren oder erstellen wir es
        $keys= [
                'fk_setting_id'=>$setting->id,
                'name'=>'adv-lastChangedAt'
        ];
        $values=['value'=>$datetime];
        $setting_attribute=
        Settings_Attribute::query()
                ->updateOrCreate($keys,$values);
    }
    public static function getDebitInfos() {
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $konto_inhaber = $setting->first()->attributes()->get()->where('name','=','konto_inhaber')->first();
        $iban = $setting->first()->attributes()->get()->where('name','=','iban')->first();
        $bic = $setting->first()->attributes()->get()->where('name','=','bic')->first();

        return [
            'konto_inhaber' => ($konto_inhaber) ? $konto_inhaber->value : '',
            'iban' => ($iban) ? $iban->value : '',
            'bic' => ($bic) ? $bic->value : ''
        ];
    }

    public static function getSenderEmailAddress() {
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes()->get()->where('name','=','email')->first();
        return ($settingAttr) ? ($settingAttr->value == '' ? 'info@visticle.online' : $settingAttr->value ) : 'info@visticle.online';
    }

    public static function getSenderTel() {
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes()->get()->where('name','=','tel')->first();
        return ($settingAttr) ? ($settingAttr->value == '' ? '' : $settingAttr->value ) : '';
    }

    public static function getReceiptNameWithNumberByKey(string $key, int $number)
    {   $is_invoice_correction = false;
        //if($key == 'invoice_correction'){$is_invoice_correction = true;$key = 'invoice';}
        $numbers = ['order_confirmation' => 'AB-','invoice' => 'RE-', 'credit' => 'GS-', 'delivery_note' => 'LS-', 'commission' => 'KL-', 'packing' => 'PS-', 'order' => 'AN-', 'article' => 'VSTCL-'
        ,'invoice_correction' => 'GS-'
        ];

        if(!isset($numbers[$key])) { return false; }

        $rangeNumber = Setting::where('fk_settings_type_id','=','1')->first()->attributes()->get()->where('name', '=', 'number_'.$key)->first();
        //if($is_invoice_correction){$key = 'invoice_correction';}
        if(!$number) { $number = 0; }
        if(!$rangeNumber) { return $numbers[$key].(string)($number); }

        return $numbers[$key].(string)( (int)$rangeNumber->value + $number);
    }

    public static function getFashionCloudApiKey() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        if(!$settingType) {
            return null;
        }
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);

        $settingAttr = $setting->first()->attributes();

        $key = $settingAttr->get()->where('name', '=', 'fc_api_key')->first()
            ? $settingAttr->get()->where('name', '=', 'fc_api_key')->first()->value
            : null;

        return $key;
    }

    public static function getZalandoClientCredentials() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $id = $settingAttr->get()->where('name', '=', 'za_client_id')->first()
        ? $settingAttr->get()->where('name', '=', 'za_client_id')->first()->value
        : null;

        $secret = $settingAttr->get()->where('name', '=', 'za_client_secret')->first()
        ? $settingAttr->get()->where('name', '=', 'za_client_secret')->first()->value
        : null;

        return ['client_id' => $id, 'client_secret' => $secret];
    }

    public static function isActiveZalandoCRParnter() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        return $settingAttr->get()->where('name', '=', 'za_cr_customer')->first()
        ? ($settingAttr->get()->where('name', '=', 'za_cr_customer')->first()->value == 'on')
        : false;
    }

    public static function getZalandoCRCredentials() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $id = $settingAttr->get()->where('name', '=', 'za_cr_client_id')->first()
        ? $settingAttr->get()->where('name', '=', 'za_cr_client_id')->first()->value
        : null;

        $api_key = $settingAttr->get()->where('name', '=', 'za_cr_api_key')->first()
        ? $settingAttr->get()->where('name', '=', 'za_cr_api_key')->first()->value
        : null;

        return ['client_id' => $id, 'api_key' => $api_key];
    }

    public static function getZalandoCRBranches() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $branches = $settingAttr->get()->where('name', '=', 'za_cr_branches')->first()
        ? $settingAttr->get()->where('name', '=', 'za_cr_branches')->first()->value
        : serialize([]);


        return unserialize($branches);
    }

    public static function getZalandoCRBrands() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $branches = $settingAttr->get()->where('name', '=', 'za_cr_brands')->first()
        ? $settingAttr->get()->where('name', '=', 'za_cr_brands')->first()->value
        : serialize([]);

        return unserialize($branches);
    }

    public static function getZalandoMinPrice() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $price = $settingAttr->get()->where('name', '=', 'za_cr_min_article_price')->first()
        ? (float)$settingAttr->get()->where('name', '=', 'za_cr_min_article_price')->first()->value
        : 0;

        return $price;
    }

    public static function getZalandoMinQty() {
        $settingType = Settings_Type::where('name','=', 'partner')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $qty = $settingAttr->get()->where('name', '=', 'za_cr_min_qty')->first()
        ? (int)$settingAttr->get()->where('name', '=', 'za_cr_min_qty')->first()->value
        : 0;

        return $qty;
    }

    //Return Footer Content for Footer 1,2 or 3
    public static function getEmailFooter($number = 1) {
        $settingType = Settings_Type::where('name','=', 'receipt')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $content = $settingAttr->get()->where('name', '=', 'email_footer_'.$number)->first()
        ? $settingAttr->get()->where('name', '=', 'email_footer_'.$number)->first()->value
        : '';


        return $content;
    }

    //Return Footer Content for Footer 1,2 or 3
    public static function getPDFFooter($number = 1) {
        $settingType = Settings_Type::where('name','=', 'receipt')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();

        $content = $settingAttr->get()->where('name', '=', 'pdf_footer_footer_'.$number)->first()
        ? $settingAttr->get()->where('name', '=', 'pdf_footer_footer_'.$number)->first()->value
        : '';


        return $content;
    }

    public static function sidenavConfig($active) {
        $config = [
            'general' => [
              'name' => 'Stammdaten',
              'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'general']),
              'iconClass' => 'fas fa-address-card'
            ],
            'number' => [
                'name' => 'Nummernkreise',
                'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'number']),
                'iconClass' => 'fas fa-sort-numeric-up-alt'
            ],
            'receipt' => [
              'name' => 'Belegverwaltung',
              'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'receipt']),
              'iconClass' => 'fas fa-receipt'
            ],
            'communication' => [
                'name' => 'Kommunikation',
                'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'communication']),
                'iconClass' => 'fas fa-satellite-dish'
            ],
            'partner' => [
                'name' => 'Partner',
                'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'partner']),
                'iconClass' => 'fas fa-handshake'
            ],
            'payment' => [
                'name' => 'Zahlungsarten',
                'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'payment']),
                'iconClass' => 'fas fa-money-bill-alt'
            ],
            'payment_conditions' => [
                'name' => 'Zahlungsbedingungen',
                'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'payment_conditions']),
                'iconClass' => 'fas fa-money-bill'
            ],
            'shipping' => [
                'name' => 'Lager und Versand',
                'route' => route('tenant.user.settings', [config()->get('tenant.identifier'), 'shipping']),
                'iconClass' => 'fas fa-warehouse'
            ],
        ];

        if(isset($config[$active])) {
            $config[$active]['isActive'] = true;
        }

        return $config;
    }

}
