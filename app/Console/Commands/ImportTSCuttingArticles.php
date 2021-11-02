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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class ImportTSCuttingArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:tscutting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert die Artikel von TS Cutting ins Visticle';

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
    public function handle(Request $request)
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
        if(!$tenant) { return; }
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

        if(!in_array($customer.'/'.$folderName, $customerFolders)) { return; }

        $dir = \storage_path().'/customers/'.$subdomain.'/'.$folderName;

        $structure = $this->dirToArray($dir);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($structure), \RecursiveIteratorIterator::SELF_FIRST);
        $count = 0;
        $categories = [];
        $oldDepth = null;
        $startDepth = null;
        $dataFileCount = 0;
        foreach($iterator as $key => $value) 
        {
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
                if($parentCatName) {
                    $parentCat = Category::where('name', '=', $parentCatName)->first();
                }
                if(preg_match('/^Ersatzteile/', $cat) == 1) {$type .= 'spare|';continue;}
                if(preg_match('/^Zubehör/', $cat) == 1) {$type .= 'equipment|';}
                $articleCat = Category::where('name', '=', $cat)
                ->where('fk_parent_category_id', '=', (($parentCat) ? $parentCat->id : null))->first();
                
                $filePath .= $cat.'/';
                $parentCatName = $cat;
                $catCount++;
            }

            if(!is_array($value)) {
                if(preg_match('/^Daten/', $value)) {
                    if(!preg_match('/\.xlsx$/i', $value)) { continue; }
                    $dataFileCount++;
                    $folderPath = $filePath;
                    $filePath .= $value;
                    echo $value. "\n";
                    
                    //Create temp file and write recent file content
                    if( $xlsx = SimpleXLSX::parse(\storage_path().'/customers/'.$subdomain.'/'.$folderName.'/DATA/'.$filePath)) {
                        $row = 0;
                        $customAttrs = [];
                        foreach($xlsx->rows() as $data) {
                                                    
                        $row++;
                    
                        //Skip first Row
                        if($row == 1) { for($i = 21; $i < count($data); $i++) { if($data[$i] != '') {  $customAttrs[$i] = $data[$i]; } else { break; } } continue; }
                        if($row == 2) { continue; }
                        
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
                                'type' => $type
                            ]
                        );
                        
                        $article->categories()->syncWithoutDetaching($articleCat->id);
						echo 'vstcl-'.$data[$ARTIKELNR].": [A+V][Cat(".$articleCat->id.")]";
                        $attrGroupIds = [];
                        foreach($customAttrs as $customAttrKey => $customAttr) {
                            $attrGroup = Attribute_Group::updateOrCreate(
                                [
                                    'name' => $customAttr
                                ],[]
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
                            if($attrGroupIds == $setGroupIds) { $setId = $attrSet->id; break; }
                        }
                        if(!$setId) {
                            $attrSet = Attribute_Set::updateOrCreate([ 'name' => $articleCat->name ]);
                            foreach($attrGroupIds as $sortedAttrGroupId) { $attrSet->groups()->syncWithoutDetaching($sortedAttrGroupId); }
                            $setId = $attrSet->id;
                        }
                        $article->update(['fk_attributeset_id' => $setId]);
                        $variation->update(['fk_attributeset_id' => $setId]);
                        $article->updateOrCreateAttribute('Rabattgruppe', $data[$RABATTGRP]);
                        $price = str_replace(' €','',$data[$LISTENPREIS]);
                        $article->updateOrCreatePrice('standard', $price);
                        $variation->updateOrCreatePrice('standard', $price);
                        echo " [A]";
                        if($article) { // $article->wasRecentlyCreated
                            

                            if($data[$ERSATZTEILE] != '') {
                                if(Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/DATA/'.$folderPath.$data[$ERSATZTEILE])) {
                                    if( $sparexlsx = SimpleXLSX::parse(\storage_path().'/customers/'.$subdomain.'/'.$folderName.'/DATA/'.$folderPath.$data[$ERSATZTEILE])) {
                                        $sparerow = 0;
                                        $sparesets = Sparesets::all();
                                        $spareArticleIds = [];
                                        $customAttrs = [];
                                        foreach($sparexlsx->rows() as $sparedata) {                         
                                            $sparerow++;
                                            if($sparerow == 1) { for($i = 21; $i < count($sparedata); $i++) { if($sparedata[$i] != '') {  $customAttrs[$i] = $sparedata[$i]; } else { break; } } continue; }
                                            if($sparerow == 2) { continue; }
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
                                                    'type' => 'article|spare',
                                                    'active' => 1
                                                ]
                                            );
                                            $sparearticle->categories()->syncWithoutDetaching($articleCat->id);

                                            $sparevariation = Article_Variation::updateOrCreate(
                                                [
                                                    'fk_article_id' => $sparearticle->id,
                                                    'vstcl_identifier' => 'vstcl-'.$sparedata[$ARTIKELNR]
                                                ],
                                                [
                                                    'active' => 1,
                                                    'stock' => 1,
                                                    'type' => 'article|spare'
                                                ]
                                            );

                                            if($sparearticle) { $spareArticleIds[] = $sparearticle->id; }
                                        }
                                        sort($spareArticleIds);
                                        // Attribute Gruppen erfassen
                                        $attrGroupIds = [];
                                        foreach($customAttrs as $customAttrKey => $customAttr) {
                                            $attrGroup = Attribute_Group::updateOrCreate(
                                                [ 'name' => $customAttr ],[]
                                            );
                                            $attrGroupIds[] = $attrGroup->id;
                                            $sparearticle->updateOrCreateAttribute($customAttr, $sparedata[$customAttrKey], $attrGroup->id);
                                            $sparevariation->updateOrCreateAttribute($customAttr, $sparedata[$customAttrKey], $attrGroup->id);
                                        }
                                        $attrSets = Attribute_Set::all();
                                        sort($attrGroupIds);
                                        $setId = null;
                                        foreach($attrSets as $attrSet) {
                                            $setGroupIds = $attrSet->groups()->pluck('fk_attributegroup_id')->toArray();
                                            sort($setGroupIds); if($attrGroupIds == $setGroupIds) { $setId = $attrSet->id; break; }
                                        }
                                        if(!$setId) {
                                            $attrSet = Attribute_Set::updateOrCreate([ 'name' => $articleCat->name ]);
                                            foreach($attrGroupIds as $sortedAttrGroupId) { $attrSet->groups()->syncWithoutDetaching($sortedAttrGroupId); }
                                            $setId = $attrSet->id;
                                        }
                                        // ENDE Attribute Gruppen erfassen
                                        $sparearticle->update(['fk_attributeset_id' => $setId]);
                                        $sparevariation->update(['fk_attributeset_id' => $setId]);
                                        $sparearticle->updateOrCreateAttribute('Rabattgruppe', $sparedata[$RABATTGRP]);
                                        $price = str_replace(' €','',$sparedata[$LISTENPREIS]);
                                        $sparearticle->updateOrCreatePrice('standard', $price);
                                        $sparevariation->updateOrCreatePrice('standard', $price);

                                        $setId = null;
                                        foreach($sparesets as $spareset) {
                                            $sparesetGroupIds = $spareset->spare_articles()->pluck('fk_article_id')->toArray();
                                            sort($sparesetGroupIds);
                                            if($spareArticleIds == $sparesetGroupIds) {
                                                $setId = $spareset->id; break; }
                                        }
                                        if(!$setId) {
                                            $spareAttrSet = Sparesets::updateOrCreate([
                                                'name' => str_replace('.xlsx', '', str_replace('Daten ', '', $data[$ERSATZTEILE]))
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
                                            ['fk_art_var_id' => null ]
                                        );

                                        
                                    }
                                    else {
                                        Log::error(SimpleXLSX::parseError());
                                    }
                                }
                            }
                            echo " [E]";
                            if($data[$ZUBEHOER] != '') {
                                if(Storage::disk('customers')->exists($subdomain.'/'.$folderName.'/DATA/'.$folderPath.$data[$ZUBEHOER])) {
                                    if( $eqxlsx = SimpleXLSX::parse(\storage_path().'/customers/'.$subdomain.'/'.$folderName.'/DATA/'.$folderPath.$data[$ZUBEHOER])) {
                                        $eqrow = 0;
                                        $eqsets = Equipmentsets::all();
                                        $eqArticleIds = [];
                                        $customAttrs = [];
                                        foreach($eqxlsx->rows() as $eqdata) {                         
                                            $eqrow++;
                                            if($eqrow == 1) { for($i = 21; $i < count($eqdata); $i++) { if($eqdata[$i] != '') {  $customAttrs[$i] = $eqdata[$i]; } else { break; } } continue; }
                                            if($eqrow == 2) { continue; }
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
                                                    'type' => 'article|equipment',
                                                    'active' => 1
                                                ]
                                            );
                                            $eqarticle->categories()->syncWithoutDetaching($articleCat->id);

                                            $eqvariation = Article_Variation::updateOrCreate(
                                                [
                                                    'fk_article_id' => $eqarticle->id,
                                                    'vstcl_identifier' => 'vstcl-'.$eqdata[$ARTIKELNR]
                                                ],
                                                [
                                                    'active' => 1,
                                                    'stock' => 1,
                                                    'type' => 'article|equipment'
                                                ]
                                            );

                                            if($eqarticle) {$eqArticleIds[] = $eqarticle->id;}
                                        }
                                        sort($eqArticleIds);
                                        
                                        // Attribute Gruppen erfassen
                                        $attrGroupIds = [];
                                        foreach($customAttrs as $customAttrKey => $customAttr) {
                                            $attrGroup = Attribute_Group::updateOrCreate( [ 'name' => $customAttr ],[] );
                                            $attrGroupIds[] = $attrGroup->id;
                                            $eqarticle->updateOrCreateAttribute($customAttr, $eqdata[$customAttrKey], $attrGroup->id);
                                            $eqvariation->updateOrCreateAttribute($customAttr, $eqdata[$customAttrKey], $attrGroup->id);
                                        }
                                        $attrSets = Attribute_Set::all();
                                        sort($attrGroupIds);
                                        $setId = null;
                                        foreach($attrSets as $attrSet) {
                                            $setGroupIds = $attrSet->groups()->pluck('fk_attributegroup_id')->toArray();
                                            sort($setGroupIds); if($attrGroupIds == $setGroupIds) { $setId = $attrSet->id; break; }
                                        }
                                        if(!$setId) {
                                            $attrSet = Attribute_Set::updateOrCreate([ 'name' => $articleCat->name ]);
                                            foreach($attrGroupIds as $sortedAttrGroupId) { $attrSet->groups()->syncWithoutDetaching($sortedAttrGroupId); }
                                            $setId = $attrSet->id;
                                        }
                                        // ENDE Attribute Gruppen erfassen
                                        $eqarticle->update(['fk_attributeset_id' => $setId]);
                                        $eqvariation->update(['fk_attributeset_id' => $setId]);
                                        $eqarticle->updateOrCreateAttribute('Rabattgruppe', $eqdata[$RABATTGRP]);
                                        $price = str_replace(' €','',$eqdata[$LISTENPREIS]);
                                        $eqarticle->updateOrCreatePrice('standard', $price);
                                        $eqvariation->updateOrCreatePrice('standard', $price);

                                        $setId = null;
                                        foreach($eqsets as $eqset) {
                                            $eqGroupIds = $eqset->equipment_articles()->pluck('fk_article_id')->toArray();
                                            sort($eqGroupIds);
                                            if($eqArticleIds == $eqGroupIds) { $setId = $eqset->id; break; }
                                        }
                                        if(!$setId) {
                                            $eqAttrSet = Equipmentsets::updateOrCreate([
                                                'name' => str_replace('.xlsx', '', str_replace('Daten ', '', $data[$ZUBEHOER]))
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
                                            ['fk_art_var_id' => null]
                                        );


                                        
                                    }
                                    else {
                                        Log::error(SimpleXLSX::parseError());
                                    }
                                }
                            }
                            echo " [Z]";
                        }else
                        {
                            echo " [SKIP]";
                        }
                        echo "\n";
                        }
                    }
                    else {
                        Log::error(SimpleXLSX::parseError());
                    }
                    
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
              {
                 $result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value);
              }
              else
              {
                 $result[] = $value;
              }
           }
        }
        return $result;
    }
}
