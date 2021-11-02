<?php

namespace App\Console\Commands\Check24;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Category;
use App\Tenant\CategoryAttribute;
use App\Tenant\CategoryProvider;
use App\Tenant\Provider;
use App\Tenant\Provider_Type;
use App\Tenant\Setting;
use App\Helpers\Miscellaneous;
use Config;
use Illuminate\Support\Facades\Storage;

class Check24ImportCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:check24_import_categories {customer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert alle Check24-Kategorien aus einer CSV.
                              Der Befehl erwartet den Dateinamen \"check24_kategorien.csv\" 
                              im Ordner \"check24_import_internal\" im jeweiligen Kundenordner.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain=$this->argument('customer');
        $tenant = Tenant::query()->where('subdomain','=',$domain)->first();
        
        if(!$tenant){
            $this->info("Für die angegebene Domain \"{$domain}\" gibt es keinen Kunden!!!");
            return;
        }

        //Datenbank zum Kunden
        Miscellaneous::loadTenantDBConnection($domain);

        //Provider-Typ check24 holen
        $provider_type=Provider_Type::query()->where('provider_key','check24')->first();

        if(!$provider_type){
            $this->info('Der gewünschte Provider-Typ \"check24\" ist nicht im System hinterlegt.');
            return;
        }

        //provider holen!
        $provider=Provider::query()->where('fk_provider_type',$provider_type->id)->first();
        if(!$provider){
            $this->info("Es ist kein Provider für chek24 hinterlegt!");
            return;
        }

        
        //Jetzt schauen wir mal, ob die gewünschte Datei existiert!!
        $path='/'. $domain . '/check24_import_internal/check24_kategorien.csv';
        $exists=Storage::disk('customers')->exists($path);

        if(!$exists){
            $this->info("Die gewünschte Datei \"{$path}\" existiert nicht!");
            return;
        }

        //Jetzt lesen wir die CSV ein!
        $absolute_path=Storage::disk('customers')->path($path);
        $file_handle=fopen($absolute_path,"r");

        if(!$file_handle){
            $this->info("Datei konnte nicht geöffnet werden\n");
            $this->info("Datei-Pfad \"{$absolute_path}\"\n");
        }

        $category_dimensions=[];
        while($row=\fgetcsv($file_handle)){
            //nach Kategorietiefe und namen splitten
            $dimension_and_name=explode('#',$row[0]);
            $dimension=$dimension_and_name[0];
            $category_name=$dimension_and_name[1];
            $category_id_check24=$row[1];

            //Wir schauen, ob es eine Elternkategorie gibt!
            $parent_category=null;
            $parent_category_id=null;
            $parent_category_id_check24=null;
            if( ($dimension-1)>0 ){
                $value_list=$category_dimensions[$dimension-2];
                $parent_category=$value_list['category'];
                $parent_category_id=$parent_category->id;
                $parent_category_id_check24=$value_list['category_id_check24'];
            } 

            //als erstes erstellen wir unsere Kategory
            //Wir müssen aber auch überprüfen, ob wir diese Kategorie bereits angelegt haben
            $count=0;

            $this->info('Abfrage beginnen auf Vorhandensein von Kategorie.');
            $category_query=Category::query()->whereHas('attributes',function($query)use($category_id_check24){
                $query->where('name','check24_id')
                ->where('value',$category_id_check24);
            })->where('name',$category_name);

            $count=$category_query->count();
            $this->info('Abfrage beenden auf Vorhandensein von Kategorie.');
            $category=null;
            if($count){
                $category=$category_query->first();
                $this->info("Die Kategory \"{$category_name}\" mit der Check24-ID \"{$category_id_check24}\" ist bereits vorhanden. Keine Neuanlage!");
                
                //Wir setzen aber trotzdem die Oberkategory
                $category->fk_parent_category_id=$parent_category_id;
                $category->save();
            }
            else{
                $data=[
                    'fk_parent_category_id'=>$parent_category_id,
                    'name'=>$category_name,
                    'slug'=>'',
                ];
                $this->info('Kategorie anlegen');

                //$category=Category::create($data);
                $category=new Category();
                $category->fk_parent_category_id=$parent_category_id;
                $category->name=$category_name;
                $category->slug='';
                $category->save();

                $this->info('Kategorie angelegt');
                

                //Wir weisen der neu erstellten Kategorie noch ein Attribut zu!
                $data=[
                    'fk_category_id'=>$category->id,
                    'name'=>'check24_id',
                    'value'=>$category_id_check24,
                ];
                $category_attribute=CategoryAttribute::create($data);

                $this->info('Kategorie-Attribut angelegt');

                //Wir verknüpfen die Kategorie noch mit einem Provider(check24)
                $data=[
                    'fk_category_id'=>$category->id,
                    'fk_provider_id'=>$provider->id,
                ];
                $category_provider=CategoryProvider::create($data);
               
                $this->info('Kategorie-Provider angelegt');

                $this->info("Die Kategorie \"{$category_name}\" mit der Check24-ID \"{$category_id_check24}\" wurde soeben angelegt.");
            }

            //Jetzt platzieren wir unsere Kategorie noch in die Dimensionsliste
            $data=[
                'category'=>$category,
                'category_id_check24'=>$category_id_check24,
            ];
            $category_dimensions[$dimension-1]=$data;
            
        }

        \fclose($file_handle);


    }
}
