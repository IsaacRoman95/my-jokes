<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserCard extends Model
{

    protected $fillable = [
        'user_id',
        'stripe_card_id',
        'last4',
        'brand',
    ];
}
