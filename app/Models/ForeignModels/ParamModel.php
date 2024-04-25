<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParamModel extends Model
{
    use HasFactory;
    protected $connection='pgsql';
    protected $guarded = [];
}
