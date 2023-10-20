<?php

namespace App\Http\Requests\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UlbWaterBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'parsadName' => 'required|string|max:255',
            'capacity' => 'required|numeric',
            'vehicleNo' => 'required|string',
            'driverName' => 'required|string',
            'driverMobileNo' => 'required|string|max:10|min:10',
            'deliveryDate' => 'required|date_format:Y-m-d|after_or_equal:'. date('Y-m-d'),
            'deliveryTime' => 'required|date_format:H:i',
            'deliveryAddress' => 'required|string|max:255',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   =>$validator->errors()->first(),
            'data'      => $validator->errors()
        ], 422),);
    }
}
