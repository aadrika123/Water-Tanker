<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdGenerationParam extends ParamModel
{
    use HasFactory;

    public function getParams($id)
    {
        return IdGenerationParam::find($id);
    }
}
