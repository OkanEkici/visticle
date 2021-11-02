<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

use App\Tenant\Article, App\Tenant\Article_Attribute, App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute;
use App\Tenant\Setting;
use App\Tenant\Settings_Attribute;
use Storage;
use App\Http\Controllers\Tenant\Providers\Fashioncloud\FashionCloudController;


class FashionCloudSyncArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
    * @var Article
    */
    protected $article;

    protected $subdomain;

    protected $apiKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($articleId, $subdomain)
    {
        $this->article = Article::find($articleId);
        $this->subdomain = $subdomain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $fcController = new FashionCloudController();
        $fcController->syncArticleOnly(request(),$this->article->id);

        return;

        $this->apiKey = Setting::getFashionCloudApiKey();
        $client = new Client(); //GuzzleHttp\Client
        $uriParams = '/products?token='.$this->apiKey;
        $errorMessage = 'Es ist ein Fehler aufgetreten';
        $successMessage = 'Daten erfolgreich synchronisiert!';
        $failed = false;
        $promises = [];

        $articleNumber = $this->article->number;
        $articleNumber = '15113236';
        if(empty($articleNumber)) {
            return;
        }
        $uriParams .= '&articleNumber='.$articleNumber;
        dd($uriParams);
        try {
            $req = new \GuzzleHttp\Psr7\Request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').$uriParams);
            $promises[] = $client->sendAsync($req)->then(function ($res) {
                echo 'I completed! ' . $res->getBody();
                $status = $res->getStatusCode();
                if($status != 200) {
                    $failed = true;
                }
                $data = json_decode($res->getBody());
                //Foreach Variation in Product
                foreach($data->data as $product) {
                    //Check if Product was updated before and update if something new
                    foreach($product->media->images as $productImg) {
                        //Check if Img already loaded and if not load
                        $this->loadImage($productImg->_id);
                    }
                }
                $failed = false;

            });

        } catch(GuzzleException $e) {
            $failed = true;
            switch($e->getCode()) {
                case 401:
                    $errorMessage = 'Ihr API Key konnte sich nicht authentifizieren! Prüfen Sie Ihn in den Einstellungen->Partner->Fashioncloud';
                break;
                default:
                break;
            }
        }


        $results = Promise\unwrap($promises);
    }

    public function loadImage($id) {
        $this->apiKey = Setting::getFashionCloudApiKey();
        $client = new Client(); //GuzzleHttp\Client
        $name = $this->article->id.'_fc_'.$id;
        $folder = '/'.$this->subdomain.'/img/products/';
        $filePath = $folder.$name. '.png';

        $articleImage = Article_Image::updateOrCreate([
            'fk_article_id' => $this->$article->id,
            'fashioncloud_id' => $id,
        ]);

        $articleImage->location = $filePath;
        $articleImage->save();

        if(Storage::disk('public')->get($filePath)){
            return;
        }
        try {
            $req = new \GuzzleHttp\Psr7\Request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products/media/images/'.$id.'?token='.$this->apiKey.'&watermark=false');
            $promise = $client->sendAsync($req)->then(function ($response) {
                $status = $response->getStatusCode();
                if($status != 200) {
                    return false;
                }
                Storage::disk('public')->put($filePath, $response->getBody());
            });
            $promise->wait();

            return true;

        } catch(GuzzleException $e) {
            return false;
        }
    }
}
