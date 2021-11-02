<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class WaWi extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name'];

    public function articles() {
        return $this->hasMany(Article::class, 'fk_wawi_id');
    }

    public function categories() {
        return $this->hasMany(Category::class, 'fk_wawi_id');
    }
}
