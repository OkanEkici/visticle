<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\UploadTrait;
use App\Tenant;
use Config, Storage;
use App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Article_Image_Attribute, App\Tenant\Article_Image;

class ImportShopwareCSV_test extends Command
{

    use UploadTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:shopwarecsv_test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert Artikel und Bestellungen die von Shopware exportiert wurden';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private $customer;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $counter=0;
        $customers = Storage::disk('customers')->directories();
        $tenants = Tenant::all();
        $swCustomers = [
            'demo2',
        ];

        foreach($customers as $customer) {
            if(!in_array($customer, $swCustomers)) {
                continue;
            }

            $customerFolders = Storage::disk('customers')->directories($customer);
            $folderName = 'shopware_import';

            if(!in_array($customer.'/'.$folderName, $customerFolders)) {
                continue;
            }
            $files = Storage::disk('customers')->files($customer.'/'.$folderName);

            //Set DB Connection
            \DB::purge('tenant');
            $tenant = $tenants->where('subdomain','=', $customer)->first();

            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);

            \DB::connection('tenant');
            $this->customer = $customer;
            foreach($files as $file) {
                $basePath = $customer.'/'.$folderName.'/';
                $possibleFiles = [
                    $basePath.'artikel_variationen.csv',
                    $basePath.'bestellungen.csv',
                    $basePath.'bilder.csv',
                    $basePath.'hersteller.csv',
                    $basePath.'kategorien.csv'
                ];
                $activeFile = null;
                if(!in_array($file, $possibleFiles)) {
                    continue;
                }

                //Create temp file and write recent file content
                $processTemp = tmpfile();
                fwrite($processTemp, Storage::disk('customers')->get($file));

                //Read File Content
                $file_content = fopen(stream_get_meta_data($processTemp)['uri'], "r");
                $row = 0;
                echo $file."\n";
                while (($data = fgetcsv($file_content, 0, ";")) !== FALSE) {
                    $row++;
                    //Skip first Row
                    if($row == 1) {
                        continue;
                    }
                    switch($file) {
                        case $basePath.'artikel_variationen.csv':                            
                            $counter += $this->processArticleVariationsData($data,$counter);                            
                        break;
                        case $basePath.'bestellungen.csv':
                            //$this->processOrdersData($data);
                        break;
                        case $basePath.'bilder.csv':
                            //$counter += $this->processImagesData($data,$counter);
                        break;
                        case $basePath.'hersteller.csv':
                            //$this->processProducerData($data);
                        break;
                        case $basePath.'kategorien.csv':
                            //$this->processCategoriesData($data);
                        break;
                    }
                    
                }
                fclose($file_content);
            }
            echo "\n".$counter." Artikel verarbeitet";
        }
    }

    private function processArticleVariationsData($data) 
	{
		//foreach ($data as $key => $value) { $data[$key] = iconv('ISO-8859-1', 'UTF-8', $value); }
        //CSV Fields
        $fields = [
            'articleid'=>0,'ordernumber'=>1,'mainnumber'=>2,'name'=>3,'additionalText'=>4,'supplier'=>5,'tax'=>6,'price_EK'=>7,'pseudoprice'=>8,
            'baseprice_EK'=>9,'from_EK'=>10,'to_EK'=>11,'price_H'=>12,'pseudoprice_H'=>13,'baseprice_H'=>14,'from_H'=>15,'to_H'=>16,'active'=>17,
            'instock'=>18,'stockmin'=>19,'description'=>20,'description_long'=>21,'shippingtime'=>22,'added'=>23,'changed'=>24,'releasedate'=>25,
            'shippingfree'=>26,'topseller'=>27,'keywords'=>28,'metatitle'=>29,'minpurchase'=>30,'purchasesteps'=>31,'maxpurchase'=>32,'purchaseunit'=>33,
            'referenceunit'=>34,'packunit'=>35,'unitID'=>36,'pricegroupID'=>37,'pricegroupActive'=>38,'laststock'=>39,'suppliernumber'=>40,'weight'=>41,
            'width'=>42,'height'=>43,'length'=>44,'ean'=>45,'similiar'=>46,'configuratorsetID'=>47,'configuratortype'=>48,'configuratorOptions'=>49,'variantid'=>50,
            'categories'=>51,'accessory'=>52,'imageUrl'=>53,'main'=>54,'attr1'=>55,'attr2'=>56,'attr3'=>57,'purchasePrice'=>58
        ];

        $shopware_id = $data[$fields['articleid']];

        $article = Article::whereHas('attributes', function($query) use ($shopware_id) {
            $query->where('name','=','eigene-artikelnr')->where('value','=', $shopware_id);
        })->first();

        if(!$article) { return 0; }
		
		$articles = Article::whereHas('attributes', function($query) use ($shopware_id) 
		{ $query->where('name','=','eigene-artikelnr')->where('value','=', $shopware_id); })->get();
		$internCounter = 0;
		foreach($articles as $article) 
		{
			//if($article->webname && $article->webname!=""){echo "[SKIP] ".$article->webname."\n"; continue;}

			echo  "[".$article->id."] ".$article->vstcl_identifier." > ".$data[$fields['name']]."\n";

			//Dont override SW-Id
			$article->updateOrCreateAttribute('sw_id', $shopware_id);
			//*
			$article->update([
				'description' => $data[$fields['description_long']],
				'short_description' => $data[$fields['description']],
				'min_stock' => $data[$fields['stockmin']],
				'webname' => $data[$fields['name']],
				'metatitle' => $data[$fields['metatitle']],
				'keywords' => $data[$fields['keywords']],
			]);

			$ean = (string)floatval(str_replace(',','.',$data[$fields['ean']]));
			$size = $data[$fields['additionalText']];
			$configurator = $data[$fields['configuratorOptions']];
			$size = substr($configurator, 9);
			if(!$size) {
				$variation = false;
			}
			else {
				$variation = $article->variations()->whereHas('attributes', function($query) use ($size) {
					$query->where('name','=','fee-size')->where('value','=',$size);
				})->first();
			}
			
			// UL -> LI erste 3 für Freitextfeld einkürzen
			$checkString = rtrim(rtrim( strip_tags(preg_replace("#<\s*\/?("."p".")\s*[^>]*?>#im", '', $data[$fields['description_long']] ),'<ul><li>'), " " ), "\n");
			$checkString_arr = explode("<ul>",$checkString);
			if(!empty(trim($checkString," ")))
			{	$this_longDescUL_arr = (is_array($checkString_arr) && count($checkString_arr)>1)? "<ul>".$checkString_arr[1] : $this->ul_to_array($checkString);
				$this_Highlights = "<ul>";
				if(is_array($this_longDescUL_arr) && count($this_longDescUL_arr) > 0)
				{	$countLI = 0;
					foreach($this_longDescUL_arr as $this_LI) 
					{	$countLI++; if($countLI>3){continue;}
						$this_Highlights .= "<li>".$this_LI."</li>";
					}
				} $this_Highlights .= "</ul>";				
			}else{$this_Highlights = "<ul></ul>";}
			

			if($variation) {
				$variation->update([
					'min_stock' => $data[$fields['stockmin']]
				]);
				$variation->updateOrCreateAttribute('sw_active', $data[$fields['active']]);
				$variation->updateOrCreateAttribute('sw_ordernumber', $data[$fields['ordernumber']]);
				$variation->updateOrCreateAttribute('sw_mainnumber', $data[$fields['mainnumber']]);
				$variation->updateOrCreateAttribute('sw_additionalText', $data[$fields['additionalText']]);
				$variation->updateOrCreateAttribute('sw_supplier', $data[$fields['supplier']]);
				$variation->updateOrCreateAttribute('sw_tax', $data[$fields['tax']]);
				$variation->updateOrCreateAttribute('sw_shippingtime', $data[$fields['shippingtime']]);
				$variation->updateOrCreateAttribute('sw_added', $data[$fields['added']]);
				$variation->updateOrCreateAttribute('sw_changed', $data[$fields['changed']]);
				$variation->updateOrCreateAttribute('sw_releasedate', $data[$fields['releasedate']]);
				$variation->updateOrCreateAttribute('sw_shippingfree', $data[$fields['shippingfree']]);
				$variation->updateOrCreateAttribute('sw_topseller', $data[$fields['topseller']]);
				$variation->updateOrCreateAttribute('sw_keywords', $data[$fields['keywords']]);
				$variation->updateOrCreateAttribute('sw_metatitle', $data[$fields['metatitle']]);
				$variation->updateOrCreateAttribute('sw_minpurchase', $data[$fields['minpurchase']]);
				$variation->updateOrCreateAttribute('sw_purchasesteps', $data[$fields['purchasesteps']]);
				$variation->updateOrCreateAttribute('sw_maxpurchase', $data[$fields['maxpurchase']]);
				$variation->updateOrCreateAttribute('sw_purchaseunit', $data[$fields['purchaseunit']]);
				$variation->updateOrCreateAttribute('sw_referenceunit', $data[$fields['referenceunit']]);
				$variation->updateOrCreateAttribute('sw_packunit', $data[$fields['packunit']]);
				$variation->updateOrCreateAttribute('sw_unitID', $data[$fields['unitID']]);
				$variation->updateOrCreateAttribute('sw_pricegroupID', $data[$fields['pricegroupID']]);
				$variation->updateOrCreateAttribute('sw_pricegroupActive', $data[$fields['pricegroupActive']]);
				$variation->updateOrCreateAttribute('sw_laststock', $data[$fields['laststock']]);
				$variation->updateOrCreateAttribute('sw_suppliernumber', $data[$fields['suppliernumber']]);
				$variation->updateOrCreateAttribute('sw_weight', $data[$fields['weight']]);
				$variation->updateOrCreateAttribute('sw_width', $data[$fields['width']]);
				$variation->updateOrCreateAttribute('sw_height', $data[$fields['height']]);
				$variation->updateOrCreateAttribute('sw_length', $data[$fields['length']]);
				$variation->updateOrCreateAttribute('sw_similiar', $data[$fields['similiar']]);
				$variation->updateOrCreateAttribute('sw_configuratorsetID', $data[$fields['configuratorsetID']]);
				$variation->updateOrCreateAttribute('sw_configuratortype', $data[$fields['configuratortype']]);
				$variation->updateOrCreateAttribute('sw_configuratorOptions', $data[$fields['configuratorOptions']]);
				$variation->updateOrCreateAttribute('sw_variantid', $data[$fields['variantid']]);
				$variation->updateOrCreateAttribute('sw_categories', $data[$fields['categories']]);
				$variation->updateOrCreateAttribute('sw_accessory', $data[$fields['accessory']]);
				$variation->updateOrCreateAttribute('sw_imageUrl', $data[$fields['imageUrl']]);
				$variation->updateOrCreateAttribute('sw_main', $data[$fields['main']]);
				$variation->updateOrCreateAttribute('sw_attr1', $data[$fields['attr1']]);
				$variation->updateOrCreateAttribute('sw_attr2', $data[$fields['attr2']]);
				$variation->updateOrCreateAttribute('sw_attr3', $data[$fields['attr3']]);
				$variation->updateOrCreateAttribute('sw_purchasePrice', $data[$fields['purchasePrice']]);
				
				$variation->updateOrCreateAttribute('sw_emcgn_highlights', $this_Highlights);
				
				
			}
			$internCounter++;
		}
		
		

        $catIdArray = array_filter(preg_split('/\|/', $data[$fields['categories']]));

        foreach($catIdArray as $catId) {
            //Categories
            $cat = Category::updateOrCreate(
                [
                    'fk_wawi_id' => null,
                    'wawi_number' => $catId
                ],
                [
                    'wawi_name' => ''
                ]
            );
            $article->categories()->syncWithoutDetaching($cat->id);
        }
        
        return $internCounter;
    }

    private function processOrdersData($data) {
        echo 'FINISHED ORDERS';
    }

    private function processImagesData($data) {
        //CSV fields
        $fields = [
            'articleid'=>0,'active'=>1,'main'=>2,'url_all'=>3,'url_1'=>4,'url_all_no_1'=>5,'url_2'=>6,'url_all_no_2'=>7,'url_3'=>8,'url_all_no_3'=>9,
            'url_4'=>10,'url_all_no_4'=>11,'url_5'=>12,'name_1'=>13,'name_2'=>14,'name_3'=>15,'name_4'=>16,'name_5'=>17
        ];

        $shopware_id = $data[$fields['articleid']];

        $article = Article::whereHas('attributes', function($query) use ($shopware_id) {
            $query->where('name','=','eigene-artikelnr')->where('value','=', $shopware_id);
        })->first();

        if(!$article) { return; }
		
		$articles = Article::whereHas('attributes', function($query) use ($shopware_id) 
		{ $query->where('name','=','eigene-artikelnr')->where('value','=', $shopware_id); })->get();
		$internCounter = 0;
		
		foreach($articles as $article) 
		{
			$urls = explode('|', $data[$fields['url_all']]);
			$mains = explode('|', $data[$fields['main']]);
			
			if(!is_array($urls)) { return; }
			if($article->images()->exists()) { return; }
			
			

			$urlCount = 0;
			foreach($urls as $url) 
			{
				$url = str_replace('stilfaktor.de','stilfaktor.de/emcgn', $url);
				$imgName = $article->id.'_base_';
				$folder = '/'.$this->customer.'/img/products/';
		
				$articleImage = Article_Image::create([
					'fk_article_id' => $article->id,
				]);
				
				$context = stream_context_create(array (
					'http' => array (
						'header' => 'Authorization: Basic ' . base64_encode("stilfaktor:stilfaktor2live!")
					)
				));

				file_put_contents(storage_path('app/public').$folder.$imgName.$articleImage->id. '.png', file_get_contents($url, false, $context));

				$articleImage->location = $folder.$imgName.$articleImage->id. '.png';
				$articleImage->update();
				
				echo  "[".$article->id."] ".$imgName.$articleImage->id. '.png'."\n";
		
				Article_Image_Attribute::updateOrCreate(
					['fk_article_image_id' => $articleImage->id, 'name' => 'sw_active'],
					['value' => $data[$fields['active']]]
				);
				
				if(isset($mains[$urlCount])) {
					Article_Image_Attribute::updateOrCreate(
						['fk_article_image_id' => $articleImage->id, 'name' => 'sw_main'],
						['value' => $mains[$urlCount]]
					);
				}
				$urlCount++;
			}
			$internCounter++;
		}

        

    }

    private function processProducerData($data) {
        echo 'FINISHED PRODUCERS';
    }

    private function processCategoriesData($data) {
        //CSV Fields
        $fields = [
            'categoryId'=>0,'parentID'=>1,'description'=>2,'position'=>3,'metatitle'=>4,'metakeywords'=>5,'metadescription'=>6,'cmsheadline'=>7,
            'cmstext'=>8,'template'=>9,'active'=>10,'blog'=>11,'external'=>12,'hidefilter'=>13,'attribute_attribute1'=>14,'attribute_attribute2'=>15,
            'attribute_attribute3'=>16,'attribute_attribute4'=>17,'attribute_attribute5'=>18,'attribute_attribute6'=>19,'CustomerGroup'=>20
        ];

        $parentCat = Category::where('wawi_number','=',$data[$fields['parentID']])->first();

        $category = Category::updateOrCreate(
            [
                'fk_wawi_id' => null,
                'wawi_number' => $data[$fields['categoryId']]
            ],
            [
                'wawi_name' => $data[$fields['description']],
                'name' => $data[$fields['description']],
                'fk_parent_category_id' => (($parentCat) ? $parentCat->id : null),
                'description' => $data[$fields['metadescription']],
                'wawi_description' => $data[$fields['metadescription']]
            ]
        );
    }
	
	private function ul_to_array($ul) 
	{
		$org_ul = $ul;
	  if (is_string($ul)) {
		// encode ampersand appropiately to avoid parsing warnings
		$ul=preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $ul);
		if (!$ul = simplexml_load_string($ul)) {
		  //trigger_error("Syntax error in UL/LI structure");
		  echo "\nFehler: '".$org_ul."'";
		  return FALSE;
		}
		return $this->ul_to_array($ul);
	  } else if (is_object($ul)) {
		$output = array();
		foreach ($ul->li as $li) {
		  $output[] = (isset($li->ul)) ? $this->ul_to_array($li->ul) : (string) $li;
		}
		return $output;
	  } else return FALSE;
	}
}
