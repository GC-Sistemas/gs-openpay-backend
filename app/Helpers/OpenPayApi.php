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
            return [
                'ok' => false,
                'type' => 'transaction',
                'message' => $e->getMessage(),
                'code' => $e->getErrorCode(),
                'category' => $e->getCategory(),
                'http_code' => $e->getHttpCode()
            ];
        } catch (\OpenpayApiRequestError $e) {
            return ['ok' => false, 'type' => 'api_request', 'message' => $e->getMessage()];
        } catch (\OpenpayApiConnectionError $e) {
            return ['ok' => false, 'type' => 'connection', 'message' => $e->getMessage()];
        } catch (\OpenpayApiAuthError $e) {
            return ['ok' => false, 'type' => 'api_auth', 'message' => $e->getMessage()];
        } catch (\OpenpayApiError $e) {
            return ['ok' => false, 'type' => 'api_error', 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['ok' => false, 'type' => 'other', 'message' => $e->getMessage()];
        }
    }
}
