<?php

namespace App\Console\Commands;

use App\AccountType;
use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Tenant\Article;
use Illuminate\Console\Command;
use Config;
use Log;
use Illuminate\Http\Request;

class ExportArticleAll extends Command
{
    protected $signature = 'export:article_fill {customer} {log} {--file_output}';

    protected $description = 'Exports All Articles to a specific VS-Shop';

    public function __construct(){parent::__construct();}

    public function handle()
    {


        $tenants = Tenant::all();

        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}

        $EchologAktiv = $this->argument('log');
        if($EchologAktiv=="false" || !$EchologAktiv){$EchologAktiv=false;}

        $account_type='';


        foreach($tenants as $tenant) {
                if($tenant->subdomain != $exec_for_customer){continue;}
                //Wir werten den Accounttyp aus!
                $account_type_instance=AccountType::query()
                ->where('id','=',$tenant->fk_account_type_id)->first();
                $account_type=$account_type_instance->type_key;
                //Set DB Connection
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');

                $file_output=false;
                if($this->option('file_output')){
                    $file_output=true;
                }

                $shopController = new VSShopController();
                $shopController->article_fill($tenant->subdomain, $EchologAktiv,$account_type,$file_output);
        }

    }
}
