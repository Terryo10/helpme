<?php
if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Form_Builder
{

    /**
     * Payment gateways instance
     */
    private $payment_gateways;

    /**
     * Available payment gateways array
     */
    private $available_gateways = array();

    public function __construct()
    {
        $this->get_payment_gateways();
        $this->init_hooks();
    }

    /**
     * Get array of available payment gateways
     */
    private function get_payment_gateways()
    {
        // Check if payment gateways class exists
        if (class_exists('ZimDonations_Payment_Gateways')) {
            $this->payment_gateways = new ZimDonations_Payment_Gateways();
            $this->available_gateways = $this->payment_gateways->get_available_gateways();
        } else {
            $this->available_gateways = array();
        }

        return $this->available_gateways;
    }

    /**
     * Get available gateways (public method)
     */
    public function get_available_gateways()
    {
        return $this->available_gateways;
    }

    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_form_scripts'));
        add_action('wp_ajax_get_gateway_payment_form', array($this, 'ajax_get_gateway_payment_form'));
        add_action('wp_ajax_nopriv_get_gateway_payment_form', array($this, 'ajax_get_gateway_payment_form'));
    }

    public function enqueue_form_scripts()
    {
        wp_enqueue_style('helpme-donations-form', plugins_url('assets/css/form.css', dirname(__FILE__)));
        wp_enqueue_script('helpme-donations-form', plugins_url('assets/js/form.js', dirname(__FILE__)), array('jquery'), '1.0.0', true);

        // Enqueue Stripe JS if Stripe is enabled
        if ($this->is_gateway_enabled('stripe')) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), '3.0', true);
        }

        // Localize script with form data
        wp_localize_script('helpme-donations-form', 'helpmeDonations', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helpme_donations_nonce'),
            'currency_symbols' => $this->get_currency_symbols(),
            'stripe_publishable_key' => $this->get_stripe_publishable_key(),
            'stripe_secret_key' => $this->get_stripe_secret_key(),
            'i18n' => array(
                'continue' => __('Continue →', 'helpme-donations'),
                'choose_payment' => __('Choose Payment →', 'helpme-donations'),
                'enter_details' => __('Enter Details →', 'helpme-donations'),
                'process_payment' => __('Process Payment →', 'helpme-donations'),
                'processing' => __('Processing your payment...', 'helpme-donations'),
                'success' => __('Payment completed successfully!', 'helpme-donations'),
                'share_copied' => __('Share text copied to clipboard!', 'helpme-donations'),
                'anonymous_donor' => __('Anonymous Donor', 'helpme-donations'),
                'loading_payment_form' => __('Loading payment form...', 'helpme-donations'),
                'payment_form_error' => __('Error loading payment form. Please try again.', 'helpme-donations')
            )
        ));
    }

    /**
     * AJAX handler to get gateway-specific payment form
     */
    public function ajax_get_gateway_payment_form()
    {
        check_ajax_referer('helpme_donations_nonce', 'nonce');

        $gateway_id = sanitize_text_field($_POST['gateway_id']);
        $amount = floatval($_POST['amount']);
        $currency = sanitize_text_field($_POST['currency']);
        $donation_data = array(
            'amount' => $amount,
            'currency' => $currency,
            'donation_id' => sanitize_text_field($_POST['donation_id'] ?? ''),
            'donor_name' => sanitize_text_field($_POST['donor_name'] ?? ''),
            'donor_email' => sanitize_email($_POST['donor_email'] ?? ''),
            'campaign_id' => intval($_POST['campaign_id'] ?? 0)
        );

        $form_html = $this->get_gateway_payment_form($gateway_id, $donation_data);

        if ($form_html) {
            wp_send_json_success(array('form_html' => $form_html));
        } else {
            wp_send_json_error(array('message' => __('Failed to load payment form', 'helpme-donations')));
        }
    }

    /**
     * Get gateway-specific payment form
     */
    private function get_gateway_payment_form($gateway_id, $donation_data)
    {
        switch ($gateway_id) {
            case 'stripe':
                return $this->get_stripe_payment_form($donation_data);
            case 'paypal':
                return $this->get_paypal_payment_form($donation_data);
            case 'paynow':
                return $this->get_paynow_payment_form($donation_data);
            case 'inbucks':
                return $this->get_inbucks_payment_form($donation_data);
            case 'zimswitch':
                return $this->get_zimswitch_payment_form($donation_data);
            default:
                return $this->get_default_payment_form($donation_data);
        }
    }

    /**
     * Get Stripe payment form
     */
    private function get_stripe_payment_form($donation_data)
    {
        ob_start();
?>
        <div class="stripe-payment-form gateway-form">
            <div class="payment-method-info">
                <h5><?php _e('Credit/Debit Card Payment', 'helpme-donations'); ?></h5>
                <p><?php _e('Enter your card details below. Your payment is secured by Stripe.', 'helpme-donations'); ?></p>
            </div>

            <div class="stripe-elements-container">
                <div class="form-group">
                    <label for="stripe-card-element"><?php _e('Card Information', 'helpme-donations'); ?></label>
                    <div id="stripe-card-element" class="stripe-element">
                        <!-- Stripe Elements will create form elements here -->
                    </div>
                    <div id="stripe-card-errors" class="error-message" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="stripe-save-card" name="save_card" value="1">
                        <?php _e('Save payment information for future donations', 'helpme-donations'); ?>
                    </label>
                </div>
            </div>

            <div class="payment-actions">
                <!-- <button type="button" id="stripe-pay-button" class="gateway-pay-button">
                    <span class="button-text">
                        <?php printf(__('Pay %s', 'helpme-donations'), $this->format_currency($donation_data['amount'], $donation_data['currency'])); ?>
                    </span>
                    <span class="button-spinner" style="display: none;"></span>
                </button> -->
            </div>

            <div class="security-notice">
                <small><?php _e('Secured by Stripe. Your card information is encrypted and secure.', 'helpme-donations'); ?></small>
            </div>
        </div>

        <script>
            window.stripe_publishable_key = "<?php echo esc_attr(get_option('helpme_donations_stripe_live_publishable_key')) ?>";
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Stripe !== 'undefined' && helpmeDonations.stripe_publishable_key) {
                    const stripe = Stripe(helpmeDonations.stripe_publishable_key);
                    const elements = stripe.elements();

                    const cardElement = elements.create('card', {
                        style: {
                            base: {
                                fontSize: '16px',
                                color: '#424770',
                                '::placeholder': {
                                    color: '#aab7c4',
                                },
                            },
                        },
                    });

                    cardElement.mount('#stripe-card-element');

                    cardElement.on('change', function(event) {
                        const displayError = document.getElementById('stripe-card-errors');
                        if (event.error) {
                            displayError.textContent = event.error.message;
                            displayError.style.display = 'block';
                        } else {
                            displayError.style.display = 'none';
                        }
                    });

                    document.getElementById('stripe-pay-button').addEventListener('click', function() {
                        handleStripePayment(stripe, cardElement);
                    });
                }
            });

            function handleStripePayment(stripe, cardElement) {
                // Implementation for Stripe payment processing
                console.log('Processing Stripe payment...');
            }
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Get PayPal payment form
     */
    private function get_paypal_payment_form($donation_data)
    {
        ob_start();
    ?>
        <div class="paypal-payment-form gateway-form">
            <div class="payment-method-info">
                <h5><?php _e('PayPal Payment', 'helpme-donations'); ?></h5>
                <p><?php _e('You will be redirected to PayPal to complete your payment securely.', 'helpme-donations'); ?></p>
            </div>

            <div class="paypal-container">
                <div id="paypal-button-container"></div>
            </div>

            <div class="security-notice">
                <small><?php _e('Secured by PayPal. You can pay with your PayPal account or credit card.', 'helpme-donations'); ?></small>
            </div>
        </div>

        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($this->get_paypal_client_id()); ?>&currency=<?php echo esc_attr($donation_data['currency']); ?>"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof paypal !== 'undefined') {
                    paypal.Buttons({
                        createOrder: function(data, actions) {
                            // Implementation for PayPal order creation
                            console.log('Creating PayPal order...');
                        },
                        onApprove: function(data, actions) {
                            // Implementation for PayPal approval
                            console.log('PayPal payment approved...');
                        }
                    }).render('#paypal-button-container');
                }
            });
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Get Paynow payment form
     */
    private function get_paynow_payment_form($donation_data)
    {
        ob_start();
    ?>
        <div class="paynow-payment-form gateway-form">
            <div class="payment-method-info">
                <h5><?php _e('Mobile Money Payment', 'helpme-donations'); ?></h5>
                <p><?php _e('Pay using EcoCash or OneMoney. Enter your mobile number below.', 'helpme-donations'); ?></p>
            </div>

            <div class="paynow-method-selection">
                <div class="method-options">
                    <label class="method-option">
                        <input type="radio" name="paynow_method" value="ecocash" checked>
                        <div class="method-card">
                            <span class="method-icon">📱</span>
                            <span class="method-name"><?php _e('EcoCash', 'helpme-donations'); ?></span>
                        </div>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="paynow_method" value="onemoney">
                        <div class="method-card">
                            <span class="method-icon">💰</span>
                            <span class="method-name"><?php _e('OneMoney', 'helpme-donations'); ?></span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="paynow-phone"><?php _e('Mobile Number', 'helpme-donations'); ?></label>
                <div class="phone-input-group">
                    <span class="country-code">+263</span>
                    <input type="tel" id="paynow-phone" name="phone" placeholder="77 123 4567" required
                        maxlength="9" class="phone-input">
                </div>
                <small class="help-text"><?php _e('Enter your mobile number without the country code', 'helpme-donations'); ?></small>
            </div>

            <div class="payment-actions">
                <!-- <button type="button" id="paynow-pay-button" class="gateway-pay-button">
                    <span class="button-text">
                        <?php printf(__('Pay %s', 'helpme-donations'), $this->format_currency($donation_data['amount'], $donation_data['currency'])); ?>
                    </span>
                    <span class="button-spinner" style="display: none;"></span>
                </button> -->


            </div>

            <div class="security-notice">
                <small><?php _e('Secured by Paynow. You will receive a payment prompt on your phone.', 'helpme-donations'); ?></small>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('paynow-pay-button').addEventListener('click', function() {
                    const phone = document.getElementById('paynow-phone').value;
                    const method = document.querySelector('input[name="paynow_method"]:checked').value;

                    if (!phone || phone.length !== 9) {
                        alert('<?php _e("Please enter a valid 9-digit mobile number", "helpme-donations"); ?>');
                        return;
                    }

                    handlePaynowPayment(method, phone);
                });
            });

            function handlePaynowPayment(method, phone) {
                alert(method)
                console.log('Processing Paynow payment...');
                // Implementation for Paynow payment processing
            }
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Get InBucks payment form
     */
    private function get_inbucks_payment_form($donation_data)
    {
        ob_start();
    ?>
        <div class="inbucks-payment-form gateway-form">
            <div class="payment-method-info">
                <h5><?php _e('InBucks Mobile Wallet', 'helpme-donations'); ?></h5>
                <p><?php _e('Pay using your InBucks mobile wallet. Enter your registered phone number.', 'helpme-donations'); ?></p>
            </div>

            <div class="form-group">
                <label for="inbucks-phone"><?php _e('InBucks Phone Number', 'helpme-donations'); ?></label>
                <div class="phone-input-group">
                    <span class="country-code">+263</span>
                    <input type="tel" id="inbucks-phone" name="phone" placeholder="77 123 4567" required
                        pattern="[0-9]{9}" maxlength="9" class="phone-input">
                </div>
                <small class="help-text"><?php _e('Enter your InBucks registered phone number', 'helpme-donations'); ?></small>
            </div>

            <div class="payment-actions">
                <button type="button" id="inbucks-pay-button" class="gateway-pay-button">
                    <span class="button-text">
                        <?php printf(__('Pay %s', 'helpme-donations'), $this->format_currency($donation_data['amount'], $donation_data['currency'])); ?>
                    </span>
                    <span class="button-spinner" style="display: none;"></span>
                </button>
            </div>

            <div class="security-notice">
                <small><?php _e('Secured by InBucks. You will receive a payment notification on your phone.', 'helpme-donations'); ?></small>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('inbucks-pay-button').addEventListener('click', function() {
                    const phone = document.getElementById('inbucks-phone').value;

                    if (!phone || phone.length !== 9) {
                        alert('<?php _e("Please enter a valid 9-digit mobile number", "helpme-donations"); ?>');
                        return;
                    }

                    handleInBucksPayment(phone);
                });
            });

            function handleInBucksPayment(phone) {
                console.log('Processing InBucks payment...');
                // Implementation for InBucks payment processing
            }
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Get ZimSwitch payment form
     */
    private function get_zimswitch_payment_form($donation_data)
    {
        $banks = array(
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

        ob_start();
    ?>
        <div class="zimswitch-payment-form gateway-form">
            <div class="payment-method-info">
                <h5><?php _e('Bank Card Payment', 'helpme-donations'); ?></h5>
                <p><?php _e('Select your bank and you will be redirected to complete payment securely.', 'helpme-donations'); ?></p>
            </div>

            <div class="form-group">
                <label for="zimswitch-bank"><?php _e('Select Your Bank', 'helpme-donations'); ?></label>
                <select id="zimswitch-bank" name="bank_code" required class="bank-select">
                    <option value=""><?php _e('Choose your bank...', 'helpme-donations'); ?></option>
                    <?php foreach ($banks as $code => $name): ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="payment-summary">
                <div class="summary-item">
                    <span class="label"><?php _e('Amount:', 'helpme-donations'); ?></span>
                    <span class="value"><?php echo $this->format_currency($donation_data['amount'], $donation_data['currency']); ?></span>
                </div>
            </div>

            <div class="payment-actions">
                <button type="button" id="zimswitch-pay-button" class="gateway-pay-button">
                    <span class="button-text"><?php _e('Proceed to Bank', 'helpme-donations'); ?></span>
                    <span class="button-spinner" style="display: none;"></span>
                </button>
            </div>

            <div class="security-notice">
                <small><?php _e('Secured by ZimSwitch. Your payment will be processed through the banking network.', 'helpme-donations'); ?></small>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('zimswitch-pay-button').addEventListener('click', function() {
                    const bankCode = document.getElementById('zimswitch-bank').value;

                    if (!bankCode) {
                        alert('<?php _e("Please select your bank", "helpme-donations"); ?>');
                        return;
                    }

                    handleZimSwitchPayment(bankCode);
                });
            });

            function handleZimSwitchPayment(bankCode) {
                console.log('Processing ZimSwitch payment...');
                // Implementation for ZimSwitch payment processing
            }
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Get default payment form for unknown gateways
     */
    private function get_default_payment_form($donation_data)
    {
        ob_start();
    ?>
        <div class="default-payment-form gateway-form">
            <div class="payment-method-info">
                <h5><?php _e('Payment Processing', 'helpme-donations'); ?></h5>
                <p><?php _e('Click the button below to proceed with your payment.', 'helpme-donations'); ?></p>
            </div>

            <div class="payment-actions">
                <button type="button" id="default-pay-button" class="gateway-pay-button">
                    <span class="button-text">
                        <?php printf(__('Pay %s', 'helpme-donations'), $this->format_currency($donation_data['amount'], $donation_data['currency'])); ?>
                    </span>
                    <span class="button-spinner" style="display: none;"></span>
                </button>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('default-pay-button').addEventListener('click', function() {
                    console.log('Processing default payment...');
                    // Implementation for default payment processing
                });
            });
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Check if a specific gateway is enabled
     */
    private function is_gateway_enabled($gateway_id)
    {
        $enabled_gateways = get_option('helpme_donations_enabled_gateways', array());
        return in_array($gateway_id, $enabled_gateways);
    }

    /**
     * Get Stripe publishable key
     */
    private function get_stripe_publishable_key()
    {
        if (!$this->is_gateway_enabled('stripe')) {
            return '';
        }

        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_stripe_test_publishable_key', '') :
            get_option('helpme_donations_stripe_live_publishable_key', '');
    }
    private function get_stripe_secret_key()
    {
        if (!$this->is_gateway_enabled('stripe')) {
            return '';
        }

        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_stripe_test_secret_key', '') :
            get_option('helpme_donations_stripe_live_secret_key', '');
    }

    /**
     * Get PayPal client ID
     */
    private function get_paypal_client_id()
    {
        if (!$this->is_gateway_enabled('paypal')) {
            return '';
        }

        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_paypal_test_client_id', '') :
            get_option('helpme_donations_paypal_live_client_id', '');
    }

    public function render_form($atts)
    {
        $atts = shortcode_atts(array(
            'form_id' => 0,
            'campaign_id' => 0,
            'title' => 'Make a Donation',
            'description' => 'Your donation helps make a difference.',
            'amounts' => '10,25,50,100',
            'currency' => 'USD',
            'recurring' => 'true'
        ), $atts, 'helpme_donation_form');

        // Parse amounts
        $amounts = array_map('trim', explode(',', $atts['amounts']));
        $amounts = array_map('floatval', $amounts);

        ob_start();
        $this->render_form_html($atts, $amounts);
        return ob_get_clean();
    }

    private function render_form_html($atts, $amounts)
    {
        $form_id = 'helpme-donation-form-' . wp_rand(1000, 9999);
    ?>
        <div class="helpme-donations-form-wrapper" id="<?php echo esc_attr($form_id); ?>">
            <?php if (!empty($atts['title'])): ?>
                <h3 class="form-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>

            <?php if (!empty($atts['description'])): ?>
                <div class="form-description">
                    <?php echo wp_kses_post($atts['description']); ?>
                </div>
            <?php endif; ?>

            <!-- Step Progress Indicator -->
            <div class="step-progress">
                <div class="step-indicator">
                    <div class="step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label"><?php _e('Amount', 'helpme-donations'); ?></div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label"><?php _e('Details', 'helpme-donations'); ?></div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label"><?php _e('Payment', 'helpme-donations'); ?></div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label"><?php _e('Details', 'helpme-donations'); ?></div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" data-step="5">
                        <div class="step-number">5</div>
                        <div class="step-label"><?php _e('Process', 'helpme-donations'); ?></div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" data-step="6">
                        <div class="step-number">6</div>
                        <div class="step-label"><?php _e('Complete', 'helpme-donations'); ?></div>
                    </div>
                </div>
            </div>

            <form class="helpme-donation-form" data-form-id="<?php echo esc_attr($atts['form_id']); ?>" data-campaign-id="<?php echo esc_attr($atts['campaign_id']); ?>">
                <?php wp_nonce_field('helpme_donations_nonce', 'helpme_donations_nonce'); ?>

                <!-- Step 1: Amount Selection -->
                <div class="form-step active" data-step="1">
                    <div class="step-content">
                        <h4 class="step-title"><?php _e('Select Your Donation Amount', 'helpme-donations'); ?></h4>

                        <div class="amount-selection">
                            <div class="amount-buttons">
                                <?php foreach ($amounts as $amount): ?>
                                    <button type="button" class="amount-button" data-amount="<?php echo esc_attr($amount); ?>">
                                        <?php echo $this->format_currency($amount, $atts['currency']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="custom-amount">
                                <label for="custom-amount-input"><?php _e('Or enter a custom amount:', 'helpme-donations'); ?></label>
                                <div class="currency-input">
                                    <span class="currency-symbol"><?php echo $this->get_currency_symbol($atts['currency']); ?></span>
                                    <input type="number" id="custom-amount-input" name="custom_amount" placeholder="0.00" min="1" step="0.01">
                                </div>
                            </div>

                            <input type="hidden" name="amount" id="selected-amount" required>
                            <input type="hidden" name="currency" value="<?php echo esc_attr($atts['currency']); ?>">
                        </div>

                        <?php if ($atts['recurring'] === 'true'): ?>
                            <div class="recurring-options">
                                <label class="recurring-toggle">
                                    <input type="checkbox" name="is_recurring" value="1">
                                    <span><?php _e('Make this a recurring donation', 'helpme-donations'); ?></span>
                                </label>
                                <div class="recurring-interval" style="display: none;">
                                    <label><?php _e('Frequency:', 'helpme-donations'); ?></label>
                                    <select name="recurring_interval">
                                        <option value="monthly"><?php _e('Monthly', 'helpme-donations'); ?></option>
                                        <option value="quarterly"><?php _e('Quarterly', 'helpme-donations'); ?></option>
                                        <option value="yearly"><?php _e('Yearly', 'helpme-donations'); ?></option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Donor Information -->
                <div class="form-step" data-step="2">
                    <div class="step-content">
                        <h4 class="step-title"><?php _e('Your Information', 'helpme-donations'); ?></h4>

                        <div class="donor-options">
                            <label class="donor-option">
                                <input type="radio" name="donor_type" value="named" checked>
                                <div class="option-card">
                                    <strong><?php _e('I want to be recognized', 'helpme-donations'); ?></strong>
                                    <span><?php _e('Share my name with this donation', 'helpme-donations'); ?></span>
                                </div>
                            </label>

                            <label class="donor-option">
                                <input type="radio" name="donor_type" value="anonymous">
                                <div class="option-card">
                                    <strong><?php _e('Donate anonymously', 'helpme-donations'); ?></strong>
                                    <span><?php _e('Keep my donation private', 'helpme-donations'); ?></span>
                                </div>
                            </label>
                        </div>

                        <div class="donor-details">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="donor-name"><?php _e('Full Name', 'helpme-donations'); ?> <span class="required">*</span></label>
                                    <input type="text" id="donor-name" name="donor_name" required>
                                </div>

                                <div class="form-group">
                                    <label for="donor-email"><?php _e('Email Address', 'helpme-donations'); ?> <span class="required">*</span></label>
                                    <input type="email" id="donor-email" name="donor_email" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="donor-phone"><?php _e('Phone Number (Optional)', 'helpme-donations'); ?></label>
                                    <input type="tel" id="donor-phone" name="donor_phone">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="donor-message"><?php _e('Share why you\'re donating (Optional)', 'helpme-donations'); ?></label>
                                <textarea id="donor-message" name="donor_message" rows="3" placeholder="<?php _e('Your message will inspire others to donate...', 'helpme-donations'); ?>"></textarea>
                            </div>
                        </div>

                        <input type="hidden" name="anonymous" value="0">
                    </div>
                </div>

                <!-- Step 3: Payment Method -->
                <div class="form-step" data-step="3">
                    <div class="step-content">
                        <h4 class="step-title"><?php _e('Choose Payment Method', 'helpme-donations'); ?></h4>

                        <div class="payment-methods">
                            <?php if (!empty($this->available_gateways) && is_array($this->available_gateways)): ?>
                                <?php foreach ($this->available_gateways as $gateway): ?>
                                    <?php if (is_object($gateway) && isset($gateway->id)): ?>
                                        <label class="payment-method-option">
                                            <input type="radio" name="payment_gateway" value="<?php echo esc_attr($gateway->id); ?>" required>
                                            <div class="payment-method-card">
                                                <div class="payment-method-header">
                                                    <span class="payment-method-name"><?php echo esc_html($gateway->title ?? $gateway->name ?? $gateway->id); ?></span>
                                                    <div class="payment-method-icon">
                                                        <?php if ($gateway->id === 'stripe'): ?>💳<?php endif; ?>
                                                        <?php if ($gateway->id === 'paypal'): ?>🅿️<?php endif; ?>
                                                        <?php if ($gateway->id === 'paynow'): ?>📱<?php endif; ?>
                                                        <?php if ($gateway->id === 'inbucks'): ?>💰<?php endif; ?>
                                                        <?php if ($gateway->id === 'zimswitch'): ?>🏦<?php endif; ?>
                                                    </div>
                                                </div>
                                                <span class="payment-method-description"><?php echo esc_html($gateway->description ?? ''); ?></span>
                                            </div>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="payment-method-notice">
                                    <p><?php _e('Please configure payment gateways in the plugin settings to enable donations.', 'helpme-donations'); ?></p>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings&tab=gateways'); ?>" class="configure-link">
                                            <?php _e('Configure Payment Gateways', 'helpme-donations'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Payment Details -->
                <div class="form-step" data-step="4">
                    <div class="step-content">
                        <h4 class="step-title"><?php _e('Payment Details', 'helpme-donations'); ?></h4>

                        <!-- Gateway-specific payment forms will be loaded here -->
                        <div id="gateway-payment-container">
                            <div class="payment-form-placeholder">
                                <div class="loading-message">
                                    <div class="loading-spinner"></div>
                                    <p><?php _e('Loading payment form...', 'helpme-donations'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Payment Processing -->
                <div class="form-step" data-step="5">
                    <div class="step-content">
                        <h4 class="step-title"><?php _e('Complete Your Payment', 'helpme-donations'); ?></h4>

                        <div class="donation-summary">
                            <h5><?php _e('Donation Summary', 'helpme-donations'); ?></h5>
                            <div class="summary-row">
                                <span><?php _e('Amount:', 'helpme-donations'); ?></span>
                                <span class="summary-amount"></span>
                            </div>
                            <div class="summary-row recurring-summary" style="display: none;">
                                <span><?php _e('Frequency:', 'helpme-donations'); ?></span>
                                <span class="summary-frequency"></span>
                            </div>
                            <div class="summary-row">
                                <span><?php _e('Donor:', 'helpme-donations'); ?></span>
                                <span class="summary-donor"></span>
                            </div>
                            <div class="summary-row">
                                <span><?php _e('Payment Method:', 'helpme-donations'); ?></span>
                                <span class="summary-gateway"></span>
                            </div>
                        </div>

                        <div class="payment-form-container">
                            <!-- Gateway-specific payment form will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Step 6: Completion -->
                <div class="form-step" data-step="6">
                    <div class="step-content">
                        <div class="completion-message">
                            <div class="success-icon">✅</div>
                            <h4 class="completion-title"><?php _e('Thank You for Your Donation!', 'helpme-donations'); ?></h4>
                            <p class="completion-text"><?php _e('Your generosity makes a real difference. You will receive a confirmation email shortly.', 'helpme-donations'); ?></p>

                            <div class="completion-details">
                                <div class="detail-row">
                                    <span><?php _e('Transaction ID:', 'helpme-donations'); ?></span>
                                    <span class="transaction-id"></span>
                                </div>
                                <div class="detail-row">
                                    <span><?php _e('Amount:', 'helpme-donations'); ?></span>
                                    <span class="final-amount"></span>
                                </div>
                            </div>

                            <div class="completion-actions">
                                <button type="button" class="share-donation"><?php _e('Share Your Impact', 'helpme-donations'); ?></button>
                                <button type="button" class="new-donation"><?php _e('Make Another Donation', 'helpme-donations'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-messages"></div>

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" class="nav-button prev-button" style="display: none;">
                        <span><?php _e('← Previous', 'helpme-donations'); ?></span>
                    </button>
                    <button type="button" class="nav-button next-button">
                        <span><?php _e('Continue →', 'helpme-donations'); ?></span>
                    </button>
                </div>

            </form>
        </div>
<?php
    }

    private function format_currency($amount, $currency)
    {
        $symbols = array(
            'USD' => '$',
            'ZIG' => 'ZiG',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format($amount, 0);
    }

    private function get_currency_symbol($currency)
    {
        $symbols = array(
            'USD' => '$',
            'ZIG' => 'ZiG',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R'
        );

        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }

    private function get_currency_symbols()
    {
        return array(
            'USD' => '$',
            'ZIG' => 'ZiG',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R'
        );
    }
}
