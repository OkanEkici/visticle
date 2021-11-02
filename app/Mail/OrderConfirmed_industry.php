<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Tenant\Order;
use App\Tenant\Setting;

class OrderConfirmed_industry extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $PDF;
    public $AB_Nummer;
    public $bestellData;

    public function __construct(Order $order,$PDF,String $AB_Nummer, $bestellData) 
    {   $this->order = $order;
        $this->PDF = $PDF;
        $this->AB_Nummer = $AB_Nummer;
        $this->bestellData = $bestellData;
    }

    public function build()
    {   $senderMail = Setting::getSenderEmailAddress();
        return $this->from($senderMail)
        ->view('tenant.mail.order-confirmed-industry')
        ->subject('Ihre Bestellbestätigung')
        ->with(
          [
                'contactMail' => $senderMail,
                'contactTel' => Setting::getSenderTel(),
                'tenant' => config('tenant.identifier')
          ])
          ->attach($this->PDF, [ 'as' => $this->AB_Nummer, 'mime' => 'application/pdf' ]);
          
    }
}
