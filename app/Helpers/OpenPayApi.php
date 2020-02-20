<?php

namespace App\Helpers;

use Openpay;

class OpenPayApi
{
    /** Variables to use anywhere in the helper */
    public $openpay;

    public function __construct()
    {
        /** Initialize OpenPay library  */
        Openpay::getProductionMode(env('OPENPAY_PRODUCTION_MODE'));
        /** Save the OpenPay instance */
        $this->openpay = Openpay::getInstance(env('OPEN_PAY_SET_ID'), env('OPEN_PAY_SET_API_KEY'));
    }
    /** Function to try to charge a payment */
    public function charge($data)
    {
        $result = $this->send($data);
        return $result;
    }

    /** Function to send the data to OpenPay system */
    private function send($data)
    {
        try {
            /** Try to perform the transaction proccess */
            $result = $this->openpay->charges->create($data);
            return ['ok' => true, 'result' => $result];

        } catch (\OpenpayApiTransactionError $e) {
            /** Get the possible errors when try to perfom the transaction */
            $description = 'Error: type transaction, message: '.$e->getMessage().', code:'.$e->getErrorCode().', category: '.$e->getCategory().', http_code: '.$e->getHttpCode();
            return [
                'ok' => false,
                'description' => $description
            ];
        } catch (\OpenpayApiRequestError $e) {
            $description = 'Error: type transaction, message: '.$e->getMessage().', code:'.$e->getErrorCode().', category: '.$e->getCategory().', http_code: '.$e->getHttpCode();
            return [
                'ok' => false,
                'description' => $description
            ];
        } catch (\OpenpayApiConnectionError $e) {
            $description = 'Error: type transaction, message: '.$e->getMessage().', code:'.$e->getErrorCode().', category: '.$e->getCategory().', http_code: '.$e->getHttpCode();
            return [
                'ok' => false,
                'description' => $description
            ];
        } catch (\OpenpayApiAuthError $e) {
            $description = 'Error: type transaction, message: '.$e->getMessage().', code:'.$e->getErrorCode().', category: '.$e->getCategory().', http_code: '.$e->getHttpCode();
            return [
                'ok' => false,
                'description' => $description
            ];
        } catch (\OpenpayApiError $e) {
            $description = 'Error: type transaction, message: '.$e->getMessage().', code:'.$e->getErrorCode().', category: '.$e->getCategory().', http_code: '.$e->getHttpCode();
            return [
                'ok' => false,
                'description' => $description
            ];
        } catch (\Exception $e) {
            $description = 'Error: type transaction, message: '.$e->getMessage().', code:'.$e->getErrorCode().', category: '.$e->getCategory().', http_code: '.$e->getHttpCode();
            return [
                'ok' => false,
                'description' => $description
            ];
        }
    }
}
