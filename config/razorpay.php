<?php


/**
 * | Created On-21-08-2022 
 * | Created By-Sam kerketta
 * | For razorpay credentials
 */


return [

    'RAZORPAY_KEY'  => env("RAZORPAY_KEY"),
    'RAZORPAY_ID'   => env("RAZORPAY_ID"),

    'PAYMENT_GATEWAY_URL'       => '192.168.0.240:86',
    'PAYMENT_GATEWAY_END_POINT' => '/api/payment/generate-orderid',
];
