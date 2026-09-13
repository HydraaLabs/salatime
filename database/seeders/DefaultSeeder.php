<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Backward-compatible entry point; never installs demonstration accounts. */
class DefaultSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
    }
}
