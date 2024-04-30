<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
if(!function_exists('WHATSAPPJHGOVT'))
{
    function WHATSAPPJHGOVT($mobileno,$templateid,array $message=[])
    {
        $bearerToken = Config::get("whatsapp-constants.WHATSAPP_TOKEN");
        $numberId = Config::get("whatsapp-constants.WHATSAPP_NUMBER_ID");
        $url = Config::get("whatsapp-constants.WHATSAPP_URL");  
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => "+91$mobileno", //<--------------------- here
            "type" => "template",
            "template" => [
                "name" => "$templateid",
                "language" => [
                    "code" => "en_US"
                ],
                "components" => [
                    ($message
                    ?
                    (
                        ($message['content_type']??"")=="pdf"?
                        (
                            [
                                "type" => "header",
                                "parameters" => [
                                    [
                                        "type" => "document",
                                        "document" => $message[0]
                                        // [
                                        //     "link"=> "http://www.xmlpdf.com/manualfiles/hello-world.pdf",
                                        //     "filename"=> "Payment Receipt.pdf"
                                        // //     // $message[0]
                                        //     ]
                                    ]
                                ]
                            ]
                        )
                        :
                        (
                            ($message['content_type']??"")=="text"?
                            (
                                [
                                    "type" => "body",
                                    "parameters" => array_map(function($val){
                                        return ["type"=>"text","text"=>$val];
                                    },$message[0]??[])
                                ]
                            )
                            :
                            ""
                        )

                    )
                    :""),
                ]
            ]
        ];       
        $result = Http::withHeaders([
    
            "Authorization" => "Bearer $bearerToken",
            "contentType" => "application/json"

        ])->post($url.$numberId."/messages", $data);
        $responseBody = json_decode($result->getBody(),true);
        if (isset($responseBody["error"]))
        {
            $response = ['response'=>false, 'status'=> 'failure', 'msg'=>$responseBody];
        }
        else
        {
            $response = ['response'=>true, 'status'=> 'success', 'msg'=>$responseBody];                
        }
        
        return $response;
    }
}

if(!function_exists('Whatsapp_Send'))
{
    function Whatsapp_Send($mobileno,$templateid,array $message=[])
    {
        $test = Config::get("whatsapp-constants.WHATSAPP_TEST");
        $res=WHATSAPPJHGOVT($test?"9153975142":$mobileno, $templateid,$message);
        return $res;
    }
}