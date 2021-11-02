<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenant\Article;
use App\Tenant\Article_Variation;
use App\Tenant\Order;
use App\Tenant\Provider;
use Response;
use Storage, Image;

class TenantUserController extends Controller
{

    public function dashboard() 
    {   $articleVariationsCount = Article_Variation::count();
        $articleCount = Article::count() + $articleVariationsCount;
        $orderCount = Order::count();        
        $providerCount = Provider::count();

        $openOrderCount = Order::where('fk_order_status_id' , '=', '1')->count();
        $awaiting_paymentOrderCount  = Order::where('fk_order_status_id' , '=', '2')->count();
        $awaiting_fulfillmentOrderCount = Order::where('fk_order_status_id' , '=', '3')->count();

        return view('tenant.modules.dashboard.index.dashboard', [
            'articleCount' => $articleCount ,
            'orderCount'=> $orderCount,
            'providerCount' => $providerCount,
            'openOrderCount' => $openOrderCount,
            'awaiting_paymentOrderCount' => $awaiting_paymentOrderCount,
            'awaiting_fulfillmentOrderCount' => $awaiting_fulfillmentOrderCount,
            ]);
    }

    public function displayImage($filename) {

        $img = Image::cache(function($image) use ($filename) {

            $file = Storage::disk('customers')->get(TENANT_IDENT.'/img/'.$filename);
            if (!$file) {
                abort(404);
            }

            return $image->make($file);
        }, 10, true);

        return $img->response();
    }
}
