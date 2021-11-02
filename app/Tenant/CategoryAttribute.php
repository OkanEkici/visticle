<?php

namespace App\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use Illuminate\Database\Eloquent\Model;
use App\Tenant\Category;

class CategoryAttribute extends Model
{

    protected $connection = 'tenant';

    protected $fillable = ['fk_category_id', 'name', 'value', ];

    public static function boot() {
        parent::boot();

        /*
        self::created(function($category) {
            $providerC = new ProviderController();
            $providerC->createdContentByType($category, 'category');
        });

        self::updated(function($category) {
            $providerC = new ProviderController();
            $providerC->updatedContentByType($category, 'category');
        });

        self::deleted(function($category) {
            $providerC = new ProviderController();
            $providerC->deletedContentByType($category, 'category');
        });
        */
    }
    public function category() {
        return $this->belongsTo(Category::class, 'fk_category_id');
    }
}
