<?php

namespace App\Models\ForeignModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfRoleusermap extends ParamModel
{
    /**
     * | Create Role Map
     */
    public function addRoleUser($req)
    {
        $data = new WfRoleusermap;
        $data->wf_role_id   = $req->wfRoleId;
        $data->user_id      = $req->userId;
        $data->is_suspended = $req->isSuspended ?? false;
        $data->created_by   = $req->createdBy;
        $data->save();
    }

    /**
     * | Update Role Map
     */
    public function updateRoleUser($req)
    {
        $data = WfRoleusermap::find($req->id);
        $data->wf_role_id   = $req->wfRoleId    ?? $data->wf_role_id;
        $data->user_id      = $req->userId      ?? $data->user_id;
        $data->is_suspended = $req->isSuspended ?? $data->is_suspended;
        $data->save();
    }
}
