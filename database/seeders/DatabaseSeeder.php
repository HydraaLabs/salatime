<?php

namespace Database\Seeders;

use Database\Seeders\Quran\Settings\LandingSettingSeeder;
use Database\Seeders\Quran\Settings\SettingsSeeder;
use Database\Seeders\User\PermissionSeeder;
use Database\Seeders\User\RoleSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data only. Accounts and activity are created by their owners.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            LandingSettingSeeder::class,
            ReferenceContentSeeder::class,
        ]);
    }
}
