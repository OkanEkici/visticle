<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use Config;
use App\Tenant\Article;
use App\Tenant\Article_Attribute;

class AltVisticleAttributImport extends Command
{
    protected $signature = 'altvisticleimport:attribut {customer} {attribut}';
    protected $description = 'Importiert die Daten aus dem alten Visticle ins neue';

    public function __construct(){parent::__construct();}

    public function handle()
    {
        /*
        SQL:
        SELECT va.artikel_system, vaa.attribute_name, vaa.attribute_value 
        FROM visticle_artikelattribute as vaa
        INNER JOIN visticle_artikel as va ON va.artikel_id=vaa.artikel_id
        WHERE attribute_name IN ('discount-from', 'discount-to', 'new-from','new-to')
        ORDER BY vaa.timestamp ASC;
        */

/* `visticle_db`.`vaa` */
$visticle_artikel_attribute = array(
    
);


  
        $attribut = $this->argument('attribut'); if($attribut=="false"){$attribut=false;}
        $customer = $this->argument('customer'); if($customer=="false"){$customer=false;}
        /*$subdomain = $customer;
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

        foreach($visticle_artikel_attribute as $oldArticle) {
            if($oldArticle['attribute_value'] == '') { continue; }
            $m = [];
            $number = preg_match("/^[^_]+(?=_)/",str_replace('fee-','',$oldArticle['artikel_system']), $m);
            if(empty($number)) { continue; }
            $number = $m[0];

            $article = Article::where('number','=',$number)->first();
            if(!$article) { continue; }
            $name = '';
            $from = null;
            $until = null;
            switch($oldArticle['attribute_name']) {
                case 'new-to':
                    $name = 'mark_as_new';
                    $until = $oldArticle['attribute_value'];
                break;
                case 'new-from':
                    $name = 'mark_as_new';
                    $from = $oldArticle['attribute_value'];
                break;
                case 'discount-to':
                    $name = 'activate_discount';
                    $until = $oldArticle['attribute_value'];
                break;
                case 'discount-from':
                    $name = 'activate_discount';
                    $from = $oldArticle['attribute_value'];
                break;
                default:
                break;
            }
            if($name == '') { continue; }

            $articleMarketing = Article_Marketing::updateOrCreate(
            [ 'fk_article_id' => $article->id, 'name' => $name ]);

            if($from) {
                $articleMarketing->update([
                    'active' => 1,
                    'from' => date('Y-m-d', strtotime($from))
                ]);
            }
            elseif($until) {
                $articleMarketing->update([
                    'active' => 1,
                    'until' => date('Y-m-d', strtotime($until))
                ]);
            }


        } //*/
    }
}
