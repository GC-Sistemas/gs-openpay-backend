<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Helpers\OpenPayApi;
use App\Transaction;

class PayController extends Controller
{
  /** Variables to use anywhere in the class */
  public $transactionData;

  public function pay(Request $request)
  {
    /** Get user id provided by 'check user middleware' */
    $user_id = $request->user_id;

    /** Validate data provided  */
    $result = $this->validateData($request);

    if ($result['ok'] == false) {
      return response()->json([
        'ok' => false,
        'message' => $result['message']
      ], 400);
    }
    /** Get charge array to provided to OpenPay Api */
    $chargeData = $result['charge'];

    /** Create a new transaction in the db */
    $create_transaction = $this->createTransaction($chargeData, $user_id);
    /** Initialize helper to access functions of the same */
    $OpenPayApi = new OpenPayApi();

    /** Use charge function to try to charge the payment */
    $charge = $OpenPayApi->charge($chargeData);
    
    /** Validate the response */
    if ($charge['ok'] == false) {
      /** Get the error information and update that transaction */
      $dataToUpdate = [
        'status' => 'error',
        'description' => 'Error: ' . $charge['type'] . ', ' . $charge['message']
      ];

      $status = 'error';
      $order = $chargeData['description'];
      $update_transaction = $this->updateTransaction($dataToUpdate, $order, $status);

      return response()->json([
        'ok' => false,
        'message' => $charge['message']
      ], 400);

    } else {
      /** Get the transaction information and update that infomation in the db */
      $status = $charge['result']->status;
      $order = $charge['result']->serializableData['description'];
      $update_transaction = $this->updateTransaction($charge['result'], $order, $status);

      return response()->json([
        'ok' => true,
        'data' => $this->transactionData
      ], 200);
    }
  }

  private function validateData($data)
  {
    /** Get values provided by client */
    $device_session_id = $data->input('device_session_id', null);
    $token_id = $data->input('token_id', null);
    $name = $data->input('name', null);
    $last_name = $data->input('last_name', null);
    $phone_number = $data->input('phone_number', null);
    $email = $data->input('email', null);
    $city = $data->input('city', null);
    $state = $data->input('state', null);
    $line1 = $data->input('line1', null);
    $line2 = $data->input('line2', null);
    $postal_code = $data->input('postal_code', null);
    $country_code = $data->input('country_code', null);
    $amount = $data->input('amount', null);
    $currency = $data->input('currency', null);

    if ($device_session_id == null) {
      return [
        'ok' => false,
        'message' => 'Its mandatory attach the device_session_id field'
      ];
    }

    if ($token_id == null) {
      return [
        'ok' => false,
        'message' => 'Its mandatory attach the token_id field'
      ];
    }

    if ($currency == null) {
      return [
        'ok' => false,
        'message' => 'Its mandatory attach the currency field'
      ];
    } else if ($currency != 'MXN' && $currency != 'USD') {
      return [
        'ok' => false,
        'message' => 'This system just accept: USD & MXN currencies'
      ];
    }

    if ($amount == null) {
      return [
        'ok' => false,
        'message' => 'Its mandatory attach the amount to charge'
      ];
    } else if (is_numeric($amount) == false) {
      return [
        'ok' => false,
        'message' => 'The amount field, should be numeric'
      ];
    }
    /** We create the necessary array to provide the open pay collection system */
    $customer = [
      'name' => $name,
      'last_name' => $last_name,
      'email' => $email,
      'phone_number' => $phone_number,
      'address' => [
        'city' => $city,
        'state' => $state,
        'line1' => $line1,
        'line2' => $line2,
        'postal_code' => $postal_code,
        'country_code' => $country_code,
      ]
    ];

    $charge = [
      'method' => 'card',
      'source_id' => $token_id,
      'amount' => $amount,
      'currency' => $currency,
      'description' => Carbon::now()->timestamp,
      'device_session_id' => $device_session_id,
      'customer' => $customer
    ];

    return ['ok' => true, 'charge' => $charge];
  }

  private function createTransaction($data, $user_id)
  {
    /** Create a new transaction in the db */
    $transaction = new Transaction();
    $transaction->user_id = $user_id;
    $transaction->order = $data['description'];
    $transaction->device_session_id = $data['device_session_id'];
    $transaction->token_id = $data['source_id'];
    $transaction->name = $data['customer']['name'];
    $transaction->last_name = $data['customer']['last_name'];
    $transaction->phone_number = $data['customer']['phone_number'];
    $transaction->email = $data['customer']['email'];
    $transaction->city = $data['customer']['address']['city'];
    $transaction->state = $data['customer']['address']['state'];
    $transaction->address = $data['customer']['address']['line1'] . ' ' . $data['customer']['address']['line2'];
    $transaction->postal_code = $data['customer']['address']['postal_code'];
    $transaction->country_code = $data['customer']['address']['country_code'];
    $transaction->save();
    return true;
  }

  private function updateTransaction($data, $order, $status)
  {
    if ($status != 'completed') {
      $this->transactionData = [
        'status' => $data['status'],
        'description' => $data['description']
      ];
    } else {
      $this->transactionData = [
        'transaction_id' => $data->id,
        'authorization' => $data->authorization,
        'currency' => $data->currency,
        'status' => $data->status,
        'amount' => $data->serializableData['amount'],

        'card_type' => $data->card->type,
        'card_brand' => $data->card->brand,
        'card_bank_name' => $data->card->bank_name,

        'card_number' => $data->card->serializableData['card_number'],
        'holder_name' => $data->card->serializableData['holder_name'],
        'card_expiration_year' => $data->card->serializableData['expiration_year'],
        'card_expiration_month' => $data->card->serializableData['expiration_month'],
        'description' => 'Succesful transaction'
      ];
    }
    
    $update = Transaction::where('order', $order)->update($this->transactionData);

    return true;
  }
}
