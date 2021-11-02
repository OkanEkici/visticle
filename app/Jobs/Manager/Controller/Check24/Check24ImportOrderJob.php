<?php

namespace App\Jobs\Manager\Controller\Check24;

use App\Helpers\Miscellaneous;
use App\Tenant;
use App\Tenant\Provider_Type;
use App\Tenant\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class Check24ImportOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subdomain=null;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($subdomain)
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
        Artisan::call('import:check24_import_order',['customer' => $this->subdomain]);
    }

}
