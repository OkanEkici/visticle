<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

use App\Tenant\TenantUser_Config;
use App\Tenant\TenantUser;
use Illuminate\Http\Request;
use Response;
use Auth;

class TenantUserConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\TenantUser_Config  $tenantUser_Config
     * @return \Illuminate\Http\Response
     */
    public function show(TenantUser_Config $tenantUser_Config)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\TenantUser_Config  $tenantUser_Config
     * @return \Illuminate\Http\Response
     */
    public function edit(TenantUser_Config $tenantUser_Config)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\TenantUser_Config  $tenantUser_Config
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TenantUser_Config $tenantUser_Config)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\TenantUser_Config  $tenantUser_Config
     * @return \Illuminate\Http\Response
     */
    public function destroy(TenantUser_Config $tenantUser_Config)
    {
        //
    }

    public function updateDatatablesColumnConfig(Request $request, string $table) {
        $configurable = TenantUser::getTableConfigurations();
        if(!isset($configurable[$table])) {
            return;
        }
        $config = Auth::user()->config()->where('name', '=', 'columnconfig_'.$table)->first();
        if($config) {
            $columnconfig = unserialize($config->value);
        }
        else {
            $columnconfig = $configurable[$table];
        }

        $columnId = $request->columnId;
        $active = ($request->active == '1') ? '1' : '0';
        $columnconfig[$columnId] = $active;
        TenantUser_Config::updateOrCreate(
            ['fk_tenant_user_id' => Auth::user()->id, 'name' => 'columnconfig_'.$table],
            ['value' => serialize($columnconfig)]
        );

        return Response::json($columnconfig);
    }
}
