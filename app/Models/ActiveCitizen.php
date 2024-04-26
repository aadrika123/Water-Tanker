<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ActiveCitizen extends Authenticatable
{
    use HasFactory;
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection="pgsql_master";
    protected $guarded=[];
}
