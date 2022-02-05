<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Player extends Authenticatable
{
    protected $primaryKey  = 'player_sn';
    public $incrementing = false;
    protected $keyType = 'string';

    use HasFactory;

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_sn', 'team_sn');
    }
}
