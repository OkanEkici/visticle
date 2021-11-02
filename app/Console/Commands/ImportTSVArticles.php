<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant; use Storage, Config;
use App\Tenant\Category;
use App\Tenant\Article;
use Log;

use App\Tenant\Article_Image;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

class ImportTSVArticles extends Command
{
    protected $signature = 'import:tsv_articles {customer} {file_name}';
    // Tinker:  Artisan::call('import:tsv_articles wunderschoen-mode someday_ArtikelStammdatenExport.tsv');
    protected $description = 'Importiert die Artikel aus einer TSV Datei ins Visticle';

    public function __construct(){ parent::__construct(); }
    private function fileExtension($s) { $n = strrpos($s,"."); return ($n===false) ? "" : substr($s,$n+1); }
    private function loadImage($Bildpfad,$EAN, Article $article,$variations = false,$subdomain,$Log=false,$Testing=true) 
    {
        $client = new Client(); 
        $folder = "/".$subdomain.'/img/products/';
        //($article->images()->count()+1)
        
        if($variations)
        {   // Bild für Artikel
            if($article && !$Testing)
            {  $articleImage = Article_Image::updateOrCreate(['fk_article_id' => $article->id,'location' => $Bildpfad ]);}                
            
            $fileName = $article->id."_".$articleImage->id.".".$this->fileExtension($Bildpfad); 
            $filePath = $folder.$fileName;
            $articleImage->location = $filePath;
            $articleImage->save();

            if($variations && !$Testing)
            {   foreach($variations as $variation)
                {   // Bild für Variation
                    $articleVarImage = Article_Variation_Image::updateOrCreate(
                    [ 'fk_article_variation_id' => $variation->id,'location' => $filePath],
                    [ 'loaded' => 1 ]);  
                }
            }
            
            $imgTypes = ['original','200', '512', '1024'];
            $Fail = false;
            foreach($imgTypes as $imgType) 
            {   if($Fail){continue;}
                if($imgType != 'original') { $filePath = $folder.$imgType.'/'.$fileName;}
                
                if($article && $articleImage && !$Testing)
                {   Article_Image_Attribute::updateOrCreate(
                    ['fk_article_image_id' => $articleImage->id, 'name' => 'imgType'],
                    ['value' => $imgType]);
                }
                if($variations && $articleVarImage && !$Testing)
                {   foreach($variations as $variation)
                    {   // Attr für Variation
                        Article_Variation_Image_Attribute::updateOrCreate(
                        ['fk_article_variation_image_id' => $articleVarImage->id, 'name' => 'imgType'],
                        ['value' => $imgType]);
                    }
                }                
                
                if(Storage::disk('public')->exists($filePath)){ if($Log){echo "\n[IMG:bereits vorhanden]";} continue; }
                try {
                    $res = $client->request('GET', $Bildpfad );
                    $status = $res->getStatusCode();
                    if($status != 200) {
                        if($Log){echo "\n[IMGfail:".json_encode($res)."]";}
                        if(!$Testing){   $Fail = true; }
                    }
                    else 
                    { if(!$Testing){Storage::disk('public')->put($filePath, $res->getBody());} 
                        if($Log){echo "[save]";}
                    }                
        
                } catch(GuzzleException $e) {
                    // Bilddatei Download wurde abgelehnt
                    if($Log){echo "\n[IMGfail2:".$e->getMessage()."]";}
                    if(!$Testing){   $Fail = true; }
                }   
                if($Fail){
                    if(!$Testing)
                    {   if($article && $articleImage)
                        {
                            $articleImageAttrs = $articleImage->attributes()->get();
                            foreach($articleImageAttrs as $articleImageAttr)
                            {$articleImageAttr->delete();}
                            $articleImage->delete();
                        }
                        if($variations)
                        {   foreach($variations as $variation)
                            {   // Bilder für Variationen ebenfalls wieder cleanen
                                $articleVarImage = Article_Variation_Image::where('location', '=', $filePath)->where('fk_article_variation_id','=',$variation->id)->first();
                                if($articleVarImage){
                                    $articleVarImageAttrs = $articleVarImage->attributes()->get();
                                    foreach($articleVarImageAttrs as $articleVarImageAttr)
                                    {$articleVarImageAttr->delete();}
                                    $articleVarImage->delete();
                                }
                                // Bilder für Variationen ebenfalls wieder cleanen
                                $articleVarImage = Article_Variation_Image::where('location', '=', $Bildpfad)->where('fk_article_variation_id','=',$variation->id)->first();
                                if($articleVarImage){
                                    $articleVarImageAttrs = $articleVarImage->attributes()->get();
                                    foreach($articleVarImageAttrs as $articleVarImageAttr)
                                    {$articleVarImageAttr->delete();}
                                    $articleVarImage->delete();
                                }
                            }
                        }	 
                    }
                    continue;
                } 
            }
        }
        return true;
    }

    public function handle()
    {   $EchologAktiv=true; $Testing = false;
        $EAN=0;
        $Bildpfad_1=1; $Bildpfad_2=2; $Bildpfad_3=3; 
        $Material=4; $Produkttitel=5; $Beschreibung=6;

        $Farbcode=7; $Farbe=8; $Groesse=9;
        $Pflegehinweise=10; $WarengruppeNr=11; $Warengruppe=12; $Zielgruppe=13;
        $Modelnummer=14; $Label=15; $UVP=16;

        $customer = $this->argument('customer'); if($customer=="false"){$customer=false;}
        $file_name = $this->argument('file_name'); if($file_name=="false"){$file_name=false;}
        if(!$file_name) { if($EchologAktiv){echo "\n".'ImportTSVArticles | keine Import Datei angegeben: '.$customer;}
            Log::channel('single')->info('ImportTSVArticles | keine Import Datei angegeben: '.$customer); 
        }
        $importFolder = "import-data";

        $tenants = Tenant::inRandomOrder()->get();
        $tenantTeams = [];
        foreach ($tenants as $tenant) { $tenantTeams[] = $tenant->subdomain; }
        if(!in_array($customer, $tenantTeams)) { 
            if($EchologAktiv){echo "\n"."ImportTSVArticles | ".$customer." existiert nicht als Tenant!";}
            Log::info("ImportTSVArticles | ".$customer." existiert nicht als Tenant!"); return; 
        }
        $tenant = Tenant::where('subdomain', '=', $customer)->first();
        if(!$tenant) {   
            if($EchologAktiv){echo "\n".'ImportTSVArticles Customer not found as Visticle Tenant: '.$customer;}
            Log::channel('single')->info('ImportTSVArticles Customer not found as Visticle Tenant: '.$customer); return; 
        }
        $import_folder = $customer.'/'.$importFolder;
                        
            $customerFolders = Storage::disk('customers')->directories($customer);
            if(in_array($customer.'/'.$importFolder, $customerFolders)) 
            {   $files = Storage::disk('customers')->files($customer.'/'.$importFolder);
                if(empty($files)) { 
                    if($EchologAktiv){echo "\n".'ImportTSVArticles | keine Import Dateien gefunden: '.$customer.'/'.$importFolder;}
                    Log::channel('single')->info('ImportTSVArticles | keine Import Dateien gefunden: '.$customer.'/'.$importFolder); return; 
                }                
                //Sort files to process oldest first
                usort($files, function($a, $b) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });
                // Check Files exist
                $ArticlesTSV = false; $CountSuccess = 0;$CountFail = 0;
                foreach($files as $fileName) 
                {   if(strpos((String)$fileName, (String)$file_name))
                    {
                        $csv = array_map('str_getcsv', file(Storage::disk('customers')->path($fileName)) );
						$file = fopen(Storage::disk('customers')->path($fileName),"r");
                        if ($file) 
                        { $row = 0;							
                            while (($line = fgets($file)) !== false) 
                            { $row++; if($row == 1) {continue;}
								$line = mb_convert_encoding($line,'UTF-8', "iso-8859-1");
                                $data = preg_split("/[\t]/", str_replace('"', '', $line));
                                
                                // Check EAN 
                                $variations = []; $article = false;

                                $variations = Article_Variation::where('vstcl_identifier','=','vstcl-'.$data[$EAN])->get();


                                $variation_extra_ean = Article_Variation::where('extra_ean', '=', $data[$EAN])->get();
                                if($variation_extra_ean){$variations = $variation_extra_ean;}

                                if($variations->first())
                                {$article = Article::where('id', '=', $variations->first()->fk_article_id)->first();}
                                
                                if(!$article)
                                {   $article = Article::where('ean', '=', $data[$EAN])->first();
                                    if($article){$variations = $article->variations()->where('vstcl_identifier','=','vstcl-'.$data[$EAN])->get();}
                                }
								
								if(!$article)
                                {   $article = Article::where('vstcl_identifier', '=', 'vstcl-'.$data[$EAN])->first();
                                    if($article){$variations = $article->variations()->where('vstcl_identifier','=','vstcl-'.$data[$EAN])->get();}
                                }

                                if($article && $variations->first())
                                {   $changes = false;
                                    if($article)
                                    {
                                        // Kurzbeschreibung
                                        if(($data[$Material]!=' ') &&($data[$Material]!='') && !empty($data[$Material]) && !strpos((String)$data[$Material],(String)$article->short_description ))
                                        { $article->short_description = $data[$Material]."<br>".$article->short_description; $changes = true; }
                                        // Web Name
                                        if($article->webname != $data[$Produkttitel])
                                        { $article->webname = $data[$Produkttitel]; $changes = true; }
                                        // Beschreibung
                                        if($article->description != $data[$Beschreibung])
                                        { $article->description = $data[$Beschreibung]; $changes = true; }
                                    }
                                    if($changes){if(!$Testing){$article->save();}}

                                    if($data[$Bildpfad_1] != "")
                                    {   $ergebnis = $this->loadImage($data[$Bildpfad_1],$data[$EAN], $article,$variations,$customer,$EchologAktiv,$Testing);
                                        $changes = true;
                                    }
                                    if($data[$Bildpfad_2] != "")
                                    {   $ergebnis = $this->loadImage($data[$Bildpfad_2],$data[$EAN], $article,$variations,$customer,$EchologAktiv,$Testing);
                                        $changes = true;
                                    }
                                    if($data[$Bildpfad_3] != "")
                                    {   $ergebnis = $this->loadImage($data[$Bildpfad_3],$data[$EAN], $article,$variations,$customer,$EchologAktiv,$Testing);
                                        $changes = true;
                                    }

                                    if($EchologAktiv && $changes){$article->save();$CountSuccess++;echo "\n[A(".$article->id.")]";}
                                }else{$CountFail++;if($EchologAktiv){echo "\n [A nicht gefunden (".json_encode($article)."|".json_encode($variations).") (EAN: ".$data[$EAN].")]";}}								
							}
							fclose($file);
                            echo "\nFertig\n\nUpdates: ".$CountSuccess."\nFails: ".$CountFail;
						}						
                }  }              
            }

    }
}
