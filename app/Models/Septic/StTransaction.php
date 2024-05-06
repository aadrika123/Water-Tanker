<?php

namespace App\Models\Septic;

use App\Models\WtChequeDtl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StTransaction extends Model
{
    use HasFactory;

    public function getChequeDtls()
    {
        return $this->hasOne(StChequeDtl::class,"tran_id","id")->orderBy("id","DESC")->first();
    }
}
