<?php
/**
 * Help Me Donations - ZimSwitch Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Gateway_ZimSwitch {

    /**
     * Gateway ID
     */
    public $id = 'zimswitch';

    /**
     * Gateway name
     */
    public $name = 'ZimSwitch';

    /**
     * Gateway description
     */
    public $description = 'Pay using ZimSwitch banking network';

    /**
     * Supported currencies
     */
    public $supported_currencies = array('USD', 'ZIG');

    /**
     * API endpoint
     */
    private $api_endpoint = 'https://api.zimswitch.co.zw/v1/';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_zimswitch_initiate_payment', array($this, 'ajax_initiate_payment'));
        add_action('wp_ajax_nopriv_zimswitch_initiate_payment', array($this, 'ajax_initiate_payment'));
        add_action('wp_ajax_zimswitch_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_nopriv_zimswitch_check_status', array($this, 'ajax_check_status'));
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        $enabled = get_option('helpme_donations_zimswitch_enabled', false);
        $merchant_id = get_option('helpme_donations_zimswitch_merchant_id', '');
        $api_key = get_option('helpme_donations_zimswitch_api_key', '');

        return $enabled && !empty($merchant_id) && !empty($api_key);
    }

    /**
     * Process payment
     */
    public function process_payment($donation_data) {
        try {
            $transaction_ref = $this->generate_transaction_reference();
            
            $payment_data = array(
                'merchant_id' => get_option('helpme_donations_zimswitch_merchant_id', ''),
                'amount' => number_format($donation_data['amount'], 2, '.', ''),
                'currency' => $donation_data['currency'],
                'transaction_ref' => $transaction_ref,
                'description' => $this->get_payment_description($donation_data),
                'customer_email' => $donation_data['donor_email'],
                'customer_name' => $donation_data['donor_name'],
                'return_url' => $this->get_return_url($donation_data['donation_id']),
                'cancel_url' => $this->get_cancel_url($donation_data['donation_id']),
                'callback_url' => $this->get_callback_url(),
                'metadata' => array(
                    'donation_id' => $donation_data['donation_id'],
                    'source' => 'zim_donations_plugin'
                )
            );

            $response = $this->api_request('transactions/initiate', $payment_data, 'POST');

            if (isset($response['status']) && $response['status'] === 'success') {
                return array(
                    'success' => true,
                    'transaction_id' => $transaction_ref,
                    'redirect_url' => $response['payment_url'],
                    'status' => 'pending',
                    'message' => __('Redirecting to ZimSwitch payment page', 'zim-donations')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => isset($response['message']) ? $response['message'] : __('ZimSwitch payment initiation failed.', 'zim-donations')
                );
            }

        } catch (Exception $e) {
            error_log('ZimSwitch Payment Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => __('ZimSwitch payment failed. Please try again.', 'zim-donations')
            );
        }
    }

    /**
     * Initiate payment via AJAX
     */
    public function ajax_initiate_payment() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        $donation_data = array(
            'amount' => floatval($_POST['amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'donation_id' => sanitize_text_field($_POST['donation_id']),
            'donor_name' => sanitize_text_field($_POST['donor_name']),
            'donor_email' => sanitize_email($_POST['donor_email']),
            'bank_code' => sanitize_text_field($_POST['bank_code'] ?? '')
        );

        $result = $this->process_payment($donation_data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Check payment status via AJAX
     */
    public function ajax_check_status() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        $transaction_ref = sanitize_text_field($_POST['transaction_ref']);
        $donation_id = sanitize_text_field($_POST['donation_id']);

        try {
            $response = $this->api_request('transactions/status', array(
                'merchant_id' => get_option('helpme_donations_zimswitch_merchant_id', ''),
                'transaction_ref' => $transaction_ref
            ), 'POST');

            if (isset($response['status'])) {
                $status = $this->map_payment_status($response['transaction_status']);
                
                if ($status === 'completed') {
                    $this->update_donation_status($donation_id, 'completed', $response);
                    wp_send_json_success(array(
                        'status' => 'completed',
                        'message' => __('Payment completed successfully!', 'zim-donations')
                    ));
                } elseif ($status === 'failed') {
                    $this->update_donation_status($donation_id, 'failed', $response);
                    wp_send_json_success(array(
                        'status' => 'failed',
                        'message' => __('Payment failed.', 'zim-donations')
                    ));
                } else {
                    wp_send_json_success(array(
                        'status' => 'pending',
                        'message' => __('Payment is being processed.', 'zim-donations')
                    ));
                }
            } else {
                wp_send_json_error(array(
                    'message' => __('Unable to check payment status.', 'zim-donations')
                ));
            }

        } catch (Exception $e) {
            error_log('ZimSwitch Status Check Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Unable to check payment status.', 'zim-donations')
            ));
        }
    }

    /**
     * Handle webhook
     */
    public function handle_webhook() {
        $payload = file_get_contents('php://input');
        $headers = getallheaders();

        // Verify webhook signature
        if (!$this->verify_webhook_signature($payload, $headers)) {
            http_response_code(400);
            exit('Invalid signature');
        }

        $data = json_decode($payload, true);

        if (isset($data['event_type']) && isset($data['transaction_ref'])) {
            switch ($data['event_type']) {
                case 'transaction.completed':
                    $this->handle_transaction_completed($data);
                    break;
                case 'transaction.failed':
                    $this->handle_transaction_failed($data);
                    break;
                case 'transaction.cancelled':
                    $this->handle_transaction_cancelled($data);
                    break;
            }
        }

        http_response_code(200);
        exit('OK');
    }

    /**
     * Get payment form HTML
     */
    public function get_payment_form($donation_data) {
        $banks = $this->get_supported_banks();
        
        ob_start();
        ?>
        <div class="zimswitch-payment-form">
            <div class="payment-instructions">
                <h4><?php _e('Pay with ZimSwitch', 'zim-donations'); ?></h4>
                <p><?php _e('Select your bank and you will be redirected to complete your payment securely.', 'zim-donations'); ?></p>
            </div>

            <div class="form-group">
                <label for="zimswitch-bank"><?php _e('Select Your Bank', 'zim-donations'); ?></label>
                <select id="zimswitch-bank" name="bank_code" required>
                    <option value=""><?php _e('Choose your bank...', 'zim-donations'); ?></option>
                    <?php foreach ($banks as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="payment-summary">
                <div class="summary-item">
                    <span class="label"><?php _e('Amount:', 'zim-donations'); ?></span>
                    <span class="value"><?php echo $this->format_currency($donation_data['amount'], $donation_data['currency']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label"><?php _e('Currency:', 'zim-donations'); ?></span>
                    <span class="value"><?php echo esc_html($donation_data['currency']); ?></span>
                </div>
            </div>

            <button type="button" id="zimswitch-pay-button" class="payment-button">
                <span class="button-text"><?php _e('Proceed to Bank', 'zim-donations'); ?></span>
                <span class="button-spinner" style="display: none;"></span>
            </button>

            <div id="zimswitch-error-message" class="error-message" style="display: none;"></div>

            <div class="security-info">
                <small><?php _e('Your payment will be processed securely through the ZimSwitch network.', 'zim-donations'); ?></small>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const payButton = document.getElementById('zimswitch-pay-button');
            const bankSelect = document.getElementById('zimswitch-bank');
            const errorMessage = document.getElementById('zimswitch-error-message');

            payButton.addEventListener('click', function() {
                const bankCode = bankSelect.value;
                
                if (!bankCode) {
                    showError('<?php _e("Please select your bank.", "zim-donations"); ?>');
                    return;
                }

                initiatePayment(bankCode);
            });

            function initiatePayment(bankCode) {
                showLoading(true);
                hideError();

                const formData = new FormData();
                formData.append('action', 'zimswitch_initiate_payment');
                formData.append('nonce', '<?php echo wp_create_nonce("zim_donations_nonce"); ?>');
                formData.append('amount', '<?php echo esc_js($donation_data["amount"]); ?>');
                formData.append('currency', '<?php echo esc_js($donation_data["currency"]); ?>');
                formData.append('donation_id', '<?php echo esc_js($donation_data["donation_id"]); ?>');
                formData.append('donor_name', '<?php echo esc_js($donation_data["donor_name"]); ?>');
                formData.append('donor_email', '<?php echo esc_js($donation_data["donor_email"]); ?>');
                formData.append('bank_code', bankCode);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);
                    
                    if (data.success) {
                        // Redirect to bank payment page
                        window.location.href = data.data.redirect_url;
                    } else {
                        showError(data.data.message || '<?php _e("Payment initiation failed.", "zim-donations"); ?>');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showError('<?php _e("Payment initiation failed.", "zim-donations"); ?>');
                });
            }

            function showLoading(show) {
                const buttonText = payButton.querySelector('.button-text');
                const buttonSpinner = payButton.querySelector('.button-spinner');
                
                if (show) {
                    buttonText.style.display = 'none';
                    buttonSpinner.style.display = 'inline-block';
                    payButton.disabled = true;
                } else {
                    buttonText.style.display = 'inline-block';
                    buttonSpinner.style.display = 'none';
                    payButton.disabled = false;
                }
            }

            function showError(message) {
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
            }

            function hideError() {
                errorMessage.style.display = 'none';
            }
        });
        </script>

        <style>
        .zimswitch-payment-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .payment-instructions {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #0073aa;
        }

        .payment-instructions h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background: white;
        }

        .payment-summary {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-item:last-child {
            margin-bottom: 0;
        }

        .summary-item .label {
            font-weight: bold;
        }

        .payment-button {
            width: 100%;
            background: #0073aa;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
            transition: background-color 0.3s;
        }

        .payment-button:hover {
            background: #005177;
        }

        .payment-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #f5c6cb;
        }

        .security-info {
            text-align: center;
            color: #666;
            margin-top: 15px;
        }

        .button-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * API request helper
     */
    private function api_request($endpoint, $data = array(), $method = 'GET') {
        $api_key = get_option('helpme_donations_zimswitch_api_key', '');
        $merchant_id = get_option('helpme_donations_zimswitch_merchant_id', '');

        $url = $this->api_endpoint . $endpoint;

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'X-Merchant-ID' => $merchant_id,
            'User-Agent' => 'ZimDonations/' . ZIM_DONATIONS_VERSION
        );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 45
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('ZimSwitch API request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown ZimSwitch API error';
            throw new Exception('ZimSwitch API error: ' . $error_message);
        }

        return json_decode($body, true);
    }

    /**
     * Helper methods
     */
    private function generate_transaction_reference() {
        return 'ZSW_' . strtoupper(uniqid()) . '_' . time();
    }

    private function get_payment_description($donation_data) {
        return sprintf(
            __('Donation to %s via ZimSwitch', 'zim-donations'),
            get_bloginfo('name')
        );
    }

    private function get_callback_url() {
        return add_query_arg(array(
            'zim-donations-webhook' => '1',
            'gateway' => 'zimswitch'
        ), home_url());
    }

    private function get_return_url($donation_id) {
        return add_query_arg(array(
            'zim-donation' => 'success',
            'donation_id' => $donation_id
        ), home_url());
    }

    private function get_cancel_url($donation_id) {
        return add_query_arg(array(
            'zim-donation' => 'cancelled',
            'donation_id' => $donation_id
        ), home_url());
    }

    private function get_supported_banks() {
        return array(
            'cbz' => 'CBZ Bank',
            'stanbic' => 'Stanbic Bank',
            'standard_chartered' => 'Standard Chartered Bank',
            'barclays' => 'Barclays Bank',
            'fbc' => 'FBC Bank',
            'nedbank' => 'Nedbank Zimbabwe',
            'zb_bank' => 'ZB Bank',
            'steward_bank' => 'Steward Bank',
            'agribank' => 'Agribank',
            'peoples_own' => "People's Own Savings Bank"
        );
    }

    private function format_currency($amount, $currency) {
        $currency_manager = new ZimDonations_Currency_Manager();
        return $currency_manager->format_currency($amount, $currency);
    }

    private function map_payment_status($zimswitch_status) {
        $status_map = array(
            'successful' => 'completed',
            'completed' => 'completed',
            'paid' => 'completed',
            'settled' => 'completed',
            'failed' => 'failed',
            'declined' => 'failed',
            'cancelled' => 'failed',
            'timeout' => 'failed',
            'pending' => 'pending',
            'processing' => 'pending',
            'initiated' => 'pending'
        );

        return isset($status_map[strtolower($zimswitch_status)]) ? $status_map[strtolower($zimswitch_status)] : 'pending';
    }

    private function update_donation_status($donation_id, $status, $response_data) {
        global $wpdb;
        
        $db = new ZimDonations_DB();
        $donations_table = $db->get_donations_table();
        
        $update_data = array(
            'status' => $status,
            'gateway_transaction_id' => isset($response_data['transaction_ref']) ? $response_data['transaction_ref'] : '',
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

    private function verify_webhook_signature($payload, $headers) {
        // ZimSwitch webhook signature verification
        $api_key = get_option('helpme_donations_zimswitch_api_key', '');
        $signature = isset($headers['X-ZimSwitch-Signature']) ? $headers['X-ZimSwitch-Signature'] : '';
        
        $expected_signature = hash_hmac('sha256', $payload, $api_key);
        
        return hash_equals($expected_signature, $signature);
    }

    private function handle_transaction_completed($data) {
        if (isset($data['metadata']['donation_id'])) {
            $this->update_donation_status($data['metadata']['donation_id'], 'completed', $data);
        }
    }

    private function handle_transaction_failed($data) {
        if (isset($data['metadata']['donation_id'])) {
            $this->update_donation_status($data['metadata']['donation_id'], 'failed', $data);
        }
    }

    private function handle_transaction_cancelled($data) {
        if (isset($data['metadata']['donation_id'])) {
            $this->update_donation_status($data['metadata']['donation_id'], 'failed', $data);
        }
    }
} 