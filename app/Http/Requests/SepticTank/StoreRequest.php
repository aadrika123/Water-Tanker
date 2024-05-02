<?php

namespace App\Http\Requests\SepticTank;

use App\Models\ForeignModels\PropProperty;
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
        $propProperty = new PropProperty();
        $rules= [
            'ulbId' => 'required|integer',
            'locationId' => 'required|integer',
            'ulbArea' => 'required|boolean',  
            'applicantName' => 'required|string|max:255',
            'cleaningDate' => 'required|date_format:Y-m-d|after_or_equal:'. date('Y-m-d'),
            'mobile' => 'required|digits:10',
            'email' => 'required|email',
            'wardId' => $this->ulbArea == 1 ? "required|integer":'nullable',
            'holdingNo' => $this->ulbArea == 1 ? ("required|string|max:20|"):'nullable',
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
        ], 422),);
    }
}
