<?php

require_once( 'vendor/autoload.php' );
require_once( 'config.php' );

$gateway = new Braintree_Gateway( [
  'environment' => BT_ENVIRONMENT,
  'merchantId' => BT_MERCHANTID,
  'publicKey' => BT_PUBLICKEY,
  'privateKey' => BT_PRIVATEKEY
] );

$clientToken = $gateway->clientToken()->generate();

?><!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Braintree PayPal Test</title>
        <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    </head>
    <body>
        <h1>Braintree/PayPal Test</h1>
        <!-- Load PayPal's checkout.js Library. -->
        <script src="https://www.paypalobjects.com/api/checkout.js" data-version-4 log-level="warn"></script>
        
        <!-- Load the client component. -->
        <script src="https://js.braintreegateway.com/web/3.20.0/js/client.min.js"></script>
        
        <!-- Load the PayPal Checkout component. -->
        <script src="https://js.braintreegateway.com/web/3.20.0/js/paypal-checkout.min.js"></script>

        <div style="border: 1px solid black; margin: 5px;">
            <div>PayPal button gets rendered here:</div>
            <div id="paypal-button"></div>
            <div><strong>PayPal button status:</strong> <span id="paypal-button-status">Waiting for Braintree initialization</span></div>
            <div><strong>Vaulting status:</strong> <span id="paypal-status">Not Started</span></div>
            <div><strong>Transaction status:</strong> <span id="paypal-txn-status">Not Started</span></div>
        </div>
        <div style="border: 1px solid black; margin: 5px;">
            <div>PayPal Credit button gets rendered here:</div>
            <div id="paypal-credit-button"></div>
            <div><strong>PayPal Credit button status:</strong> <span id="paypal-credit-button-status">Waiting for Braintree initialization</span></div>
            <div><strong>Transaction status:</strong> <span id="paypal-credit-status">Not Started</span></div>
        </div>
        
        <script>
         function updatePPButtonStatus(msg) {
           $('#paypal-button-status').html(msg);
         }

         function updatePPCButtonStatus(msg) {
           $('#paypal-credit-button-status').html(msg);
         }

         function updatePPStatus(msg) {
           $('#paypal-status').html(msg);
         }

         function updatePPCStatus(msg) {
           $('#paypal-credit-status').html(msg);
         }

         function updatePPTxnStatus(msg) {
           $('#paypal-txn-status').html(msg);
         }
         
         // Create a client.
         braintree.client.create({
           authorization: '<?= $clientToken ?>'
         }, function (clientErr, clientInstance) {
           
           // Stop if there was a problem creating the client.
           // This could happen if there is a network error or if the authorization
           // is invalid.
           if (clientErr) {
             console.error('Error creating client:', clientErr);
             updatePPButtonStatus('Error while creating Braintree client: ' + clientErr);
             updatePPCButtonStatus('Error while creating Braintree client: ' + clientErr);
             return;
           }

           updatePPButtonStatus('Waiting for Braintree/PayPal Checkout initialization');
           updatePPCButtonStatus('Waiting for Braintree/PayPal Checkout initialization');
           
           // Create a PayPal Checkout component.
           braintree.paypalCheckout.create({
             client: clientInstance
           }, function (paypalCheckoutErr, paypalCheckoutInstance) {
             
             // Stop if there was a problem creating PayPal Checkout.
             // This could happen if there was a network error or if it's incorrectly
             // configured.
             if (paypalCheckoutErr) {
               updatePPButtonStatus('Error creating PayPal Checkout: ' + paypalCheckoutErr);
               updatePPCButtonStatus('Error creating PayPal Checkout: ' + paypalCheckoutErr);
               return;
             }

             updatePPButtonStatus('Rendering PayPal Checkout button');
             updatePPCButtonStatus('Rendering PayPal Credit button');
             
             // Set up PayPal with the checkout.js library
             paypal.Button.render({
               env: 'sandbox', // or 'production'
               payment: function () {
                 updatePPStatus('Button clicked; waiting for buyer to complete checkout');
                 return paypalCheckoutInstance.createPayment({
                   // Your PayPal options here. For available options, see
                   // http://braintree.github.io/braintree-web/current/PayPalCheckout.html#createPayment
                   flow: 'vault'
                 });
               },
               
               onAuthorize: function (data, actions) {
                 updatePPStatus('Buyer completed checkout; tokenizing payment');
                 return paypalCheckoutInstance.tokenizePayment(data)
                                              .then(function (payload) {
                                                // Submit `payload.nonce` to your server.
                                                updatePPStatus('Payment tokenized, vaulting payment method');
                                                $.ajax( 'ajax.php', {
                                                  method: 'POST',
                                                  data: {
                                                    action: 'vault',
                                                    nonce: payload.nonce
                                                  }
                                                }).done(function(data) {
                                                  if(data.ok) {
                                                    updatePPStatus('Vaulting completed; customer ID = ' + data.custId );
                                                    updatePPTxnStatus('Processing transaction');
                                                    $.ajax( 'ajax.php', {
                                                      method: 'POST',
                                                      data: {
                                                        action: 'processTransactionFromVault',
                                                        custId: data.custId
                                                      }
                                                    }).done(function(data) {
                                                      if(data.ok) {
                                                        updatePPTxnStatus('Transaction completed; transaction ID = ' + data.txnId);
                                                      } else {
                                                        updatePPTxnStatus('Transaction failed: ' + data.error);
                                                      }
                                                    }).fail(function(jqXHR, textStatus, errorThrown) {
                                                      updatePPStatus('Failed to submit transaction for processing: ' + errorThrown.message);
                                                    });
                                                  } else {
                                                    updatePPStatus('Vaulting failed: ' + data.error);
                                                  }
                                                }).fail(function(jqXHR, textStatus, errorThrown) {
                                                  updatePPStatus('Failed to submit token for vaulting: ' + errorThrown.message);
                                                });
                                              });
               },
               
               onCancel: function (data) {
                 updatePPStatus('Buyer cancelled checkout');
                 console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));
               },
               
               onError: function (err) {
                 updatePPStatus('Error occurred: ' + err);
                 console.error('checkout.js error', err);
               }
             }, '#paypal-button').then(function () {
               // The PayPal button will be rendered in an html element with the id
               // `paypal-button`. This function will be called when the PayPal button
               // is set up and ready to be used.
               updatePPButtonStatus('Ready');
             });

             paypal.Button.render({
               env: 'sandbox', // or 'production'
               style: {
                 label: 'credit'
               },
               
               payment: function () {
                 updatePPCStatus('Button clicked; waiting for buyer to complete checkout');
                 return paypalCheckoutInstance.createPayment({
                   // Your PayPal options here. For available options, see
                   // http://braintree.github.io/braintree-web/current/PayPalCheckout.html#createPayment
                   flow: 'checkout',
                   amount: 200.00,
                   currency: 'USD',
                   offerCredit: true
                 });
               },
               
               onAuthorize: function (data, actions) {
                 updatePPCStatus('Buyer completed checkout; tokenizing payment');
                 return paypalCheckoutInstance.tokenizePayment(data)
                                              .then(function (payload) {
                                                // Submit `payload.nonce` to your server.
                                                updatePPCStatus('Payment tokenized; submitting transaction to server');
                                                $.ajax( 'ajax.php', {
                                                  method: 'POST',
                                                  data: {
                                                    action: 'processTransaction',
                                                    nonce: payload.nonce
                                                  }
                                                }).done(function(data) {
                                                  if(data.ok) {
                                                    updatePPCStatus('Transaction completed; transaction ID = ' + data.txnId);
                                                  } else {
                                                    updatePPCStatus('Transaction failed: ' + data.error);
                                                  }
                                                }).fail(function(jqXHR, textStatus, errorThrown) {
                                                  updatPPCStatus('Failed to submit transaction for processing: ' + errorThrown.message);
                                                });
                                              });
               },
               
               onCancel: function (data) {
                 updatePPCStatus('Buyer cancelled checkout');
                 console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));
               },
               
               onError: function (err) {
                 updatePPCStatus('Error occurred: ' + err);
                 console.error('checkout.js error', err);
               }
             }, '#paypal-credit-button').then(function () {
               // The PayPal button will be rendered in an html element with the id
               // `paypal-button`. This function will be called when the PayPal button
               // is set up and ready to be used.
               updatePPCButtonStatus('Ready');
             });
             
           });
           
         });
        </script>
    </body>
</html>
