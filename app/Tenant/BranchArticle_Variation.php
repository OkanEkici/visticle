<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class BranchArticle_Variation extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_variation_id', 'fk_branch_id', 'stock', 'batch_nr'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($branch_article_variation)use($content_manager) {

                $content_manager->registrateOperation($branch_article_variation,'insert','immediate');
            });

            self::updated(function($branch_article_variation)use($content_manager) {
                $content_manager->registrateOperation($branch_article_variation,'update','immediate');
            });

            self::deleting(function($branch_article_variation)use($content_manager) {
                $content_manager->registrateOperation($branch_article_variation,'delete','immediate');
            });
        }
        catch(\Exception $e){

        }
    }


    public function branch() {
        return $this->belongsTo(Branch::class, 'fk_branch_id');
    }

    public function article_variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_article_variation_id');
    }
}
