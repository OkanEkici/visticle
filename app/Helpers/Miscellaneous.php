<?php

namespace App\Helpers;

use App\Tenant;
use Config;

class Miscellaneous{
    public static function getRandomString($length=20){
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++)
        {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public static function loadTenantDBConnection($subdomain){
        if(!$subdomain) { return; }

        $tenant=Tenant::query()->where('subdomain','=',$subdomain)->first();

        if(!$tenant){
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
    }
    public static function loadMainDBConnection(){
        //Set DB Connection
        \DB::purge('mysql');
        \DB::connection('mysql');
    }
    /**
     * Diese Funktion ändert über die GD-Bibliothek die Höhe eines Bildes.
     * Die Breite wird proportional angepasst.
     */
    public static function resizeImageHeight($image_path,$height){
        $extension=\exif_imagetype($image_path);

        $image_resource=null;
        if(IMAGETYPE_JPEG==$extension){
            $image_resource=imagecreatefromjpeg($image_path);
        }
        elseif(IMAGETYPE_PNG==$extension){
            $image_resource=imagecreatefrompng($image_path);
        }
        
        if($image_resource){
            $image_resource=\imagescale($image_resource,-1,$height);

            if(IMAGETYPE_JPEG==$extension){
                \imagejpeg($image_resource,$image_path);
            }
            elseif(IMAGETYPE_PNG==$extension){
                \imagepng($image_resource,$image_path);
            }
            
        }        
    }
}
