<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;

class Sparesets_Categories extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        , 'fk_category_id'
        , 'fk_spareset_id'
    ];

    public static function boot() {
        parent::boot();

        self::created(function($sparesetCategory) 
        {
            $providerC = new ProviderController();
            $providerC->createdContentByType($sparesetCategory, 'spareset_category');
        });

        self::updated(function($sparesetCategory) 
        {
            $providerC = new ProviderController();
            $providerC->updatedContentByType($sparesetCategory, 'spareset_category');
        });

        self::deleted(function($sparesetCategory) 
        {
            $providerC = new ProviderController();
            $providerC->deletedContentByType($sparesetCategory, 'spareset_category');
        });
    }

    public function category() {
        return $this->belongsTo(Category::class, 'fk_category_id');
    }
    
    public function spareset() {
        return $this->belongsTo(Sparesets::class, 'fk_spareset_id');
    }


    
}
