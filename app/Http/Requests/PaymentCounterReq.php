<?php

namespace App\Http\Requests;

use App\Models\Septic\StBooking;
use App\Models\WtBooking;
use Carbon\Carbon;

class PaymentCounterReq extends ParameReq
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $model = preg_match("/water-tanker/i",$this->path()) ? new WtBooking() : new StBooking();
        $mNowDate       = Carbon::now()->format('Y-m-d'); 
        $mRegex         = '/^[a-zA-Z1-9][a-zA-Z1-9\.\s]+$/';
        $rules["paymentMode"]="required|alpha|in:".$this->_PAYMENT_MODES;
        $rules["applicationId"]="required|digits_between:1,9223372036854775807|exists:".$model->getConnectionName().".".$model->getTable().",id";
        if(isset($this->paymentMode) && $this->paymentMode!="CASH")
        {
            $rules["chequeNo"] ="required";
            $rules["chequeDate"] ="required|date|date_format:Y-m-d|after_or_equal:$mNowDate";
            $rules["bankName"] ="required|regex:$mRegex";
            $rules["branchName"] ="required|regex:$mRegex";
        } 
        return $rules;
    }
}
