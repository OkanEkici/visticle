<?php

use App\Tenant\Synchro_Status;
use Illuminate\Database\Seeder;

class SynchroStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Synchro_Status::create([
            'description' => 'Erfolgreich'
        ]);
        Synchro_Status::create([
            'description' => 'Fehlgeschlagen'
        ]);
        Synchro_Status::create([
            'description' => 'In Bearbeitung'
        ]);
    }
}
