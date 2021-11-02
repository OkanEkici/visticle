<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;

class ArticleProvider extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_provider_id', 'fk_article_id', 'active'];

    public static function boot() {
        parent::boot();

        self::created(function($articleprovider) {
            $providerC = new ProviderController();
            $providerC->createdContentByType($articleprovider, 'articleprovider');
        });

        self::updated(function($articleprovider) {
            $providerC = new ProviderController();
            $providerC->updatedContentByType($articleprovider, 'articleprovider');
        });

        self::deleted(function($articleprovider) {
            $providerC = new ProviderController();
            $providerC->deletedContentByType($articleprovider, 'articleprovider');
        });
    }

    public function provider() {
        return $this->belongsTo(Provider::class, 'fk_provider_id');
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }

    public function variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_article_variation_id');
    }
}
