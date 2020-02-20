<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Helpers\OpenPayApi;
use App\Transaction;
use App\Card;
use App\Client;

class PayController extends Controller
{

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

    /** Create a new client */
    $createClient = $this->createClient($result['client']);

    /** Create a new transaction in the db */
    $create_transaction = $this->createTransaction($result['transaction'], $user_id, $createClient['client_id']);

    /** Initialize helper to access functions of the same */
    $OpenPayApi = new OpenPayApi();

    /** Use charge function to try to charge the payment */
    $charge = $OpenPayApi->charge($result['charge']);

    /** Validate the response */
    if ($charge['ok'] == false) {

      /** Get the error information and update that transaction */
      $dataToUpdate = [
        'status' => 'error',
        'description' => $charge['description']
      ];

      $update_transaction = $this->updateTransaction($dataToUpdate, $result['transaction']['order'], 'error');

      return response()->json([
        'ok' => false,
        'message' => $charge['description']
      ], 400);
      
    } else {
      /** Process the response provided by OpenPayApi */
      $processChargeData = $this->processChargeData($charge['result']);

      /** Get the transaction information and update that infomation in the db */
      $order = $charge['result']->serializableData['description'];
      $createCard = $this->createCard($processChargeData['card'], $create_transaction['transaction_id']);
      $update_transaction = $this->updateTransaction($processChargeData['transaction'], $order, 'completed');

      return response()->json([
        'ok' => true,
        'data' => $processChargeData
      ], 200);
    }
  }

  private function createClient($data)
  {
    /** Create a new client in the db */
    $client = new Client();
    $client->name = $data['name'];
    $client->last_name = $data['last_name'];
    $client->phone_number = $data['phone_number'];
    $client->email = $data['email'];
    $client->city = $data['city'];
    $client->state = $data['state'];
    $client->address = $data['address'];
    $client->postal_code = $data['postal_code'];
    $client->country_code = $data['country_code'];
    $client->save();

    return ['client_id' => $client->id];
  }

  private function createCard($data, $transaction_id)
  {
    /** Create a new card in the db */
    $card = new Card();
    $card->transaction_id = $transaction_id;
    $card->card_type = $data['card_type'];
    $card->card_brand = $data['card_brand'];
    $card->card_bank_name = $data['card_bank_name'];
    $card->card_number = $data['card_number'];
    $card->holder_name = $data['holder_name'];
    $card->card_expiration_year = $data['card_expiration_year'];
    $card->card_expiration_month = $data['card_expiration_month'];
    $card->save();
    return true;
  }

  private function createTransaction($data, $user_id, $client_id)
  {
    /** Create a new transaction in the db */
    $transaction = new Transaction();
    $transaction->user_id = $user_id;
    $transaction->client_id = $client_id;
    $transaction->order = $data['order'];
    $transaction->device_session_id = $data['device_session_id'];
    $transaction->token_id = $data['token_id'];
    $transaction->amount = $data['amount'];
    $transaction->currency = $data['currency'];
    $transaction->save();

    return ['transaction_id' => $transaction->id];
  }

  private function updateTransaction($data, $order, $status)
  {
    if ($status != 'completed') {
      $updateData = [
        'status' => $data['status'],
        'description' => $data['description']
      ];
    } else {
      $updateData = [
        'transaction_id' => $data['transaction_id'],
        'authorization' => $data['authorization'],
        'currency' => $data['currency'],
        'status' => $data['status'],
        'amount' => $data['amount'],
        'description' => 'Succesful transaction'
      ];
    }
    $update = Transaction::where('order', $order)->update($updateData);
    return true;
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
    $order = Carbon::now()->timestamp;
    /** Array to create one charge */

    $charge = [
      'method' => 'card',
      'source_id' => $token_id,
      'amount' => $amount,
      'currency' => $currency,
      'description' => $order,
      'device_session_id' => $device_session_id,
      'customer' => [
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
      ]
    ];

    /** Array to create one client in the database */
    $client = [
      'name' => $name,
      'last_name' => $last_name,
      'email' => $email,
      'phone_number' => $phone_number,
      'address' => $line1 . ' ' . $line2,
      'city' => $city,
      'state' => $state,
      'postal_code' => $postal_code,
      'country_code' => $country_code,
    ];

    /** Array to create one transaction in the database */
    $transaction = [
      'order' => $order,
      'device_session_id' => $device_session_id,
      'token_id' => $token_id,
      'amount' => $amount,
      'currency' => $currency,
    ];

    return ['ok' => true, 'charge' => $charge, 'client' => $client, 'transaction' => $transaction];
  }

  private function processChargeData($data)
  {
    $card = [
      'card_type' => $data->card->type,
      'card_brand' => $data->card->brand,
      'card_bank_name' => $data->card->bank_name,

      'card_number' => $data->card->serializableData['card_number'],
      'holder_name' => $data->card->serializableData['holder_name'],
      'card_expiration_year' => $data->card->serializableData['expiration_year'],
      'card_expiration_month' => $data->card->serializableData['expiration_month'],
    ];

    $transaction = [
      'transaction_id' => $data->id,
      'authorization' => $data->authorization,
      'currency' => $data->currency,
      'status' => $data->status,
      'amount' => $data->serializableData['amount'],
      'description' => 'Succesful transaction'
    ];

    return [ 'card' => $card, 'transaction' => $transaction ];
  }
}
