<?php

function helpme_submit_paynow_donation()
{

    // Verify nonce
    // check_ajax_referer('helpme_donation_nonce', 'security');
    try {
        global $wpdb;

        $integration_id  = get_option('helpme_donations_paynow_integration_id');
        $integration_key = get_option('helpme_donations_paynow_integration_key');

        // Sanitize input
        $amount             = isset($_POST['amount']) ? floatval($_POST['amount']) : null;
        $currency           = sanitize_text_field($_POST['currency'] ?? 'USD');
        $name               = sanitize_text_field($_POST['donor_name'] ?? '');
        $email              = sanitize_email($_POST['donor_email'] ?? '');
        $phone              = sanitize_text_field($_POST['phone'] ?? '');
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


        $return_url = add_query_arg('donation', $donation_id, get_permalink(get_option('helpme_donations_success_page')));
        $result_url = $return_url;

        $paynow_url = 'https://www.paynow.co.zw/Interface/InitiateTransaction';
        // $paynow_url = "https://staysure.co.zw/tanaka_api/index.php?cellnumber=0$phone&amount=$amount";
        // $paynow_url = 'https://www.paynow.co.zw/interface/remotetransaction';

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
            if ($donations !== false) {
                // Now update poll_url for that donation
                $wpdb->update(
                    $donations_table,
                    ['poll_url' =>  $pollUrl],
                    ['donation_id' => $donation_id]
                );
            }
            $status = $paynow->pollTransaction($pollUrl);

            if ($status->paid()) {
                wp_send_json_success(['message' => "Payment successful", 'poll_url' => $pollUrl]);
            } else {
                wp_send_json_error(['message' => "Payment was successful", 'poll_url' => $pollUrl]);
            }
        } else {
            wp_send_json_error([
                'message' => "Couldn't initiate payment",
                'debug'   => ""
            ]);
        }

        // $reference       = $donation_id;
        // $additional_info = "Donation by $name";
        // $status          = 'Message';
        // $method          = 'ecocash';

        // $post_data = [
        //     'resulturl'      => $result_url,
        //     'returnurl'      => $return_url,
        //     'reference'      => $reference,
        //     'amount'         => number_format($amount, 2, '.', ''),
        //     'id'             => $integration_id,
        //     'additionalinfo' => $additional_info,
        //     'authemail'      => $email,
        //     'status'         => $status,
        //     'method'         => $method,
        //     'phone'          => '0' . $phone
        // ];

        // // $post_data['hash'] = createHash($post_data, $integration_key);
        // $fields_string = (new WC_Paynow_Helper())->CreateMsg($post_data, $integration_key);


        // $response = wp_remote_request($paynow_url, [
        //     'body'    => $fields_string,
        //     'method' => 'POST',
        //     'timeout' => 45
        // ]);

        // if (is_wp_error($response)) {
        //     wp_send_json_error(['message' => 'Could not connect to Paynow.']);
        // }

        // parse_str(wp_remote_retrieve_body($response), $response_data);

        // if (isset($response_data['status']) && $response_data['status'] === 'Ok') {
        //     wp_send_json_success(['redirect_url' => $response_data['browserurl']]);
        // } else {
        //     wp_send_json_error(['message' => $response_data['error'] . "Paynow error" ?? 'Payment initiation failed.']);
        // }

        return;
    } catch (Exception $error) {
        wp_send_json_error(['message' => $error->getMessage() ?? 'Payment initiation failed.']);
    }
}

function initiatingPayment($id)
{
    // Paynow Setup
    $integration_id  = get_option('helpme_donations_paynow_integration_id');
    $integration_key = get_option('helpme_donations_paynow_integration_key');
    $paynow = new Paynow\Payments\Paynow(
        "$integration_id",
        "$integration_key",
        "https://staysure.co.zw/test/test.php?transaction_id=$id",

        // The return url can be set at later stages. You might want to do this if you want to pass data to the return url (like the reference of the transaction)
        "https://staysure.co.zw/test/test.php?transaction_id=$id"
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
