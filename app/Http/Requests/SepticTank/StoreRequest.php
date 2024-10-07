<?php

namespace App\Http\Requests\SepticTank;

use App\Models\ForeignModels\PropProperty;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $user = Auth()->user();
        $this->merge(["userType"=>$user->user_type,"citizenId"=>$user->id]);
        if($user->gettable()=="users")
        {
            $this->merge(["ulbId"=>$user->ulb_id,"userId"=>$user->id,"citizenId"=>null]);
        }
        
        $rules= [
            'ulbId' => 'required|integer',
            'locationId' => 'required|integer',
            'ulbArea' => 'required|boolean',  
            'applicantName' => 'required|string|max:255',
            'cleaningDate' => 'required|date_format:Y-m-d|after:'. date('Y-m-d'),
            'mobile' => 'required|digits:10',
            'email' => 'required|email',
            'wardId' => $this->ulbArea == 1 ? "required|integer":'nullable',
            'holdingNo' => $this->ulbArea == 1 ? (
                                                [
                                                   "nullable" ,
                                                   "string",
                                                   "max:20",
                                                   function ($attribute, $value, $fail) {
                                                        // Custom validation logic to check if the value exists
                                                        // You can use the OR condition here
                                                        $existsInTable1 = PropProperty::where('status', 1)
                                                            ->where("ulb_id",$this->ulbId)
                                                            ->where(function($where) use($value){
                                                                $where->orWhere("new_holding_no",$value)
                                                                ->orWhere("holding_no",$value);
                                                            })
                                                            ->exists();                                        
                                                        if (!$existsInTable1) {
                                                            $fail('The '.$attribute.' is invalid.');
                                                        }
                                                    },
                                                ]
                                                // "required|string|max:20"
                                                )
                                                    
                                                :'nullable',
            'roadWidth' => 'required|numeric',
            'distance' => 'required|numeric',
            'capacityId' => 'nullable|integer',
            'address' => 'required|string|max:255',
            'buildingType' => 'required|integer',                                              // 1 - Within ULB, 0 - Outside ULB
        ];
        return $rules;
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   => 'Validation errors',
            'data'      => $validator->errors()
        ], 200),);
    }
}
