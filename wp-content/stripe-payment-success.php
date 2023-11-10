<?php
// webhook.php
//
// Use this sample code to handle webhook events in your integration.
//
// 1) Paste this code into a new file (webhook.php)
//
// 2) Install dependencies
//   composer require stripe/stripe-php
//
// 3) Run the server on http://localhost:4242
//   php -S localhost:4242

require 'vendor/autoload.php';

// The library needs to be configured with your account's secret key.
// Ensure the key is kept out of any version control system you might be using. 
$stripe = new \Stripe\StripeClient('sk_test_...');

// This is your Stripe CLI webhook secret for testing your endpoint locally.
$endpoint_secret = 'whsec_a075d47173b9d193e4785ffa2cf466e7371b2488e81e6cf68373899ecd1c6950';

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

            // Assuming the PaymentIntent has a customer email set
            if ($paymentIntent->receipt_email) {
                $charge = $paymentIntent->charges->data[0];
                $receipt_url = $charge->receipt_url;

                // Use your preferred method to send an email to the customer.
                // For example, using PHP's mail function:
                $to = $paymentIntent->receipt_email;
                $subject = "Your receipt from [Your Company Name]";
                $message = "Thank you for your purchase. Here is your receipt: " . $receipt_url;
                $headers = "From: no-reply@yourcompany.com\r\n";
                if (mail($to, $subject, $message, $headers)) {
                    error_log("Receipt email sent.");
                } else {
                    echo "Failed to send receipt email.";
                }
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