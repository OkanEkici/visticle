<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use App\Tenant\Category;
use App\Tenant\Article;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Variation_Image, App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Attribute_Group;
use App\Tenant\Attribute_Set;
use App\Tenant\Sparesets;
use App\Tenant\Sparesets_Articles;
use App\Tenant\Sparesets_SpareArticles;
use App\Tenant\Equipmentsets;
use App\Tenant\Equipmentsets_Articles;
use App\Tenant\Equipmentsets_EquipmentArticles;
use Illuminate\Support\Str;
use Image; use Illuminate\Http\Request;
use Config, Storage, SimpleXLSX;
use Log;
use League\Flysystem\FileNotFoundException;
use App\Http\Middleware\NotFoundHttpException;


class ImportTSCuttingArticles_img_restbilder extends Command
{
    protected $signature = 'import:tscutting_img_restbilder';
    protected $description = 'Importiert die Artikel von TS Cutting ins Visticle';

    public function __construct()
    {
        parent::__construct();
    }
	
    public function handle(Request $request)
    {	$FAILED_IMAGES = []; 
        //CSV Fields
        $ARTIKELNR = 0; $LISTENPREIS = 1; $RABATTGRP = 2; $DESCRIPTION = 3; 
        $NAME1 = 4; // Artikelname
        $NAME2 = 5; // Kurzbeschreibung // unwichtig
        $BILD1 = 6; $BILD2 = 7; $BILD3 = 8;
        $PIKTO1 = 9; $PIKTO2 = 10; $PIKTO3 = 11; $PIKTO4 = 12; $PIKTO5 = 13; $PIKTO6 = 14; $PIKTO7 = 15; $PIKTO8 = 16; $PIKTO9 = 17; 
        $SCHNITTEMPF = 18; $ZUBEHOER = 19; $ERSATZTEILE = 20; $VPE = 21; $GROESSE = 22;

        //$subdomain = 'ts-cutting';
        $subdomain = 'demo3';

        $tenant = Tenant::where('subdomain','=',$subdomain)->first();
        if(!$tenant) { return; }
        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant'); $config['database'] = $tenant->db; $config['username'] = $tenant->db_user; $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config); \DB::connection('tenant');

        $customer = $tenant->subdomain;

        $customerFolders = Storage::disk('customers')->directories($customer);
        $folderName = 'ts_cutting_import';
		$DATAfolderName = 'DATA_korrektur'; 

        if(!in_array($customer.'/'.$folderName, $customerFolders)) { return; }

        $dir = \storage_path().'/customers/'.$subdomain.'/'.$folderName;

        $structure = $this->dirToArray($dir);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($structure), \RecursiveIteratorIterator::SELF_FIRST);
        $count = 0;
        $categories = [];
        $oldDepth = null;
        $startDepth = null;
        $dataFileCount = 0;
        foreach($iterator as $key => $value) {
            $depth = $iterator->getDepth();

            // loop through the subIterators...             
            $keys = array();    
            // in this case i skip the grand parent (numeric array)
            for ($i = $iterator->getDepth()-1; $i>0; $i--) { $keys[] = $iterator->getSubIterator($i)->key(); }

            $r_keys = array_reverse($keys);
            $parentCatName = null;
            $parentCat = null;
            $catCount = 0;
            $filePath = '';
            $type = 'article|';

            foreach($r_keys as $cat) {
                if($parentCatName) {$parentCat = Category::where('name', '=', $parentCatName)->first();}
                //if(preg_match('/^Ersatzteile/', $cat) == 1) {$type .= 'spare|';continue;}
                //if(preg_match('/^Zubehör/', $cat) == 1) {$type .= 'equipment|';}
                $articleCat = Category::where('name', '=', $cat)
                ->where('fk_parent_category_id', '=', (($parentCat) ? $parentCat->id : null))->first();
                
                $filePath .= $cat.'/';
                $parentCatName = $cat;
                $catCount++;
            }

            if(!is_array($value)) {
                if(preg_match('/^Daten/', $value)) 
				{
                    if(!preg_match('/\.xlsx$/i', $value)) { continue; }
                    $dataFileCount++;
                    $folderPath = $filePath;
                    $filePath .= $value;
                    echo $value. "\n";
                    
                    //Create temp file and write recent file content
                    if( $xlsx = SimpleXLSX::parse(\storage_path().'/customers/'.$subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$filePath)) {
                        $row = 0;
                        $customAttrs = [];
                        foreach($xlsx->rows() as $data) 
                        {   $row++;
                        
                            //Skip first Row
                            if($row == 1) {
                                for($i = 21; $i < count($data); $i++) {
                                    if($data[$i] != '') {  $customAttrs[$i] = $data[$i]; } else { break; } }
                                continue;
                            } if($row == 2) { continue; }
                            
                            $article = Article::where('vstcl_identifier','=','vstcl-'.$data[$ARTIKELNR])->first();
                            $variation = Article_Variation::where('vstcl_identifier','=','vstcl-'.$data[$ARTIKELNR])->first();
                            //if($article->type != "spare|"){continue;}
						                            
                            if($article) 
                            { 
                                //echo "\n".'vstcl-'.$data[$ARTIKELNR].": [A+V]";
                                // $article->wasRecentlyCreated
                                /*$alt_images = Article_Image::where('fk_article_id', '=', $article->id )->get();
                                $alt_var_images = Article_Variation_Image::where('fk_article_variation_id', '=', $variation->id )->get();
                                if($alt_images)
                                {
                                    foreach($alt_images as $alt_image) 
                                    {   $alt_images_attrs = Article_Image_Attribute::where('fk_article_image_id', '=', $alt_image->id )->get();
                                        foreach($alt_images_attrs as $alt_images_attr) {$alt_images_attr->delete();}
                                        $alt_image->delete();                                    
                                    } echo "[del old IMG]";
                                }
                                if($alt_var_images)
                                {
                                    foreach($alt_var_images as $alt_var_image) 
                                    {   $alt_var_images_attrs = Article_Variation_Image_Attribute::where('fk_article_variation_image_id', '=', $alt_var_image->id )->get();
                                        foreach($alt_var_images_attrs as $alt_var_images_attr) {$alt_var_images_attr->delete();}
                                        $alt_var_image->delete();                                    
                                    } echo "[del old varIMG]";
                                }*/
                                //if($alt_images && $alt_images->first() && $alt_var_images && $alt_var_images->first()) {echo '[IMG-skip]'."\n"; continue; }

                                for($i = $BILD1; $i <= $PIKTO9; $i++) 
                                {   if($data[$i] == '') { continue; }
                                    
                                        $thisPath = $subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$data[$i];
                                        if( !Storage::disk('customers')->exists($thisPath) ) 
                                        {   $thisPath = $subdomain.'/ausnahmen/'.basename($data[$i], "");
                                            $image = Article_Image::Create([ 'fk_article_id' => $article->id ]);								
                                            $var_image = Article_Variation_Image::Create(['fk_article_variation_id' => $variation->id]);
                                            echo "\n";
                                        }
                                        else{ continue; /* SKIP alle bereits eingelesenen */}
									
                                    try{
										if(!Storage::disk('customers')->exists($thisPath)) 
                                        {   $image->delete();$var_image->delete(); 
											echo "\n [IMG_fail] ".$thisPath; 
											if(!in_array(basename($thisPath, ""),$FAILED_IMAGES)){$FAILED_IMAGES[]=basename($thisPath, "");}
                                            continue; 
                                        }
										
                                        $file = Storage::disk('customers')->get($thisPath);
                                        $imageName = $article->id.'_base_'.$image->id;
                                        Storage::disk('public')->put($subdomain.'/img/products/'.$imageName.'.jpg', $file);
                                                                            
                                        $image->update(['location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg']);
                                        $var_image->update(['location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg']);

                                        if($i > 8) {
                                            Article_Image_Attribute::updateOrCreate(
                                                [   'fk_article_image_id' => $image->id,
                                                    'name' => 'is_pikto'
                                                ],[ 'value' => "on" ]
                                            );
                                            Article_Variation_Image_Attribute::updateOrCreate(
                                                [   'fk_article_variation_image_id' => $var_image->id,
                                                    'name' => 'is_pikto'
                                                ],[ 'value' => "on" ]
                                            );
                                        }
                                        else
                                        { // nur Artikelbilder verarbeiten
                                            Article_Image_Attribute::Create(['fk_article_image_id' => $image->id, 'name' => 'imgType','value' => "original"],[]);										
                                            Article_Variation_Image_Attribute::Create(['fk_article_variation_image_id' => $var_image->id, 'name' => 'imgType','value' => "original"],[]);

                                            // Versionen anlegen
                                            $versions = ['200' => [ 'height' => 200 ],'512' => [ 'height' => 512 ],'1024' => [ 'height' => 1024 ] ];
                                            foreach($versions as $versionKey => $versionVal) 
                                            { 
                                                    Storage::disk('public')->makeDirectory($subdomain.'/img/products/'.$versionKey);
                                                    Storage::disk('public')->put($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg', $file);
                                                    $imageName = $article->id.'_base_'.$image->id;
                                                
                                                    try{
                                                        $img = Image::make( Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg') ) 
                                                        ->encode('jpg', 100)
                                                        ->resize(null, $versionVal['height'], function ($constraint) {
                                                            $constraint->aspectRatio();
                                                            $constraint->upsize();
                                                        })->save(Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'));
                                                        
                                                    }
                                                    catch(NotSupportedException $ex) {echo "\n[IMG konnte nicht convertiert werden] ".$subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'."\n";}
                                                    
                                                    Article_Image_Attribute::Create(['fk_article_image_id' => $image->id, 'name' => 'imgType','value' => $versionKey],[]);										
                                                    Article_Variation_Image_Attribute::Create(['fk_article_variation_image_id' => $var_image->id, 'name' => 'imgType','value' => $versionKey],[]);
                                                    //echo " [IMG ".$versionKey."]";
                                            }
                                        }
                                        echo " [IMG]"; 
                                    }
                                    catch(FileNotFoundException $ex) {
                                        $image->delete(); $var_image->delete(); 
										echo "\n [IMG_fail]".$thisPath;
										if(!in_array(basename($thisPath, ""),$FAILED_IMAGES)){$FAILED_IMAGES[]=basename($thisPath, "");}
                                    }
                                }

                                
                                if($data[$ERSATZTEILE] != '') { //echo "\n".'Spare begins';
                                    if(Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$data[$ERSATZTEILE])) {
                                        if( $sparexlsx = SimpleXLSX::parse(\storage_path().'/customers/'.$subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$data[$ERSATZTEILE])) {
                                            $sparerow = 0;
                                            $sparesets = Equipmentsets::all();
                                            $spareArticleIds = [];
                                            foreach($sparexlsx->rows() as $sparedata) {                         
                                                $sparerow++;
                                                if($sparerow == 1 || $sparerow == 2) {continue;}

                                                $sparearticle = Article::where('vstcl_identifier', '=', 'vstcl-'.$sparedata[$ARTIKELNR])->first();
                                                $sparevariation = Article_Variation::where('vstcl_identifier','=','vstcl-'.$sparedata[$ARTIKELNR])->first();
                                                
                                                //echo "\n".'Spare > vstcl-'.$sparedata[$ARTIKELNR]." ";
                                                
                                                /*$zalt_images = Article_Image::where('fk_article_id', '=', $sparearticle->id )->get();
                                                $zalt_var_images = Article_Variation_Image::where('fk_article_variation_id', '=', $sparevariation->id )->get();
                                                if($zalt_images)
                                                {
                                                    foreach($zalt_images as $alt_image) 
                                                    {   $alt_images_attrs = Article_Image_Attribute::where('fk_article_image_id', '=', $alt_image->id )->get();
                                                        foreach($alt_images_attrs as $alt_images_attr) {$alt_images_attr->delete();}
                                                        $alt_image->delete();
                                                    } echo "[del old IMG]";
                                                }
                                                if($zalt_var_images)
                                                {
                                                    foreach($zalt_var_images as $alt_var_image) 
                                                    {   $alt_var_images_attrs = Article_Variation_Image_Attribute::where('fk_article_variation_image_id', '=', $alt_var_image->id )->get();
                                                        foreach($alt_var_images_attrs as $alt_var_images_attr) {$alt_var_images_attr->delete();}
                                                        $alt_var_image->delete();
                                                    } echo "[del old varIMG]";
                                                }*/
                                                //if($zalt_images && $zalt_images->first() && $zalt_var_images && $zalt_var_images->first()) {echo '[IMG-skip]'."\n"; continue; }

                                                for($i = $BILD1; $i <= $PIKTO9; $i++) 
                                                {   if($sparedata[$i] == '') { continue; }
                                                    

                                                        $thisPath = $subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$sparedata[$i];
                                                        if( !Storage::disk('customers')->exists($thisPath) ) 
                                                        {   $thisPath = $subdomain.'/ausnahmen/'.basename($sparedata[$i], "");
                                                            $image = Article_Image::Create([ 'fk_article_id' => $sparearticle->id ]);								
                                                            $var_image = Article_Variation_Image::Create(['fk_article_variation_id' => $sparevariation->id]);
                                                            echo "\n";
                                                        }
                                                        else{ continue; /* SKIP alle bereits eingelesenen */}
                                                    
                                                    try{
                                                        if(!Storage::disk('customers')->exists($thisPath)) 
                                                        {   $image->delete();$var_image->delete(); 
                                                            echo "\n [IMG_fail] ".$thisPath; 
                                                            if(!in_array(basename($thisPath, ""),$FAILED_IMAGES)){$FAILED_IMAGES[]=basename($thisPath, "");}
                                                            continue; 
                                                        }

                                                        $file = Storage::disk('customers')->get($thisPath);
                                                        $imageName = $sparearticle->id.'_base_'.$image->id;
                                                        Storage::disk('public')->put($subdomain.'/img/products/'.$imageName.'.jpg', $file);
                                                                                                            
                                                        $image->update(['location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg']);
                                                        $var_image->update(['location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg']);

                                                        if($i > 8) {
                                                            Article_Image_Attribute::updateOrCreate(
                                                                [   'fk_article_image_id' => $image->id,
                                                                    'name' => 'is_pikto'
                                                                ],[ 'value' => "on" ]
                                                            );
                                                            Article_Variation_Image_Attribute::updateOrCreate(
                                                                [   'fk_article_variation_image_id' => $var_image->id,
                                                                    'name' => 'is_pikto'
                                                                ],[ 'value' => "on" ]
                                                            );
                                                        }
                                                        else
                                                        { // nur Artikelbilder verarbeiten
                                                            Article_Image_Attribute::Create(['fk_article_image_id' => $image->id, 'name' => 'imgType','value' => "original"],[]);										
                                                            Article_Variation_Image_Attribute::Create(['fk_article_variation_image_id' => $var_image->id, 'name' => 'imgType','value' => "original"],[]);

                                                            // Versionen anlegen
                                                            $versions = ['200' => [ 'height' => 200 ],'512' => [ 'height' => 512 ],'1024' => [ 'height' => 1024 ] ];
                                                            foreach($versions as $versionKey => $versionVal) 
                                                            { 
                                                                    Storage::disk('public')->makeDirectory($subdomain.'/img/products/'.$versionKey);
                                                                    Storage::disk('public')->put($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg', $file);
                                                                    $imageName = $sparearticle->id.'_base_'.$image->id;
                                                                
                                                                    try{
                                                                        $img = Image::make( Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg') ) 
                                                                        ->encode('jpg', 100)
                                                                        ->resize(null, $versionVal['height'], function ($constraint) {
                                                                            $constraint->aspectRatio();
                                                                            $constraint->upsize();
                                                                        })->save(Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'));
                                                                        
                                                                    }
                                                                    catch(NotSupportedException $ex) {echo "\n[IMG konnte nicht convertiert werden] ".$subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'."\n";}
                                                                    
                                                                    Article_Image_Attribute::Create(['fk_article_image_id' => $image->id, 'name' => 'imgType','value' => $versionKey],[]);										
                                                                    Article_Variation_Image_Attribute::Create(['fk_article_variation_image_id' => $var_image->id, 'name' => 'imgType','value' => $versionKey],[]);
                                                                    //echo " [IMG ".$versionKey."]";
                                                            }
                                                        }
                                                        echo " [IMG]";
                                                    }
                                                    catch(FileNotFoundException $ex) {
                                                        $image->delete(); $var_image->delete(); 
														echo "\n [IMG_fail] ".$thisPath;
														if(!in_array(basename($thisPath, ""),$FAILED_IMAGES)){$FAILED_IMAGES[]=basename($thisPath, "");}
                                                    }
                                                }

                                            }
                                            
                                        }
                                        else {
                                            Log::error(SimpleXLSX::parseError());
                                        }
                                    }
                                }
                                //echo " [E]";
                                
                                if($data[$ZUBEHOER] != '') { //echo "\n".'Equipment begins';
                                    if(Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$data[$ZUBEHOER])) {
                                        if( $eqxlsx = SimpleXLSX::parse(\storage_path().'/customers/'.$subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$data[$ZUBEHOER])) {
                                            $eqrow = 0;
                                            $eqsets = Equipmentsets::all();
                                            $eqArticleIds = [];
                                            foreach($eqxlsx->rows() as $eqdata) {                         
                                                $eqrow++;
                                                if($eqrow == 1 || $eqrow == 2) {continue;}

                                                $eqarticle = Article::where('vstcl_identifier', '=', 'vstcl-'.$eqdata[$ARTIKELNR])->first();
                                                $eqvariation = Article_Variation::where('vstcl_identifier','=','vstcl-'.$eqdata[$ARTIKELNR])->first();
                                                
                                                //echo "\n".'Equipment > vstcl-'.$eqdata[$ARTIKELNR]." ";
                                                
                                                /*$eqalt_images = Article_Image::where('fk_article_id', '=', $eqarticle->id )->get();
                                                $eqalt_var_images = Article_Variation_Image::where('fk_article_variation_id', '=', $eqvariation->id )->get();
                                                if($eqalt_images)
                                                {
                                                    foreach($eqalt_images as $alt_image) 
                                                    {   $alt_images_attrs = Article_Image_Attribute::where('fk_article_image_id', '=', $alt_image->id )->get();
                                                        foreach($alt_images_attrs as $alt_images_attr) {$alt_images_attr->delete();}
                                                        $alt_image->delete();
                                                    } echo "[del old IMG]";
                                                }
                                                if($eqalt_var_images)
                                                {
                                                    foreach($eqalt_var_images as $alt_var_image) 
                                                    {   $alt_var_images_attrs = Article_Variation_Image_Attribute::where('fk_article_variation_image_id', '=', $alt_var_image->id )->get();
                                                        foreach($alt_var_images_attrs as $alt_var_images_attr) {$alt_var_images_attr->delete();}
                                                        $alt_var_image->delete();
                                                    } echo "[del old varIMG]";
                                                }*/
                                                //if($zalt_images && $zalt_images->first() && $zalt_var_images && $zalt_var_images->first()) {echo '[IMG-skip]'."\n"; continue; }
                                            
                                                for($i = $BILD1; $i <= $PIKTO9; $i++) 
                                                {   if($eqdata[$i] == '') { continue; }
                                                    

                                                        $thisPath = $subdomain.'/'.$folderName.'/'.$DATAfolderName.'/'.$folderPath.$eqdata[$i];
                                                        if( !Storage::disk('customers')->exists($thisPath) ) 
                                                        {   $thisPath = $subdomain.'/ausnahmen/'.basename($eqdata[$i], "");
                                                            $image = Article_Image::Create([ 'fk_article_id' => $eqarticle->id ]);								
                                                            $var_image = Article_Variation_Image::Create(['fk_article_variation_id' => $eqvariation->id]);
                                                            echo "\n";
                                                        }
                                                        else{ continue; /* SKIP alle bereits eingelesenen */}
                                                    
                                                    try{
                                                        if(!Storage::disk('customers')->exists($thisPath)) 
                                                        {   $image->delete();$var_image->delete(); 
                                                            echo "\n [IMG_fail] ".$thisPath; 
                                                            if(!in_array(basename($thisPath, ""),$FAILED_IMAGES)){$FAILED_IMAGES[]=basename($thisPath, "");}
                                                            continue; 
                                                        }

                                                   
                                                        $file = Storage::disk('customers')->get($thisPath);
                                                        $imageName = $eqarticle->id.'_base_'.$image->id;
                                                        Storage::disk('public')->put($subdomain.'/img/products/'.$imageName.'.jpg', $file);
                                                                                                            
                                                        $image->update(['location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg']);
                                                        $var_image->update(['location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg']);

                                                        if($i > 8) {
                                                            Article_Image_Attribute::updateOrCreate(
                                                                [   'fk_article_image_id' => $image->id,
                                                                    'name' => 'is_pikto'
                                                                ],[ 'value' => "on" ]
                                                            );
                                                            Article_Variation_Image_Attribute::updateOrCreate(
                                                                [   'fk_article_variation_image_id' => $var_image->id,
                                                                    'name' => 'is_pikto'
                                                                ],[ 'value' => "on" ]
                                                            );
                                                        }
                                                        else
                                                        { // nur Artikelbilder verarbeiten
                                                            Article_Image_Attribute::Create(['fk_article_image_id' => $image->id, 'name' => 'imgType','value' => "original"],[]);										
                                                            Article_Variation_Image_Attribute::Create(['fk_article_variation_image_id' => $var_image->id, 'name' => 'imgType','value' => "original"],[]);

                                                            // Versionen anlegen
                                                            $versions = ['200' => [ 'height' => 200 ],'512' => [ 'height' => 512 ],'1024' => [ 'height' => 1024 ] ];
                                                            foreach($versions as $versionKey => $versionVal) 
                                                            { 
                                                                    Storage::disk('public')->makeDirectory($subdomain.'/img/products/'.$versionKey);
                                                                    Storage::disk('public')->put($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg', $file);
                                                                    $imageName = $eqarticle->id.'_base_'.$image->id;
                                                                
                                                                    try{
                                                                        $img = Image::make( Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg') ) 
                                                                        ->encode('jpg', 100)
                                                                        ->resize(null, $versionVal['height'], function ($constraint) {
                                                                            $constraint->aspectRatio();
                                                                            $constraint->upsize();
                                                                        })->save(Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'));
                                                                        
                                                                    }
                                                                    catch(NotSupportedException $ex) {echo "\n[IMG konnte nicht convertiert werden] ".$subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'."\n";}
                                                                    
                                                                    Article_Image_Attribute::Create(['fk_article_image_id' => $image->id, 'name' => 'imgType','value' => $versionKey],[]);										
                                                                    Article_Variation_Image_Attribute::Create(['fk_article_variation_image_id' => $var_image->id, 'name' => 'imgType','value' => $versionKey],[]);
                                                                    //echo " [IMG ".$versionKey."]";
                                                            }
                                                        }
                                                        echo " [IMG]"; 
                                                    }
                                                    catch(FileNotFoundException $ex) {
                                                        $image->delete(); $var_image->delete(); 
														echo "\n [IMG_fail] ".$thisPath;
														if(!in_array(basename($thisPath, ""),$FAILED_IMAGES)){$FAILED_IMAGES[]=basename($thisPath, "");}
                                                    }
                                                }

                                            }
                                            
                                        }
                                        else {
                                            Log::error(SimpleXLSX::parseError());
                                        }
                                    }
                                }
                            
                            }else
                            {
                                continue;
                            }
                            //echo "\n";
                        }
                    }
                    else {
                        Log::error(SimpleXLSX::parseError());
                    }
                    
                }
            }
            
        }
		echo "\nFails:\n";
		foreach($FAILED_IMAGES as $FAILED_IMAGE)
		{ echo $FAILED_IMAGE."\n"; }
    }

    private function dirToArray($dir) {
        $result = [];
        $cdir = scandir($dir);
        foreach ($cdir as $key => $value)
        {
           if (!in_array($value,array(".","..")))
           {
              if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
              { $result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value); }
              else{ $result[] = $value; }
           }
        }
        return $result;
    }
}
