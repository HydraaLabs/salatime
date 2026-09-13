<?php

namespace App\Console\Commands;

use App\Models\Quran\Role\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSalaTimeAdmin extends Command
{
    protected $signature = 'salatime:admin';
    protected $description = 'Create a SalaTime administrator interactively (no default credentials)';

    public function handle(): int
    {
        $role = Role::query()->where('alias', 'administrator')->first();
        if (! $role) {
            $this->error('Run php artisan migrate --seed first.');
            return self::FAILURE;
        }

        $input = [
            'first_name' => trim((string) $this->ask('Name')),
            'email' => strtolower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password (at least 12 characters, letters and numbers)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::FAILURE;
        }

        DB::transaction(function () use ($input, $role) {
            $user = User::query()->create([
                'first_name' => $input['first_name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'is_admin' => true,
            ]);
            $user->roles()->attach($role->id);
        });
        $this->info('Administrator created. Sign in at /login.');
        return self::SUCCESS;
    }
}
