<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class ArticleProviderSync extends Model
{
    protected $connection = 'tenant';

    protected $fillable=[
        'fk_article_id',
        'fk_provider_id',
        'operation',
        'subject',
        'subject_id',
        'context',
        'context_value',
        'priority',
    ];
    public function article(){
        return $this->belongsTo(Article::class,'fk_article_id');
    }
    public function provider(){
        return $this->belongsTo(Provider::class,'fk_provider_id');
    }
    public function deletion(){
        return $this->hasOne(ArticleProviderSyncDeletion::class,'fk_sync_id');
    }
}
