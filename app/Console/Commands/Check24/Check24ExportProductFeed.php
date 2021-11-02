<?php

namespace App\Console\Commands\Check24;

use App\Manager\Plattform\PlattformManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use App\Tenant, App\Tenant\Branch;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Attribute;
use App\Tenant\Category;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\WaWi;
use Storage, Config;
use Log;
use Illuminate\Support\Facades\Artisan;
use League\Csv\Writer;
use stdClass;

class Check24ExportProductFeed extends Command
{
    protected $signature = 'export:check24_export_productfeed {customer}';
    protected $description = 'Exportiert zu einem bestimmten Kunden den Productfeed';


    public function __construct()
    {
        parent::__construct();


    }

    protected $customer;
    protected $csv_path;
    protected $provider=null;

    protected function loadProvider(){
        $this->provider=Provider::query()->whereHas('type',function($query){
            $query->where('provider_key','check24');
        })->first();
    }
    public function handle()
    {
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}
        if(!$exec_for_customer){return;}

        $customer=$exec_for_customer;
        $this->customer=$exec_for_customer;
        //Tenant abgreifen nach dem Customer, also subdomain
        $tenant=Tenant::where('subdomain','=',$customer)->first();

        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');


        $this->loadProvider();



        $this->export_productfeed();
    }
    protected function checkFCAttributes(Builder $query){
        $fc_variation_attributes=[
            'fc_styleGroupId',
            'fc_colorGroupId',
            'fc_styleGroupId',
            'fc_manufacturerAttributes_colorName',
            'fc_fcAttributes_targetGroup',
            'fc_fcAttributes_ageGroup',
            'fc_manufacturerAttributes_size',
            'fc_fcAttributes_material',
            'fc_manufacturerAttributes_colorName',
            'fc_manufacturerAttributes_colorCode'
        ];

        foreach($fc_variation_attributes as $attribute){
            $query->whereHas('attributes',function($query) use($attribute){
                $query->where('name',$attribute);
            });
        }

    }
    public function export_productfeed(){
        $folder=Storage::disk('customers')->path($this->customer . '/check24_export_internal/productfeed.csv');
        $json_path=Storage::disk('customers')->path($this->customer . '/check24_export_internal/productfeed.json');

        Storage::disk('customers')->makeDirectory($this->customer . '/check24_export_internal');

        $writer = Writer::createFromPath($folder, 'w+');

        //Semikolon als Trenner nehmen
        $writer->setDelimiter(';');


        echo $folder;
        //$writer->setNewline('\n');
        /*
        $article_ids=Article_Attribute::query()->where('name','fc_brandName')->select('fk_article_id')
                        ->distinct('fk_article_id')->orderByDesc('updated_at')->limit(5)->get()
                        ->pluck('fk_article_id')->toArray();


        $variations=[];
        foreach($article_ids as $article_id){
            $variations[]=Article_Variation::query()->where('fk_article_id',$article_id)->first();
        }
        */

        /*
        $variation_ids=Article_Variation_Attribute::query()->where('name','fc_fcAttributes_material')
                        ->select('fk_article_variation_id')->distinct('fk_article_variation_id')
                        ->limit(5)->get()->pluck('fk_article_variation_id')->toArray();


        $variations=Article_Variation::query()->whereIn('id',$variation_ids)->get();
        */

        //Hauptquery
        $variations=Article_Variation::query();

        //Wir schränken auf Check24 ein und aktiv!
        $provider_id=Provider::query()->whereHas('type',function($query){
            $query->where('provider_key','check24');
        })->first()->id;

        $provider=Provider::find($provider_id);

        $variations->whereHas('article',function($query)use($provider_id){
            $query->whereHas('provider',function($query)use($provider_id){
                $query->where('fk_provider_id',$provider_id);
                $query->where('active',1);
            });
            $query->where('active',1);
        });

        //Wir müssen einen Bestand haben!
        $variations->whereHas('branches',function($query){
            $query->where('stock','>',0);
        });

        //Wir stellen sicher, dass die Variationsattribute von Fashioncloud unterstützt werden
        //$this->checkFCAttributes($variations);


        $rabatt=0.0;

        /**
         * IN Abhängigkeit vom Kunden aufbauen
         */
        switch($this->customer){
            case "wunderschoen-mode":
                //Wir schränken auf folgende Filialen einDB::delete('delete users where name = ?', ['John'])
                /**
                 * 1. Tommy Aschaffenburg  ==> wawi_number 015
                 * 2. Esprit Landau ==> wawi_number 011
                 * 3. Street One Worms  ==> wawi_number 052
                 */
                $variations->whereHas('branches',function($query){
                    $query->whereHas('branch',function($query){
                        $query->where('wawi_number','019');
                        $query->orWhere('wawi_number','009');
                        $query->orWhere('wawi_number','011');
                        $query->orWhere('wawi_number','012');
                        $query->orWhere('wawi_number','020');
                        $query->orWhere('wawi_number','000');
                        $query->orWhere('wawi_number','003');
                        $query->orWhere('wawi_number','004');
                        $query->orWhere('wawi_number','005');
                        $query->orWhere('wawi_number','006');
                        $query->orWhere('wawi_number','013');
                        $query->orWhere('wawi_number','014');
                        $query->orWhere('wawi_number','021');
                        $query->orWhere('wawi_number','010');
                        $query->orWhere('wawi_number','016');
                        $query->orWhere('wawi_number','015');
                        $query->orWhere('wawi_number','007');
                        $query->orWhere('wawi_number','008');
                        $query->orWhere('wawi_number','001');
                        $query->orWhere('wawi_number','002');
                        $query->orWhere('wawi_number','018');
                        $query->orWhere('wawi_number','053');
                        $query->orWhere('wawi_number','050');
                        $query->orWhere('wawi_number','051');
                        $query->orWhere('wawi_number','052');
                        $query->orWhere('wawi_number','070');
                        $query->orWhere('wawi_number','071');
                        $query->orWhere('wawi_number','208');
                        $query->orWhere('wawi_number','204');
                        $query->orWhere('wawi_number','214');
                        $query->orWhere('wawi_number','200');
                        $query->orWhere('wawi_number','215');
                        $query->orWhere('wawi_number','211');
                        $query->orWhere('wawi_number','213');
                        $query->orWhere('wawi_number','212');
                        $query->orWhere('wawi_number','210');

                    });
                });
                //Jetzt den Preis auf mindestens  19,90 Euro einschränken
                $min_price=19.90;
                $variations->whereHas('prices',function($query)use($min_price){
                    $query->where('name','standard')
                    ->whereRaw('convert(replace(value,\',\',\'.\'), decimal(10,2)) >= ?',[$min_price]);
                });
                break;
            case "zoergiebel":
                //Preis auf mindestens 30$ setzen
                $min_price=30.00;
                $variations->whereHas('prices',function($query)use($min_price){
                    $query->where('name','standard')
                    ->whereRaw('convert(replace(value,\',\',\'.\'), decimal(10,2)) >= ?',[$min_price]);
                });

                break;
            case "schwoeppe":
                //Preis auf mindestens 30$ setzen
                $min_price=25.00;
                $variations->whereHas('prices',function($query)use($min_price){
                    $query->where('name','standard')
                    ->whereRaw('convert(replace(value,\',\',\'.\'), decimal(10,2)) >= ?',[$min_price]);
                });



                break;
            case "melchior":
                //Preis auf mindestens 30$ setzen
                $min_price=30.00;
                $variations->whereHas('prices',function($query)use($min_price){
                    $query->where('name','standard')
                    ->whereRaw('convert(replace(value,\',\',\'.\'), decimal(10,2)) >= ?',[$min_price]);
                });
                break;
        }



        //Anzahl der zu erwartenden Datensätze ausgeben
        $this->info("Der Kunde \"{$this->customer}\" wird voraussichtlich {$variations->count('*')} Variationen nach Check24 exportieren können.");


        //exit;





        //alles holen
        $variations=$variations->get();

        $records=[];
        $no_check24_connection=0;
        foreach($variations as $variation){
            //Check24-Kategorie entnehmen
            /**
             * Das wird ein bisschen komplizierter...da wir jetzt einen umständlichen Weg gehen müssen.
             * Wir müssen an die Warengruppe kommen und darüber die check24-Kategorie nehmen.
             */


             $usual_categorie=$variation->article->categories()
                ->first();
            if(!$usual_categorie){
                $this->info("ARtikel-ID[{$variation->article->id}] ohne Artikel.");
                continue;
            }

            //Wir holen alle Warengruppen!! Let's rock baby....
            $waregroups=[];
            $usual_categories=$variation->article->categories()->get();
            foreach($usual_categories as $usual_categorie){

                foreach($usual_categorie->waregroups as $waregroup)
                {
                    $waregroups[]=$waregroup;
                }

            }

            //Warengruppe holen
            //$waregroups=$usual_categorie->waregroups;

            if(!count($waregroups)){
                $this->info("ARtikel-ID[{$variation->article->id}] ohne Warengruppe.");
                continue;
            }

            //Jetzt Check24 Kategorie holen
            $check24_categorie=null;
            foreach($waregroups as $waregroup){
                $check24_categorie=$waregroup->providerCategories($provider)->first();
                if($check24_categorie){
                    break;
                }
            }


            if(!$check24_categorie){
                $no_check24_connection++;
                $this->info("Wir haben leider keine Verknüpfung zwischen Warengruppe und Check24!! [{$no_check24_connection}]");
                continue;
            }
            $check24_category_id=$check24_categorie->attributes()->where('name','check24_id')->first()->value;


            //#################################
            //Rabattlogik!!!

            $mainAr=$variation->article;
            $rabatt=0.00;

            if($this->customer == 'schwoeppe')
            {

                $categories = $mainAr->categories()->get();
                $waregroups=[];
                foreach($categories as $category){
                    $groups=$category->waregroups;
                    foreach($groups as $group){
                        $waregroups[]=$group;
                    }
                }
                $categories=$waregroups;

                $Warengruppen_ausgeschlossen = ['10023','10021'];

                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();

                if($RabattSaison){switch($RabattSaison->value)
                {   case "2121":
                        $WareGroupsCheck = 0;
                        if($categories){
                            foreach($categories as $Cat){
                                if($Cat && $Cat->wawi_number != null)
                                {
                                    if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen)){$WareGroupsCheck=1;}
                                }
                            }
                        }
                        if($WareGroupsCheck==0){ $rabatt = 10.00; }
                    break;
                    case "2022":
                    case "2021":
                        $WareGroupsCheck = 0;
                        if($categories)
                        {
                            foreach($categories as $Cat)
                            {   if($Cat && $Cat->wawi_number != null)
                                {
                                    if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen))
                                    {
                                        $WareGroupsCheck=1;
                                    }
                                }
                        }
                        }
                        if($WareGroupsCheck==0){ $rabatt = 30.00; }
                    break;
                    case "1921":
                    case "1922":
                    case "1821":
                    case "1822":
                    case "1721":
                    case "1722":
                    case "1621":
                    case "1622":
                    case "1521":
                    case "1522":
                        $WareGroupsCheck = 0;
                        if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen)){$WareGroupsCheck=1;} }  } }
                        if($WareGroupsCheck==0){ $rabatt = 50.00; }
                    break;
                }}
            }
            elseif($this->customer == 'zoergiebel' )
            {
                $rabatt=10.00;
            }


            //##############################




            $record=[

                /*
                ##########################################
                ###   Attribute zum Angebotsdatenfeed  ###
                ##########################################
                */
                //ID
                $variation->vstcl_identifier,

                //Lieferzeit
                '3-4 Werktage',

                //Versandkosten
                '4,99 Eur',

                //Bestand
                $variation->getStock(),



                /*
                ########################################
                ###   Attribute zum Produktdatenfeed ###
                ########################################
                */
                //Produktname, aus der Fashioncloud, ansonsten aus Wawi
                ($variation->article->attributes()->where('name','=','fc_brandName')->first() ?
                $variation->article->attributes()->where('name','=','fc_brandName')->first()->value . ' ' . $variation->article->name :
                $variation->article->attributes()->where('name','=','hersteller')->first()->value . ' ' . $variation->article->name
                ),
                //Produktname ohne Marke
                $variation->article->name,
                //Produktlinie
                '',
                //Variation-Identifier: aus fc_styleGroupId + fc_colorGroupId
                ($variation->attributes()->where('name','fc_styleGroupId')->first() ? $variation->attributes()->where('name','fc_styleGroupId')->first()->value : '')
                . ' ' .
                ($variation->attributes()->where('name','fc_colorGroupId')->first() ? $variation->attributes()->where('name','fc_colorGroupId')->first()->value : ''),
                //Modelidentifier: aus fc_styleGroupId
                ($variation->attributes()->where('name','fc_styleGroupId')->first() ? $variation->attributes()->where('name','fc_styleGroupId')->first()->value : ''),
                //Kategorie ID
                //$variation->article->categories()->first()->id,
                $check24_category_id,
                //Kategoriepfad ausnahmsweise fest eintragen
                $check24_categorie->getCategoryAttributeValuePath('check24_id'),
                //Kategorie-Namenspfad
                $check24_categorie->getCategoryNamePath(),
                //Sekundärkategorie-ID
                '',
                //Kurzbeschreibung
                (!empty($variation->article->short_description) ? "\"" . str_replace("\n","",$variation->article->short_description)  . "\"" : ""),
                //Beschreibung
                "\"" . str_replace("\n","",$variation->article->description) . " \"",
                //Beschreibung freigegeben
                '',
                //Amazon Sales Rank
                '',
                //Unverbindliche Preisempfehlung
                number_format((float) ((float)$variation->getStandardPrice()/100.00 * (100.00-$rabatt) ) ,2,'.','') . ' EUR',
                //Verkaufspreis
                number_format((float) ((float)$variation->getStandardPrice()/100.00 * (100.00-$rabatt) ) ,2,'.','') . ' EUR',
                //Custom Label 3
                '',
                //Custom Label 4
                '',
                //EAN
                str_replace('vstcl-','',$variation->vstcl_identifier),
                //Asin
                '',
                //MPNR: Herstellernummer
                $variation->article->attributes()->where('name','hersteller-nr')->first()->value,
                //SKU
                $variation->vstcl_identifier,
                //'upc'
                '',
            ];

            $images_url='https://' . $this->customer . '.visticle.online/storage';
            //Jetzt die Bilder laden
            //$var_images=$variation->images()->limit(10)->get();
            $var_images=$variation->images()->whereHas('attributes',function($query){
                $query->where('name','imgType')
                    ->where('value','original');
            })->distinct('location')->limit(10)->get();

            //Jetzt holen wuir uns nur eindeutige Bilder
            $locations=[];
            $new_image_list=[];
            foreach($var_images as $var_image){
                if(!in_array($var_image->location,$locations)){
                    $locations[]=$var_image->location;
                    $new_image_list[]=$var_image;
                }
            }
            $var_images=$new_image_list;


            foreach($var_images as $var_image){
                $image_path=$images_url . $var_image->location;
                $record[]=$image_path;
            }
            //Den Rest auffüllen
            $rest=10- count($var_images);
            for($i=1;$i<=$rest;$i++){
                $record[]='';
            }

            //Farbe
            $record[]=($variation->attributes()->where('name','fc_manufacturerAttributes_colorName')
            ->first() ? $variation->attributes()->where('name','fc_manufacturerAttributes_colorName')
            ->first()->value : '');

            //Zielgruppe
            $record[]=($variation->attributes()->where('name','fc_fcAttributes_targetGroup')
            ->first() ? $variation->attributes()->where('name','fc_fcAttributes_targetGroup')
            ->first()->value : '');

            //Altergruppe
            $record[]=($variation->attributes()->where('name','fc_fcAttributes_ageGroup')
            ->first() ? $variation->attributes()->where('name','fc_fcAttributes_ageGroup')
            ->first()->value : '');

            //Größe
            $record[]=($variation->attributes()->where('name','fc_manufacturerAttributes_size')
            ->first() ? $variation->attributes()->where('name','fc_manufacturerAttributes_size')
            ->first()->value : '');

            //Größensystem
            $record[]='';

            //Marke
            $record[]=($variation->article->attributes()->where('name','fc_brandName')
            ->first() ? $variation->article->attributes()->where('name','fc_brandName')
            ->first()->value : $variation->article->attributes()->where('name','=','hersteller')->first()->value);

            //Material, als Obermaterial
            $record[]=($variation->attributes()->where('name','fc_fcAttributes_material')
            ->first() ? $variation->attributes()->where('name','fc_fcAttributes_material')
            ->first()->value : '');

            //Innenfutter
            $record[]='';

            //Passform'
            $record[]='';

            //Verschluss'
            $record[]='';

            //Muster
            $record[]='';

            //Herstellerfarbe
            $record[]=($variation->attributes()->where('name','fc_manufacturerAttributes_colorName')
            ->first() ? $variation->attributes()->where('name','fc_manufacturerAttributes_colorName')
            ->first()->value : '');

            //Norm
            $record[]='';

            //Farbcode
            $record[]=($variation->attributes()->where('name','fc_manufacturerAttributes_colorCode')
            ->first() ? $variation->attributes()->where('name','fc_manufacturerAttributes_colorCode')
            ->first()->value : '');

            //Materialhinweis
            $record[]='';

            //Nachhaltigkeit
            $record[]='';

            //Membran
            $record[]='';

            //Obermaterial Oberer Teil',
            $record[]='';

            //Obermaterial Unterer Teil'
            $record[]='';

            //Obermaterial Rückseite
            $record[]='';

            //Obermaterial Einsatz'
            $record[]='';

            //Obermaterial Ärmel
            $record[]='';

            //Innenmaterial'
            $record[]='';

            //Material Füllung'
            $record[]='';

            //Fütterungsdicke
            $record[]='';

            //Stretchanteil
            $record[]='';

            //Mischverhältnis
            $record[]='';

            //Qualität/Stoffart
            $record[]='';

            //'(Attribut) Pflegehinweise',
            $record[]='';

            //'(Attribut) Schnitt',
            $record[]='';
            //'(Attribut) Sondergröße',
            $record[]='';
            //'(Attribut) Länge',
            $record[]='';
            //'(Attribut) Gesamtlänge (cm)',
            $record[]='';
            //'(Attribut) Ärmellänge (cm)',
            $record[]='';
            //'(Attribut) Ärmelbündchen',
            $record[]='';
            //'(Attribut)  Rückenbreite (cm)',
            $record[]='';
            //'(Attribut)  Beininnenlänge (cm)',
            $record[]='';
            //'(Attribut) Beinaußenlänge (cm)',
            $record[]='';
            //'(Attribut) Beinabschluss',
            $record[]='';
            //'(Attribut) Bundart',
            $record[]='';
            //'(Attribut) Bundhöhe',
            $record[]='';
            //'(Attribut) Bundfalte',
            $record[]='';
            //'(Attribut) Ausschnitt',
            $record[]='';
            //'(Attribut) Kragenweite',
            $record[]='';
            //'(Attribut) Saum',
            $record[]='';
            //'(Attribut) Nähte',
            $record[]='';
            //'(Attribut) Transparenz',
            $record[]='';
            //'(Attribut) Applikationen',
            $record[]='';
            //'(Attribut) Kapuze',
            $record[]='';
            //'(Attribut) Träger',
            $record[]='';
            //'(Attribut) Integrierter Gürtel',
            $record[]='';
            //'(Attribut) Integrierter BH',
            $record[]='';
            //'(Attribut) Fußschlaufen',
            $record[]='';
            //'(Attribut) Daumenschlaufen',
            $record[]='';
            //'(Attribut) Waschung',
            $record[]='';
            //'(Attribut) Sportart',
            $record[]='';
            //'(Attribut) Wassersäule',
            $record[]='';
            //'(Attribut) Technische Funktion',
            $record[]='';
            //'(Attribut) Anzahl Schichten',
            $record[]='';
            //'(Attribut) Technologie',
            $record[]='';
            //'(Attribut) Atmungsaktivität in g/mÂ²/24h',
            $record[]='';
            //'(Attribut) Bauschkraft in cuin',
            $record[]='';
            //'(Attribut) Cup',
            $record[]='';
            //'(Attribut) Körbchengröße',
            $record[]='';
            //'(Attribut) Unterbrustumfang',
            $record[]='';
            //'(Attribut) Bügel',
            $record[]='';
            //'(Attribut) Größenverstellbarkeit',
            $record[]='';
            //'(Attribut) Zwickel',
            $record[]='';
            //'(Attribut) Eingriff',
            $record[]='';
            //'(Attribut) DEN',
            $record[]='';
            //'(Attribut) Multipack',
            $record[]='';
            //'(Attribut) Herstellungsort',
            $record[]='';
            //'(Attribut) Textilkennzeichnung',
            $record[]='';
            //'(Attribut) Breite',
            $record[]='';
            //'(Attribut) Umfang',
            $record[]='';
            //'(Attribut) Taillenumfang (cm)',
            $record[]='';
            //'(Attribut) Hüftumfang (cm)',
            $record[]='';
            //'(Attribut) Brustumfang',
            $record[]='';
            //'(Attribut) Körpergröße (cm)',
            $record[]='';
            //'(Attribut) Alter',
            $record[]='';
            //'(Attribut) Hosenlänge',
            $record[]='';
            //'(Attribut) Unterbrustumfang (cm)',
            $record[]='';
            //'(Attribut) Handbreite (cm)',
            $record[]='';
            //'(Attribut)  Mittelfingerlänge (cm)',
            $record[]='';
            //'(Attribut) Gesäßumfang (cm)',
            $record[]='';
            //'(Attribut) Bundweite',
            $record[]='';
            //'(Attribut) Anlass',
            $record[]='';
            //'(Attribut) Saison',
            $record[]='';
            //'(Attribut) Sonstige',
            $record[]='';
            //'(Attribut) Applikationen',
            $record[]='';
            //'(Attribut) Modestil',
            $record[]='';



            array_push($records,$record);
        }

        //jetzt erstellen wir erst mal den Kopf
        $table_head=[
            'ID',
            'Lieferzeit',
            'Versandkosten',
            'Bestand',
            'Produktname',
            'Produktname ohne Marke',
            'Produktlinie',
            'Variation-Identifier',
            'Model-Identifier',
            'Kategorie-ID',
            'Kategoriepfad',
            'Kategorie-Namenspfad',
            'Sekundärkategorie-IDs',
            'Kurzbeschreibung',
            'Ausführliche Beschreibung',
            'Beschreibung freigegeben',
            'Amazon Sales Rank',
            'Unverbindliche Preisempfehlung',
            'Verkaufspreis',
            'Custom Label 3',
            'Custom Label 4',
            'EAN',
            'ASIN',
            'MPNR',
            'SKU',
            'UPC',
            'Bild-URL #1',
            'Bild-URL #2',
            'Bild-URL #3',
            'Bild-URL #4',
            'Bild-URL #5',
            'Bild-URL #6',
            'Bild-URL #7',
            'Bild-URL #8',
            'Bild-URL #9',
            'Bild-URL #10',
            '(Attribut) Farbe',
            '(Attribut) Geschlecht',
            '(Attribut) Altersgruppe',
            '(Attribut) Größe',
            '(Attribut) Größensystem',
            '(Attribut) Marke',
            '(Attribut) Obermaterial',
            '(Attribut) Innenfutter',
            '(Attribut) Passform',
            '(Attribut) Verschluss',
            '(Attribut) Muster',
            '(Attribut) Herstellerfarbe',
            '(Attribut) Norm',
            '(Attribut) Farbcode',
            '(Attribut) Materialhinweis',
            '(Attribut) Nachhaltigkeit',
            '(Attribut) Membran',
            '(Attribut) Obermaterial Oberer Teil',
            '(Attribut) Obermaterial Unterer Teil',
            '(Attribut) Obermaterial Rückseite',
            '(Attribut) Obermaterial Einsatz',
            '(Attribut) Obermaterial Ärmel',
            '(Attribut) Innenmaterial',
            '(Attribut) Material Füllung',
            '(Attribut) Fütterungsdicke',
            '(Attribut) Stretchanteil',
            '(Attribut) Mischverhältnis',
            '(Attribut) Qualität/Stoffart',
            '(Attribut) Pflegehinweise',
            '(Attribut) Schnitt',
            '(Attribut) Sondergröße',
            '(Attribut) Länge',
            '(Attribut) Gesamtlänge (cm)',
            '(Attribut) Ärmellänge (cm)',
            '(Attribut) Ärmelbündchen',
            '(Attribut)  Rückenbreite (cm)',
            '(Attribut)  Beininnenlänge (cm)',
            '(Attribut) Beinaußenlänge (cm)',
            '(Attribut) Beinabschluss',
            '(Attribut) Bundart',
            '(Attribut) Bundhöhe',
            '(Attribut) Bundfalte',
            '(Attribut) Ausschnitt',
            '(Attribut) Kragenweite',
            '(Attribut) Saum',
            '(Attribut) Nähte',
            '(Attribut) Transparenz',
            '(Attribut) Applikationen',
            '(Attribut) Kapuze',
            '(Attribut) Träger',
            '(Attribut) Integrierter Gürtel',
            '(Attribut) Integrierter BH',
            '(Attribut) Fußschlaufen',
            '(Attribut) Daumenschlaufen',
            '(Attribut) Waschung',
            '(Attribut) Sportart',
            '(Attribut) Wassersäule',
            '(Attribut) Technische Funktion',
            '(Attribut) Anzahl Schichten',
            '(Attribut) Technologie',
            '(Attribut) Atmungsaktivität in g/mÂ²/24h',
            '(Attribut) Bauschkraft in cuin',
            '(Attribut) Cup',
            '(Attribut) Körbchengröße',
            '(Attribut) Unterbrustumfang',
            '(Attribut) Bügel',
            '(Attribut) Größenverstellbarkeit',
            '(Attribut) Zwickel',
            '(Attribut) Eingriff',
            '(Attribut) DEN',
            '(Attribut) Multipack',
            '(Attribut) Herstellungsort',
            '(Attribut) Textilkennzeichnung',
            '(Attribut) Breite',
            '(Attribut) Umfang',
            '(Attribut) Taillenumfang (cm)',
            '(Attribut) Hüftumfang (cm)',
            '(Attribut) Brustumfang',
            '(Attribut) Körpergröße (cm)',
            '(Attribut) Alter',
            '(Attribut) Hosenlänge',
            '(Attribut) Unterbrustumfang (cm)',
            '(Attribut) Handbreite (cm)',
            '(Attribut)  Mittelfingerlänge (cm)',
            '(Attribut) Gesäßumfang (cm)',
            '(Attribut) Bundweite',
            '(Attribut) Anlass',
            '(Attribut) Saison',
            '(Attribut) Sonstige',
            '(Attribut) Applikationen',
            '(Attribut) Modestil',
        ];
        $writer->insertOne($table_head);
        $writer->insertAll($records);

        //Nun erstellen wir die Json-Datei
        $this->createJsonFeed($table_head,$records,$json_path);

        //Anzahl der zu erwartenden Datensätze ausgeben
        $count=count($records);
        $this->info("Der Kunde \"{$this->customer}\" konnte {$count} Variationen nach Check24 exportieren.");

        //Nun die die erstellten Dateien nach Check24 kopieren
        if($count){
            $this->csv_path=$folder;
            //$this->warn('Erst mal kein Export!!');
            $this->exportFiles();
        }
    }
    protected function exportFiles()
    {
        //Einstellungen für den Kunden holen
        $plattform_manager=new PlattformManager($this->customer);
        $ftp_settings=$plattform_manager->getPlattformSettings($this->provider,['ftp'=>1]);


        //Ftp-Verbindung
        $ftp_connection=ftp_connect($ftp_settings['host'],$ftp_settings['port']);


        $ftp_settings['connection']=$ftp_connection;


        //FTP-Login
        $login_result=ftp_login($ftp_connection,$ftp_settings['user'],$ftp_settings['password']);

        //Loggen, wenn keine Verbindung aufgebaut werden konnte!
        if(!$ftp_connection || !$login_result){

            ftp_close($ftp_connection);
            return;
        }

        //in das Import-Verzeichnis wechseln
        $export_folder=config('plattform-manager.check24.ftp.export_folder');
        ftp_chdir($ftp_connection,$export_folder);

        //Jetzt die Datei hinschieben
        //$file_name=date('YmdHisu') . '_' . config('plattform-manager.check24.ftp.file_name_productfeed');
        $file_name=config('plattform-manager.check24.ftp.file_name_productfeed');
        $file_transfer_status= ftp_nb_put($ftp_connection,$file_name,$this->csv_path);

        While($file_transfer_status==FTP_MOREDATA){
            $file_transfer_status=ftp_nb_continue($ftp_connection);
        }
        if(!$file_transfer_status==FTP_FINISHED){

            ftp_close($ftp_connection);
            return;
        }

        //am Ende FTP-Verbindung schliessen
        ftp_close($ftp_connection);

        //Dem Plattformmanager mitteilen, dass der Export stattgefunden hat
        $plattform_manager->plattformTellsAction($this->provider,['action'=>'export_productfeed','customer'=>$this->customer]);
    }
    protected function createJsonFeed($heads,$records,$json_path){
        $key_values=[];

        foreach($records as $record){
            $counter=0;
            $json_object=new stdClass();
            foreach($heads as $head){
                $json_object->{$head}=$record[$counter];
                $counter++;
            }
            $key_values[]=$json_object;
        }

        $json_text=json_encode($key_values);

        file_put_contents($json_path,$json_text);
    }
}
