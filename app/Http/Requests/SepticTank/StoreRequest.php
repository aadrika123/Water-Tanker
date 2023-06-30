<?php

namespace App\Http\Requests\SepticTank;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
        return [
            'ulbId' => 'required|integer',
            'locationId' => 'required|integer',
            'applicantName' => 'required|string|max:255',
            'cleaningDate' => 'required|date_format:Y-m-d|after_or_equal:'. date('Y-m-d'),
            'mobile' => 'required|digits:10',
            'email' => 'required|email',
            'wardId' => 'required|integer',
            'holdingNo' => 'required|string|max:20',
            'roadWidth' => 'required|numeric',
            'distance' => 'required|numeric',
            'capacity' => 'required|numeric',
            'address' => 'required|string|max:255',
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
