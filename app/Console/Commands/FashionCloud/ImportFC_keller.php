<?php

namespace App\Console\Commands\FashionCloud;

use App\Tenant;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article;
use App\Tenant\Setting;
use App\Http\Controllers\Tenant\Providers\Fashioncloud\FashionCloudController;
use Config;
use Log;

class ImportFC_keller
{
    protected $all=false;
    public function __construct($all=false){
        $this->all=$all;
    }

    public function handle()
    {
        $tenant = Tenant::where('subdomain','=','keller')->first();

        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        if(Setting::getFashionCloudApiKey() == null) { return; }
        else{
            //*
            $synchroType = Synchro_Type::where('key','=','fashioncloud_update')->first();
            $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
            $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
            $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
            $hasSynchro = true; $synchro = null;
            if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS)
            { $hasSynchro = false; }
            if($hasSynchro) {
                $synchro = Synchro::create(
                [   'fk_synchro_type_id' => $synchroType->id,
                    'fk_synchro_status_id' => $inProgressSynchroS->id,
                    'start_date' => date('Y-m-d H:i:s')
                ]);
            }
            $fcArticleIds=null;
            /*
            if($this->all){
                $fcArticleIds = Article::inRandomOrder()->get()->pluck('id')->toArray();
            }
            else{
                $fcArticleIds = Article::inRandomOrder()->where('fashioncloud_updated_at', '=', null)->get()->pluck('id')->toArray();
            }
            */

            $fcArticleIds = FashionCloudController::getArticleIDsToUpdate();

            echo '\n Artikel-IDs zum Aktualisieren: \n';
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
            } // */
            Log::info("Fashioncloud Sync abgeschlossen für ".$tenant->subdomain."");
        }
    }
}
