<?php

namespace App\Models\Mobile;

use Illuminate\Database\Eloquent\Model;

class MobileIdentity extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['refresh_token', 'subject'];

    protected $casts = ['refresh_token' => 'encrypted'];

    public function account()
    {
        return $this->belongsTo(MobileAccount::class, 'mobile_account_id');
    }
}
