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
        return [
            'ulbId' => 'required|integer',
            'citizenId' => 'required|integer',
            'agencyId' => 'required|integer',
            'bookingDate' => 'required|date_format:Y-m-d',
            'deliveryDate' => 'required|date_format:Y-m-d',
            'deliveryTime' => 'required|date_format:H:i',
            'mobile' => 'required|digits:10',
            'email' => 'required|email',
            'wardId' => 'required|integer',
            'capacityId' => 'required|integer',
            'quantity' => 'required|integer',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   => 'Validation errors',
            'data'      => $validator->errors()
        ], 422),);
    }
}
