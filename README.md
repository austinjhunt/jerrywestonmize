# jerrywestonmize.com

## Colibri Theme Update Documentation

When you update the theme, theme file edits are cleared away. These modifications need to be added back after updating the colibri theme.

### `header.php`

```

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
	<?php acf_form_head();?> // THIS IS WHAT YOU NEED TO RE-ADD
    <?php wp_head(); ?>
    <?php colibriwp_theme()->get( 'css' )->render(); ?>
</head>

<body id="colibri" <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
    wp_body_open();
} else {
    do_action( 'wp_body_open' );
}
?>


<div class="site" id="page-top">
    <?php colibriwp_theme()->get( 'header' )->render(); ?>

```

### `functions.php`

```

// begin mods
// change the logout url
add_filter('logout_url', 'jwm_logout_url');
function jwm_logout_url($default)
{
	// set your URL here
	// // Parse the URL into its components.
	$parsedUrl = parse_url($default);

	// Get the query string from the parsed URL.
	$queryString = $parsedUrl['query'];

	// Remove the leading question mark from the query string.
	return 'https://jerrywestonmize.com/jwmsecure.php?' . $queryString;
}

// change the forgot password URL on login page
function jwm_lostpassword_url()
{
	return 'https://jerrywestonmize.com/jwmsecure.php?action=lostpassword';
}
add_filter('lostpassword_url', 'jwm_lostpassword_url');

// change the reset password link in the email you get after clicking forgot password
function custom_reset_password_message($message, $key, $user_login, $user_data)
{
	// Check if the message contains the URL we want to replace
	if (strpos($message, 'wp-login.php?action=rp') !== false) {
		$user_locale = get_user_meta($user_data->ID, 'locale', true);
		$wp_lang = !empty($user_locale) ? $user_locale : 'en_US';
		// Construct the new URL
		$new_url = 'https://jerrywestonmize.com/jwmsecure.php?action=rp&key=' . $key . '&login=' . rawurlencode($user_login) . '&wp_lang=' . $wp_lang;

		$old_url = 'https://jerrywestonmize.com/wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode($user_login) . '&wp_lang=' . $wp_lang;
		// Replace the old URL with the new URL
		$message = str_replace($old_url, $new_url, $message);
	}

	return $message;
}
add_filter('retrieve_password_message', 'custom_reset_password_message', 10, 4);

// change the behavior after log out completion (default redirects to wp-login.php)
add_action('wp_logout', 'jwm_logout_complete');

function jwm_logout_complete()
{
	wp_redirect(site_url());
	exit();
}

add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar()
{
	if (!current_user_can('administrator') && !is_admin()) {
		show_admin_bar(false);
	}
}

// end of mods

add_action('after_switch_theme', 'colibriwp_check_php_version');
......

```


## Payment Receipts - Amelia Code Modification

If the Amelia plugin is updated, there is a chance this code will need to be re-added. 

Amelia doesn't properly trigger Stripe payment receipts because by default it does not tie in "receipt_email" key in the initial PaymentIntent creation API request to Stripe. 

To fix this, I customized the logic of `wp-content/plugins/ameliabooking/src/Infrastructure/Services/Payment/StripeService.php` to add that `receipt_email` key with the value of the `Customer Email` metadata that Weston had already added under the Amelia > Settings > Payments > Stripe > Customer Metadata menu. 
 
These are the specific changes to the [wp-content/plugins/ameliabooking/src/Infrastructure/Services/Payment/StripeService.php](wp-content/plugins/ameliabooking/src/Infrastructure/Services/Payment/StripeService.php) file:
```
...

if ($stripeSettings['manualCapture']) {
	$stripeData['capture_method'] = 'manual';
}

if ($data['metaData']) {
	$stripeData['metadata'] = $data['metaData'];
}

...

// BEGIN MODS

if ($data['metaData']['Customer Email']) {
	$stripeData['receipt_email'] = $data['metaData']['Customer Email'];
}
// also added a fallback description since that was appearing as null on the Stripe side
if ($data['description']) {
	$stripeData['description'] = $data['description'];
} else {
	$stripeData['description'] = 'Payment for ' . $data['metaData']['Customer Name'] . ' - ' . $data['metaData']['Customer Email'] . ' - ' . $data['metaData']['Service'] . '';
}
// END MODS

....

$stripeData = apply_filters(
	'amelia_before_stripe_payment',
	$stripeData
);

$intent = PaymentIntent::create($stripeData);

```

According to these docs on the PaymentIntent API reference: [https://stripe.com/docs/api/payment_intents](https://stripe.com/docs/api/payment_intents), including a `receipt_email` will automatically trigger a receipt to be sent to that email upon the success of the payment intent. [https://stripe.com/docs/api/payment_intents/object#payment_intent_object-receipt_email](https://stripe.com/docs/api/payment_intents/object#payment_intent_object-receipt_email)

`receipt_email`: *Email address that the receipt for the resulting payment will be sent to. If receipt_email is specified for a payment in live mode, a receipt will be sent regardless of your email settings.*

To test this, I deployed that update and did the following:

1. Ensured Amelia is using the live keys
2. Temporarily reduced the price of one of the paid services to $0.50 (minimum allowable charge with Stripe processing)
3. Booked one of those lessons on the front end with my own debit card
4. I got the lesson approved notification as normal, and within about 15 seconds I also got the receipt email from stripe.
