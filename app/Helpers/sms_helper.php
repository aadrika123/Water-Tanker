<?php

use Illuminate\Support\Facades\Config;

if (!function_exists('SMSJHGOVT')) {
    function SMSJHGOVT($mobileno, $message, $templateid = null)
    {
        if (strlen($mobileno) == 10 && is_numeric($mobileno) && $templateid != NULL) {
            $username      = Config::get("sms-constants.SMS_USER_NAME");               #_username of the department
            $password      = Config::get("sms-constants.SMS_PASSWORD");                #_password of the department
            $senderid      = Config::get("sms-constants.SMS_SENDER_ID");               #_senderid of the deparment
            $deptSecureKey = Config::get("sms-constants.SMS_SECURE_KEY");              #_departmentsecure key for encryption of message...
            $url           = Config::get("sms-constants.SMS_URL");
            $message       = $message;                                                #_message content
            $encryp_password = sha1(trim($password));

            $key = hash('sha512', trim($username) . trim($senderid) . trim($message) . trim($deptSecureKey));
            $data = array(
                "username"       => trim($username),
                "password"       => trim($encryp_password),
                "senderid"       => trim($senderid),
                "content"        => trim($message),
                "mobileno"       => trim($mobileno),
                "key"            => trim($key),
                "templateid"     => $templateid,
                "smsservicetype" => "singlemsg",
            );

            $fields = '';
            foreach ($data as $key => $value) {
                $fields .= $key . '=' . urlencode($value) . '&';
            }
            rtrim($fields, '&');
            $post = curl_init();
            //curl_setopt($post, CURLOPT_SSLVERSION, 5); // uncomment for systems supporting TLSv1.1 only
            curl_setopt($post, CURLOPT_SSLVERSION, 6); // use for systems supporting TLSv1.2 or comment the line
            curl_setopt($post, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($post, CURLOPT_URL, $url);
            curl_setopt($post, CURLOPT_POST, count($data));
            curl_setopt($post, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($post); //result from mobile seva server
            curl_close($post);
            $response = ['response' => true, 'status' => 'success', 'msg' => 1];
            if (strpos($result, '402,MsgID') !== false) {
                $response = ['response' => true, 'status' => 'success', 'msg' => $result];
            } else {
                $response = ['response' => false, 'status' => 'failure', 'msg' => $result];
            }

            //print_r($response);
            return $response;
        } else {
            if ($templateid == NULL)
                $response = ['response' => false, 'status' => 'failure', 'msg' => 'Template Id is required'];
            else
                $response = ['response' => false, 'status' => 'failure', 'msg' => 'Invalid Mobile No.'];
            return $response;
        }
    }
}
if (!function_exists('send_sms')) {
    function send_sms($mobile, $message, $templateid)
    {
        $test = Config::get("sms-constants.SMS_TEST");
        // $res=SMSJHGOVT($test?"9153975142":$mobile, $message, $templateid);
        return []; //$res;
    }
}

if (!function_exists("OTP")) {
    function OTP($data = array(), $sms_for = null)
    {
        if (strtoupper($sms_for) == strtoupper('Application OTP')) {
            try {
                #OTP Code. {#var#} for Your Application No {#var#} {#var#}              
                $sms = "OTP Code." . $data["otp"] . " for Your Application No " . $data["application_no"] . " " . $data["ulb_name"] . "";
                $temp_id = "1307162359726658524";
                return array("sms" => $sms, "temp_id" => $temp_id, 'status' => true);
            } catch (Exception $e) {
                return array(
                    "sms_formate" => "OTP Code. {#var#} for Your Application No {#var#} {#var#}",
                    "discriuption" => "1. 2 para required 
                      2. 1st para array('otp'=>'','application_no'=>'','ulb_name'=>'') sizeof 3  
                      3. 2nd para sms for ",
                    "error" => $e->getMessage(),
                    'status' => false
                );
            }
        } elseif (strtoupper($sms_for) == strtoupper('Holding Online Payment OTP')) {
            try {
                #Dear Citizen, your OTP for online payment of Holding Tax for {#var#} is INR {#var#}. This OPT is valid for {#var#} minutes only.              
                $sms = "Dear Citizen, your OTP for online payment of Holding Tax for " . $data["holding_no"] . " is INR " . $data["amount"] . ". This OPT is valid for " . $data["validity"] . " minutes only.";
                $temp_id = "1307161908198113240";
                return array("sms" => $sms, "temp_id" => $temp_id, 'status' => true);
            } catch (Exception $e) {
                return array(
                    "sms_formate" => "Dear Citizen, your OTP for online payment of Holding Tax for {#var#} is INR {#var#}. This OPT is valid for {#var#} minutes only.",
                    "discriuption" => "1. 2 para required 
                      2. 1st para array('holding_no'=>'','amount'=>'','validity'=>'') sizeof 3  
                      3. 2nd para sms for ",
                    "error" => $e->getMessage(),
                    'status' => false
                );
            }
        } else {
            return array(
                'sms' => 'pleas supply two para',
                '1' => 'array()',
                '2' => "sms for 
                          1. Application OTP
                          2. Holding Online Payment OTP",
                'status' => false
            );
        }
    }
}

if(!function_exists("ApplySms")){
    function ApplySms($data){
        try {
            #Dear citizen, your {var1} {var2} {var3} has been generated successfully. For more info, please call {var4}. -UD&HD.GOJ.              
            $sms = "Dear citizen, your ".$data["for"]." booking No.".$data["booking_no"]." has been generated successfully. For more info, please call ".$data["toll_free"].". -UD&HD.GOJ.";
            $temp_id = "1307171162952198045";
            return array("sms" => $sms, "temp_id" => $temp_id, 'status' => true);
        } catch (Exception $e) {
            return array(
                "sms_formate" => "Dear citizen, your {var1} {var2} has been generated successfully. For more info, please call {var3}. -UD&HD.GOJ.",
                "discriuption" => "1. 2 para required 
                  2. 1st para array('for'=>'','booking_no'=>'','tool_free'=>'') sizeof 3 ",
                "error" => $e->getMessage(),
                'status' => false
            );
        }
    }
}

if(!function_exists("paymentSms")){
    function paymentSms($data){
        try {
            #Dear citizen, your {var1} payment of INR {var2} for {var3}-{var4} has been successfully processed. For more info call us {var5}. -UD&HD.GOJ.              
            $sms = "Dear citizen, your ".$data["for"]." payment of INR ".$data["tran_amount"]." for ".$data["for"]."-".$data["booking_no"]." has been successfully processed. For more info call us ".$data["tool_fee"].". -UD&HD.GOJ.";
            $temp_id = "1307171162964390436";
            return array("sms" => $sms, "temp_id" => $temp_id, 'status' => true);
        } catch (Exception $e) {
            return array(
                "sms_formate" => "Dear citizen, your {var1} payment of INR {var2} for {var3}-{var4} has been successfully processed. For more info call us {var5}. -UD&HD.GOJ.
                ",
                "discriuption" => "1. 2 para required 
                  2. 1st para array('for'=>'','booking_no'=>'','tool_free'=>'') sizeof 3 ",
                "error" => $e->getMessage(),
                'status' => false
            );
        }
    }
}
