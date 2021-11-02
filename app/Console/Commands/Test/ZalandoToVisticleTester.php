<?php

namespace App\Console\Commands\Test;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use Config;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;

class ZalandoToVisticleTester extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:zalando_to_visticle {apikey}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hiermit testen wir, was passiert, wenn Zalando uns anpingt, um Aufträge zu übermitteln.';

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
        $apikey=$this->argument('apikey');

        $client = new Client();

        $header=[
            'content-type'=>'application/json',
            'user-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0',
            'x-api-key' => $apikey,
        ];
        $body=[

        ];

        $url='https://www.visticle.online/api/zalando/order-state';

        /*
        $req = $client->request("post",$url_authorization,
                ['json'=> $body,'verify' => true]);
                */
        try{
            $req=$client->post($url,['body'=> json_encode($body),'headers'=>$header,'verify' => true]);
            //$req=$client->post($url_authorization,['json'=> $body,'debug' => true]);

            $status = $req->getStatusCode();
            $BodyClose = $req->getBody()->getContents();

            echo $status;
        }
        catch(\Exception $e){
            echo $e->getMessage();
        }






    }
}
