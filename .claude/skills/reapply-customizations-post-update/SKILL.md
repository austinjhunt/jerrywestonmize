Re-apply all custom modifications to the Colibri theme and Amelia plugin after a WordPress update, then open a pull request with the changes.

Follow this workflow in order:

## Step 1: Create a Feature Branch

Run the following git commands in sequence:
1. `git checkout main` — ensure you are on main
2. `git pull origin main` — get the latest updates
3. `git checkout -b reapply-customizations` — create a new feature branch rebased on current main

If a branch named `reapply-customizations` already exists, append a timestamp suffix, e.g. `reapply-customizations-$(date +%Y%m%d%H%M%S)`.

## Step 2: Check and Apply Customizations

For each of the three files below, read the file first, check whether the customization is already present, and apply it only if missing or incomplete.

### File A: `wp-content/themes/colibri-wp/header.php`

Ensure `<?php acf_form_head();?>` is present on the line immediately before `<?php wp_head(); ?>`. The correct block:

```php
    <?php acf_form_head();?>
    <?php wp_head(); ?>
```

### File B: `wp-content/themes/colibri-wp/functions.php`

Ensure the `// begin mods` ... `// end of mods` block exists early in the file (after any `define(...)` lines but before other theme code). The full block to insert/verify:

```php
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

// change login url - added on 1/6/2024 becaus login form began redirecting
// to default wp-login.php?redirect_to ... after submission
add_filter('login_url', 'jwm_login_url');
function jwm_login_url($default) {
	$parsedUrl = parse_url($default);
	$queryString = $parsedUrl['query'];
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
```

### File C: `wp-content/plugins/ameliabooking/src/Infrastructure/Services/Payment/StripeService.php`

Find the section containing `if ($stripeSettings['manualCapture'])` and `if ($data['metaData'])`. Ensure the following `// begin mods with fallback values` ... `// end mods with fallback values` block exists after the `$stripeData['metadata']` assignment and before the `apply_filters('amelia_before_stripe_payment', ...)` call:

```php
            // begin mods with fallback values
            if ($data['description']) {
                $stripeData['description'] = $data['description'];
            } else if (!empty($data['metaData']['Customer Name'])) {
                $stripeData['description'] = 'Payment for ' . $data['metaData']['Customer Name'] . ' - ' . $data['metaData']['Customer Email'] . ' - ' . $data['metaData']['Service'] . '';
            }

            $customerId = $this->createCustomer($data, $additionalStripeData);

            if ($customerId) {
                $stripeData = array_merge($stripeData, ['customer' => $customerId]);
            }

            if (!empty($data['customerData']) && !empty($data['customerData']['email'])) {
                $stripeData['receipt_email'] = $data['customerData']['email'];
            } else if (!empty($data['metaData']['Customer Email'])) {
                $stripeData['receipt_email'] = $data['metaData']['Customer Email'];
            }
            // end mods with fallback values
```

If a vanilla update reset this file, it will have a bare `if ($data['description'])` block without the fallback `else if` and will be missing the `receipt_email` logic. Replace that bare block (and any adjacent `$customerId` / `$stripeData['customer']` lines if present) with the full custom block above.

## Step 3: Commit Changes

After applying any needed edits:

1. Run `git diff --name-only` to see which files changed.
2. If there are no changes (all customizations were already present), report that and stop — do not commit or push.
3. If there are changes, stage and commit only the three files above:
   ```
   git add wp-content/themes/colibri-wp/header.php
   git add wp-content/themes/colibri-wp/functions.php
   git add wp-content/plugins/ameliabooking/src/Infrastructure/Services/Payment/StripeService.php
   git commit -m "re-apply custom modifications after code updates"
   ```

## Step 4: Push and provide the PR URL

1. Push the branch: `git push -u origin <branch-name>`
2. Provide user with the URL to open the PR

## Step 5: Report Results

Summarize what was done:
- Which files already had correct customizations (no change needed)
- Which files were missing customizations and were updated
- The pull request URL
