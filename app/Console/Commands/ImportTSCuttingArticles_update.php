<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use App\Tenant\Category;
use App\Tenant\Article;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute;
use App\Tenant\Attribute_Group;
use App\Tenant\Attribute_Set;
use App\Tenant\Sparesets;
use App\Tenant\Sparesets_Articles;
use App\Tenant\Sparesets_SpareArticles;
use App\Tenant\Equipmentsets;
use App\Tenant\Equipmentsets_Articles;
use App\Tenant\Equipmentsets_EquipmentArticles;
use Illuminate\Support\Str;
use Image;
use Config, Storage, SimpleXLSX;
use Log;
use League\Flysystem\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class ImportTSCuttingArticles_update extends Command
{
    protected $signature = 'import:tscutting_update';
    protected $description = 'Importiert die Artikel von TS Cutting ins Visticle UPDATE';
    
    public function __construct(){ parent::__construct(); }

    public function fgetcsvUTF8(&$handle, $length, $separator = ';')
    {   
        if (($buffer = fgets($handle, $length)) !== false)
        {
            $buffer = $this->autoUTF($buffer);
            return str_getcsv($buffer, $separator);
        }
        
        return false;
    }
    public function autoUTF($s)
    {
        // detect UTF-8
        if (preg_match('#[\x80-\x{1FF}\x{2000}-\x{3FFF}]#u', $s))
            return $s;
    
        // detect WINDOWS-1250
        if (preg_match('#[\x7F-\x9F\xBC]#', $s))
            return iconv('WINDOWS-1250', 'UTF-8', $s);
    
        // assume ISO-8859-2
        return iconv('ISO-8859-2', 'UTF-8', $s);
    }

    public function handle()
    {
        //CSV Fields
        $ARTIKELNR = 0;
        $LISTENPREIS = 1;
        $RABATTGRP = 2;
        $DESCRIPTION = 3; 
        $NAME1 = 4; // Artikelname
        $NAME2 = 5; // Kurzbeschreibung // unwichtig
        $BILD1 = 6;
        $BILD2 = 7;
        $BILD3 = 8;
        $PIKTO1 = 9;
        $PIKTO2 = 10;
        $PIKTO3 = 11;
        $PIKTO4 = 12;
        $PIKTO5 = 13;
        $PIKTO6 = 14;
        $PIKTO7 = 15;
        $PIKTO8 = 16;
        $PIKTO9 = 17;
        $SCHNITTEMPF = 18;
        $ZUBEHOER = 19;
        $ERSATZTEILE = 20;
        $VPE = 21;
        $GROESSE = 22;

        $subdomain = 'ts-cutting';
        $tenant = Tenant::where('subdomain','=',$subdomain)->first();
        if(!$tenant) {
            return;
        }
        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        $customer = $tenant->subdomain;

        $customerFolders = Storage::disk('customers')->directories($customer);
        $folderName = 'ts_cutting_import';

        if(!in_array($customer.'/'.$folderName, $customerFolders)) {
            return;
        }

        $dir = \storage_path().'/customers/'.$subdomain.'/'.$folderName;
		
        $structure = $this->dirToArray($dir);
 
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($structure), \RecursiveIteratorIterator::SELF_FIRST);
        $count = 0;
        $categories = [];
        $oldDepth = null;
        $startDepth = null;
        $dataFileCount = 0;
        //Log::info("Lade Daten TS-Cutting Export");
        //Log::info($structure);
        foreach($iterator as $key => $value) {
            $depth = $iterator->getDepth();

            // loop through the subIterators... 
            
            $keys = array();    
            // in this case i skip the grand parent (numeric array)
            for ($i = $iterator->getDepth()-1; $i>0; $i--) {
                $keys[] = $iterator->getSubIterator($i)->key();
            }

            $r_keys = array_reverse($keys);
            $parentCatName = null;
            $parentCat = null;
            $catCount = 0;
            $filePath = '';
            $type = 'article|';

            foreach($r_keys as $cat) {
                if($parentCatName) 
                { $parentCat = Category::where('name', '=', $parentCatName)->first(); }
                if(preg_match('/^Ersatzteile/', $cat) == 1) {
                    $type .= 'spare|';
                    continue;
                }
                if(preg_match('/^Zubehör/', $cat) == 1) {
                    $type .= 'equipment|';
                    //continue;
                }
                $articleCat = Category::updateOrCreate(
                    [
                        'name' => $cat,
                        'slug' => Str::slug($cat, '-'),
                        'fk_parent_category_id' => (($parentCat) ? $parentCat->id : null)
                    ],
                    []
                );
                $filePath .= $cat.'/';
                $parentCatName = $cat;
                $catCount++;
            }

            if(!is_array($value)) {
                if(preg_match('/^Daten/', $value)) {
                    if(!preg_match('/\.csv$/i', $value)) { continue; }
                    $dataFileCount++;
                    $folderPath = $filePath;
                    $filePath .= $value;
                    echo $filePath. "\n";

                    //dd(dirname (dirname (dirname (\storage_path()))));
                    $file_content = fopen(dirname (dirname (dirname (\storage_path())))."/storage/customers/".$subdomain.'/'.$folderName.'/DATA2/'.$filePath, "r");
                    $file_length = filesize(dirname (dirname (dirname (\storage_path())))."/storage/customers/".$subdomain.'/'.$folderName.'/DATA2/'.$filePath);
                    //Log::info("Datei(".$file_length."): ".storage_path().'/customers/'.$subdomain.'/'.$folderName.'/DATA2/'.$filePath );
                    //Log::info(print_r($this->fgetcsvUTF8($file_content, filesize($file_content), ";"),true));
                    $row = 0;
                    $customAttrs = [];
                    
                    while( ($data = $this->fgetcsvUTF8($file_content,$file_length, ";")) !== false) 
                    {   $row++; if($row == 1 || $row == 2) { continue; }
                        for($i = 21; $i < count($data); $i++) {
                            if($data[$i] != '') { 
                                $customAttrs[$i] = $data[$i];
                            }
                        }
                        
                        //TODO: Check if equiment or spare
                        $article = Article::updateOrCreate(
                            [
                                'vstcl_identifier' => 'vstcl-'.$data[$ARTIKELNR],
                                'number' => $data[$ARTIKELNR]
                            ],
                            [
                                'description' => $data[$DESCRIPTION],
                                'name' => $data[$NAME1],
                                'webname' => $data[$NAME1],
                                'slug' => Str::slug($data[$NAME1], '-'),
                                'fk_attributeset_id' => 1,
                                'type' => $type,
                                'active' => 1
                            ]
                        );
                        $variation = Article_Variation::updateOrCreate(
                            [
                                'fk_article_id' => $article->id,
                                'vstcl_identifier' => 'vstcl-'.$data[$ARTIKELNR]
                            ],
                            [
                                'active' => 1,
                                'stock' => 1,
                                'fk_attributeset_id' => 1,
                                'type' => $type
                            ]
                        );
                        echo 'vstcl-'.$data[$ARTIKELNR].": [A+V]";
                        $article->categories()->syncWithoutDetaching($articleCat->id);
                        $attrGroupIds = [];
                        foreach($customAttrs as $customAttrKey => $customAttr) {
                            $attrGroup = Attribute_Group::updateOrCreate(
                                [ 'name' => $customAttr ],[]
                            );
                            $attrGroupIds[] = $attrGroup->id;
                            $article->updateOrCreateAttribute($customAttr, $data[$customAttrKey], $attrGroup->id);
                            $variation->updateOrCreateAttribute($customAttr, $data[$customAttrKey], $attrGroup->id);
                        }
                        $attrSets = Attribute_Set::all();
                        sort($attrGroupIds);
                        $setId = null;
                        foreach($attrSets as $attrSet) {
                            $setGroupIds = $attrSet->groups()->pluck('fk_attributegroup_id')->toArray();
                            sort($setGroupIds);
                            if($attrGroupIds == $setGroupIds) {
                                $setId = $attrSet->id;
                                break;
                            }
                        }
                        if(!$setId) {
                            $attrSet = Attribute_Set::updateOrCreate([
                                'name' => $articleCat->name
                            ]);
                            foreach($attrGroupIds as $sortedAttrGroupId) {
                                $attrSet->groups()->syncWithoutDetaching($sortedAttrGroupId);
                            }
                            $setId = $attrSet->id;
                        }
                        $article->update(['fk_attributeset_id' => $setId]);
                        $variation->update(['fk_attributeset_id' => $setId]);
                        $article->updateOrCreateAttribute('Rabattgruppe', $data[$RABATTGRP]);
                        $price = str_replace(' €','',str_replace(',','.',$data[$LISTENPREIS]));
                        $article->updateOrCreatePrice('standard', $price);
                        $variation->updateOrCreatePrice('standard', $price);
                        echo " [A]";
                        if($article->wasRecentlyCreated) {
                            for($i = $BILD1; $i <= $PIKTO9; $i++) {
                                if($data[$i] == '') { continue; }

                                $versions = [
                                    '200' => [ 'height' => 200 ]
                                   ,'512' => [ 'height' => 512 ]
                                   ,'1024' => [ 'height' => 1024 ]
                                ];

                                $image = Article_Image::updateOrCreate([
                                    'fk_article_id' => $article->id
                                ]);
                                
                                try{
                                    if(!Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$i])) {
                                        $image->delete();
                                        continue;
                                    }
                                    $file = Storage::disk('customers')->get($subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$i]);
                                    Storage::disk('public')->put($subdomain.'/img/products/'.$article->id.'_base_'.$image->id.'.jpg', $file);
                                    $imageName = $article->id.'_base_'.$image->id;
                                    echo " [IMG]";
                                    $image->update([
                                        'location' => '/'.$subdomain.'/img/products/'.$imageName.'.jpg'
                                    ]);


                                    foreach($versions as $versionKey => $versionVal) {

                                        Storage::disk('public')->makeDirectory($subdomain.'/img/products/'.$versionKey);
                                        Storage::disk('public')->put($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg', $file);

                                        try{
											$img = Image::make( Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg') ) 
											->encode('jpg', 100)
											->resize(null, $versionVal['height'], function ($constraint) {
												$constraint->aspectRatio();
												$constraint->upsize();
											})->save(Storage::disk('public')->path($subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg'));
											echo " [IMG ".$versionKey."]";
										}
										catch(NotSupportedException $ex) {Log::info("[IMG konnte nicht convertiert werden] ".$subdomain.'/img/products/'.$versionKey.'/'.$imageName.'.jpg');}
				
                    
                                        Article_Image_Attribute::updateOrCreate(
                                            ['fk_article_image_id' => $image->id, 'name' => 'imgType'],
                                            ['value' => $versionKey]
                                        );
                                    }

                                    if($i > 8) {
                                        Article_Image_Attribute::updateOrCreate(
                                            [
                                                'fk_article_image_id' => $image->id,
                                                'name' => 'is_pikto'
                                            ],
                                            [
                                                'value' => 1
                                            ]
                                        );
                                    }
                                }
                                catch(FileNotFoundException $ex) {
                                    $image->delete();
                                }
    
                            }
                        }
                        
                        if($data[$ERSATZTEILE] != '') {
                            if(Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ERSATZTEILE])) {
                                
								
								$file_content_s = fopen(dirname (dirname (dirname (\storage_path())))."/storage/customers/".$subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ERSATZTEILE], "r");
								$file_length_s = filesize(dirname (dirname (dirname (\storage_path())))."/storage/customers/".$subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ERSATZTEILE]);
                                //Log::info("Datei(".$file_length_s."): ".storage_path().'/customers/'.$subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ERSATZTEILE] );
                                                                
                                    $sparerow = 0;
                                    $sparesets = Sparesets::all();
                                    $spareArticleIds = [];
                                    while( ($sparedata = $this->fgetcsvUTF8($file_content_s,$file_length_s, ";")) !== false) 
                                    {   $sparerow++; if($sparerow == 1 || $sparerow == 2) { continue; }
                                        $sparearticle = Article::updateOrCreate(
                                            [
                                                'vstcl_identifier' => 'vstcl-'.$sparedata[$ARTIKELNR],
                                                'number' => $sparedata[$ARTIKELNR]
                                            ],
                                            [
                                                'description' => $sparedata[$DESCRIPTION],
                                                'name' => $sparedata[$NAME1],
                                                'webname' => $sparedata[$NAME1],
                                                'slug' => Str::slug($sparedata[$NAME1], '-'),
                                                'fk_attributeset_id' => 1,
                                                'type' => 'article|spare',
                                                'active' => 1
                                            ]
                                        );
                                        $sparearticle->categories()->syncWithoutDetaching($articleCat->id);
                                        if($sparearticle) { $spareArticleIds[] = $sparearticle->id; }
                                    }
                                    fclose($file_content_s);
                                    sort($spareArticleIds);
                                    $setId = null;
                                    foreach($sparesets as $spareset) {
                                        $sparesetGroupIds = $spareset->spare_articles()->pluck('fk_article_id')->toArray();
                                        sort($sparesetGroupIds);
                                        if($spareArticleIds == $sparesetGroupIds) {
                                            $setId = $spareset->id;
                                            break;
                                        }
                                    }
                                    if(!$setId) {
                                        $spareAttrSet = Sparesets::updateOrCreate([
                                            'name' => str_replace('.csv', '', str_replace('.xlsx', '', str_replace('Daten ', '', $data[$ERSATZTEILE])))
                                        ]);
                                        foreach($spareArticleIds as $spareArticleId) {
                                            Sparesets_SpareArticles::updateOrCreate([
                                                'fk_spareset_id' => $spareAttrSet->id,
                                                'fk_article_id' => $spareArticleId
                                            ], ['fk_art_var_id' => null]);
                                        }
                                        $setId = $spareAttrSet->id;
                                    }
                                    Sparesets_Articles::updateOrCreate(
                                        [
                                            'fk_spareset_id' => $setId,
                                            'fk_article_id' => $article->id
                                        ],
                                        [ 'fk_art_var_id' => null ]
                                    );
                                
                            }
                        }
                        echo " [E]";
                        if($data[$ZUBEHOER] != '') {
                            if(Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ZUBEHOER])) {
                                
              
								$file_content_e = fopen(dirname (dirname (dirname (\storage_path())))."/storage/customers/".$subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ZUBEHOER], "r");
								$file_length_e = filesize(dirname (dirname (dirname (\storage_path())))."/storage/customers/".$subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ZUBEHOER]);
                                //Log::info("Datei(".$file_length_e."): ".storage_path().'/customers/'.$subdomain.'/'.$folderName.'/DATA2/'.$folderPath.$data[$ZUBEHOER] );

                                    $eqrow = 0;
                                    $eqsets = Equipmentsets::all();
                                    $eqArticleIds = [];
                                    while( ($eqdata = $this->fgetcsvUTF8($file_content_e,$file_length_e, ";")) !== false) 
                                    {   $eqrow++; if($eqrow == 1 || $eqrow == 2) { continue; }
                                        $eqarticle = Article::updateOrCreate(
                                            [
                                                'vstcl_identifier' => 'vstcl-'.$eqdata[$ARTIKELNR],
                                                'number' => $eqdata[$ARTIKELNR]
                                            ],
                                            [
                                                'description' => $eqdata[$DESCRIPTION],
                                                'name' => $eqdata[$NAME1],
                                                'webname' => $eqdata[$NAME1],
                                                'slug' => Str::slug($eqdata[$NAME1], '-'),
                                                'fk_attributeset_id' => 1,
                                                'type' => 'article|equipment',
                                                'active' => 1
                                            ]
                                        );
                                        $eqarticle->categories()->syncWithoutDetaching($articleCat->id);
                                        if($eqarticle) { $eqArticleIds[] = $eqarticle->id; }
                                    }
                                    fclose($file_content_e);

                                    sort($eqArticleIds);
                                    $setId = null;
                                    foreach($eqsets as $eqset) {
                                        $eqGroupIds = $eqset->equipment_articles()->pluck('fk_article_id')->toArray();
                                        sort($eqGroupIds);
                                        if($eqArticleIds == $eqGroupIds) {
                                            $setId = $eqset->id;
                                            break;
                                        }
                                    }
                                    if(!$setId) {
                                        $eqAttrSet = Equipmentsets::updateOrCreate([
                                            'name' => str_replace('.csv', '', str_replace('.xlsx', '', str_replace('Daten ', '', $data[$ZUBEHOER])))
                                        ]);
                                        foreach($eqArticleIds as $eqArticleId) {
                                            Equipmentsets_EquipmentArticles::updateOrCreate([
                                                'fk_eqset_id' => $eqAttrSet->id,
                                                'fk_article_id' => $eqArticleId
                                            ], ['fk_art_var_id' => null]);
                                        }
                                        $setId = $eqAttrSet->id;
                                    }
                                    Equipmentsets_Articles::updateOrCreate(
                                        [
                                            'fk_eqset_id' => $setId,
                                            'fk_article_id' => $article->id
                                        ],
                                        [
                                            'fk_art_var_id' => null
                                        ]
                                    );
                                
                            }
                        }
                        echo " [Z]"."\n";
                        }
                     
                    fclose($file_content);
                    
                }
            }
            
        }

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
              else
              { $result[] = $value; }
           }
        }
        return $result;
    }
}
