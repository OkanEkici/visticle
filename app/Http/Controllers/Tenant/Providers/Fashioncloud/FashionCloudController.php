<?php

namespace App\Http\Controllers\Tenant\Providers\Fashioncloud;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

use App\Jobs\FashionCloudSyncArticles;
use App\Jobs\FashionCloudSyncArticle;
//use App\Http\Controllers\Tenant\Providers\Fashioncloud\FashionCloudController;
use Redirect,Response;
use App\Tenant\Article, App\Tenant\Article_Attribute, App\Tenant\Article_Image, App\Tenant\Article_Image_Attribute;
use App\Tenant\Article_Variation, App\Tenant\Article_Variation_Image, App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\Setting;
use App\Tenant\Settings_Attribute;
use Storage;
use DateTime;
use Log;

class FashionCloudController extends Controller
{
    private $apiKey;

    private $brandIds = [];

    //In Days
    private static $update_interval=30;

    public function __construct() {

    }

    public function syncArticles(Request $request) {

        FashionCloudSyncArticles::dispatch();
        return redirect()->back()->withSuccess('Daten werden im Hintergrund synchronisiert!');

    }

    public function syncArticle(Request $request, $id) {
        FashionCloudSyncArticle::dispatch($id, $request->get('identifier'));
        return redirect()->back()->withSuccess('Daten werden im Hintergrund synchronisiert!');
    }

    //Diese Methode liefert die IDs aller ARtikel, die eine Aktualisierung benötigen
    public static function getArticleIDsToUpdate(){
        $articles=Article::query()->where('fashioncloud_updated_at','=',null);

        //->orWhere(function($query){
            /*
            $query->whereDoesntHave('images.attributes',function($query){
                $query->where('name','fc_expiresAt');
            });
            */
            //$query->orWhereHas('images.attributes',function($query){
            //    $query->where('name','fc_expiresAt');

                //aktuelles Datum
            //    $date_text=date('Y-m-d');
            //    $query->whereDate('value','<=',$date_text);
            //});
        //})
        //->orWhere(function($query){
            /*
            $query->whereDoesntHave('variations.images.attributes',function($query){
                $query->where('name','fc_expiresAt');
            });
            */
            //$query->orWhereHas('variations.images.attributes',function($query){
            //    $query->where('name','fc_expiresAt');

                //aktuelles Datum
              //  $date_text=date('Y-m-d');
              //  $query->whereDate('value','<=',$date_text);
            //});
        //})
        //->orWhereRaw('datediff(curdate(), case when fashioncloud_updated_at is null then curdate() else fashioncloud_updated_at end) >= ?',[self::$update_interval]);

        $ids=$articles->get()->pluck('id')->toArray();

        return $ids;
    }
    //Diese Methode aktualisiert alle Artikel, die eine Aktualisierung benötigen
    public function syncArticlesToUpdate(){
        $ids=self::getArticleIDsToUpdate();
        $this->syncMultipleArticles(request(),$ids,true);
    }
    public function syncMultipleArticles(Request $request, $ids = [],$Log=true) {
        $this->apiKey = Setting::getFashionCloudApiKey();
        if(!$this->apiKey) {
            return redirect()->back()->withError('Kein API Key vorhanden');
        }
        $ids = ($request->ids) ? $request->ids : $ids;
        $failed = false;
        $errorMessage = 'Es ist ein Fehler aufgetreten';
        $successMessage = 'Daten erfolgreich synchronisiert!';
        foreach($ids as $id) {
            $failed = $this->syncArticleOnly($request, $id, false, $Log);
        }
        if($failed) {
            return redirect()->back()->withError($errorMessage);
        }
        else {
            return redirect()->back()->withSuccess($successMessage);
        }
    }

    public function syncArticleOnly(Request $request, $id, $redirect = true,$Log=true) {
        $article = Article::find($id);
        $oldDate = $article->fashioncloud_updated_at;
        //$article->fashioncloud_updated_at = date('Y-m-d H:i:s');
        //$article->save();

        $checkNameSet=0;$checkDescSet=0;$checkShortdescSet=0;
        $this->apiKey = Setting::getFashionCloudApiKey();
         //GuzzleHttp\Client
        $uriParams = '/products?token='.$this->apiKey;
        $errorMessage = 'Es ist ein Fehler aufgetreten';
        $successMessage = 'Daten erfolgreich synchronisiert!';
        $failed = false; $promises = [];

        $ean = $article->ean;
        if(empty($ean)) { return redirect()->back()->withWarning('Dieser Artikel hat keine EAN'); }
        $uriParams .= '&gtin='.$ean;
        $checkVarloaded=0;

        if(strtotime($oldDate) >= strtotime(date('Y-m-d H:i:s') . '-1 day'))
        {   if($Log){echo "\n[SKIP-Artikel:".$article->id."]";} }
        else
        {
            $variation_success=0;
            $variation_fail=0;
            foreach($article->variations()->get() as $variation)
            {
                $varEan = str_replace('vstcl-', '', $variation->vstcl_identifier);
                if($varEan==""){continue;}
                if($Log){echo "\nprüfe ohne Status: ".env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products?token='.$this->apiKey.'&gtin='.$varEan;}
                $client = new Client();
                $res = $client->request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products?token='.$this->apiKey.'&gtin='.$varEan);
                $data = json_decode($res->getBody());

                 //Wenn das Data-Feld leer ist, so muss dass geloggt werden in einer Datei
                if(count( $data->data)==0){
                    Log::channel("fashioncloud_error")->info(env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products?token='.$this->apiKey.'&gtin='.$varEan);
                }
                //dd($data);
                $status = $res->getStatusCode();
                if($status != 200) { Log::info('FashionCloud Status: '.$status.' EAN: '.$varEan);
                    //nochmal mit EAN versuchen
                    if($variation->ean != "")
                    {
                        $varEan = $variation->ean;
                        if($varEan==""){continue;}
                        if($Log){echo "\nStatus !=200: prüfe: ".env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products?token='.$this->apiKey.'&gtin='.$varEan;}
                        $client = new Client();
                        $res = $client->request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products?token='.$this->apiKey.'&gtin='.$varEan);
                        $data = json_decode($res->getBody());




                        //dd($data);
                        $status = $res->getStatusCode();
                        if($status != 200) {Log::info('2. FashionCloud Status: '.$status.' EAN: '.$varEan);}
                    }
                }




                foreach($data->data as $product)
                {
                    //Check if Img already loaded and if not load
                    foreach($product->media->images as $varProductImg)
                    {
                        $image_result=
                        $this->loadVariationImage($varProductImg->_id, $variation, $request,$Log,$varProductImg);
                        if($image_result){
                            $variation_success++;
                        }
                        else{
                            $variation_fail++;
                        }
                        $checkVarloaded=1;
                    }

                    if($checkNameSet == 0)
                    {
                        if(isset($product->manufacturerAttributes->name) && !empty($product->manufacturerAttributes->name))
                        {
                            $article->name = $product->manufacturerAttributes->name;
                            $checkNameSet=1;
                        }
                    }
                    if($checkDescSet == 0){if(isset($product->manufacturerAttributes->description) && !empty($product->manufacturerAttributes->description)) { $article->description = $product->manufacturerAttributes->description;$checkDescSet=1; }}
                    if($checkShortdescSet == 0){if(isset($product->manufacturerAttributes->shortDescription) && !empty($product->manufacturerAttributes->description)) { $article->short_description = $product->manufacturerAttributes->shortDescription;$checkShortdescSet=1; }}

                    /**
                     * @author Tanju Özsoy
                     * /Nun greifen wir alles ab für jede Artikelvariation, was die Api so hergibt.
                     */
                    $this->loadProductInfos($product,$article,$variation);
                }
            }
            if($checkNameSet == 1 ||$checkDescSet == 1||$checkShortdescSet == 1 ||$checkVarloaded==1)
            {
                $failed = false;
            }




            try {
                $client = new Client();
                $res = $client->request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').$uriParams);
                $status = $res->getStatusCode();
                if($status != 200) { $failed = true; }

                $data = json_decode($res->getBody());

                 //Wenn das Data-Feld leer ist, so muss dass geloggt werden in einer Datei
                 if(count( $data->data)==0){
                    Log::channel("fashioncloud_error")->info(env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').$uriParams);
                }

                $lastUpdateArticle = $article->fashioncloud_updated_at;
                if(!is_null($lastUpdateArticle)) { $articleDate = new DateTime($lastUpdateArticle); }

                //$article->update(['batch_nr' => date($article->id.'YmdHis')]);
                //dd($data); //See data
                //Foreach Variation in Product
                $checkBrand=false;

                $article_success=0;
                $article_fail=0;
                foreach($data->data as $product) {
                    $fashionCloudDate = new DateTime($product->updated);

                    /*
                    // Check if Article was updated before
                    if($lastUpdateArticle != null && $fashionCloudDate < $articleDate) {
                        continue;
                    }*/
                    //Check if Product was updated before and update if something new
                    foreach($product->media->images as $productImg) {
                        //Check if Img already loaded and if not load
                        $image_result=
                        $this->loadImage($productImg->_id, $article, $request,$Log,$productImg);

                        if($image_result){
                            $article_success++;
                        }
                        else{
                            $article_fail++;
                        }
                    }
                    //Update Attributes
                    if($checkNameSet == 0 && isset($product->manufacturerAttributes->name) && !empty($product->manufacturerAttributes->name))
                    { $article->name = $product->manufacturerAttributes->name; $checkNameSet=1; }
                    if($checkDescSet == 0 && isset($product->manufacturerAttributes->description) && !empty($product->manufacturerAttributes->description))
                    { $article->description = $product->manufacturerAttributes->description;$checkDescSet=1; }
                    if($checkShortdescSet == 0 && isset($product->manufacturerAttributes->shortDescription) && !empty($product->manufacturerAttributes->description))
                    { $article->short_description = $product->manufacturerAttributes->shortDescription;$checkShortdescSet=1; }
                    //Marke abgreifen und setzen
                    if($checkBrand==false && isset($product->brand->_id) && !empty($product->brand->_id) ){
                        $brand_id=$product->brand->_id;
                        $this->loadBrand($brand_id,$article);
                        $checkBrand=true;
                    }
                }

                //Wir dürfen das Update-Datum für die Fashioncloud nur setzen, wenn wir problemlos alle
                //Bilder setzen konnten!
                if( ($article_success>0 && $variation_success>0) && ($article_fail==0 && $variation_fail==0) )
                {
                    $article->fashioncloud_updated_at = date('Y-m-d H:i:s');
                    echo 'UPdatedatum gesetzt!';
                }

                $article->save();
                $failed = false;

            } catch(GuzzleException $e) {
                $failed = true;
                Log::error('FashionCloud Exception: '.$e->getCode().' - '.$e->getMessage());
                switch($e->getCode()) {
                    case 401:
                        $errorMessage = 'Ihr API Key konnte sich nicht authentifizieren! Prüfen Sie Ihn in den Einstellungen->Partner->Fashioncloud';
                    break;
                    default:
                    break;
                }
            }
        }
        if(!$redirect) { return $failed; }

        if($failed) { return redirect()->back()->withError($errorMessage); }
        else { return redirect()->back()->withSuccess($successMessage); }

    }
    private function loadProductInfos($product,$article,$variation)
    {
        //StyleGroupID
        if(isset($product->styleGroupId) && !empty($product->styleGroupId))
        {
            $variation->updateOrCreateAttribute('fc_styleGroupId', $product->styleGroupId);
        }
        //ColorGroupID
        if(isset($product->colorGroupId) && !empty($product->colorGroupId))
        {
            $variation->updateOrCreateAttribute('fc_colorGroupId', $product->colorGroupId);
        }
        //ColorGroupID
        if(isset($product->colorGroupId) && !empty($product->colorGroupId))
        {
            $variation->updateOrCreateAttribute('fc_colorGroupId', $product->colorGroupId);
        }
        //ColorGroupID
        if(isset($product->colorGroupId) && !empty($product->colorGroupId))
        {
            $variation->updateOrCreateAttribute('fc_colorGroupId', $product->colorGroupId);
        }
        //manufacturerAttributes_name
        if(isset($product->manufacturerAttributes->name) && !empty($product->manufacturerAttributes->name))
        {
            $variation->updateOrCreateAttribute('fc_manufacturerAttributes_name', $product->manufacturerAttributes->name);
        }
        //manufacturerAttributes_description
        if(isset($product->manufacturerAttributes->description) && !empty($product->manufacturerAttributes->description))
        {
            $variation->updateOrCreateAttribute('fc_manufacturerAttributes_description', $product->manufacturerAttributes->description);
        }
         //manufacturerAttributes_size
         if(isset($product->manufacturerAttributes->size) && !empty($product->manufacturerAttributes->size))
         {
             $variation->updateOrCreateAttribute('fc_manufacturerAttributes_size', $product->manufacturerAttributes->size);
         }
         //manufacturerAttributes_colorCode
         if(isset($product->manufacturerAttributes->colorCode) && !empty($product->manufacturerAttributes->colorCode))
         {
             $variation->updateOrCreateAttribute('fc_manufacturerAttributes_colorCode', $product->manufacturerAttributes->colorCode);
         }
         //manufacturerAttributes_colorName
         if(isset($product->manufacturerAttributes->colorName) && !empty($product->manufacturerAttributes->colorName))
         {
             $variation->updateOrCreateAttribute('fc_manufacturerAttributes_colorName', $product->manufacturerAttributes->colorName);
         }
         //manufacturerAttributes_material
         if(isset($product->manufacturerAttributes->material) && !empty($product->manufacturerAttributes->material))
         {
             $variation->updateOrCreateAttribute('fc_manufacturerAttributes_material', $product->manufacturerAttributes->material);
         }
         //fcAttributes_searchColors
         if(isset($product->fcAttributes->searchColors) && !empty($product->fcAttributes->searchColors))
         {
             $search_colors_text=implode(':',$product->fcAttributes->searchColors);
             $variation->updateOrCreateAttribute('fc_fcAttributes_searchColors', $search_colors_text);
         }
         //fcAttributes_material
         if(isset($product->fcAttributes->material) && !empty($product->fcAttributes->material))
         {
             $variation->updateOrCreateAttribute('fc_fcAttributes_material', $product->fcAttributes->material);
         }
         //fcAttributes_material
         if(isset($product->fcAttributes->ageGroup) && !empty($product->fcAttributes->ageGroup))
         {
             $variation->updateOrCreateAttribute('fc_fcAttributes_ageGroup', $product->fcAttributes->ageGroup);
         }
         //fcAttributes_targetGroup
         if(isset($product->fcAttributes->targetGroup) && !empty($product->fcAttributes->targetGroup))
         {
             $variation->updateOrCreateAttribute('fc_fcAttributes_targetGroup', $product->fcAttributes->targetGroup);
         }
         //fcAttributes_season
         if(isset($product->fcAttributes->season) && !empty($product->fcAttributes->season))
         {
             $variation->updateOrCreateAttribute('fc_fcAttributes_season', $product->fcAttributes->season);
         }
    }

    private function loadBrand($brand_id,$article) {
        $this->apiKey = Setting::getFashionCloudApiKey();

        $client = new Client(); //GuzzleHttp\Client
        try {
            $res = $client->request('GET', 'https://api.fashion.cloud/v1/brands/'.$brand_id.'?&apiKey='.$this->apiKey);
            $status = $res->getStatusCode();
            if($status != 200) {
                return false;
            }
            $data = json_decode($res->getBody());

            if(isset($data->name) && !empty($data->name) && isset($data->description) && !empty($data->description)){
                $article->updateOrCreateAttribute('fc_brandName',$data->name);
                $article->updateOrCreateAttribute('fc_brandDescription',$data->description);
            }

            return true;

        } catch(GuzzleException $e) {
            return false;
        }
    }
    private function loadBrands() {
        $this->apiKey = Setting::getFashionCloudApiKey();
        $articleIds = ['5c82d6c026a8840012e06135'];
        $client = new Client(); //GuzzleHttp\Client
        try {
            $res = $client->request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/brands?token='.$this->apiKey.'');
            $status = $res->getStatusCode();
            if($status != 200) {
                return false;
            }
            $data = json_decode($res->getBody());
            foreach($data->data as $brand) {
                $this->brandIds[] = $brand->_id;
            }

            return true;

        } catch(GuzzleException $e) {
            return false;
        }
    }

    private static function checkFitImageSize($image_path,$heigt){
        $file_info=getimagesize($image_path);
        if($file_info[1]!=$height){
            \App\Helpers\Miscellaneous::resizeImageHeight($image_path,$height);
        }
    }
    private function loadImage($id, Article $article, Request $request,$Log=false,$productImg) {
        $this->apiKey = Setting::getFashionCloudApiKey();
        $client = new Client(); //GuzzleHttp\Client
        $name = $id;
        $folder = '/fashioncloud/img/';
        $filePath = $folder.$name. '.png';

        $imgTypes = ['200', '512', '1024', 'original'];

        foreach($imgTypes as $imgType) {
            $px = '';
            if($imgType != 'original') {
                $filePath = $folder.$imgType.'/'.$name. '.png';
                $px = '&px='.$imgType.'&minAcceptableSize='.$imgType;
            }

            $articleImage = Article_Image::updateOrCreate([
                'fk_article_id' => $article->id,
                'fashioncloud_id' => $id,
            ]);

            $articleImage->location = $filePath;
            $articleImage->save();

            Article_Image_Attribute::updateOrCreate(
                ['fk_article_image_id' => $articleImage->id, 'name' => 'imgType'],
                ['value' => $imgType]
            );

            //Ablaufdatum speichern!
            if(isset($productImg->expiresAt) && !empty($productImg->expiresAt)){
                $expires_at_text=$productImg->expiresAt;
                //Nur das Datum herausholen
                $expires_at_text=explode('T',$expires_at_text)[0];

                echo 'Ablaufdatum ' . $expires_at_text;
                Article_Image_Attribute::updateOrCreate(
                    ['fk_article_image_id' => $articleImage->id,
                    'name' => 'fc_expiresAt'],
                    ['value' => $expires_at_text]
                );
            }

            //if(Storage::disk('public')->exists($filePath)){ continue; }
            $image_exists=false;
            if(Storage::disk('public')->exists($filePath))
            {
                $image_exists=true;
            }
            try {
                $res = $client->request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products/media/images/'.$id.'?token='.$this->apiKey.'&watermark=false'.$px);
                $status = $res->getStatusCode();
                if($status != 200 && $image_exists==false) {
                    if($Log){echo "\n[IMGfail:".json_encode($res)."]";}
					$articleImageAttrs = $articleImage->attributes()->get();
					foreach($articleImageAttrs as $articleImageAttr)
					{$articleImageAttr->delete();}


                    $articleImage->delete();

                    Log::info("FashionCloud Image konnte nicht geladen werden: ".$filePath." für ".$article->id);
                }
                elseif($status==200)
                {
                    Storage::disk('public')->put($filePath, $res->getBody());
                    if($Log){echo "[save]";}
                    if($imgType!='original'){
                        try{
                            $absolute_path=Storage::disk('public')->path($filePath);
                            self::checkFitImageSize($absolute_path,$imgType);
                            if($Log){echo "[Bild angepasst]";}
                        }
                        catch(\Exception $e){

                        }

                    }
                    //jetzt liefern wir ein positives ergebnis zurück
                    return true;
                }

            } catch(GuzzleException $e) {
                // Bilddatei Download wurde von FashionCloud abgelehnt
                if($Log){echo "\n[IMGfail2:".$e->getMessage()."]";}
				$articleImageAttrs = $articleImage->attributes()->get();
				foreach($articleImageAttrs as $articleImageAttr)
				{$articleImageAttr->delete();}
				$articleImage->delete();
            }

        }

        //sollten wir diese Zeile erreichen, so konnte kein Bild geladen werden. Es gab kein Update! Und das quittieren wir mit false!
        return false;
    }

    private function loadVariationImage($id, Article_Variation $variation, Request $request,$Log=false,$productImg) {
        $this->apiKey = Setting::getFashionCloudApiKey();
        $client = new Client(); //GuzzleHttp\Client
        $name = $id;
        $article = $variation->article()->first();
        $folder = '/fashioncloud/img/';
        $filePath = $folder.$name. '.png';

        $imgTypes = ['200', '512', '1024', 'original'];

        foreach($imgTypes as $imgType) {

            $px = '';
            if($imgType != 'original') {
                $filePath = $folder.$imgType.'/'.$name. '.png';
                $px = '&px='.$imgType."&minAcceptableSize=".$imgType;
            }

            $articleImage = Article_Image::updateOrCreate([
                'fk_article_id' => $article->id,
                'fashioncloud_id' => $id,
            ]);

            $articleImage->location = $filePath;
            $articleImage->save();

            Article_Image_Attribute::updateOrCreate(
                ['fk_article_image_id' => $articleImage->id, 'name' => 'imgType'],
                ['value' => $imgType]
            );

            $articleVarImage = Article_Variation_Image::updateOrCreate([
                'fk_article_variation_id' => $variation->id,
                'fashioncloud_id' => $id,
            ]);

            $articleVarImage->location = $filePath;
            $articleVarImage->save();

            Article_Variation_Image_Attribute::updateOrCreate(
                ['fk_article_variation_image_id' => $articleVarImage->id, 'name' => 'imgType'],
                ['value' => $imgType]
            );


             //Ablaufdatum speichern!
             if(isset($productImg->expiresAt) && !empty($productImg->expiresAt)){
                $expires_at_text=$productImg->expiresAt;
                //Nur das Datum herausholen
                $expires_at_text=explode('T',$expires_at_text)[0];

                echo 'Ablaufdatum: ' . $expires_at_text;
                Article_Variation_Image_Attribute::updateOrCreate(
                    ['fk_article_variation_image_id' => $articleImage->id,
                    'name' => 'fc_expiresAt'],
                    ['value' => $expires_at_text]
                );
            }

            //if(Storage::disk('public')->exists($filePath)){ continue; }
            $image_exists=false;
            if(Storage::disk('public')->exists($filePath))
            {
                $image_exists=true;
            }
            try {
                $res = $client->request('GET', env('FASHION_CLOUD_URL', 'https://api.fashion.cloud/v2').'/products/media/images/'.$id.'?token='.$this->apiKey.'&watermark=false'.$px);
                $status = $res->getStatusCode();
                if($status != 200 && $image_exists==false) {
                    if($Log){echo "\n[IMGfail:".json_encode($res)."]";}
					$articleImageAttrs = $articleImage->attributes()->get();
					foreach($articleImageAttrs as $articleImageAttr)
					{$articleImageAttr->delete();}
					$articleVarImageAttrs = $articleVarImage->attributes()->get();
					foreach($articleVarImageAttrs as $articleVarImageAttr)
					{$articleVarImageAttr->delete();}

                    $articleVarImage->delete();
					    $articleImage->delete();

                    Log::info(json_encode($res)."\nFashionCloud Image konnte nicht geladen werden(V): ".$filePath." für ".$article->id." ".'https://api.fashion.cloud/v2'.'/products/media/images/'.$id.'?token='.$this->apiKey.'&watermark=false'.$px);
                }
                elseif($status==200) {
                    Storage::disk('public')->put($filePath, $res->getBody());



                    if($Log){echo "[save]";}

                    if($imgType!='original'){
                        try{
                            $absolute_path=Storage::disk('public')->path($filePath);
                            self::checkFitImageSize($absolute_path,$imgType);
                            if($Log){echo "[Bild angepasst]";}
                        }
                        catch(\Exception $e){

                        }

                    }

                      //Nach dem Speichern quittieren wir das ganze mit einer positiven Rückgabe!
                      return true;
                }

            } //catch(Guzzle\Http\Exception\BadResponseException $e) {
            catch(GuzzleException $e) {
                // Bilddatei Download wurde von FashionCloud abgelehnt

                    if($Log){echo "\n[IMGfail2:".$e->getMessage()."]";}
                    $articleImageAttrs = $articleImage->attributes()->get();
                    foreach($articleImageAttrs as $articleImageAttr)
                    {$articleImageAttr->delete();}
                    $articleVarImageAttrs = $articleVarImage->attributes()->get();
                    foreach($articleVarImageAttrs as $articleVarImageAttr)
                    {$articleVarImageAttr->delete();}
                    $articleVarImage->delete();
                    $articleImage->delete();
            }

        }

        //erreichen wir diese Zeilen, so fand keine Aktualisierung statt!
        return false;

    }
}
