<?php

namespace App\Console\Commands\Wix;

use App\Tenant;
use Illuminate\Console\Command;
use App\Http\Controllers\Tenant\Providers\Amazon\AmazonMWSController;

use App\Tenant\Provider;
use App\Tenant\Branch;
use App\Tenant\Synchro;
use Config;
use App\Helpers\Miscellaneous;
use App\WixUser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class CreateWixUser extends Command
{
    protected $signature = 'admin:create_wix_user {customer_domain}';

    protected $description = 'Dieser Befehl erstellt einen Benutzer für Wix mit allen umfangreichen
                              Sachen, die dazu gehören wie Plattformeinrichtung und Datensätze in der Main-DB.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $customer_domain=$this->argument('customer_domain');

        //Nun müssen wir schauen, ob wir einen Tenant haben für die genannte Domäne
        $customer=Tenant::query()->where('subdomain','=',$customer_domain)
                    ->first();

        if(!$customer){
            $this->info('Sie müssen die Domäne eines bestehnden Kunden eingeben.');
            $this->info("Für \"{$customer_domain}\" gibt es keinen Eintrag.");
            return;
        }

        //Jetzt erstellen wir einen Eintrag in der Tabelle "wix_users" in der Main-DB
        //Dafür erstellen wir eine Benutzer-ID und ein Passwort
        $user_id=Miscellaneous::getRandomString();
        $password=Miscellaneous::getRandomString();
        $password_hashed=bcrypt($password);
        $password_crypted=Crypt::encryptString($password);
        //Wir erstellen oder updaten wir einen wix_user
        $key=['fk_tenant_id'=>$customer->id];
        $data=[
            'name'=>$customer_domain,
            'user_id'=>$user_id,
            'password'=>$password_hashed ,
            'password_crypted'=>$password_crypted,
        ];
        $wix_user=WixUser::updateOrCreate($key,$data);

        //Wir müssen die ID mit dem Passwort zusammen ausgeben
        $this->info("Der Kunde {$customer_domain} hat folgende Daten erhalten:");
        $this->info("User_ID: {$user_id}");
        $this->info("Password: {$password}");
        //Jetzt erstellen wir noch die Plattform Wix für unseren Kunden

    }
}
?>
