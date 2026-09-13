<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Backward-compatible entry point; never installs demonstration accounts. */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
    }
}
