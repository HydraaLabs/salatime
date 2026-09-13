<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpenSourceInstallationTest extends TestCase
{
    public function test_empty_installation_and_repeat_seed_never_create_personal_data(): void
    {
        if (getenv('SALATIME_INSTALL_TESTS') !== 'true') {
            $this->markTestSkipped('Opt in with SALATIME_INSTALL_TESTS=true and an empty MySQL/MariaDB test database.');
        }
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        $this->assertSame([], DB::select('SHOW TABLES'), 'Use a new empty test database. This test never drops existing tables.');
        config(['theme29.installed' => true, 'theme29.purchase_code' => null]);
        $this->assertSame(0, Artisan::call('migrate', ['--seed' => true, '--force' => true]));

        $privateTables = ['users', 'profiles', 'role_user', 'sessions', 'password_reset_tokens',
            'personal_access_tokens', 'reset_passwords', 'device_infos', 'mobile_accounts',
            'mobile_identities', 'mobile_account_actions', 'mobile_preferences',
            'mobile_reading_entries', 'mobile_reading_operations', 'jobs', 'job_batches',
            'failed_jobs', 'donations', 'transactions', 'prayer_times'];
        foreach ($privateTables as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(114, DB::table('chapters')->count());
        $this->assertSame(6236, DB::table('chapter_details')->count());
        $this->assertSame(4, DB::table('translators')->count());
        $this->assertGreaterThan(20000, DB::table('verse_translations')->count());
        $tables = array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES'));
        $counts = array_combine($tables, array_map(fn ($table) => DB::table($table)->count(), $tables));
        DB::table('settings')->where('name', 'company_name')->update(['value' => 'My own instance']);
        $this->assertSame(0, Artisan::call('db:seed', ['--force' => true]));
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' must not duplicate rows');
        }
        $this->assertSame('My own instance', DB::table('settings')->where('name', 'company_name')->value('value'));
        $this->get('/install-demo-data')->assertNotFound();
        $this->get('/symlink')->assertNotFound();
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/install')->assertRedirect(route('landing'));

        $password = 'LocalTestPassword123';
        $this->artisan('salatime:admin')
            ->expectsQuestion('Name', 'Local administrator')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters, letters and numbers)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();
        $user = User::query()->sole();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame('administrator', $user->roles()->sole()->alias);
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
        $this->artisan('salatime:admin')
            ->expectsQuestion('Name', 'Duplicate')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters, letters and numbers)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertFailed();
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, DB::table('mobile_accounts')->count());
    }
}
