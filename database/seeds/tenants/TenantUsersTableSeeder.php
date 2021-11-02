<?php

use App\Tenant\TenantUser;
use Illuminate\Database\Seeder;

class TenantUsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TenantUser::create([
            'name' => 'Lukas Möller',
            'email' => 'moeller@visc-media.de',
            'email_verified_at' => null,
            'password' => bcrypt('test'),
        ]);

        TenantUser::create([
            'name' => 'Sebastian Schulz',
            'email' => 'schulz@visc-media.de',
            'email_verified_at' => null,
            'password' => bcrypt('test'),
        ]);

        TenantUser::create([
            'name' => 'Demo User',
            'email' => 'demo@visticle.de',
            'email_verified_at' => null,
            'password' => bcrypt('demo123'),
        ]);
    }
}
