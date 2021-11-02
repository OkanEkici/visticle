<?php

namespace App\Jobs\Manager\Controller;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Helpers\Miscellaneous;

class DoArticleBatchPerTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subdomain;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $subdomain)
    {
        $this->subdomain=$subdomain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Miscellaneous::loadTenantDBConnection($this->subdomain);

        $shopController = new VSShopController();
        //Log::channel('single')->info("prepare Batch: ".$tenant->subdomain);
        echo 'im job';
        $shopController->article_batch($this->subdomain);
        echo 'aus dem job';
    }
}
