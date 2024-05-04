<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempTransaction extends ParamModel
{
    use HasFactory;

    public function tempTransaction($req)
    {
        $mTempTransaction = new TempTransaction();
        return $mTempTransaction->create($req)->id;
    }


    
}
