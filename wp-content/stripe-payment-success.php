<?php

require 'vendor/autoload.php';

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


$MODE = getenv('MODE');
$STRIPE_LIVE_PUBLISHABLE_KEY = getenv('STRIPE_LIVE_PUBLISHABLE_KEY');
$STRIPE_LIVE_SECRET_KEY = getenv('STRIPE_LIVE_SECRET_KEY');
$STRIPE_TEST_PUBLISHABLE_KEY = getenv('STRIPE_TEST_PUBLISHABLE_KEY');
$STRIPE_TEST_SECRET_KEY = getenv('STRIPE_TEST_SECRET_KEY');
$STRIPE_CLI_WEBHOOK_SECRET = getenv('STRIPE_CLI_WEBHOOK_SECRET');

if ($MODE == '' or $MODE == 'test') {
    $STRIPE_SECRET_KEY = $STRIPE_TEST_SECRET_KEY;
} else if ($MODE == 'live') {
    $STRIPE_SECRET_KEY = $STRIPE_LIVE_SECRET_KEY;
}
// The library needs to be configured with your account's secret key.
// Ensure the key is kept out of any version control system you might be using. 
$stripe = new \Stripe\StripeClient($STRIPE_SECRET_KEY);

// This is your Stripe CLI webhook secret for testing your endpoint locally.
$endpoint_secret = $STRIPE_CLI_WEBHOOK_SECRET;

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );
} catch (\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    http_response_code(400);
    exit();
}

// Handle the event
switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object; // The PaymentIntent object.

        // Here you can perform a check or add your business logic

        // Trigger an email receipt
        try {
            // Retrieve the PaymentIntent to ensure it has the `charges` data
            $paymentIntent = $stripe->paymentIntents->retrieve(
                $paymentIntent->id,
                ['expand' => ['charges.data.balance_transaction']]
            );

            // write serialized object to error log
            error_log('paymentIntent: ' . print_r($paymentIntent, true));

            // Assuming the PaymentIntent has a customer email set
            if ($paymentIntent->receipt_email) {
                $charge = $paymentIntent->charges->data[0];
                $receipt_url = $charge->receipt_url;

                // Use your preferred method to send an email to the customer.
                // For example, using PHP's mail function:
                $to = $paymentIntent->receipt_email;
                $subject = "Your receipt from Jerry Weston Mize Music";
                $message = "Thank you for your purchase. Here is your receipt: " . $receipt_url;
                $headers = "From: no-reply@yourcompany.com\r\n";
                if (mail($to, $subject, $message, $headers)) {
                    error_log("Receipt email sent.");
                } else {
                    echo "Failed to send receipt email.";
                }
            } else {
                error_log("PaymentIntent without customer email.");
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Handle error
            error_log('Error sending receipt: ', $e->getMessage());
            http_response_code(400);
            exit();
        }

        break;
    // ... handle other event types
    default:
        echo 'Received unknown event type ' . $event->type;
}

http_response_code(200);