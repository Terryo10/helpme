<?php
if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Form_Builder {
    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_form_scripts'));
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

            <form class="helpme-donation-form" data-form-id="<?php echo esc_attr($atts['form_id']); ?>" data-campaign-id="<?php echo esc_attr($atts['campaign_id']); ?>">
                <?php wp_nonce_field('helpme_donations_nonce', 'helpme_donations_nonce'); ?>
                
                <!-- Amount Selection -->
                <div class="form-section" id="amount-section">
                    <h4 class="section-title"><?php _e('Select Donation Amount', 'helpme-donations'); ?></h4>
                    
                    <div class="amount-selection">
                        <div class="amount-buttons">
                            <?php foreach ($amounts as $amount): ?>
                                <button type="button" class="amount-button" data-amount="<?php echo esc_attr($amount); ?>">
                                    <?php echo $this->format_currency($amount, $atts['currency']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="custom-amount">
                            <label for="custom-amount-input"><?php _e('Custom Amount:', 'helpme-donations'); ?></label>
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

                <!-- Personal Information -->
                <div class="form-section" id="personal-section">
                    <h4 class="section-title"><?php _e('Your Information', 'helpme-donations'); ?></h4>
                    
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
                            <label for="donor-phone"><?php _e('Phone Number', 'helpme-donations'); ?></label>
                            <input type="tel" id="donor-phone" name="donor_phone">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="donor-message"><?php _e('Message (Optional)', 'helpme-donations'); ?></label>
                        <textarea id="donor-message" name="donor_message" rows="3" placeholder="<?php _e('Share why you\'re donating...', 'helpme-donations'); ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="anonymous" value="1">
                            <span><?php _e('Make this donation anonymous', 'helpme-donations'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="form-section" id="payment-section">
                    <h4 class="section-title"><?php _e('Payment Method', 'helpme-donations'); ?></h4>
                    
                    <div class="payment-methods">
                        <div class="payment-method-notice">
                            <p><?php _e('Please configure payment gateways in the plugin settings to enable donations.', 'helpme-donations'); ?></p>
                            <?php if (current_user_can('manage_options')): ?>
                                <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings&tab=gateways'); ?>" class="configure-link">
                                    <?php _e('Configure Payment Gateways', 'helpme-donations'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="form-section" id="submit-section">
                    <button type="submit" class="submit-donation" disabled>
                        <span class="button-text"><?php _e('Complete Donation', 'helpme-donations'); ?></span>
                        <span class="button-spinner" style="display: none;"></span>
                    </button>
                    
                    <div class="form-messages"></div>
                </div>
            </form>
        </div>

        <style>
        .helpme-donations-form-wrapper {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-title {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
            font-size: 28px;
        }

        .form-description {
            text-align: center;
            margin-bottom: 30px;
            color: #666;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .amount-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .amount-button {
            background: #f8f9fa;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            padding: 15px 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .amount-button:hover,
        .amount-button.selected {
            background: #007cba;
            border-color: #007cba;
            color: white;
        }

        .custom-amount {
            margin-bottom: 20px;
        }

        .currency-input {
            display: flex;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            overflow: hidden;
        }

        .currency-symbol {
            background: #f8f9fa;
            padding: 12px 15px;
            border-right: 1px solid #e1e1e1;
            font-weight: bold;
        }

        .currency-input input {
            border: none;
            padding: 12px 15px;
            flex: 1;
            font-size: 16px;
        }

        .currency-input input:focus {
            outline: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007cba;
        }

        .required {
            color: #dc3545;
        }

        .recurring-options {
            margin-top: 20px;
        }

        .recurring-toggle {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .recurring-toggle input {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .recurring-interval select {
            max-width: 200px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-label input {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .payment-method-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }

        .configure-link {
            display: inline-block;
            background: #007cba;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }

        .configure-link:hover {
            background: #005a87;
            color: white;
        }

        .submit-donation {
            width: 100%;
            background: #007cba;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-donation:hover:not(:disabled) {
            background: #005a87;
        }

        .submit-donation:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .button-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .form-messages {
            margin-top: 20px;
        }

        .form-message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .form-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .helpme-donations-form-wrapper {
                margin: 10px;
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .amount-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#<?php echo esc_js($form_id); ?> .helpme-donation-form');
            const amountButtons = form.querySelectorAll('.amount-button');
            const customAmountInput = form.querySelector('#custom-amount-input');
            const selectedAmountInput = form.querySelector('#selected-amount');
            const submitButton = form.querySelector('.submit-donation');
            const recurringCheckbox = form.querySelector('input[name="is_recurring"]');
            const recurringInterval = form.querySelector('.recurring-interval');
            const messagesContainer = form.querySelector('.form-messages');

            // Amount selection
            amountButtons.forEach(button => {
                button.addEventListener('click', function() {
                    amountButtons.forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedAmountInput.value = this.dataset.amount;
                    customAmountInput.value = '';
                    checkFormValidity();
                });
            });

            // Custom amount input
            customAmountInput.addEventListener('input', function() {
                amountButtons.forEach(b => b.classList.remove('selected'));
                selectedAmountInput.value = this.value;
                checkFormValidity();
            });

            // Recurring options
            if (recurringCheckbox && recurringInterval) {
                recurringCheckbox.addEventListener('change', function() {
                    recurringInterval.style.display = this.checked ? 'block' : 'none';
                });
            }

            // Form validation
            function checkFormValidity() {
                const amount = parseFloat(selectedAmountInput.value);
                const name = form.querySelector('#donor-name').value.trim();
                const email = form.querySelector('#donor-email').value.trim();
                
                submitButton.disabled = !(amount > 0 && name && email);
            }

            // Listen for input changes
            form.addEventListener('input', checkFormValidity);

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                showMessage('Payment gateway configuration required. Please contact the site administrator.', 'error');
            });

            function showMessage(message, type) {
                messagesContainer.innerHTML = `<div class="form-message ${type}">${message}</div>`;
            }
        });
        </script>
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

    public function enqueue_form_scripts() {
        // Scripts are inline for now
    }
}