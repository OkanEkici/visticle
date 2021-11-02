<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * @author Tanju Özsoy <email@email.com>
 *
 * 04.01.2021
 * Diese Klasse liefert den korrekten Steuersatz aus, wie sie in der Konfigurationsdatei
 * "vat" hinterlegt ist.
 */
class VAT{
    public  const ORDINARY='ordinary';
    public  const REDUCED='reduced';

    public static function getVAT($tax_type=self::ORDINARY,$locale=null){
        if($locale==null){
            $locale=config('app.locale');
        }

        $current_path="vat.{$locale}.{$tax_type}.current";
        $favored_path="vat.{$locale}.{$tax_type}.favored";

        $current_tax=config($current_path);
        $favored_tax=config($favored_path,null);


        //Schauen wir mal, ob es ein favourisierung gibt!
        $tax=floatval($current_tax) ;
        if($favored_tax){
            $deadline=Carbon::createFromFormat('d.m.Y',$favored_tax['deadline']);
            $now=Carbon::now();

            if($now->greaterThanOrEqualTo($deadline)){
                $tax=floatval( $favored_tax['value']);
            }
        }

        return $tax;
    }
}
