<?php

namespace App\Tenant;

use App\Tenant\ArticleProviderSync;
use Illuminate\Database\Eloquent\Model;

class ArticleProviderSyncDeletion extends Model
{
    protected $connection = 'tenant';

    protected $fillable=[
        'fk_sync_id','value',
    ];
    public function articleProviderSync(){
        return $this->belongsTo(ArticleProviderSync::class,'fk_sync_id');
    }
}
