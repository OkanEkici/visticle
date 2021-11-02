<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWResource;

use Illuminate\Database\Eloquent\Model;

class SWArticleVariationPrice extends SWResource {
    public function buildGet($model = null) {

    }
    public function buildGetAll() {

    }
    public function buildPost(Model $model = null) {
        $var_id = $model->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
        if(!$var_id) { return; }

        $article = $model->article()->first();
        $sw_id = $article->attributes()->where('name','=','sw_id')->whereNotNull('value')->first();
        if(!$sw_id) { return; }

        $body = [
            'variants' => [
                'id' => $var_id->value,
                'number' => $var_id->value,
                'articleId' => $sw_id->value,
            ]
        ];

        $this->setBody($body);
    }
    public function buildPut(Model $model = null) {
        $var_id = $model->attributes()->where('name','=','sw_variantid')->whereNotNull('value')->first();
        if(!$var_id) {
            return;
        }
        $body = [
            'variants' => [
                'id' => $var_id->value
            ]
        ];

        $this->setBody($body);
    }
    public function buildDelete(Model $model = null) {

    }
}