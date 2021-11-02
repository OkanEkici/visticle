<?php

namespace App\Http\Middleware;
use App\Tenant;
use App\Services\TenantManager;
use App\Tenant_Keys;
use App\Tenant\Provider;
use Illuminate\Contracts\Encryption\DecryptException;
use Closure;
use Config;


class APIkey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $api_key = $request->header('x-api-key');
        if($api_key == '' || !$api_key) {
            $api_key = $request->apikey;
        }

        /**
         * @author Tanju Özsoy
         * 15.01.2021
         * Vielleicht braucht man, warum auch immer bei einigen Kunden den Authorization-Header
         */
        if($api_key == '' || !$api_key) {
            $api_key = $request->header('Authorization');
        }



        if ($api_key == '' || !$api_key) {

            return response('No access key', 401);


        } else {

            $tenant_key = Tenant_Keys::where('access_key', $api_key)->first();

            if (!$tenant_key) {

                return response('Invalid access key', 401);


            } else {
                $tenant = Tenant::find($tenant_key->fk_tenant_id);
                if(!$tenant) {
                    return response("Invalid team account");
                }
                $tenantManager = new TenantManager;
                $tenantManager->setTenant($tenant);
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');
                view()->share('identifier', $tenant->getSubdomain());
                config()->set('tenant', ['identifier' => $tenant->getSubdomain()]);
                $provider = Provider::where('id' ,'=', $tenant_key->provider_id)->first();

                if(!$provider) {
                    return response()->json([
                        'message' => 'Provider not found'
                    ], 404);

                }

                $request->attributes->add([
                    'provider' => $provider
                ]);

              return $next($request);

            }

        }

    }
}
