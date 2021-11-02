<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWResource;

use Illuminate\Database\Eloquent\Model;

class SWBranchVariation extends SWResource {
    private $stock;

    public function buildGet($model = null) {

    }
    public function buildGetAll() {

    }
    public function buildPost(Model $model = null) {

    }

    public function buildPut(Model $model = null) {
        $var_id = $model->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
        if(!$var_id) {
            return;
        }
        $body = [
            'variants' => [
                [
                    'number' => $var_id->value,
                    'inStock' => $model->getStock(),
                    'active' => (($model->getStock()>0)? $model->active : 0 ) //(($model->isActiveForShop()) ? 1 : 0)
                ]
            ],
        ];

        $this->setBody($body);
    }
    public function buildDelete(Model $model = null) {

    }

    public function setStock($stock) {
        $this->stock = $stock;
    }
}