<?php

namespace App\Http\Middleware;


use Closure;
use App\Services\TenantManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IdentifyTenant
{
    /**
    * @var App\Services\TenantManager
    */
    protected $tenantManager;
    
    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $host = $request->route('identifier');
        $request->route()->forgetParameter('identifier');

        if ($this->tenantManager->loadTenant(substr($host, 0))) {
            if($request->route()->getName() == 'tenant.auth') {
                $request->attributes->add(['tenant' => $this->tenantManager->getTenant()]);
            }
            $request->attributes->add(['identifier' => $host]);
            define('TENANT_IDENT', $host);
            return $next($request);
        }
        
        throw new NotFoundHttpException;
    }
}
