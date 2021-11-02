<?php

use Illuminate\Database\Seeder;
use App\Tenant\Synchro_Type;

class SynchroTypeSeederUpdate extends Seeder
{
    protected $synchro_key='kltrend_import_json';
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         //Wenn es den besagten Typ nicht gibt erstellen wir ihn.
         if(!Synchro_Type::query()->where('key','=',$this->synchro_key)->count())
         {
            $values=[
                'key'=>$this->synchro_key,
                'name'=>'KLTrend Import Json',
                'description'=>'Artikelimport von KLTrend über Json.',
            ];
            Synchro_Type::create($values);

            echo 'Der neue Synchrotyp \"' . $this->synchro_key . '\" wurde erstellt.';
        }
    }
}
