<?php

namespace App\Http\Controllers;

use App\MpesaTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


class MpesaController extends Controller
{
    //Auth function
    public function generateAccessToken()
    {
        $consumer_key = "Bkg5u8Rtr69VYh8hIytfs1WpbyGIl3HU";
        $consumer_secret= "y9Y0mZ8qFuHdyoPH";
        $credentials = base64_encode($consumer_key.":".$consumer_secret);
        $url = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials));
        curl_setopt($curl, CURLOPT_HEADER,false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


        $curl_response = curl_exec($curl);
        $access_token=json_decode($curl_response);
        // echo $access_token->access_token;
        return $access_token->access_token;

    }

    /*
    *Lipa na mpesa password
    */
    public function lipaNaMpesaPassword(){
        $lipa_time = Carbon::rawParse('now')->format('YmdHms');
        $passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
        $BusinessShortCode = 174379;
        $timestamp = $lipa_time;

        $lipa_na_mpesa_password = base64_encode($BusinessShortCode.$passkey.$timestamp);
        return $lipa_na_mpesa_password;
    }

    /*
    *Lipa na Mpesa STK push
     */
    public function customerMpesaSTKPush()
    {
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_HTTPHEADER, array('Content-Type:application/json',
        'Authorization:Bearer '.$this->generateAccessToken()));

        $curl_post_data = [
            //Fill in the request parameters with valid values
            'BusinessShortCode' => 174379,
            'Password' => $this->lipaNaMpesaPassword(),
            'Timestamp' => Carbon::rawParse('now')->format('YmdHms'),
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => 5,
            'PartyA' => 254725239191,//phone number sending
            'PartyB' => 174379, //organization shortcode receiving the funds
            'PhoneNumber' => 254725239191,//phone number sending the funds
            'CallBackURL' => 'https://blog.hlab.tech/',//the url response where mpesa response will be sent
            'AccountReference' => "GrosBeaq Music",
            'TransactionDesc' => "Testing stk push on sandbox"
        ];

          $data_string = json_encode($curl_post_data);

          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_POST, true);
          curl_setopt($curl, CURLOPT_POSTFIELDS,$data_string);

          $curl_response = curl_exec($curl);

           return $curl_response;
    }

    /**
     * J-son Response to M-pesa API feedback -  success 0r failure
     */

    public function createValidationResponse($result_code, $result_description){
        $result = json_encode(["result_code"=>$result_code,"ResultDesc"=>$result_description]);
        $response = new Response();
        $response->header->set("Content-type","application/json: charaset=utf-8");
        $response->setContent($result);
        return $response;
    }

    /*
    *Mpesa Validation Method
    *Safaricom will only call your validation if you have requested by writting
    * an official letter to them
    */
    public function mpesaValidation(Request $request){
        $result_code = 0;
        $result_description = "Accepted validation request";
        return $this->createValidationResponse($result_code,$result_description);
    }
    /**
     * M-pesa Transaction confirmation method, we sAVE THE TRANSACTION TO THE db
     */
    public function mpesaConfirmation(Request $request){
        $content = json_decode($request->getContent());

        $mpesa_transaction = new MpesaTransaction();
        $mpesa_transaction->TransactionType = $content->TransactionType;
        $mpesa_transaction->TransID = $content->TransID;
        $mpesa_transaction->TransTime = $content->TransTime;
        $mpesa_transaction->TransAmount = $content->TransAmount;
        $mpesa_transaction->BusinessShortCode = $content->BusinessShortCode;
        $mpesa_transaction->BillRefNumber = $content->BillRefNumber;
        $mpesa_transaction->InvoiceNumber = $content->InvoiceNumber;
        $mpesa_transaction->OrgAccountBalance = $content->OrgAccountBalance;
        $mpesa_transaction->ThirdPartyTransID = $content->ThirdPartyTransID;
        $mpesa_transaction->MSISDN = $content->MSISDN;
        $mpesa_transaction->FirstName = $content->FirstName;
        $mpesa_transaction->MiddleName = $content->MiddleName;
        $mpesa_transaction->LastName = $content->LastName;
        $mpesa_transaction->save();

        //responding to the confirmation request
        $response = new Response();
        $response->header->set("content-type","text/xml: charset=utf-8");
        $response->setContent(json_encode(["C2BPaymentConfirmationResult"=>"Success"]));

        return $response;
    }
}
