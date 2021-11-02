<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Tenant\Order;
use App\Tenant\Setting;

class NotificationTrackingID extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $senderMail = Setting::getSenderEmailAddress();

        return $this->from($senderMail)
        ->view('tenant.mail.platform.check24.notify-tracking-id')
        ->subject('Sendungsverfolgung für Ihre Bestellung bei check24')
        ->with(
          [
                'contactMail' => $senderMail,
                'contactTel' => Setting::getSenderTel(),
                'tenant' => config('tenant.identifier'),
                'order' => $this->order,
          ]);
    }
}
