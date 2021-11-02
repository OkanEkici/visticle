<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article;
use App\Tenant\Setting;
use App\Http\Controllers\Tenant\Providers\Fashioncloud\FashionCloudController;
use Config;

class ImportFashionCloudContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:fashioncloud {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert den Content von Fashioncloud für neue Artikel.';

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
        $tenants = Tenant::inRandomOrder()->get();

        foreach($tenants as $tenant) {

                //Set DB Connection
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');

                if(Setting::getFashionCloudApiKey() == null) { continue; }


                $fcArticleIds = FashionCloudController::getArticleIDsToUpdate();

                echo '\n Artikel-IDs zum Aktualisieren('. $tenant->subdomain .')';
                var_dump(count($fcArticleIds));
                continue;

                $synchroType = Synchro_Type::where('key','=','fashioncloud_update')->first();
                $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
                $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
                $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
                $hasSynchro = true;
                $synchro = null;
                if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS) {
                    $hasSynchro = false;
                }
                if($hasSynchro) {
                    $synchro = Synchro::create(
                        [
                            'fk_synchro_type_id' => $synchroType->id,
                            'fk_synchro_status_id' => $inProgressSynchroS->id,
                            'start_date' => date('Y-m-d H:i:s')
                        ]
                    );
                }


                if(Setting::getFashionCloudApiKey() != null) {
                    /*
                    $article_query=Article::inRandomOrder();
                    if($this->option('all')){
                        //Alles!
                    }
                    else{
                        $article_query->where('fashioncloud_updated_at', '=', null);
                    }
                    */
                    //$fcArticleIds = $article_query->get()->pluck('id')->toArray();

                    $fcArticleIds = FashionCloudController::getArticleIDsToUpdate();

                    echo '\n Artikel-IDs zum Aktualisieren('. $tenant->subdomain .')';
                    var_dump(count($fcArticleIds));

                    $synchro->expected_count = count($fcArticleIds);
                    try {
                        $request = new \Illuminate\Http\Request();
                        $fcController = new FashionCloudController();
                        $fcController->syncMultipleArticles($request, $fcArticleIds);
                    }
                    catch(Exception $ex) {
                        if($synchro) {
                            $synchro->end_date = date('Y-m-d H:i:s');
                            $synchro->fk_synchro_status_id = $errorSynchroS->id;
                            $synchro->save();
                        }
                    }
                    if($synchro) {
                        $synchro->success_count = count($fcArticleIds);
                        $synchro->end_date = date('Y-m-d H:i:s');
                        $synchro->fk_synchro_status_id = $successSynchroS->id;
                        $synchro->save();
                    }
                }
        }
    }
}
