<?php

require_once( 'vendor/autoload.php' );
require_once( 'config.php' );
header( 'Content-Type: application/json' );

$gateway = new Braintree_Gateway( [
  'environment' => BT_ENVIRONMENT,
  'merchantId' => BT_MERCHANTID,
  'publicKey' => BT_PUBLICKEY,
  'privateKey' => BT_PRIVATEKEY
] );

if( !array_key_exists( 'action', $_POST ) ) {
  fail( 'No action specified' );
}

switch( trim( $_POST[ 'action' ] ) ) {
  case 'vault': vaultPayment(); break;
  case 'processTransaction': processTransaction(); break;
  case 'processTransactionFromVault': processTransactionFromVault(); break;
  default: fail( 'Unsupported action' ); break;
}

function fail( $msg ) {
  die( json_encode( [ 'ok' => false, 'error' => $msg ] ) );
}

function vaultPayment() {
  global $gateway;
  
  if( !array_key_exists( 'nonce', $_POST ) || !strlen( trim( $_POST[ 'nonce' ] ) ) ) {
    fail( 'Nonce missing from request' );
  }

  $result = $gateway->customer()->create( [
    'paymentMethodNonce' => trim( $_POST[ 'nonce' ] )
  ] );

  error_log( print_r( $result, true ) );

  if( $result->success ) {
    $custid = $result->customer->id;
    die( json_encode( [ 'ok' => true, 'custId' => $custid ] ) );
  } else {
    fail( 'Error while attempting to vault payment method: ' . $result->message );
  }
}

function processTransaction() {
  global $gateway;

  if( !array_key_exists( 'nonce', $_POST ) || !strlen( trim( $_POST[ 'nonce' ] ) ) ) {
    fail( 'Nonce missing from request' );
  }

  $result = $gateway->transaction()->sale( [
    'amount' => '200.00',
    'paymentMethodNonce' => trim( $_POST[ 'nonce' ] ),
    'options' => [
      'submitForSettlement' => true
    ]
  ] );

  if( $result->success ) {
    $txnid = $result->transaction->id;
    die( json_encode( [ 'ok' => true, 'txnId' => $txnid ] ) );
  } else {
    fail( 'Error while processing payment: ' . $result->message );
  }
}

function processTransactionFromVault() {
  global $gateway;

  if( !array_key_exists( 'custId', $_POST ) || !strlen( trim( $_POST[ 'custId' ] ) ) ) {
    fail( 'Customer ID missing from request' );
  }

  $result = $gateway->transaction()->sale( [
    'amount' => '200.00',
    'options' => [
      'submitForSettlement' => true,
      'paypal' => [
        'description' => 'Electric Utility'
      ]
    ],
    'customerId' => trim( $_POST[ 'custId' ] )
  ] );

  if( $result->success ) {
    $txnid = $result->transaction->id;
    die( json_encode( [ 'ok' => true, 'txnId' => $txnid ] ) );
  } else {
    fail( 'Error while processing payment: ' . $result->message );
  }
}

