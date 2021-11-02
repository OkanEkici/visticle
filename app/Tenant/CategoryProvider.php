<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class CategoryProvider extends Model
{
    protected $connection = 'tenant';

    protected $fillable=['fk_category_id','fk_provider_id'];

    public function category(){
        return $this->belongsTo(Category::class,'fk_category_id');
    }
    public function provider(){
        return $this->belongsTo(Provider::class,'fk_provider_id');
    }
}
