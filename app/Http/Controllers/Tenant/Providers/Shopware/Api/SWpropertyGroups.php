<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;

use App\Http\Controllers\Tenant\Providers\Shopware\Api\SWResource;

use Illuminate\Database\Eloquent\Model;
use Log;

class SWpropertyGroups extends SWResource 
{    
    public function buildPost(Model $model = null) 
    {   
        $body = [
            'id' => $this->getRId()
            ,'name' => $model->name
            ,'position' =>0
            ,'comparable' => true
            ,'sortmode' => 3
        ];
        $this->setBody($body);
    }
    public function buildPut(Model $model = null) {}
    public function buildDelete(Model $model = null) {}
    public function buildGet($model = null) {}
    public function buildGetAll() {}

}