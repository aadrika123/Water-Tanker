<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WfWorkflow extends ParamModel
{
    use HasFactory;

    public function listbyId($req)
    {
        $data = WfWorkflow::where('id', $req->id)
            ->where('is_suspended', false)
            ->first();
        return $data;
    }
}
