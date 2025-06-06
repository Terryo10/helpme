<?php
if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Form_Builder {
    
    /**
     * Payment gateways instance
     */
    private $payment_gateways;

    /**
     * Available payment gateways array
     */
    private $available_gateways = array();

    public function __construct() {
        $this->get_payment_gateways();
        $this->init_hooks();
    }

    /**
     * Get array of available payment gateways
     */
    private function get_payment_gateways() {
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
    public function get_available_gateways() {
        return $this->available_gateways;
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_form_scripts'));
    }

    public function enqueue_form_scripts() {
        wp_enqueue_style('helpme-donations-form', plugins_url('assets/css/form.css', dirname(__FILE__)));
        wp_enqueue_script('helpme-donations-form', plugins_url('assets/js/form.js', dirname(__FILE__)), array('jquery'), '1.0.0', true);
        
        // Localize script with form data
        wp_localize_script('helpme-donations-form', 'helpmeDonations', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helpme_donations_nonce'),
            'currency_symbols' => $this->get_currency_symbols(),
            'i18n' => array(
                'continue' => __('Continue →', 'helpme-donations'),
                'choose_payment' => __('Choose Payment →', 'helpme-donations'),
                'process_payment' => __('Process Payment →', 'helpme-donations'),
                'processing' => __('Processing your payment...', 'helpme-donations'),
                'success' => __('Payment completed successfully!', 'helpme-donations'),
                'share_copied' => __('Share text copied to clipboard!', 'helpme-donations'),
                'anonymous_donor' => __('Anonymous Donor', 'helpme-donations')
            )
        ));
    }

    public function render_form($atts) {
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

    private function render_form_html($atts, $amounts) {
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
                        <div class="step-label"><?php _e('Process', 'helpme-donations'); ?></div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step" data-step="5">
                        <div class="step-number">5</div>
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
                                                    <div class="payment-method-icon">💳</div>
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

                <!-- Step 4: Payment Processing -->
                <div class="form-step" data-step="4">
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

                <!-- Step 5: Completion -->
                <div class="form-step" data-step="5">
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

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" class="nav-button prev-button" style="display: none;">
                        <span><?php _e('← Previous', 'helpme-donations'); ?></span>
                    </button>
                    <button type="button" class="nav-button next-button">
                        <span><?php _e('Continue →', 'helpme-donations'); ?></span>
                    </button>
                </div>

                <div class="form-messages"></div>
            </form>
        </div>
        <?php
    }

    private function format_currency($amount, $currency) {
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

    private function get_currency_symbol($currency) {
        $symbols = array(
            'USD' => '$',
            'ZIG' => 'ZiG',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R'
        );

        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }

    private function get_currency_symbols() {
        return array(
            'USD' => '$',
            'ZIG' => 'ZiG',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R'
        );
    }
}