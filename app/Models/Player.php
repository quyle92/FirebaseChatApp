<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Player extends Authenticatable
{
    protected $primaryKey  = 'player_sn';
    public $incrementing = false;
    protected $keyType = 'string';

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_sn', 'team_sn');
    }
}
