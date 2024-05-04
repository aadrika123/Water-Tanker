<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;

class ParameReq extends FormRequest
{

    protected $_CURRENT_DATE;
    protected $_CURRENT_DATE_TIME;
    protected $_REX_DATE_YYYY_MM_DD;
    protected $_REX_DATE_YYYY_MM;
    protected $_REX_ALPHA;
    protected $_REX_ALPHA_NUM;
    protected $_REX_ALPHA_NUM_SPACE;
    protected $_REX_ALPHA_SPACE;
    protected $_REX_ALPHA_NUM_DOT_SPACE;
    protected $_REX_ALPHA_NUM_OPS_DOT_MIN_COM_AND_SPACE_SL;
    protected $_REX_NUM_DOT;
    protected $_REX_APPLICATION_TYPE;
    protected $_REX_OWNER_NAME;
    protected $_REX_MOBILE_NO;
    protected $_PAYMENT_MODES;

    public function __construct()
    {
        $paymentMode = Config::get("constants.OFFLINE_PAYMENT_MODS");
        $this->_CURRENT_DATE                                = Carbon::now()->format('Y-m-d');
        $this->_CURRENT_DATE_TIME                           = Carbon::now()->format('Y-m-d H:i:s');
        $this->_REX_DATE_YYYY_MM_DD                         = "/^([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]))+$/i";
        $this->_REX_DATE_YYYY_MM                            = "/^([12]\d{3}-(0[1-9]|1[0-2]))+$/i";
        $this->_REX_ALPHA                                   = "/^[a-zA-Z]+$/i";
        $this->_REX_ALPHA_SPACE                             = "/^[a-zA-Z\s]+$/i";
        $this->_REX_ALPHA_NUM                               = "/^[a-zA-Z0-9-]+$/i";
        $this->_REX_ALPHA_NUM_SPACE                         = "/^[a-zA-Z0-9- ]+$/i";
        $this->_REX_ALPHA_NUM_DOT_SPACE                     = "/^[a-zA-Z0-9][a-zA-Z0-9\. \s]+$/i";
        $this->_REX_ALPHA_NUM_OPS_DOT_MIN_COM_AND_SPACE_SL  = "/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i";
        $this->_REX_NUM_DOT                                 = "/^\d+(?:\.\d+)+$/i";
        $this->_REX_OWNER_NAME                              = "/^([a-zA-Z0-9]+)(\s[a-zA-Z0-9\.\,\']+)*$/i";
        $this->_REX_MOBILE_NO                               = "/[0-9]{10}/";
        $this->_PAYMENT_MODES   = implode(",",$paymentMode);
        
    }
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
            //
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
