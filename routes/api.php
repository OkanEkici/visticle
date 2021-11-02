<?php

use App\Http\Controllers\Tenant\Providers\Wix\WixInstallController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Console\Commands\Test\WixShopTest;
use App\Http\Controllers\Tenant\Providers\Wix\WixController;
use Illuminate\Http\JsonResponse;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::prefix('/v1')->middleware(['APIkey'])->group(function() {

    //Articles
    Route::get('articles', 'Tenant\ArticleController@getArticles');
    Route::get('articles/{id}', 'Tenant\ArticleController@getArticle');

    //Stock
    Route::patch('stock/{ean}', 'Tenant\ArticleController@updateStock');
    Route::get('stock/{ean}', 'Tenant\ArticleController@getStock');

    //Orders
    Route::get('orders/customer/{customerId}', 'Tenant\OrderController@getCustomerOrders');
    Route::get('orders/{id}/customer/{customerId}', 'Tenant\OrderController@getCustomerOrder');
    Route::post('order','Tenant\OrderController@postOrder');
    Route::patch('order/{id}','Tenant\OrderController@updateOrder');

    Route::get('testmail', 'Tenant\OrderController@testMail');

    //Providerconfig
    Route::get('config', 'Tenant\ProviderController@getConfig');

    Route::post('updatePayPalPayment', 'Tenant\OrderController@updatePayPalPayment');

    Route::post('verified_user', 'Tenant\CustomerController@verified_user');
    Route::post('unverified_user', 'Tenant\CustomerController@unverified_user');

});

Route::prefix('/zalando')->middleware(['APIkey'])->group(function() {
    Route::post('order-state', 'Tenant\Providers\Zalando\ZalandoController@orderState');
});

/**
 * @author Tanju Özsoy
 * Hier legen wir von Wix geforderte Redirect URLs an. Wir brauchen einmal die sogenannte
 * App-URL. Darauf werden die Wix-Kunden umgeleitet, wenn sie die Installation unserer Wix App anstossen.
 * Dann brauchen wir eine weitere RedirectURL, mit der wir den Authorisierungsvorgang zwischen uns und dem Kunden abschliessen können.
 * Desweiteren legen wir noch eine Webhook an, die die Webhooks, für die wir unsere Wix-App eingetragen haben,
 * als sogenannte Wix-Events entgegennimmt.
 */
Route::prefix('/wix')->group(function(){

    //Applikations URL
    Route::match(['get', 'post'], 'install-start',[WixInstallController::class,'startInstallation']);

    //Redirekt URL für den Abschluss der Installation
    Route::match(['get', 'post'], 'install-complete', [WixInstallController::class,'completeInstallation'])
    ->middleware('auth.basic:wix,user_id');


    // Webhook für Wix-Events, für die wir uns eingetragen haben.
    // Die Verarbeitung muss dann umgeleitet werden auf einen Controller
    Route::match(['post','put','get'],'event/order/new',function(Request $request) {

        $content=$request->getContent();
        $parts=explode('.',$content);
        $jwt_encoded=$parts[1];
        $jwt_decoded=base64_decode($jwt_encoded);
        $data=json_decode($jwt_decoded);
        $data=json_decode($data->data);

        $pController = new WixShopTest();
        $pController->wixToVisticleOrder($data->orderId);
        return new JsonResponse(null,200);

    });

});
