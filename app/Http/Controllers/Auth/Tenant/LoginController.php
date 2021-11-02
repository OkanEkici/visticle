<?php

namespace App\Http\Controllers\Auth\Tenant;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Config;
use App\Tenant\TenantUser;

class LoginController extends Controller
{

    public function __construct()
    {
        $this->middleware('guest:tenant')->except('logout');
    }

    /**
     * Handle an authentication attempt.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return Response
     */
    public function authenticate(Request $request)
    {
        if($request->attributes->get('tenant')) {
            //Set DB Connection
            \DB::purge('tenant');
            $tenant = $request->attributes->get('tenant');

            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);

            \DB::connection('tenant');
        }
        $credentials = $request->only('email', 'password');

        if (Auth::guard('tenant')->attempt($credentials)) {
            // Authentication passed...
            return redirect()->intended();
        }
        else {
            return redirect('/login')->withErrors(['email' => 'Emailadresse oder Passwort falsch']);
        }
    }

    public function index() {
        return view('tenant.auth.login');
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        return redirect('/login');
    }
}