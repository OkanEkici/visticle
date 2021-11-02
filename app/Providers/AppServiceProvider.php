<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TenantManager;
use Illuminate\Contracts\Encryption\DecryptException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $manager = new TenantManager;
    

        $this->app->instance(TenantManager::class, $manager);
        $this->app->bind(Tenant::class, function () use ($manager) {
            return $manager->getTenant();
        });

        $this->app['db']->extend('tenant', function ($config, $name) use ($manager) {
            $tenant = $manager->getTenant();
            if($tenant != null) {
                $tenant_type = $tenant->type()->first();
                $type_key = ($tenant_type) ? $tenant_type->type_key : 'vstcl-industry';
                view()->share('identifier', $tenant->getSubdomain());
                view()->share('tenant_type', $type_key);
                config()->set('tenant', ['identifier' => $tenant->getSubdomain(),'tenant_type' => $type_key]);
            }
            if ($tenant) {
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
            }
            return $this->app['db.factory']->make($config, $name);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
