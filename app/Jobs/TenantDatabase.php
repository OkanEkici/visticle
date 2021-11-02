<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Tenant;
use App\Services\TenantManager;

class TenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    protected $tenantManager;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TenantManager $tenantManager)
    {
        $database    = $this->tenant->db;
        $connection  = \DB::connection('tenant');
        $createMysql = true;
        $dynamic = env('TENANT_DB_CREATION_DYNAMIC', 'false');
        if($dynamic == 'true'){
            $createMysql = $connection->statement('CREATE DATABASE ' . $database);
        }
        if ($createMysql) {
            $tenantManager->setTenant($this->tenant);
            \DB::purge('tenant');
            $this->migrate();
            $tenantManager->createResourceFolder();
        } else {
            if($dynamic == 'true'){
                $connection->statement('DROP DATABASE ' . $database);
            }
        }
    }

    private function migrate() {
        $migrator = app('migrator');
        $migrator->setConnection('tenant');

        $dynamic = env('TENANT_DB_CREATION_DYNAMIC', 'false');
        if($dynamic == 'true'){
            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }
    
            $migrator->run(database_path('migrations/tenants'), []);
        }
    }
}
