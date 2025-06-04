<?php
/**
 * InBucks Payment Gateway for Zimbabwe Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Gateway_InBucks {

    /**
     * Gateway ID
     */
    public $id = 'inbucks';

    /**
     * Gateway name
     */
    public $name = 'InBucks';

    /**
     * Gateway description
     */
    public $description = 'Pay using InBucks mobile wallet';

    /**
     * Supported currencies
     */
    public $supported_currencies = array('USD', 'ZIG');

    /**
     * API endpoint
     */
    private $api_endpoint = 'https://api.inbucks.com/v1/';

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
        add_action('wp_ajax_inbucks_initiate_payment', array($this, 'ajax_initiate_payment'));
        add_action('wp_ajax_nopriv_inbucks_initiate_payment', array($this, 'ajax_initiate_payment'));
        add_action('wp_ajax_inbucks_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_nopriv_inbucks_check_status', array($this, 'ajax_check_status'));
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        $enabled = get_option('zim_donations_inbucks_enabled', false);
        $api_key = get_option('zim_donations_inbucks_api_key', '');
        $secret_key = get_option('zim_donations_inbucks_secret_key', '');

        return $enabled && !empty($api_key) && !empty($secret_key);
    }

    /**
     * Process payment
     */
    public function process_payment($donation_data) {
        try {
            $transaction_id = $this->generate_transaction_id();
            
            $payment_data = array(
                'amount' => $donation_data['amount'],
                'currency' => $donation_data['currency'],
                'reference' => $donation_data['donation_id'],
                'description' => $this->get_payment_description($donation_data),
                'customer_email' => $donation_data['donor_email'],
                'customer_name' => $donation_data['donor_name'],
                'transaction_id' => $transaction_id,
                'callback_url' => $this->get_callback_url(),
                'return_url' => $this->get_return_url($donation_data['donation_id']),
                'cancel_url' => $this->get_cancel_url($donation_data['donation_id'])
            );

            $response = $this->api_request('payments/initiate', $payment_data, 'POST');

            if (isset($response['status']) && $response['status'] === 'success') {
                return array(
                    'success' => true,
                    'transaction_id' => $transaction_id,
                    'payment_url' => $response['payment_url'],
                    'status' => 'pending',
                    'message' => __('Please complete payment using InBucks', 'zim-donations')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => isset($response['message']) ? $response['message'] : __('InBucks payment failed.', 'zim-donations')
                );
            }

        } catch (Exception $e) {
            error_log('InBucks Payment Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => __('InBucks payment failed. Please try again.', 'zim-donations')
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
            'phone_number' => sanitize_text_field($_POST['phone_number'])
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

        $transaction_id = sanitize_text_field($_POST['transaction_id']);
        $donation_id = sanitize_text_field($_POST['donation_id']);

        try {
            $response = $this->api_request('payments/status/' . $transaction_id, array(), 'GET');

            if (isset($response['status'])) {
                $status = $this->map_payment_status($response['status']);
                
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
            error_log('InBucks Status Check Error: ' . $e->getMessage());
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

        if (isset($data['event_type']) && isset($data['transaction_id'])) {
            switch ($data['event_type']) {
                case 'payment.completed':
                    $this->handle_payment_completed($data);
                    break;
                case 'payment.failed':
                    $this->handle_payment_failed($data);
                    break;
                case 'payment.cancelled':
                    $this->handle_payment_cancelled($data);
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
        ob_start();
        ?>
        <div class="inbucks-payment-form">
            <div class="payment-instructions">
                <h4><?php _e('Pay with InBucks', 'zim-donations'); ?></h4>
                <p><?php _e('You will be redirected to InBucks to complete your payment.', 'zim-donations'); ?></p>
            </div>

            <div class="form-group">
                <label for="inbucks-phone"><?php _e('Phone Number', 'zim-donations'); ?></label>
                <input type="tel" id="inbucks-phone" name="phone_number" placeholder="0777123456" required>
                <small><?php _e('Enter your InBucks registered phone number', 'zim-donations'); ?></small>
            </div>

            <button type="button" id="inbucks-pay-button" class="payment-button">
                <span class="button-text"><?php _e('Pay with InBucks', 'zim-donations'); ?></span>
                <span class="button-spinner" style="display: none;"></span>
            </button>

            <div id="inbucks-error-message" class="error-message" style="display: none;"></div>
            <div id="inbucks-status-message" class="status-message" style="display: none;"></div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const payButton = document.getElementById('inbucks-pay-button');
            const phoneInput = document.getElementById('inbucks-phone');
            const errorMessage = document.getElementById('inbucks-error-message');
            const statusMessage = document.getElementById('inbucks-status-message');

            payButton.addEventListener('click', function() {
                const phoneNumber = phoneInput.value.trim();
                
                if (!phoneNumber) {
                    showError('<?php _e("Please enter your phone number.", "zim-donations"); ?>');
                    return;
                }

                initiatePayment(phoneNumber);
            });

            function initiatePayment(phoneNumber) {
                showLoading(true);
                hideMessages();

                const formData = new FormData();
                formData.append('action', 'inbucks_initiate_payment');
                formData.append('nonce', '<?php echo wp_create_nonce("zim_donations_nonce"); ?>');
                formData.append('amount', '<?php echo esc_js($donation_data["amount"]); ?>');
                formData.append('currency', '<?php echo esc_js($donation_data["currency"]); ?>');
                formData.append('donation_id', '<?php echo esc_js($donation_data["donation_id"]); ?>');
                formData.append('donor_name', '<?php echo esc_js($donation_data["donor_name"]); ?>');
                formData.append('donor_email', '<?php echo esc_js($donation_data["donor_email"]); ?>');
                formData.append('phone_number', phoneNumber);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);
                    
                    if (data.success) {
                        showStatus('<?php _e("Payment initiated. Please check your phone for InBucks prompt.", "zim-donations"); ?>');
                        startStatusCheck(data.data.transaction_id);
                    } else {
                        showError(data.data.message || '<?php _e("Payment initiation failed.", "zim-donations"); ?>');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showError('<?php _e("Payment initiation failed.", "zim-donations"); ?>');
                });
            }

            function startStatusCheck(transactionId) {
                const checkInterval = setInterval(function() {
                    checkPaymentStatus(transactionId, function(status) {
                        if (status === 'completed') {
                            clearInterval(checkInterval);
                            showStatus('<?php _e("Payment completed successfully!", "zim-donations"); ?>');
                            setTimeout(function() {
                                window.location.href = '<?php echo esc_url($this->get_success_url()); ?>';
                            }, 2000);
                        } else if (status === 'failed') {
                            clearInterval(checkInterval);
                            showError('<?php _e("Payment failed. Please try again.", "zim-donations"); ?>');
                        }
                    });
                }, 3000); // Check every 3 seconds

                // Stop checking after 5 minutes
                setTimeout(function() {
                    clearInterval(checkInterval);
                }, 300000);
            }

            function checkPaymentStatus(transactionId, callback) {
                const formData = new FormData();
                formData.append('action', 'inbucks_check_status');
                formData.append('nonce', '<?php echo wp_create_nonce("zim_donations_nonce"); ?>');
                formData.append('transaction_id', transactionId);
                formData.append('donation_id', '<?php echo esc_js($donation_data["donation_id"]); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        callback(data.data.status);
                    }
                })
                .catch(error => {
                    console.error('Status check error:', error);
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
                statusMessage.style.display = 'none';
            }

            function showStatus(message) {
                statusMessage.textContent = message;
                statusMessage.style.display = 'block';
                errorMessage.style.display = 'none';
            }

            function hideMessages() {
                errorMessage.style.display = 'none';
                statusMessage.style.display = 'none';
            }
        });
        </script>

        <style>
        .inbucks-payment-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .payment-instructions {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007cba;
        }

        .payment-instructions h4 {
            margin: 0 0 10px 0;
            color: #007cba;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-group small {
            display: block;
            color: #666;
            margin-top: 5px;
        }

        .payment-button {
            width: 100%;
            background: #007cba;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
        }

        .payment-button:hover {
            background: #005a87;
        }

        .payment-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .status-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
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
        $api_key = get_option('zim_donations_inbucks_api_key', '');
        $secret_key = get_option('zim_donations_inbucks_secret_key', '');

        $url = $this->api_endpoint . $endpoint;

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'X-API-Secret' => $secret_key
        );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('InBucks API request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown InBucks API error';
            throw new Exception('InBucks API error: ' . $error_message);
        }

        return json_decode($body, true);
    }

    /**
     * Helper methods
     */
    private function generate_transaction_id() {
        return 'inb_' . uniqid() . '_' . time();
    }

    private function get_payment_description($donation_data) {
        return sprintf(
            __('Donation to %s', 'zim-donations'),
            get_bloginfo('name')
        );
    }

    private function get_callback_url() {
        return add_query_arg(array(
            'zim-donations-webhook' => '1',
            'gateway' => 'inbucks'
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

    private function get_success_url() {
        $success_page = get_option('zim_donations_success_page');
        return $success_page ? get_permalink($success_page) : home_url();
    }

    private function map_payment_status($inbucks_status) {
        $status_map = array(
            'completed' => 'completed',
            'successful' => 'completed',
            'paid' => 'completed',
            'failed' => 'failed',
            'declined' => 'failed',
            'cancelled' => 'failed',
            'pending' => 'pending',
            'processing' => 'pending'
        );

        return isset($status_map[$inbucks_status]) ? $status_map[$inbucks_status] : 'pending';
    }

    private function update_donation_status($donation_id, $status, $response_data) {
        global $wpdb;
        
        $db = new ZimDonations_DB();
        $donations_table = $db->get_donations_table();
        
        $update_data = array(
            'status' => $status,
            'gateway_transaction_id' => isset($response_data['transaction_id']) ? $response_data['transaction_id'] : '',
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
        // InBucks webhook signature verification
        // This is a simplified version - implement proper verification in production
        $secret_key = get_option('zim_donations_inbucks_secret_key', '');
        $signature = isset($headers['X-InBucks-Signature']) ? $headers['X-InBucks-Signature'] : '';
        
        $expected_signature = hash_hmac('sha256', $payload, $secret_key);
        
        return hash_equals($expected_signature, $signature);
    }

    private function handle_payment_completed($data) {
        if (isset($data['reference'])) {
            $this->update_donation_status($data['reference'], 'completed', $data);
        }
    }

    private function handle_payment_failed($data) {
        if (isset($data['reference'])) {
            $this->update_donation_status($data['reference'], 'failed', $data);
        }
    }

    private function handle_payment_cancelled($data) {
        if (isset($data['reference'])) {
            $this->update_donation_status($data['reference'], 'failed', $data);
        }
    }
} 