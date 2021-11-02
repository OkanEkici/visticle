<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Provider_Config_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_provider_config_id', 'name', 'value'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($provider_config_attribute)use($content_manager) {
                $content_manager->registrateOperation($provider_config_attribute,'insert','scheduled');
            });

            self::updated(function($provider_config_attribute)use($content_manager) {
                $content_manager->registrateOperation($provider_config_attribute,'update','scheduled');
            });

            self::deleting(function($provider_config_attribute)use($content_manager) {
                $content_manager->registrateOperation($provider_config_attribute,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function config() {
        return $this->belongsTo(Provider_Config::class, 'fk_provider_config_id');
    }
}
