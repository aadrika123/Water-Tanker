<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WtTransaction extends Model
{
    use HasFactory;

    public function getChequeDtls()
    {
        return $this->hasOne(WtChequeDtl::class,"tran_id","id")->orderBy("id","DESC")->first();
    }

    public function getBooking()
    {
        return $this->belongsTo(WtBooking::class,"booking_id","id")->first();
    }
}
