<?php
/**
 * Paynow Payment Gateway (EcoCash & OneMoney)
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Gateway_Paynow {

    /**
     * Gateway ID
     */
    public $id = 'paynow';

    /**
     * Gateway title
     */
    public $title = 'Mobile Money (EcoCash/OneMoney)';

    /**
     * Gateway description
     */
    public $description = 'Pay with EcoCash or OneMoney';

    /**
     * Supported countries
     */
    public $supported_countries = array('ZW');

    /**
     * Supported currencies
     */
    public $supported_currencies = array('USD', 'ZIG');

    /**
     * Paynow API settings
     */
    private $integration_id;
    private $integration_key;
    private $test_mode;

    /**
     * API URLs
     */
    private $api_url = 'https://www.paynow.co.zw/interface/initiatetransaction';
    private $poll_url = 'https://www.paynow.co.zw/interface/pollstatus';

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
        $this->integration_id = get_option('zim_donations_paynow_integration_id', '');
        $this->integration_key = get_option('zim_donations_paynow_integration_key', '');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_paynow_check_status', array($this, 'check_payment_status'));
        add_action('wp_ajax_nopriv_paynow_check_status', array($this, 'check_payment_status'));
        add_action('init', array($this, 'handle_return_url'));
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        $enabled = get_option('zim_donations_paynow_enabled', 0);

        if (!$enabled) {
            return false;
        }

        if (empty($this->integration_id) || empty($this->integration_key)) {
            return false;
        }

        return true;
    }

    /**
     * Get payment form HTML
     */
    public function get_payment_form($amount, $currency) {
        if (!$this->is_available()) {
            return '<p>' . __('Paynow payment is not available.', 'zim-donations') . '</p>';
        }

        ob_start();
        ?>
        <div id="paynow-payment-form" class="zim-payment-form">
            <div class="paynow-method-selection">
                <h4><?php _e('Select Payment Method', 'zim-donations'); ?></h4>

                <div class="paynow-methods">
                    <label class="paynow-method">
                        <input type="radio" name="paynow_method" value="ecocash" checked>
                        <div class="method-card">
                            <img src="<?php echo ZIM_DONATIONS_PLUGIN_URL; ?>assets/images/ecocash-logo.png" alt="EcoCash" class="method-logo">
                            <span class="method-name"><?php _e('EcoCash', 'zim-donations'); ?></span>
                        </div>
                    </label>

                    <label class="paynow-method">
                        <input type="radio" name="paynow_method" value="onemoney">
                        <div class="method-card">
                            <img src="<?php echo ZIM_DONATIONS_PLUGIN_URL; ?>assets/images/onemoney-logo.png" alt="OneMoney" class="method-logo">
                            <span class="method-name"><?php _e('OneMoney', 'zim-donations'); ?></span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="paynow-phone-input">
                <label for="paynow-phone">
                    <?php _e('Mobile Number', 'zim-donations'); ?>
                    <span class="required">*</span>
                </label>
                <div class="phone-input-group">
                    <span class="country-code">+263</span>
                    <input type="tel" id="paynow-phone" name="phone" placeholder="77 123 4567" required
                           pattern="[0-9]{9}" maxlength="9" class="paynow-phone-field">
                </div>
                <small class="help-text">
                    <?php _e('Enter your mobile number without the country code', 'zim-donations'); ?>
                </small>
            </div>

            <div class="paynow-amount-display">
                <div class="amount-breakdown">
                    <div class="amount-row">
                        <span><?php _e('Donation Amount:', 'zim-donations'); ?></span>
                        <span class="amount"><?php echo $this->format_currency($amount, $currency); ?></span>
                    </div>
                    <div class="amount-row total">
                        <span><?php _e('Total to Pay:', 'zim-donations'); ?></span>
                        <span class="amount"><?php echo $this->format_currency($amount, $currency); ?></span>
                    </div>
                </div>
            </div>

            <div class="paynow-instructions">
                <h5><?php _e('Payment Instructions:', 'zim-donations'); ?></h5>
                <ol>
                    <li><?php _e('Select your mobile money provider above', 'zim-donations'); ?></li>
                    <li><?php _e('Enter your mobile number', 'zim-donations'); ?></li>
                    <li><?php _e('Click "Pay Now" to initiate payment', 'zim-donations'); ?></li>
                    <li><?php _e('You will receive a payment prompt on your phone', 'zim-donations'); ?></li>
                    <li><?php _e('Enter your PIN to complete the payment', 'zim-donations'); ?></li>
                </ol>
            </div>

            <button type="button" id="paynow-submit-button" class="paynow-submit-btn"
                    data-amount="<?php echo esc_attr($amount); ?>"
                    data-currency="<?php echo esc_attr($currency); ?>">
                <span id="paynow-button-text">
                    <?php printf(__('Pay %s Now', 'zim-donations'), $this->format_currency($amount, $currency)); ?>
                </span>
                <div id="paynow-spinner" class="paynow-spinner hidden"></div>
            </button>

            <div class="paynow-secure-notice">
                <small>
                    <i class="paynow-shield-icon"></i>
                    <?php _e('Payments are processed securely by Paynow', 'zim-donations'); ?>
                </small>
            </div>
        </div>

        <!-- Payment Status Modal -->
        <div id="paynow-status-modal" class="paynow-modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Processing Payment', 'zim-donations'); ?></h3>
                </div>
                <div class="modal-body">
                    <div id="paynow-status-content">
                        <div class="status-step active" id="step-initiating">
                            <div class="step-icon"><div class="spinner"></div></div>
                            <div class="step-text"><?php _e('Initiating payment...', 'zim-donations'); ?></div>
                        </div>
                        <div class="status-step" id="step-sent">
                            <div class="step-icon">⏳</div>
                            <div class="step-text"><?php _e('Payment request sent to your phone', 'zim-donations'); ?></div>
                        </div>
                        <div class="status-step" id="step-waiting">
                            <div class="step-icon">📱</div>
                            <div class="step-text"><?php _e('Please check your phone and enter your PIN', 'zim-donations'); ?></div>
                        </div>
                        <div class="status-step" id="step-processing">
                            <div class="step-icon"><div class="spinner"></div></div>
                            <div class="step-text"><?php _e('Processing payment...', 'zim-donations'); ?></div>
                        </div>
                    </div>

                    <div id="paynow-timeout-message" class="timeout-message hidden">
                        <p><?php _e('Payment is taking longer than expected.', 'zim-donations'); ?></p>
                        <button type="button" id="paynow-check-status" class="btn-secondary">
                            <?php _e('Check Status', 'zim-donations'); ?>
                        </button>
                        <button type="button" id="paynow-cancel-payment" class="btn-cancel">
                            <?php _e('Cancel', 'zim-donations'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .paynow-methods {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .paynow-method {
                cursor: pointer;
            }

            .paynow-method input[type="radio"] {
                display: none;
            }

            .method-card {
                border: 2px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                text-align: center;
                transition: all 0.3s ease;
                background: white;
            }

            .paynow-method input:checked + .method-card {
                border-color: #0073aa;
                background: #f0f8ff;
            }

            .method-card:hover {
                border-color: #0073aa;
                box-shadow: 0 2px 8px rgba(0,115,170,0.1);
            }

            .method-logo {
                height: 40px;
                margin-bottom: 8px;
            }

            .method-name {
                display: block;
                font-weight: bold;
                color: #333;
            }

            .phone-input-group {
                display: flex;
                border: 1px solid #ccc;
                border-radius: 4px;
                overflow: hidden;
            }

            .country-code {
                background: #f5f5f5;
                padding: 12px 15px;
                border-right: 1px solid #ccc;
                font-weight: bold;
            }

            .paynow-phone-field {
                border: none;
                padding: 12px 15px;
                flex: 1;
                font-size: 16px;
            }

            .paynow-phone-field:focus {
                outline: none;
                box-shadow: 0 0 0 2px #0073aa;
            }

            .help-text {
                color: #666;
                font-style: italic;
                margin-top: 5px;
                display: block;
            }

            .amount-breakdown {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                margin: 15px 0;
            }

            .amount-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
            }

            .amount-row:last-child {
                margin-bottom: 0;
            }

            .amount-row.total {
                border-top: 1px solid #ddd;
                padding-top: 8px;
                font-weight: bold;
                font-size: 18px;
            }

            .paynow-instructions {
                background: #e8f4f8;
                border-left: 4px solid #0073aa;
                padding: 15px;
                margin: 15px 0;
            }

            .paynow-instructions h5 {
                margin-top: 0;
                color: #0073aa;
            }

            .paynow-instructions ol {
                margin-bottom: 0;
                padding-left: 20px;
            }

            .paynow-instructions li {
                margin-bottom: 5px;
            }

            .paynow-submit-btn {
                background: #0073aa;
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 4px;
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                width: 100%;
                position: relative;
                transition: background 0.3s ease;
            }

            .paynow-submit-btn:hover {
                background: #005a87;
            }

            .paynow-submit-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .paynow-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255,255,255,.3);
                border-radius: 50%;
                border-top-color: #fff;
                animation: spin 1s ease-in-out infinite;
            }

            .paynow-spinner.hidden {
                display: none;
            }

            .paynow-secure-notice {
                text-align: center;
                margin-top: 15px;
                color: #666;
            }

            .paynow-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .paynow-modal.hidden {
                display: none;
            }

            .modal-content {
                background: white;
                border-radius: 8px;
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            }

            .modal-header {
                padding: 20px;
                border-bottom: 1px solid #ddd;
                text-align: center;
            }

            .modal-header h3 {
                margin: 0;
                color: #333;
            }

            .modal-body {
                padding: 20px;
            }

            .status-step {
                display: flex;
                align-items: center;
                padding: 10px 0;
                opacity: 0.4;
                transition: opacity 0.3s ease;
            }

            .status-step.active {
                opacity: 1;
            }

            .status-step.completed {
                opacity: 1;
                color: #28a745;
            }

            .step-icon {
                width: 30px;
                height: 30px;
                margin-right: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .step-icon .spinner {
                width: 20px;
                height: 20px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            .timeout-message {
                text-align: center;
                padding: 20px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                margin-top: 20px;
            }

            .timeout-message.hidden {
                display: none;
            }

            .btn-secondary, .btn-cancel {
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin: 0 5px;
                font-size: 14px;
            }

            .btn-secondary {
                background: #6c757d;
                color: white;
            }

            .btn-cancel {
                background: #dc3545;
                color: white;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            @media (max-width: 600px) {
                .paynow-methods {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                let paymentPollInterval;
                let pollRequestId;

                // Phone number formatting
                $('#paynow-phone').on('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length > 9) {
                        value = value.substring(0, 9);
                    }
                    this.value = value;
                });

                // Submit payment
                $('#paynow-submit-button').on('click', function() {
                    const phone = $('#paynow-phone').val();
                    const method = $('input[name="paynow_method"]:checked').val();
                    const amount = $(this).data('amount');
                    const currency = $(this).data('currency');

                    if (!phone || phone.length !== 9) {
                        alert('<?php _e("Please enter a valid 9-digit mobile number", "zim-donations"); ?>');
                        return;
                    }

                    initiatePaynowPayment(method, phone, amount, currency);
                });

                // Check status button
                $('#paynow-check-status').on('click', function() {
                    if (pollRequestId) {
                        checkPaymentStatus(pollRequestId);
                    }
                });

                // Cancel payment
                $('#paynow-cancel-payment').on('click', function() {
                    cancelPayment();
                });

                function initiatePaynowPayment(method, phone, amount, currency) {
                    const $button = $('#paynow-submit-button');
                    const $buttonText = $('#paynow-button-text');
                    const $spinner = $('#paynow-spinner');

                    // Disable button and show spinner
                    $button.prop('disabled', true);
                    $buttonText.hide();
                    $spinner.removeClass('hidden');

                    $.ajax({
                        url: zimDonations.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'process_donation',
                            nonce: zimDonations.nonce,
                            gateway: 'paynow',
                            method: method,
                            phone: phone,
                            amount: amount,
                            currency: currency,
                            donor_email: $('input[name="donor_email"]').val(),
                            donor_name: $('input[name="donor_name"]').val(),
                            campaign_id: $('input[name="campaign_id"]').val() || 0
                        },
                        success: function(response) {
                            if (response.success) {
                                pollRequestId = response.data.poll_request_id;
                                showPaymentStatusModal();
                                startPollingPaymentStatus();
                            } else {
                                alert(response.data.message || '<?php _e("Payment initiation failed", "zim-donations"); ?>');
                                resetButton();
                            }
                        },
                        error: function() {
                            alert('<?php _e("An error occurred. Please try again.", "zim-donations"); ?>');
                            resetButton();
                        }
                    });
                }

                function showPaymentStatusModal() {
                    $('#paynow-status-modal').removeClass('hidden');
                    updateStepStatus('step-initiating', 'completed');
                    updateStepStatus('step-sent', 'active');
                }

                function startPollingPaymentStatus() {
                    let pollCount = 0;
                    const maxPolls = 24; // 2 minutes with 5-second intervals

                    paymentPollInterval = setInterval(function() {
                        pollCount++;

                        if (pollCount >= maxPolls) {
                            clearInterval(paymentPollInterval);
                            showTimeoutMessage();
                            return;
                        }

                        checkPaymentStatus(pollRequestId);
                    }, 5000);

                    // Initial status update
                    setTimeout(function() {
                        updateStepStatus('step-sent', 'completed');
                        updateStepStatus('step-waiting', 'active');
                    }, 2000);

                    setTimeout(function() {
                        updateStepStatus('step-waiting', 'completed');
                        updateStepStatus('step-processing', 'active');
                    }, 10000);
                }

                function checkPaymentStatus(requestId) {
                    $.ajax({
                        url: zimDonations.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'paynow_check_status',
                            nonce: zimDonations.nonce,
                            poll_request_id: requestId
                        },
                        success: function(response) {
                            if (response.success) {
                                const status = response.data.status;

                                if (status === 'paid') {
                                    clearInterval(paymentPollInterval);
                                    paymentSuccessful();
                                } else if (status === 'cancelled' || status === 'failed') {
                                    clearInterval(paymentPollInterval);
                                    paymentFailed(response.data.message);
                                }
                                // Continue polling for other statuses
                            }
                        }
                    });
                }

                function updateStepStatus(stepId, status) {
                    const $step = $('#' + stepId);
                    $step.removeClass('active completed').addClass(status);
                }

                function showTimeoutMessage() {
                    $('#paynow-timeout-message').removeClass('hidden');
                }

                function paymentSuccessful() {
                    $('#paynow-status-content').html(`
                    <div class="success-message">
                        <div class="success-icon">✅</div>
                        <h3><?php _e("Payment Successful!", "zim-donations"); ?></h3>
                        <p><?php _e("Thank you for your donation. You will receive a confirmation email shortly.", "zim-donations"); ?></p>
                        <button type="button" onclick="window.location.reload()" class="btn-primary">
                            <?php _e("Continue", "zim-donations"); ?>
                        </button>
                    </div>
                `);
                }

                function paymentFailed(message) {
                    $('#paynow-status-content').html(`
                    <div class="error-message">
                        <div class="error-icon">❌</div>
                        <h3><?php _e("Payment Failed", "zim-donations"); ?></h3>
                        <p>${message || '<?php _e("Your payment could not be processed. Please try again.", "zim-donations"); ?>'}</p>
                        <button type="button" onclick="closeModal()" class="btn-secondary">
                            <?php _e("Try Again", "zim-donations"); ?>
                        </button>
                    </div>
                `);
                }

                function cancelPayment() {
                    clearInterval(paymentPollInterval);
                    closeModal();
                    resetButton();
                }

                function closeModal() {
                    $('#paynow-status-modal').addClass('hidden');
                }

                function resetButton() {
                    const $button = $('#paynow-submit-button');
                    const $buttonText = $('#paynow-button-text');
                    const $spinner = $('#paynow-spinner');

                    $button.prop('disabled', false);
                    $buttonText.show();
                    $spinner.addClass('hidden');
                }

                // Global function for modal close
                window.closeModal = closeModal;
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Process payment
     */
    public function process_payment($donation_data) {
        try {
            $phone = $this->format_phone_number($donation_data['phone']);
            $method = $donation_data['method']; // ecocash or onemoney

            // Create payment request
            $payment_data = array(
                'reference' => $this->generate_reference($donation_data['donation_id']),
                'amount' => $donation_data['amount'],
                'currency' => $donation_data['currency'],
                'phone' => $phone,
                'method' => $method,
                'email' => $donation_data['donor_email'],
                'additional_info' => sprintf(
                    'Donation from %s',
                    $donation_data['donor_name']
                )
            );

            $response = $this->initiate_payment($payment_data);

            if ($response && $response['status'] === 'Ok') {
                return array(
                    'success' => true,
                    'redirect' => false,
                    'poll_request_id' => $response['pollurl'],
                    'transaction_id' => $response['hash'],
                    'message' => __('Payment initiated successfully. Please check your phone.', 'zim-donations')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $response['error'] ?? __('Payment initiation failed.', 'zim-donations')
                );
            }

        } catch (Exception $e) {
            error_log('Paynow Payment Error: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => __('Payment processing failed. Please try again.', 'zim-donations')
            );
        }
    }

    /**
     * Initiate payment with Paynow
     */
    private function initiate_payment($data) {
        $values = array(
            'resulturl' => $this->get_result_url(),
            'returnurl' => $this->get_return_url(),
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'id' => $this->integration_id,
            'additionalinfo' => $data['additional_info'],
            'authemail' => $data['email'],
            'phone' => $data['phone'],
            'method' => $data['method']
        );

        // Generate hash
        $string = '';
        foreach ($values as $key => $value) {
            if ($key !== 'hash') {
                $string .= $value;
            }
        }
        $string .= $this->integration_key;
        $values['hash'] = strtoupper(hash('sha512', $string));

        // Make request
        $response = wp_remote_post($this->api_url, array(
            'body' => $values,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            throw new Exception('Network error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = $this->parse_response($body);

        return $result;
    }

    /**
     * Parse Paynow response
     */
    private function parse_response($response) {
        $result = array();
        $lines = explode("\n", trim($response));

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $result[strtolower(trim($key))] = trim($value);
            }
        }

        return $result;
    }

    /**
     * Check payment status
     */
    public function check_payment_status() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        $poll_url = sanitize_text_field($_POST['poll_request_id']);

        $response = wp_remote_get($poll_url, array(
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(__('Failed to check payment status.', 'zim-donations'));
        }

        $body = wp_remote_retrieve_body($response);
        $result = $this->parse_response($body);

        $status = strtolower($result['status'] ?? 'unknown');

        // Map Paynow statuses to our internal statuses
        $status_map = array(
            'paid' => 'paid',
            'awaiting delivery' => 'paid',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            'created' => 'pending',
            'sent' => 'pending',
            'delivered' => 'pending'
        );

        $mapped_status = $status_map[$status] ?? 'pending';

        wp_send_json_success(array(
            'status' => $mapped_status,
            'paynow_status' => $status,
            'message' => $this->get_status_message($mapped_status)
        ));
    }

    /**
     * Handle return URL
     */
    public function handle_return_url() {
        if (isset($_GET['paynow_return']) && $_GET['paynow_return'] === '1') {
            $reference = sanitize_text_field($_GET['reference'] ?? '');
            $status = sanitize_text_field($_GET['status'] ?? '');

            if ($reference && $status) {
                $this->process_return($reference, $status);
            }

            // Redirect to success or failure page
            $redirect_url = $status === 'Paid' ?
                get_permalink(get_option('zim_donations_success_page')) :
                get_permalink(get_option('zim_donations_cancel_page'));

            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Process return from Paynow
     */
    private function process_return($reference, $status) {
        global $wpdb;

        // Extract donation ID from reference
        $donation_id = str_replace('ZD_PAYNOW_', '', $reference);

        if (!is_numeric($donation_id)) {
            return;
        }

        // Update donation status
        $new_status = strtolower($status) === 'paid' ? 'completed' : 'failed';

        $wpdb->update(
            $wpdb->prefix . 'zim_donations',
            array(
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $donation_id),
            array('%s', '%s'),
            array('%d')
        );

        // Trigger completion actions
        if ($new_status === 'completed') {
            do_action('zim_donations_payment_completed', $donation_id, array('gateway' => 'paynow'));
        }
    }

    /**
     * Handle webhook
     */
    public function handle_webhook() {
        // Paynow doesn't use traditional webhooks, but result URL
        $data = $_POST;

        if (empty($data['reference']) || empty($data['status'])) {
            status_header(400);
            exit('Invalid data');
        }

        // Verify hash if provided
        if (isset($data['hash'])) {
            $received_hash = $data['hash'];
            unset($data['hash']);

            $string = '';
            foreach ($data as $value) {
                $string .= $value;
            }
            $string .= $this->integration_key;

            $calculated_hash = strtoupper(hash('sha512', $string));

            if ($received_hash !== $calculated_hash) {
                status_header(400);
                exit('Hash verification failed');
            }
        }

        $this->process_return($data['reference'], $data['status']);

        status_header(200);
        exit('OK');
    }

    /**
     * Generate payment reference
     */
    private function generate_reference($donation_id) {
        return 'ZD_PAYNOW_' . $donation_id . '_' . time();
    }

    /**
     * Format phone number
     */
    private function format_phone_number($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // Add country code if not present
        if (strlen($phone) === 9) {
            $phone = '263' . $phone;
        } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '263' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Get result URL
     */
    private function get_result_url() {
        return add_query_arg(array(
            'zim-donations-webhook' => '1',
            'gateway' => 'paynow'
        ), home_url('/'));
    }

    /**
     * Get return URL
     */
    private function get_return_url() {
        return add_query_arg(array(
            'paynow_return' => '1'
        ), home_url('/'));
    }

    /**
     * Get status message
     */
    private function get_status_message($status) {
        $messages = array(
            'paid' => __('Payment completed successfully!', 'zim-donations'),
            'cancelled' => __('Payment was cancelled.', 'zim-donations'),
            'failed' => __('Payment failed. Please try again.', 'zim-donations'),
            'pending' => __('Payment is being processed...', 'zim-donations')
        );

        return $messages[$status] ?? __('Unknown payment status.', 'zim-donations');
    }

    /**
     * Format currency amount
     */
    private function format_currency($amount, $currency) {
        $symbols = array(
            'USD' => '$',
            'ZIG' => 'ZIG',
            'EUR' => '€',
            'GBP' => '£'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format($amount, 2);
    }
}