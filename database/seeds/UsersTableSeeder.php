<?php

use App\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'VISC DEMO',
            'email' => 'demo@visc-media.de',
            'email_verified_at' => null,
            'password' => bcrypt('visc123'),
        ]);
    }
}
