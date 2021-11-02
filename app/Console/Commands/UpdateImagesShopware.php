<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use Config;
use App\Tenant\Provider;
use App\Tenant\Provider_Type;
use DateTime;

use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Image;
use Log;

class UpdateImagesShopware extends Command
{
    protected $signature = 'shopware:imageupdate';
    protected $description = 'Aktualisiert die Produktbilder in Shopware im Hintergrund';

    public function __construct(){parent::__construct();}

    public function handle()
    {
        $tenants = Tenant::all();
        $date = new DateTime;
        $date->modify('-5 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');

        foreach($tenants as $tenant) 
        {//Set DB Connection
            \DB::purge('tenant');
            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);

            \DB::connection('tenant');

            $swType = Provider_Type::where('provider_key','=','shopware')->first();
            if(!$swType) { continue; }
            $swShop = Provider::where('fk_provider_type', '=', $swType->id)->first();
            if(!$swShop) { continue; }
            $swController = new ShopwareAPIController();

            $updatedImgAttrs = Article_Image_Attribute::where('name','=','sw_preview')->where('updated_at', '>=', $formatted_date)->get();
            $updatedImgs = Article_Image::where('updated_at', '>=', $formatted_date)->get();
            $updatedImgIds = [];

            foreach($updatedImgs as $updatedImg) {
                $swController->update_cron_article_image($updatedImg);
                $updatedImgIds[] = $updatedImg->id;
            }

            foreach($updatedImgAttrs as $updatedImgAttr) {
                $img = $updatedImgAttr->image()->first();
                if($img)
                {
                    if(in_array($img->id, $updatedImgIds)) { continue; }
                    $swController->update_cron_article_image($img);
                    $updatedImgIds[] = $img->id;
                }else
                {   Log::channel('single')->info('Shopware '.$tenant->subdomain.' Image konnte nicht gefunden werden zu Attribut ID: '.$updatedImgAttr->id);
                    continue; 
                }                
            }
            if(count($updatedImgIds) > 0) {
                Log::channel('single')->info('Shopware '.$tenant->subdomain.' '.count($updatedImgIds).' Bilderupdates', $updatedImgIds);
            }
        }
    }
}
