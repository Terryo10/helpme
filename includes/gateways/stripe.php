<?php
/**
 * Stripe Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Gateway_Stripe {

    /**
     * Gateway ID
     */
    public $id = 'stripe';

    /**
     * Gateway title
     */
    public $title = 'Credit/Debit Card (Stripe)';

    /**
     * Gateway description
     */
    public $description = 'Pay securely with your credit or debit card';

    /**
     * Supported countries
     */
    public $supported_countries = array('US', 'CA', 'GB', 'AU', 'ZW');

    /**
     * Supported currencies
     */
    public $supported_currencies = array('USD', 'EUR', 'GBP', 'CAD', 'AUD');

    /**
     * Test mode
     */
    private $test_mode;

    /**
     * API keys
     */
    private $publishable_key;
    private $secret_key;
    private $webhook_secret;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_settings();
        $this->init_hooks();
    }

    /**
     * Initialize settings
     */
    private function init_settings() {
        $this->test_mode = get_option('zim_donations_test_mode', 1);

        if ($this->test_mode) {
            $this->publishable_key = get_option('zim_donations_stripe_test_publishable_key', '');
            $this->secret_key = get_option('zim_donations_stripe_test_secret_key', '');
        } else {
            $this->publishable_key = get_option('zim_donations_stripe_publishable_key', '');
            $this->secret_key = get_option('zim_donations_stripe_secret_key', '');
        }

        $this->webhook_secret = get_option('zim_donations_stripe_webhook_secret', '');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_stripe_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_nopriv_stripe_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_stripe_confirm_payment', array($this, 'confirm_payment'));
        add_action('wp_ajax_nopriv_stripe_confirm_payment', array($this, 'confirm_payment'));
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        $enabled = get_option('zim_donations_stripe_enabled', 0);

        if (!$enabled) {
            return false;
        }

        if (empty($this->publishable_key) || empty($this->secret_key)) {
            return false;
        }

        return true;
    }

    /**
     * Enqueue Stripe scripts
     */
    public function enqueue_scripts() {
        if (!is_admin() && $this->is_available()) {
            wp_enqueue_script(
                'stripe-js',
                'https://js.stripe.com/v3/',
                array(),
                '3.0',
                true
            );

            wp_enqueue_script(
                'zim-donations-stripe',
                ZIM_DONATIONS_PLUGIN_URL . 'assets/js/stripe.js',
                array('jquery', 'stripe-js'),
                ZIM_DONATIONS_VERSION,
                true
            );

            wp_localize_script('zim-donations-stripe', 'stripeConfig', array(
                'publishableKey' => $this->publishable_key,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('stripe_nonce')
            ));
        }
    }

    /**
     * Get payment form HTML
     */
    public function get_payment_form($amount, $currency) {
        if (!$this->is_available()) {
            return '<p>' . __('Stripe payment is not available.', 'zim-donations') . '</p>';
        }

        ob_start();
        ?>
        <div id="stripe-payment-form" class="zim-payment-form">
            <div class="stripe-form-group">
                <label for="stripe-card-element">
                    <?php _e('Card Details', 'zim-donations'); ?>
                </label>
                <div id="stripe-card-element" class="stripe-element">
                    <!-- Stripe Elements will create form elements here -->
                </div>
                <div id="stripe-card-errors" class="stripe-errors" role="alert"></div>
            </div>

            <div class="stripe-form-group">
                <label>
                    <input type="checkbox" id="stripe-save-card" name="save_card" value="1">
                    <?php _e('Save card for future donations', 'zim-donations'); ?>
                </label>
            </div>

            <div class="stripe-form-group">
                <button type="button" id="stripe-submit-button" class="stripe-submit-btn" data-amount="<?php echo esc_attr($amount); ?>" data-currency="<?php echo esc_attr($currency); ?>">
                    <span id="stripe-button-text">
                        <?php printf(__('Donate %s', 'zim-donations'), $this->format_currency($amount, $currency)); ?>
                    </span>
                    <div id="stripe-spinner" class="stripe-spinner hidden"></div>
                </button>
            </div>

            <div class="stripe-secure-notice">
                <small>
                    <i class="stripe-lock-icon"></i>
                    <?php _e('Your payment information is secure and encrypted', 'zim-donations'); ?>
                </small>
            </div>
        </div>

        <style>
            .stripe-element {
                background: white;
                padding: 12px;
                border: 1px solid #ccc;
                border-radius: 4px;
                margin-bottom: 10px;
            }

            .stripe-element.StripeElement--focus {
                border-color: #0073aa;
                box-shadow: 0 0 0 1px #0073aa;
            }

            .stripe-element.StripeElement--invalid {
                border-color: #dc3232;
            }

            .stripe-errors {
                color: #dc3232;
                font-size: 14px;
                margin-top: 5px;
            }

            .stripe-submit-btn {
                background: #0073aa;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                width: 100%;
                position: relative;
            }

            .stripe-submit-btn:hover {
                background: #005a87;
            }

            .stripe-submit-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .stripe-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255,255,255,.3);
                border-radius: 50%;
                border-top-color: #fff;
                animation: spin 1s ease-in-out infinite;
            }

            .stripe-spinner.hidden {
                display: none;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .stripe-secure-notice {
                text-align: center;
                margin-top: 10px;
                color: #666;
            }

            .stripe-form-group {
                margin-bottom: 15px;
            }

            .stripe-form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Process payment
     */
    public function process_payment($donation_data) {
        try {
            // Create payment intent
            $payment_intent = $this->create_stripe_payment_intent($donation_data);

            if (!$payment_intent) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create payment intent.', 'zim-donations')
                );
            }

            // Handle recurring donations
            if ($donation_data['is_recurring']) {
                return $this->process_recurring_payment($donation_data, $payment_intent);
            }

            return array(
                'success' => true,
                'redirect' => false,
                'payment_intent_id' => $payment_intent['id'],
                'client_secret' => $payment_intent['client_secret'],
                'transaction_id' => $payment_intent['id'],
                'message' => __('Payment intent created successfully.', 'zim-donations')
            );

        } catch (Exception $e) {
            error_log('Stripe Payment Error: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => __('Payment processing failed. Please try again.', 'zim-donations')
            );
        }
    }

    /**
     * Create Stripe Payment Intent
     */
    private function create_stripe_payment_intent($donation_data) {
        $amount = intval($donation_data['amount'] * 100); // Convert to cents
        $currency = strtolower($donation_data['currency']);

        $args = array(
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => array(
                'donation_id' => $donation_data['donation_id'],
                'donor_email' => $donation_data['donor_email'],
                'donor_name' => $donation_data['donor_name'],
                'campaign_id' => $donation_data['campaign_id'] ?? 0
            ),
            'receipt_email' => $donation_data['donor_email']
        );

        // Add description
        $description = sprintf(
            __('Donation from %s', 'zim-donations'),
            $donation_data['donor_name']
        );

        if (!empty($donation_data['campaign_id'])) {
            $campaign = $this->get_campaign($donation_data['campaign_id']);
            if ($campaign) {
                $description .= ' - ' . $campaign->title;
            }
        }

        $args['description'] = $description;

        return $this->stripe_request('payment_intents', $args, 'POST');
    }

    /**
     * Process recurring payment
     */
    private function process_recurring_payment($donation_data, $payment_intent) {
        try {
            // Create customer first
            $customer = $this->create_stripe_customer($donation_data);

            if (!$customer) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create customer for recurring donation.', 'zim-donations')
                );
            }

            // Create subscription
            $subscription = $this->create_stripe_subscription($donation_data, $customer['id']);

            if (!$subscription) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create subscription.', 'zim-donations')
                );
            }

            // Calculate next payment date
            $next_payment_date = $this->calculate_next_payment_date($donation_data['recurring_interval']);

            return array(
                'success' => true,
                'redirect' => false,
                'subscription_id' => $subscription['id'],
                'customer_id' => $customer['id'],
                'transaction_id' => $payment_intent['id'],
                'next_payment_date' => $next_payment_date,
                'message' => __('Recurring donation setup successful.', 'zim-donations')
            );

        } catch (Exception $e) {
            error_log('Stripe Recurring Payment Error: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => __('Failed to setup recurring donation.', 'zim-donations')
            );
        }
    }

    /**
     * Create Stripe customer
     */
    private function create_stripe_customer($donation_data) {
        $args = array(
            'email' => $donation_data['donor_email'],
            'name' => $donation_data['donor_name'],
            'metadata' => array(
                'donor_id' => $donation_data['donation_id'],
                'source' => 'zim_donations'
            )
        );

        if (!empty($donation_data['donor_phone'])) {
            $args['phone'] = $donation_data['donor_phone'];
        }

        return $this->stripe_request('customers', $args, 'POST');
    }

    /**
     * Create Stripe subscription
     */
    private function create_stripe_subscription($donation_data, $customer_id) {
        // First create a price for this donation amount
        $price = $this->create_stripe_price($donation_data);

        if (!$price) {
            return false;
        }

        $args = array(
            'customer' => $customer_id,
            'items' => array(
                array('price' => $price['id'])
            ),
            'metadata' => array(
                'donation_id' => $donation_data['donation_id'],
                'campaign_id' => $donation_data['campaign_id'] ?? 0
            )
        );

        return $this->stripe_request('subscriptions', $args, 'POST');
    }

    /**
     * Create Stripe price
     */
    private function create_stripe_price($donation_data) {
        $amount = intval($donation_data['amount'] * 100); // Convert to cents
        $currency = strtolower($donation_data['currency']);

        // Map intervals
        $interval_map = array(
            'monthly' => 'month',
            'yearly' => 'year',
            'weekly' => 'week'
        );

        $interval = $interval_map[$donation_data['recurring_interval']] ?? 'month';

        $args = array(
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => array(
                'interval' => $interval
            ),
            'product_data' => array(
                'name' => sprintf(
                    __('Recurring Donation - %s', 'zim-donations'),
                    get_bloginfo('name')
                )
            )
        );

        return $this->stripe_request('prices', $args, 'POST');
    }

    /**
     * AJAX: Create payment intent
     */
    public function create_payment_intent() {
        check_ajax_referer('stripe_nonce', 'nonce');

        $donation_data = array(
            'amount' => floatval($_POST['amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'donor_email' => sanitize_email($_POST['donor_email']),
            'donor_name' => sanitize_text_field($_POST['donor_name']),
            'donation_id' => intval($_POST['donation_id']),
            'campaign_id' => intval($_POST['campaign_id'] ?? 0)
        );

        $payment_intent = $this->create_stripe_payment_intent($donation_data);

        if ($payment_intent) {
            wp_send_json_success(array(
                'client_secret' => $payment_intent['client_secret']
            ));
        } else {
            wp_send_json_error(__('Failed to create payment intent.', 'zim-donations'));
        }
    }

    /**
     * AJAX: Confirm payment
     */
    public function confirm_payment() {
        check_ajax_referer('stripe_nonce', 'nonce');

        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id']);
        $donation_id = intval($_POST['donation_id']);

        // Retrieve payment intent from Stripe
        $payment_intent = $this->stripe_request("payment_intents/{$payment_intent_id}", array(), 'GET');

        if ($payment_intent && $payment_intent['status'] === 'succeeded') {
            // Update donation record
            global $wpdb;

            $wpdb->update(
                $wpdb->prefix . 'zim_donations',
                array(
                    'status' => 'completed',
                    'gateway_transaction_id' => $payment_intent_id,
                    'gateway_response' => json_encode($payment_intent),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $donation_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );

            wp_send_json_success(array(
                'message' => __('Payment confirmed successfully.', 'zim-donations')
            ));
        } else {
            wp_send_json_error(__('Payment confirmation failed.', 'zim-donations'));
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handle_webhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($this->webhook_secret)) {
            status_header(400);
            exit('Webhook secret not configured');
        }

        try {
            $event = $this->verify_webhook_signature($payload, $sig_header, $this->webhook_secret);
        } catch (Exception $e) {
            status_header(400);
            exit('Webhook signature verification failed');
        }

        // Handle the event
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded($event['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $this->handle_payment_failed($event['data']['object']);
                break;

            case 'invoice.payment_succeeded':
                $this->handle_recurring_payment_succeeded($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handle_recurring_payment_failed($event['data']['object']);
                break;

            default:
                error_log('Unhandled Stripe webhook event: ' . $event['type']);
        }

        status_header(200);
        exit('OK');
    }

    /**
     * Handle successful payment
     */
    private function handle_payment_succeeded($payment_intent) {
        global $wpdb;

        $donation_id = $payment_intent['metadata']['donation_id'] ?? null;

        if (!$donation_id) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'zim_donations',
            array(
                'status' => 'completed',
                'gateway_transaction_id' => $payment_intent['id'],
                'gateway_response' => json_encode($payment_intent),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $donation_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        // Trigger completion actions
        do_action('zim_donations_payment_completed', $donation_id, $payment_intent);
    }

    /**
     * Handle failed payment
     */
    private function handle_payment_failed($payment_intent) {
        global $wpdb;

        $donation_id = $payment_intent['metadata']['donation_id'] ?? null;

        if (!$donation_id) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'zim_donations',
            array(
                'status' => 'failed',
                'gateway_response' => json_encode($payment_intent),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $donation_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        // Trigger failure actions
        do_action('zim_donations_payment_failed', $donation_id, $payment_intent);
    }

    /**
     * Make Stripe API request
     */
    private function stripe_request($endpoint, $args = array(), $method = 'POST') {
        $url = 'https://api.stripe.com/v1/' . $endpoint;

        $headers = array(
            'Authorization' => 'Bearer ' . $this->secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded'
        );

        $body = '';
        if (!empty($args)) {
            $body = http_build_query($args);
        }

        $request_args = array(
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        );

        $response = wp_remote_request($url, $request_args);

        if (is_wp_error($response)) {
            error_log('Stripe API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) >= 400) {
            error_log('Stripe API Error: ' . $body);
            return false;
        }

        return $data;
    }

    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($payload, $sig_header, $secret) {
        $elements = explode(',', $sig_header);
        $signature = '';
        $timestamp = '';

        foreach ($elements as $element) {
            list($key, $value) = explode('=', $element, 2);
            if ($key === 'v1') {
                $signature = $value;
            } elseif ($key === 't') {
                $timestamp = $value;
            }
        }

        if (empty($signature) || empty($timestamp)) {
            throw new Exception('Invalid signature header');
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $secret);

        if (!hash_equals($expected_signature, $signature)) {
            throw new Exception('Signature verification failed');
        }

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - $timestamp) > 300) {
            throw new Exception('Timestamp outside tolerance');
        }

        return json_decode($payload, true);
    }

    /**
     * Calculate next payment date
     */
    private function calculate_next_payment_date($interval) {
        $next_date = new DateTime();

        switch ($interval) {
            case 'weekly':
                $next_date->add(new DateInterval('P1W'));
                break;
            case 'monthly':
                $next_date->add(new DateInterval('P1M'));
                break;
            case 'yearly':
                $next_date->add(new DateInterval('P1Y'));
                break;
            default:
                $next_date->add(new DateInterval('P1M'));
        }

        return $next_date->format('Y-m-d H:i:s');
    }

    /**
     * Format currency amount
     */
    private function format_currency($amount, $currency)
    {
        $symbols = array(
            'USD' => '$',
            'ZIG' => 'ZIG',
            'EUR' => '€',
            'GBP' => '£'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format($amount, 2);
    }
    


    
    /**
     * Get campaign details
     */
    private function get_campaign($campaign_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_campaigns WHERE id = %d",
            $campaign_id
        ));
    }
}