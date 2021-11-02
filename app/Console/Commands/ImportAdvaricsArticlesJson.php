<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Http\Controllers\Tenant\Wawis\Advarics\AdvaricsApiController;
use App\Tenant; use Storage, Config;
use App\Tenant\Category;
use App\Tenant\Article;
use Log;

class ImportAdvaricsArticlesJson extends Command
{
    protected $signature = 'import:advaricsarticles_json {customer}';

    protected $description = 'Importiert die Artikel von Advarics ins Visticle';

    public function __construct()
    { parent::__construct(); }

    public function handle()
    {
        $customer = $this->argument('customer'); if($customer=="false"){$customer=false;}
        $importFolder = "import-data";

        $advaricsCustomers = [ 'zoergiebel' ];

        foreach($advaricsCustomers as $advaricsCustomer) 
        {
            if(!in_array($customer, $advaricsCustomers)) { continue; }

            $tenant = Tenant::where('subdomain', '=', $advaricsCustomer)->first();
            if(!$tenant) 
            {   Log::channel('single')->info('Advarics Customer not found as Visticle Tenant: '.$advaricsCustomer);
                continue;
            }
            //Service Id Check
            if(!$tenant->advarics_service_id || $tenant->advarics_service_id == '') { continue; }
            
            $customerFolders = Storage::disk('customers')->directories($customer);
            if(in_array($customer.'/'.$importFolder, $customerFolders)) 
            {   $files = Storage::disk('customers')->files($customer.'/'.$importFolder);
                if(empty($files)) { continue; }                
                //Sort files to process oldest first
                usort($files, function($a, $b) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });

                // Check Files exist
                $ArticlesJSON = false;
                $CategoriesJSON = false;
                $BrandsJSON = false;
                foreach($files as $fileName) 
                { 
                    if(strpos($fileName, "articles.json")){$ArticlesJSON= json_decode(file_get_contents(Storage::disk('customers')->path($fileName)));} 
                    //if(strpos($fileName, "categories.json")){$CategoriesJSON= file_get_contents(Storage::disk('customers')->path($fileName));} 
                    //if(strpos($fileName, "brands.json")){$BrandsJSON= file_get_contents(Storage::disk('customers')->path($fileName));}             
                }                
                
            }
            if(is_object($ArticlesJSON)) 
            {   if(isset($ArticlesJSON->articles))
                {
                    $apiController = new AdvaricsApiController($tenant->advarics_service_id);
                    echo "\n";
                    foreach($ArticlesJSON->articles as $article)
                    {                           
                        $apiController->createAdvaricsArticle($article,$customer,1);
                        $vistArticle = Article::where('vstcl_identifier', '=', "vstcl-".$article->articleId)->first();
                        if($vistArticle)
                        {
                            $cat = Category::where('wawi_number', '=', $article->goodsGroupNo)
                                ->where('wawi_name', '=', $article->goodsGroupName)->first();
                            foreach($cat->shopcategories()->get() as $shopcat) {
                                $vistArticle->categories()->syncWithoutDetaching($shopcat->id);
                            }
                        } 
                    }
                }
                
            }

        }
    }
}
