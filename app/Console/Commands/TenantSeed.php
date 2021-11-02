<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use Config;

class TenantSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:seed {customer} {seeder_class_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ruft die übergebene Seederklasse für einen Kunden auf.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $customer=$this->argument('customer');
        $seeder_class_name=$this->argument('seeder_class_name');
        $tenant=Tenant::query()->where('subdomain','=',$customer)->first();
        if(!$tenant){
            echo 'Es besteht kein Eintrag für den gewählten Kunden \"' . $customer . '\"';
            return 1;
        }
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        //Jetzt rufen wir die Seederklasse auf
        $this->call('db:seed',['--class'=>$seeder_class_name]);

        return 0;
    }
}
