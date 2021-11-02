<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Price_Groups_Categories extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id', 'category_id', 'group_id', 'standard', 'discount'];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function pricegroup() {
        return $this->belongsTo(Price_Groups::class, 'group_id');
    }
}
