<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;

class Equipmentsets_Categories extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        , 'fk_category_id'
        , 'fk_eqset_id'
    ];

    public static function boot() {
        parent::boot();

        self::created(function($eqsetCategory) 
        {
            $providerC = new ProviderController();
            $providerC->createdContentByType($eqsetCategory, 'eqset_category');
        });

        self::updated(function($eqsetCategory) 
        {
            $providerC = new ProviderController();
            $providerC->updatedContentByType($eqsetCategory, 'eqset_category');
        });

        self::deleted(function($eqsetCategory) 
        {
            $providerC = new ProviderController();
            $providerC->deletedContentByType($eqsetCategory, 'eqset_category');
        });
    }

    public function category() {
        return $this->belongsTo(Category::class, 'fk_category_id');
    }
    
    public function equipmentset() {
        return $this->belongsTo(Equipmentsets::class, 'fk_eqset_id');
    }


    
}
