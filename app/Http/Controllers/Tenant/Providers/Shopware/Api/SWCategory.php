<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWResource;

use Illuminate\Database\Eloquent\Model;

class SWCategory extends SWResource {
    public function buildGet($model = null) {

    }
    public function buildGetAll() {

    }
    public function buildPost(Model $model = null) {
        $parent = $model->parentCategory()->first();
        $swParentId = null;
        if($parent && $parent->fk_wawi_id == null) {
            $swParentId = $parent->wawi_number;
        }
        $body = [
            'name' => $model->name,
            'metaDescription' => $model->description,
            'metaKeywords' => $model->description
        ];
        if($swParentId) {
            $body['parentId'] = $swParentId;
        }
        $this->setBody($body);
    }
    public function buildPut(Model $model = null) {
        $parent = $model->parentCategory()->first();
        $swParentId = null;
        if($parent && $parent->fk_wawi_id == null) {
            $swParentId = $parent->wawi_number;
        }
        $body = [
            'name' => $model->name,
            'metaDescription' => $model->description,
            'metaKeywords' => $model->description
        ];
        if($swParentId) {
            $body['parentId'] = $swParentId;
        }
        $this->setBody($body);
    }
    public function buildDelete(Model $model = null) {

    }
}