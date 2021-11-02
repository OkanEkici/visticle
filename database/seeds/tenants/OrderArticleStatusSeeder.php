<?php

use Illuminate\Database\Seeder;
use App\Tenant\OrderArticle_Status;

class OrderArticleStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        OrderArticle_Status::create([
            'description' => 'In Vorbereitung'
        ]);

        OrderArticle_Status::create([
            'description' => 'Versendet'
        ]);

        OrderArticle_Status::create([
            'description' => 'Angekommen'
        ]);

        OrderArticle_Status::create([
            'description' => 'Kein Bestand'
        ]);

        OrderArticle_Status::create([
            'description' => 'Storniert'
        ]);
    }
}
