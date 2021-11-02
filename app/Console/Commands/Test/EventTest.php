<?php

namespace App\Console\Commands\Test;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use Config;

class EventTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:event_test {customer?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test, ob durch Umwege eine Mass-Assignment-Operation zentral registriert werden kann.';

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
     * @return mixed
     */
    public function handle()
    {
        $domain=$this->argument('customer');
        $tenant = Tenant::query()->where('subdomain','=',$domain)->first();
        $tenants=null;
        if(!$tenant){
            $tenants=Tenant::all();
        }
        else{
            $tenants=Tenant::where('id',$tenant->id)->get();
        }

        foreach($tenants as $tenant){
            //Set DB Connection
            \DB::purge('tenant');
            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);
            \DB::connection('tenant');



            //alle Artikel holen
            $article=Article::first();

            echo "Test startet \n";
            $GLOBALS['event_test']=true;

            $article->name=$article->name . ' -';
            $article->update();

            $article->delete();

        }


    }
}
