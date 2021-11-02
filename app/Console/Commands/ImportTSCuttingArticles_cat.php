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


class ImportTSCuttingArticles_cat extends Command
{
    protected $signature = 'import:tscutting_cat';

    protected $description = 'Importiert die Artikel von TS Cutting ins Visticle';

    public function __construct(){parent::__construct();}

    public function handle(Request $request)
    {
        $subdomain = 'ts-cutting';
        $tenant = Tenant::where('subdomain','=',$subdomain)->first();
        if(!$tenant) {return;}
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

        if(!in_array($customer.'/'.$folderName, $customerFolders)) {return;}

        $dir = \storage_path().'/customers/'.$subdomain.'/'.$folderName;

        $structure = $this->dirToArray($dir);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($structure), \RecursiveIteratorIterator::SELF_FIRST);
        $count = 0;
        $categories = [];
        $oldDepth = null;
        $startDepth = null;
        $dataFileCount = 0;
        echo "-Starte Cats Import-\n";
        foreach($iterator as $key => $value) 
        {
            $depth = $iterator->getDepth();
            // loop through the subIterators...             
            $keys = array();    
            // in this case i skip the grand parent (numeric array)
            for ($i = $iterator->getDepth()-1; $i>0; $i--) {$keys[] = $iterator->getSubIterator($i)->key();}

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
                if(preg_match('/^Ersatzteile/', $cat) == 1) { $type .= 'spare|';continue; }
                if(preg_match('/^Zubehör/', $cat) == 1) {  $type .= 'equipment|'; }
                $articleCat = Category::updateOrCreate(
                    [
                        'name' => $cat,
                        'slug' => Str::slug($cat, '-'),
                        'fk_parent_category_id' => (($parentCat) ? $parentCat->id : null)
                    ],
                    []
                );
                $parentCatName = $cat;
                $catCount++;
                echo "(".$catCount.".)".$cat."\n";
            }
            // zweiter run parentCat ID`s
            $parentCatName = null;
            $parentCat = null;
            $catCount = 0;
            echo "-zweiter Run-\n";
            foreach($r_keys as $cat) {
                if($parentCatName) {
                    $parentCat = Category::where('name', '=', $parentCatName)->first();
                }
                if(preg_match('/^Ersatzteile/', $cat) == 1) { $type .= 'spare|';continue; }
                if(preg_match('/^Zubehör/', $cat) == 1) {  $type .= 'equipment|'; }
                $articleCat = Category::updateOrCreate(
                    [
                        'name' => $cat,
                        'slug' => Str::slug($cat, '-'),
                        'fk_parent_category_id' => (($parentCat) ? $parentCat->id : null)
                    ],
                    []
                );
                $parentCatName = $cat;
                $catCount++;
                echo "(".$catCount.".)".$cat."\n";
            }

            
        }
        echo "-Import abgeschlossen-\n";
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
