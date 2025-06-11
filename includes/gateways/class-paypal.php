<?php

/**
 * PayPal Payment Gateway for Zimbabwe Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Gateway_PayPal
{

    /**
     * Gateway ID
     */
    public $id = 'paypal';

    /**
     * Gateway name
     */
    public $name = 'PayPal';

    /**
     * Gateway description
     */
    public $description = 'Pay securely using PayPal';

    /**
     * Supported currencies
     */
    public $supported_currencies = array('USD', 'EUR', 'GBP', 'ZAR');

    /**
     * API endpoints
     */
    private $api_endpoints = array(
        'sandbox' => 'https://api-m.sandbox.paypal.com',
        'live' => 'https://api-m.sandbox.paypal.com',
        // 'live' => 'https://api-m.paypal.com'
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // add_action('wp_ajax_nopriv_paypal_create_order', array($this, 'ajax_create_order'));
        // add_action('wp_ajax_paypal_capture_order', array($this, 'ajax_capture_order'));
        // add_action('wp_ajax_nopriv_paypal_capture_order', array($this, 'ajax_capture_order'));
    }

    /**
     * Check if gateway is available
     */
    public function is_available()
    {
        $enabled = get_option('helpme_donations_paypal_enabled', false);
        $client_id = $this->get_client_id();
        $client_secret = $this->get_client_secret();

        return $enabled && !empty($client_id) && !empty($client_secret);
    }

    /**
     * Process payment
     */
    public function process_payment($donation_data)
    {
        try {
            // Create PayPal order
            $order_data = array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(
                    array(
                        'amount' => array(
                            'currency_code' => $donation_data['currency'],
                            'value' => number_format($donation_data['amount'], 2, '.', '')
                        ),
                        'description' => $this->get_payment_description($donation_data),
                        'custom_id' => $donation_data['donation_id']
                    )
                ),
                'payment_source' => array(
                    'paypal' => array(
                        'experience_context' => array(
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'brand_name' => get_bloginfo('name'),
                            'locale' => 'en-US',
                            'landing_page' => 'LOGIN',
                            'user_action' => 'PAY_NOW',
                            'return_url' => $this->get_return_url($donation_data['donation_id']),
                            'cancel_url' => $this->get_cancel_url($donation_data['donation_id'])
                        )
                    )
                )
            );

            $response = $this->api_request('v2/checkout/orders', $order_data, 'POST');

            if (isset($response['id'])) {
                return array(
                    'success' => true,
                    'redirect_url' => $this->get_approval_url($response),
                    'transaction_id' => $response['id'],
                    'status' => 'pending'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to create PayPal order.', 'zim-donations')
                );
            }
        } catch (Exception $e) {
            error_log('PayPal Payment Error: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => __('PayPal payment failed. Please try again.' . $e->getMessage(), 'zim-donations')
            );
        }
    }

    /**
     * Create order via AJAX
     */
    public function paypal_create_order()
    {

        // check_ajax_referer('zim_donations_nonce', 'nonce');

        $donation_data = array(
            'amount' => floatval($_POST['amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'donation_id' => sanitize_text_field($_POST['transaction_id']),
            'donor_name' => sanitize_text_field($_POST['donor_name']) ?? 'Unknown',
            'donor_email' => sanitize_email($_POST['donor_email']) ?? 'pikigene01@gmail.com'
        );

        $result = $this->process_payment($donation_data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Capture order via AJAX
     */
    public function ajax_capture_order()
    {
        check_ajax_referer('helpme_donation_nonce', 'nonce');

        $order_id = sanitize_text_field($_POST['order_id']);
        $donation_id = sanitize_text_field($_POST['donation_id']);

        try {
            $response = $this->api_request("v2/checkout/orders/{$order_id}/capture", array(), 'POST');

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                // Update donation status
                $this->update_donation_status($donation_id, 'completed', $response);

                wp_send_json_success(array(
                    'message' => __('Payment completed successfully!', 'zim-donations'),
                    'transaction_id' => $response['id']
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Payment capture failed.', 'zim-donations')
                ));
            }
        } catch (Exception $e) {
            error_log('PayPal Capture Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Payment capture failed.', 'zim-donations')
            ));
        }
    }

    /**
     * Handle webhook
     */
    public function handle_webhook()
    {
        $payload = file_get_contents('php://input');
        $headers = getallheaders();

        // Verify webhook signature
        if (!$this->verify_webhook_signature($payload, $headers)) {
            http_response_code(400);
            exit('Invalid signature');
        }

        $event = json_decode($payload, true);

        switch ($event['event_type']) {
            case 'CHECKOUT.ORDER.COMPLETED':
                $this->handle_order_completed($event);
                break;
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handle_payment_completed($event);
                break;
            case 'PAYMENT.CAPTURE.DECLINED':
                $this->handle_payment_declined($event);
                break;
        }

        http_response_code(200);
        exit('OK');
    }

    /**
     * Get payment form HTML
     */
    public function get_payment_form($donation_data)
    {
        ob_start();
?>
        <div class="paypal-payment-form">
            <div id="paypal-button-container"></div>
            <div id="paypal-error-message" class="error-message" style="display: none;"></div>
        </div>

        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($this->get_client_id()); ?>&currency=<?php echo esc_attr($donation_data['currency']); ?>"></script>
        <script>
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'paypal_create_order',
                            nonce: '<?php echo wp_create_nonce("helpme_donation_nonce"); ?>',
                            amount: '<?php echo esc_js($donation_data["amount"]); ?>',
                            currency: '<?php echo esc_js($donation_data["currency"]); ?>',
                            donation_id: '<?php echo esc_js($donation_data["donation_id"]); ?>',
                            donor_name: '<?php echo esc_js($donation_data["donor_name"]); ?>',
                            donor_email: '<?php echo esc_js($donation_data["donor_email"]); ?>'
                        })
                    }).then(function(response) {
                        return response.json();
                    }).then(function(data) {
                        if (data.success) {
                            return data.data.transaction_id;
                        } else {
                            throw new Error(data.data.message);
                        }
                    });
                },
                onApprove: function(data, actions) {
                    return fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'paypal_capture_order',
                            nonce: '<?php echo wp_create_nonce("helpme_donation_nonce"); ?>',
                            order_id: data.orderID,
                            donation_id: '<?php echo esc_js($donation_data["donation_id"]); ?>'
                        })
                    }).then(function(response) {
                        return response.json();
                    }).then(function(data) {
                        if (data.success) {
                            // Redirect to success page
                            window.location.href = '<?php echo esc_url($this->get_success_url()); ?>';
                        } else {
                            document.getElementById('paypal-error-message').textContent = data.data.message;
                            document.getElementById('paypal-error-message').style.display = 'block';
                        }
                    });
                },
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    document.getElementById('paypal-error-message').textContent = '<?php _e("Payment failed. Please try again.", "zim-donations"); ?>';
                    document.getElementById('paypal-error-message').style.display = 'block';
                }
            }).render('#paypal-button-container');
        </script>

        <style>
            .paypal-payment-form {
                max-width: 400px;
                margin: 0 auto;
            }

            #paypal-button-container {
                margin: 20px 0;
            }

            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
            }
        </style>
<?php
        return ob_get_clean();
    }

    /**
     * API request helper
     */
    private function api_request($endpoint, $data = array(), $method = 'GET')
    {
        $access_token = $this->get_access_token();

        if (!$access_token) {
            throw new Exception('Failed to get PayPal access token');
        }

        $url = $this->get_api_url() . '/' . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'PayPal-Request-Id' => wp_generate_uuid4()
            ),
            'timeout' => 30
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('PayPal API request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown PayPal API error';
            throw new Exception('PayPal API error: ' . $error_message);
        }

        return json_decode($body, true);
    }

    /**
     * Get access token
     */
    private function get_access_token()
    {
        $transient_key = 'helpme_donations_paypal_access_token';
        $access_token = get_transient($transient_key);

        if (!$access_token) {
            $client_id = $this->get_client_id();
            $client_secret = $this->get_client_secret();

            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret)
                ),
                'body' => 'grant_type=client_credentials',
                'timeout' => 30
            );

            $response = wp_remote_post($this->get_api_url() . '/v1/oauth2/token', $args);

            if (is_wp_error($response)) {
                throw new Exception('PayPal token request failed: ' . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['access_token'])) {
                $access_token = $data['access_token'];
                $expires_in = isset($data['expires_in']) ? $data['expires_in'] - 300 : 3300; // 5 min buffer
                set_transient($transient_key, $access_token, $expires_in);
            } else {
                throw new Exception('PayPal token response error: ' . $body);
                return false;
            }
        }

        return $access_token;
    }

    /**
     * Helper methods
     */
    private function get_client_id()
    {
        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_paypal_test_client_id', '') :
            get_option('helpme_donations_paypal_test_client_id', '');
    }

    private function get_client_secret()
    {
        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_paypal_test_client_secret', '') :
            get_option('helpme_donations_paypal_test_client_secret', '');
    }

    private function get_api_url()
    {
        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ? $this->api_endpoints['sandbox'] : $this->api_endpoints['live'];
    }

    private function get_payment_description($donation_data)
    {
        return sprintf(
            __('Donation to %s', 'zim-donations'),
            get_bloginfo('name')
        );
    }

    private function get_return_url($donation_id)
    {
        return add_query_arg(array(
            'zim-donation' => 'success',
            'donation_id' => $donation_id
        ), get_permalink(get_option('helpme_donations_success_page')));
    }

    private function get_cancel_url($donation_id)
    {
        return add_query_arg(array(
            'zim-donation' => 'cancelled',
            'donation_id' => $donation_id
        ), get_permalink(get_option('helpme_donations_cancelled_page')));
    }

    private function get_success_url($donation_id = 0)
    {
        return add_query_arg(array(
            'zim-donation' => 'success',
            'donation_id' => $donation_id
        ), get_permalink(get_option('helpme_donations_success_page')));
    }

    // private function get_approval_url($order_response) {
    //     wp_send_json_error($order_response);
    //     if (isset($order_response['links'])) {
    //         foreach ($order_response['links'] as $link) {
    //             if ($link['rel'] === 'approve') {
    //                 return $link['href'];
    //             }
    //         }
    //     }
    //     return '';
    // }

    private function get_approval_url($response)
    {
        if (isset($response['links']) && is_array($response['links'])) {
            foreach ($response['links'] as $link) {
                if (
                    isset($link['rel']) &&
                    in_array($link['rel'], ['approve', 'payer-action']) &&
                    isset($link['href'])
                ) {
                    return $link['href'];
                }
            }
        }
        return '';
    }

    private function update_donation_status($donation_id, $status, $response_data)
    {
        global $wpdb;

        $db = new ZimDonations_DB();
        $donations_table = $db->get_donations_table();

        $update_data = array(
            'status' => $status,
            'gateway_transaction_id' => $response_data['id'],
            'updated_at' => current_time('mysql')
        );

        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
        }

        $wpdb->update(
            $donations_table,
            $update_data,
            array('donation_id' => $donation_id),
            array('%s', '%s', '%s'),
            array('%s')
        );
    }

    private function verify_webhook_signature($payload, $headers)
    {
        // PayPal webhook signature verification
        // This is a simplified version - implement proper verification in production
        return true;
    }

    private function handle_order_completed($event)
    {
        // Handle completed order
        $order_id = $event['resource']['id'];
        // Implementation here
    }

    private function handle_payment_completed($event)
    {
        // Handle completed payment
        $capture_id = $event['resource']['id'];
        // Implementation here
    }

    private function handle_payment_declined($event)
    {
        // Handle declined payment
        $capture_id = $event['resource']['id'];
        // Implementation here
    }
}
