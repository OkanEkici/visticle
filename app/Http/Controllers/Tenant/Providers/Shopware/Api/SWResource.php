<?php

namespace App\Http\Controllers\Tenant\Providers\Shopware\Api;
use Illuminate\Database\Eloquent\Model;

abstract class SWResource {

    protected $body = [];
    protected $filter;
    protected $model;
    protected $rId;
    protected $data = [];

    abstract protected function buildGet(Model $model = null);
    abstract protected function buildGetAll();
    abstract protected function buildPost(Model $model = null);
    abstract protected function buildPut(Model $model = null);
    abstract protected function buildDelete(Model $model = null);

    public function getBody() { return $this->body; }
    public function getRId() { return $this->rId; }
    public function setRId($rid) { $this->rId = $rid; }
    public function setData($data) { $this->data = $data; }
    public function getData() { return $this->data; }
    public function setFilter($filter) { $this->filter = $filter; }
    public function setBody($body) { $this->body = $body; }
    public function setModel($model) { $this->model = $model; }

    protected function replaceUmlauts($string) {
        $search = array("Ä", "Ö", "Ü", "ä", "ö", "ü");
        $replace = array("AE", "OE", "UE", "ae", "oe", "ue");
        return str_replace($search, $replace, $string);
    }

}
