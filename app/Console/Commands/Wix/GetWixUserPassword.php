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

class GetWixUserPassword extends Command
{
    protected $signature = 'admin:get_wix_user_password {customer_domain}';

    protected $description = 'Dieser Befehl entschlüsselt das Password vom Wix-User.';

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

        $wix_user=WixUser::query()->where('name','=',$customer_domain)->first();

        if(!$wix_user){
            $this->info("Für die Subdomain \"{$customer_domain}\" gibt es keinen eingetragenen Wix-User.");
        }

        $password_decrypted=Crypt::decryptString($wix_user->password_crypted);
        //Wir müssen die ID mit dem Passwort zusammen ausgeben
        $this->info("Der Kunde {$customer_domain} hat folgende Daten erhalten:");
        $this->info("User_ID: {$wix_user->user_id}");
        $this->info("Password: {$password_decrypted}");
    }
}
?>
