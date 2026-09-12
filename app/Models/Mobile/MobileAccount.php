<?php

namespace App\Models\Mobile;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MobileAccount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'email_verified_at'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed', 'email_verified_at' => 'datetime'];

    public function identities()
    {
        return $this->hasMany(MobileIdentity::class);
    }

    public function publicData(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'has_password' => $this->password !== null,
            'providers' => $this->identities()->pluck('provider')->all()];
    }
}
