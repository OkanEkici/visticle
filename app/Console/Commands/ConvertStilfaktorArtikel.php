<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant, App\Tenant\Branch, App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Article_Attribute;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Marketing;
use App\Tenant\Article_Image;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\WaWi;
use App\Tenant\Attribute_Set;
use Storage, Config;
use Log;
use Illuminate\Support\Facades\Artisan;

class ConvertStilfaktorArtikel extends Command
{
    protected $signature = 'convert:stilfaktor';
    protected $description = 'convertiert alte form in neue form und löscht alte artikel';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $tenants = Tenant::all();
        $customer = 'demo2';
        //Set DB Connection
        \DB::purge('tenant');
        $tenant = $tenants->where('subdomain','=', $customer)->first();

        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');
        echo "Convertierung Start - ".$customer."\n";

        $articles = Article::where('vstcl_identifier', 'NOT LIKE', '%--%')->get(); 
        $counter=0;

        foreach($articles as $article)  
        {    
			$articleAttributes = $article->attributes()->get();
			$articleImages = $article->images()->get();
			$articleProviders = $article->providerMappings()->get();
			$articleMarketings = $article->marketing()->get();
			$articleCategories = $article->categories()->get();
			
            $variations = $article->variations()->get(); 
            //*   
			echo "\n".$article->vstcl_identifier." ";
			// löschen der alten Einträge
			$thisCounter = 0;
			foreach($variations as $variation) 
            {
				$variationAttributes = $variation->attributes()->get();
				$variationImages = $variation->images()->get();
				$variationPrices = $variation->prices()->get();
				
				// Variation Attribute löschen
				foreach($variationAttributes as $variationAttribute)  
				{$variationAttribute->delete();}
				
				// Variation Images löschen
				foreach($variationImages as $variationImage)  
				{	
					// Variation Attributes löschen
					$variationImageAttributes = $variationImage->attributes()->get();
					foreach($variationImageAttributes as $variationImageAttribute)  
					{ $variationImageAttribute->delete(); }	
					$variationImage->delete();					
				}
				
				// Variation Preise löschen
				foreach($variationPrices as $variationPrice)  
				{$variationPrice->delete();}
				
				// variationen von Branch entfernen
				\DB::connection('tenant')->table('branch_article__variations')->where('fk_article_variation_id','=', $variation->id)->delete();
				
				 \DB::connection('tenant')->table('article__variations')->where('id', $variation->id)->delete();
				//$variation->delete();
				$thisCounter++;
			} echo "[Vars: ".$thisCounter."] ";
			
			// Artikel Attribute löschen
			$thisCounter = 0;
			foreach($articleAttributes as $articleAttribute){ $articleAttribute->delete(); $thisCounter++;}
			echo "[Attrs: ".$thisCounter."] ";
			
            // Artikel Images löschen
			$thisCounter = 0;
			foreach($articleImages as $articleImage)
			{
				$articleImageAttributes = $articleImage->attributes()->get();
				foreach($articleImageAttributes as $articleImageAttribute)  
				{$articleImageAttribute->delete();}
				$articleImage->delete();
				$thisCounter++;
			} echo "[Imgs: ".$thisCounter."] ";
			
			
			// Artikel Providers löschen
			$thisCounter = 0;
			foreach($articleProviders as $articleProvider)  
			{	 \DB::connection('tenant')->table('article_providers')->where('fk_article_id', $article->id)->delete();	
				//$articleProvider->delete();
				$thisCounter++;
			} echo "[APs: ".$thisCounter."] ";
			
			// Artikel Marketings löschen
			$thisCounter = 0;
			foreach($articleMarketings as $articleMarketing)  
			{$articleMarketing->delete(); $thisCounter++;}	
			echo "[AMs: ".$thisCounter."] ";			

			// Kategorieverbindungen löschen
			\DB::connection('tenant')->table('category_article')->where('article_id', $article->id)->delete();	
	
			 \DB::connection('tenant')->table('articles')->where('id', $article->id)->delete();
			//$article->delete();
			
			$counter++;
			
			//*/
			/*
			
			foreach($variations as $variation) 
            { 
                $var_color = $variation->getAttributeValueByKey('fee-color');
                if($var_color && $var_color != "")
                {   
                    $NEUarticles = Article::where('vstcl_identifier', '=', $article->vstcl_identifier."--".$var_color)->get(); 
                    foreach($NEUarticles as $NEUarticle)  
                    {   echo "\n".$NEUarticle->vstcl_identifier;
						
						// Artikel Werte übertragen
						$NEUarticle->name = $article->name;
						$NEUarticle->description = $article->description;
						$NEUarticle->slug = $article->slug;
						$NEUarticle->min_stock = $article->min_stock;
						$NEUarticle->active = $article->active;
						$NEUarticle->sku = $article->sku;
						$NEUarticle->fk_wawi_id = $article->fk_wawi_id;
						$NEUarticle->fk_brand_id = $article->fk_brand_id;
						$NEUarticle->short_description = $article->short_description;
						$NEUarticle->fashioncloud_updated_at = $article->fashioncloud_updated_at;
						$NEUarticle->batch_nr = $article->batch_nr;
						$NEUarticle->webname = $article->webname;
						$NEUarticle->metatitle = $article->metatitle;
						$NEUarticle->keywords = $article->keywords;
						//$NEUarticle->shopware_id = $article->shopware_id;
						$NEUarticle->tax = $article->tax;
						$NEUarticle->type = $article->type;
						$NEUarticle->fk_attributeset_id = $article->fk_attributeset_id;
						$NEUarticle->save();
						
						// Artikel Attribute übertragen
						$thisCounter = 0;
						foreach($articleAttributes as $articleAttribute)  
						{	$thisArticleAttribute = Article_Attribute::updateOrCreate( ['fk_article_id' => $NEUarticle->id ,'name' => $articleAttribute->name ,'value' => $articleAttribute->value ],[]); $thisCounter++;}
						echo "[Attr: ".$thisCounter."] ";
						// Artikel Images übertragen
						$thisCounter = 0;
						foreach($articleImages as $articleImage)  
						{	
							$thisArticleImage = Article_Image::updateOrCreate( 
							['fk_article_id' => $NEUarticle->id 
							,'location' => $articleImage->location 
							,'fashioncloud_id' => $articleImage->fashioncloud_id ],[]); 
							
							// Image Attributes setzen
							$articleImageAttributes = $articleImage->attributes()->get();
							foreach($articleImageAttributes as $articleImageAttribute)  
							{	
								$thisArticleImageAttribute = Article_Image_Attribute::updateOrCreate( 
								['fk_article_image_id' => $thisArticleImage->id 
								,'name' => $articleImageAttribute->name 
								,'value' => $articleImageAttribute->value ],[]); 
							}	
							$thisCounter++;
						}
						echo "[Img: ".$thisCounter."] ";
						
						// Artikel Providers übertragen
						$thisCounter = 0;
						foreach($articleProviders as $articleProvider)  
						{
							$thisArticleProvider = ArticleProvider::updateOrCreate( 
							['fk_article_id' => $NEUarticle->id 
							,'fk_provider_id' => $articleProvider->fk_provider_id 
							,'active' => $articleProvider->active ],[]); 
							$thisCounter++;
						}
						echo "[AP: ".$thisCounter."] ";
						
						// Artikel Marketings übertragen
						$thisCounter = 0;
						foreach($articleMarketings as $articleMarketing)  
						{ 
							$thisArticleMarketing = Article_Marketing::updateOrCreate( 
							['fk_article_id' => $NEUarticle->id 
							,'name' => $articleMarketing->name 
							,'from' => $articleMarketing->from
							,'until' => $articleMarketing->until 
							,'active' => $articleMarketing->active ],[]); 
							$thisCounter++;
						}
						echo "[AM: ".$thisCounter."] ";
						
						// Artikel Kategorien übertragen
						$thisCounter = 0;
						foreach($articleCategories as $articleCategorie)  
						{ 
							$NEUarticle->categories()->syncWithoutDetaching($articleCategorie->id);
							$thisCounter++;
						}
						echo "[Cats: ".$thisCounter."] ";
						$counter++;
						
						// Neue Variation Attribute übernehmen
						$This_variations = $NEUarticle->variations()->get(); 
						
						
						$variationAttributes = $variation->attributes()->get();
						$variationImages = $variation->images()->get();
						
						foreach($This_variations as $This_variation) 
						{	echo "\n[VAR: ".$This_variation->vstcl_identifier."] ";
							if($This_variation->vstcl_identifier == $variation->vstcl_identifier)
							{
								// Variation Attribute übertragen
								$thisCounter = 0;
								foreach($variationAttributes as $variationAttribute)  
								{	$thisVarAttribute = Article_Variation_Attribute::updateOrCreate( ['fk_article_variation_id' => $This_variation->id ,'name' => $variationAttribute->name ,'value' => $variationAttribute->value ],[]); $thisCounter++;}
								echo "[Attr: ".$thisCounter."] ";
							}
							
							// Variation Images übertragen
							$thisCounter = 0;
							foreach($variationImages as $variationImage)  
							{	
								$thisVarImage = Article_Variation_Image::updateOrCreate( 
								['fk_article_variation_id' => $This_variation->id 
								,'location' => $variationImage->location 
								,'loaded' => $variationImage->loaded ],[]); 
								
								// Image Attributes setzen
								$varImageAttributes = $articleImage->attributes()->get();
								foreach($varImageAttributes as $varImageAttribute)  
								{	
									$thisArticleImageAttribute = Article_Variation_Image_Attribute::updateOrCreate( 
									['fk_article_variation_image_id' => $thisVarImage->id 
									,'name' => $varImageAttribute->name 
									,'value' => $varImageAttribute->value ],[]); 
								}	
								$thisCounter++;
							}
							echo "[Img: ".$thisCounter."] ";
						} 
						
						
						
						// Ende Var Attribute
					}
                }
            } //*/
			
        }
        echo "Gesamt: ".$counter."\n";
    }       
}