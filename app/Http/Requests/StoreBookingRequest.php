<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules()
    {
        $user = Auth()->user();
        $this->merge(["userType"=>$user->user_type,"citizenId"=>$user->id]);
        if($user->gettable()=="users")
        {
            $this->merge(["ulbId"=>$user->ulb_id,"userId"=>$user->id,"citizenId"=>null]);
        }
        return [
            'ulbId' =>'required|integer',
            // 'citizenId' => 'required|integer',
            'applicantName' => 'required|string|max:255',
            // 'agencyId' => 'required|integer',
            // 'bookingDate' => 'required|date_format:Y-m-d',
            'deliveryDate' => 'required|date_format:Y-m-d|after:'. date('Y-m-d'),
            'deliveryTime' => 'required|date_format:H:i',
            'mobile' => 'required|digits:10',
            'email' => 'required|email',
            'locationId' => 'required|integer',
            'capacityId' => 'required|integer',
            // 'quantity' => 'required|integer',
            'address' => 'required|string|max:255',
            'wardId' => $this->ulbId == 2 ? "required|integer":'nullable'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   =>$validator->errors()->first(),
            'data'      => $validator->errors()
        ], 200),);
    }
}
