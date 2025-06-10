<?php
require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/api/app_keys.php';

function helpme_submit_paynow_donation()
{

    // Verify nonce
    // check_ajax_referer('helpme_donation_nonce', 'security');
    try {
        global $wpdb;

        // Sanitize input
        $amount             = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
        $currency           = sanitize_text_field($_POST['currency'] ?? 'USD');
        $name               = sanitize_text_field($_POST['donor_name'] ?? '');
        $email              = isset($_POST['donor_email']) ? $_POST['donor_email'] : 'pikigene01@gmail.com';
        $phone              = sanitize_text_field($_POST['phone'] ?? '');
        $method              = sanitize_text_field($_POST['gateway'] ?? '');
        $message            = sanitize_textarea_field($_POST['donor_message'] ?? '');
        $campaign_id        = intval($_POST['campaign_id'] ?? 0);
        $form_id            = intval($_POST['form_id'] ?? 0);
        $is_recurring       = isset($_POST['is_recurring']) && $_POST['is_recurring'] === 'true' ? 1 : 0;
        $recurring_interval = sanitize_text_field($_POST['recurring_interval'] ?? null);
        $anonymous          = isset($_POST['anonymous']) ? intval($_POST['anonymous']) : 0;
        $donation_id        = sanitize_text_field($_POST['donation_id'] ?? uniqid('don_'));

        $donors_table    = $wpdb->prefix . 'helpme_donors';
        $donations_table = $wpdb->prefix . 'helpme_donations';


        // Find donor
        $donor_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $donors_table WHERE email = %s", $email));

        if ($donor_id) {
            // Update donor
            $wpdb->update($donors_table, [
                'name'   => $name,
                'phone'  => $phone,
                'status' => 'active',
                'updated_at' => current_time('mysql')
            ], ['id' => $donor_id]);
        } else {
            // Insert new donor
            $wpdb->insert($donors_table, [
                'email'      => $email,
                'name'       => $name,
                'phone'      => $phone,
                'status'     => 'active',
                'created_at' => current_time('mysql')
            ]);
            $donor_id = $wpdb->insert_id;
        }

        // Insert donation
        $donations =   $wpdb->insert($donations_table, [
            'donation_id'        => $donation_id,
            'campaign_id'        => $campaign_id,
            'form_id'            => $form_id,
            'donor_id'           => $donor_id,
            'amount'             => $amount,
            'currency'           => $currency,
            'gateway'            => 'paynow',
            'status'             => 'pending',
            'is_recurring'       => $is_recurring,
            'recurring_interval' => $recurring_interval,
            'anonymous'          => $anonymous,
            'donor_name'         => $name,
            'donor_email'        => $email,
            'donor_phone'        => $phone,
            'donor_message'      => $message,
            'created_at'         => current_time('mysql')
        ]);

        $transaction_id = $wpdb->insert_id;

        if ($method === 'paynow' || $method === 'ecocash') {
            $return_url = add_query_arg('donation', $donation_id, get_permalink(get_option('helpme_donations_success_page')));
            $result_url = $return_url;

            $paynow_url = 'https://www.paynow.co.zw/Interface/InitiateTransaction';

            $paynow = initiatingPayment($donation_id);

            $paynow->setResultUrl($result_url);

            $amount = $amount;

            $today = date("Ymd");
            $ref1 = uniqid();
            $ref2 = "$today-$ref1";

            $payment = $paynow->createPayment($ref2, 'pikigene01@gmail.com');
            $payment->add('Test', $amount);

            $response = $paynow->sendMobile($payment, "0$phone", 'ecocash');


            if ($response->success()) {
                $pollUrl = $response->pollUrl();
                $wpdb->update(
                    $donations_table,
                    ['poll_url' =>  $pollUrl],
                    ['id' => $transaction_id]
                );
                $status = $paynow->pollTransaction($pollUrl);

                if ($status->paid()) {

                    // Now update poll_url for that donation
                    $wpdb->update(
                        $donations_table,
                        ['status' =>  'paid'],
                        ['id' => $transaction_id]
                    );
                    wp_send_json_success(['message' => "Payment successful", 'poll_url' => $pollUrl, 'transaction_id' => $transaction_id]);
                } else {

                    // Now update poll_url for that donation
                    $wpdb->update(
                        $donations_table,
                        ['status' =>  'cancelled'],
                        ['id' => $transaction_id]
                    );
                    wp_send_json_error(['message' => "Payment was not successful", 'poll_url' => $pollUrl, 'transaction_id' => $transaction_id]);
                }
            } else {
                wp_send_json_error([
                    'message' => "Couldn't initiate payment",
                    'debug'   => ""
                ]);
            }
        } elseif ($method === "stripe") {
            $helpMeApiKeys = new HelpMeAppKeys();

            \Stripe\Stripe::setApiKey($helpMeApiKeys->get_stripe_secret_key());

            // header('Content-Type: application/json');

            try {
                $amount = $amount * 100; // Amount in cents (e.g. $50.00)
                $currency = 'usd';

                $payment_intent = \Stripe\PaymentIntent::create([
                    'amount' => $amount,
                    'currency' => $currency,
                ]);

                // wp_send_json_success(['message' => "Payment successful", 'transaction_id' => $checkout_session->id]);
                wp_send_json_success(['message' => "Payment successful", 'client_secret' => $payment_intent->client_secret, 'transaction_id' => $transaction_id]);
            } catch (Exception $e) {
                wp_send_json_error(['message' => $e->getMessage() ?? 'Payment initiation failed.']);
            }
            wp_send_json_error(['message' => 'Another payment method coming soon...']);
        }




        return;
    } catch (Exception $error) {
        wp_send_json_error(['message' => $error->getMessage() ?? 'Payment initiation failed.']);
    }
}

function get_donation_success_url()
{
    $success_page_id = get_option('helpme_donations_success_page');
    if ($success_page_id && get_post_status($success_page_id) === 'publish') {
        return get_permalink($success_page_id);
    }
    return home_url(); // fallback
}
function get_donation_error_url()
{
    $error_page_id = get_option('helpme_donations_cancelled_page');
    if ($error_page_id && get_post_status($error_page_id) === 'publish') {
        return get_permalink($error_page_id);
    }
    return home_url(); // fallback
}

function check_paynow_payment_status()
{
    global $wpdb;

    $poll_url = sanitize_text_field($_POST['poll_url'] ?? '');

    if (empty($poll_url)) {
        wp_send_json_error(['message' => 'Missing poll_url.']);
        return;
    }

    // Step 1: Find the donation by poll_url
    $donations_table = $wpdb->prefix . 'helpme_donations';
    $donation_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $donations_table WHERE poll_url = %s",
        $poll_url
    ));

    if (!$donation_id) {
        wp_send_json_error(['message' => 'Donation not found for this poll_url.']);
        return;
    }

    try {
        // Step 2: Recreate the Paynow instance
        $paynow = initiatingPayment($donation_id);

        // Step 3: Poll status from Paynow
        $status = $paynow->pollTransaction($poll_url);

        if ($status->paid()) {
            // Step 4: Update donation status in DB
            $wpdb->update($donations_table, [
                'status'     => 'completed',
                'updated_at' => current_time('mysql')
            ], [
                'id' => $donation_id
            ]);

            wp_send_json_success(['message' => "Paid", 'donation_id' => $donation_id]);
        } else {
            $wpdb->update($donations_table, [
                'status'     => 'failed',
                'updated_at' => current_time('mysql')
            ], [
                'id' => $donation_id
            ]);

            wp_send_json_error(['message' => "Payment was cancelled", 'donation_id' => $donation_id]);
        }
    } catch (Exception $error) {
        wp_send_json_error(['message' => $error->getMessage() ?? 'Payment polling failed.']);
    }
}
function update_donation_success_payment_status()
{
    global $wpdb;

    $transaction_id = sanitize_text_field($_POST['transaction_id'] ?? '');

    if (empty($transaction_id)) {
        wp_send_json_error(['message' => 'Missing transaction_id.']);
        return;
    }

    // Step 1: Find the donation by transaction_id
    $donations_table = $wpdb->prefix . 'helpme_donations';
    $donation_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $donations_table WHERE id = %s",
        $transaction_id
    ));

    if (!$donation_id) {
        wp_send_json_error(['message' => 'Donation not found for this poll_url.']);
        return;
    }

    try {

        // Step 4: Update donation status in DB
        $wpdb->update($donations_table, [
            'status'     => 'Paid',
            'updated_at' => current_time('mysql')
        ], [
            'id' => $donation_id
        ]);

        wp_send_json_success(['message' => "Paid", 'donation_id' => $donation_id]);
    } catch (Exception $error) {
        wp_send_json_error(['message' => $error->getMessage() ?? 'Payment polling failed.']);
    }
}


function initiatingPayment($id)
{
    // Paynow Setup
    $integration_id  = get_option('helpme_donations_paynow_integration_id');
    $integration_key = get_option('helpme_donations_paynow_integration_key');
    $result_url = get_donation_success_url();
    $return_url = get_donation_error_url();
    $paynow = new Paynow\Payments\Paynow(
        "$integration_id",
        "$integration_key",
        "$result_url",

        // The return url can be set at later stages. You might want to do this if you want to pass data to the return url (like the reference of the transaction)
        "$return_url"
    );
    return $paynow;
}

function createHash($values, $IntegrationKey)
{
    $string = "";

    foreach ($values as $key => $value) {
        if (strtoupper($key) != "HASH") {
            $string .= $value;
        }
    }

    $string .= $IntegrationKey;
    $hash = hash("sha512", $string);

    return strtoupper($hash);
}
