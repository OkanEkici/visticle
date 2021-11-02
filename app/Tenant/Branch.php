<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $connection = 'tenant';
    
    protected $fillable = ['wawi_ident', 'name', 'active', 'wawi_number', 'zalando_id'];

    public function article_variations() {
        return $this->hasMany(BranchArticle_Variation::class, 'fk_branch_id');
    }
}
