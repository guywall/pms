<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Default (production-safe): roles, pipeline stages, one admin account.
     *
     * Local/testing environments also get the demo data automatically.
     * To load demo data explicitly on any environment:
     *   php artisan db:seed --force --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoSeeder::class);
        }
    }
}
