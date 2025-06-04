<?php
if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Form_Builder {
    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_save_donation_form', array($this, 'save_form'));
        add_action('wp_ajax_load_donation_form', array($this, 'load_form'));
        add_action('wp_ajax_delete_donation_form', array($this, 'delete_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_form_scripts'));
    }

    public function render_form($atts) {
        $form_id = intval($atts['form_id']);
        if ($form_id > 0) {
            $form_config = $this->get_form_config($form_id);
        } else {
            $form_config = $this->get_default_form_config($atts);
        }
        if (!$form_config) {
            return '<p>' . __('Form not found.', 'zim-donations') . '</p>';
        }
        ob_start();
        $this->render_form_html($form_config, $atts);
        return ob_get_clean();
    }

    private function get_form_config($form_id) {
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_forms WHERE id = %d AND status = 'active'",
            $form_id
        ));
        if (!$form) {
            return false;
        }
        return array(
            'id' => $form->id,
            'name' => $form->name,
            'description' => $form->description,
            'fields' => json_decode($form->form_fields, true),
            'styling' => json_decode($form->styling, true),
            'settings' => json_decode($form->settings, true)
        );
    }

    private function get_default_form_config($atts) {
        $amounts = explode(',', $atts['amounts']);
        $amounts = array_map('trim', $amounts);
        $amounts = array_map('floatval', $amounts);
        return array(
            'id' => 0,
            'name' => 'Default Form',
            'description' => $atts['description'],
            'fields' => array(
                'donor_info' => array(
                    'name' => array('required' => true, 'label' => __('Full Name', 'zim-donations')),
                    'email' => array('required' => true, 'label' => __('Email Address', 'zim-donations')),
                    'phone' => array('required' => false, 'label' => __('Phone Number', 'zim-donations')),
                    'address' => array('required' => false, 'label' => __('Address', 'zim-donations'))
                ),
                'donation_info' => array(
                    'amount' => array('required' => true, 'type' => 'amount', 'amounts' => $amounts),
                    'currency' => array('required' => true, 'default' => $atts['currency']),
                    'recurring' => array('required' => false, 'enabled' => $atts['recurring'] === 'true'),
                    'anonymous' => array('required' => false, 'enabled' => true),
                    'message' => array('required' => false, 'label' => __('Message (Optional)', 'zim-donations'))
                )
            ),
            'styling' => array(
                'theme' => 'default',
                'primary_color' => '#0073aa',
                'border_radius' => '4px',
                'font_family' => 'inherit'
            ),
            'settings' => array(
                'campaign_id' => intval($atts['campaign_id']),
                'title' => $atts['title'],
                'show_progress' => true,
                'allow_comments' => true,
                'terms_required' => false
            )
        );
    }

    private function render_form_html($config, $atts) {
        $form_id = 'zim-donation-form-' . ($config['id'] ?: wp_rand(1000, 9999));
        $campaign_id = $config['settings']['campaign_id'] ?? 0;
        $campaign = null;
        if ($campaign_id > 0) {
            $campaign = $this->get_campaign($campaign_id);
        }
        ?>
        <div class="zim-donations-form-wrapper" id="<?php echo esc_attr($form_id); ?>">
            <?php if (!empty($config['settings']['title'])): ?>
                <h3 class="form-title"><?php echo esc_html($config['settings']['title']); ?></h3>
            <?php endif; ?>
            <?php if (!empty($config['description'])): ?>
                <div class="form-description">
                    <?php echo wp_kses_post($config['description']); ?>
                </div>
            <?php endif; ?>
            <?php if ($campaign && $config['settings']['show_progress']): ?>
                <div class="campaign-progress-wrapper">
                    <?php echo $this->render_campaign_progress($campaign); ?>
                </div>
            <?php endif; ?>
            <form class="zim-donation-form" data-form-id="<?php echo esc_attr($config['id']); ?>" data-campaign-id="<?php echo esc_attr($campaign_id); ?>">
                <?php wp_nonce_field('zim_donations_nonce', 'zim_donations_nonce'); ?>
                <!-- Step 1: Donation Amount -->
                <div class="form-step" id="step-amount" data-step="1">
                    <h4 class="step-title"><?php _e('Select Donation Amount', 'zim-donations'); ?></h4>
                    <div class="amount-selection">
                        <?php $this->render_amount_selection($config['fields']['donation_info']['amount']); ?>
                    </div>
                    <?php if ($config['fields']['donation_info']['recurring']['enabled']): ?>
                        <div class="recurring-options">
                            <label class="recurring-toggle">
                                <input type="checkbox" name="is_recurring" value="1">
                                <span class="toggle-text"><?php _e('Make this a recurring donation', 'zim-donations'); ?></span>
                            </label>
                            <div class="recurring-interval hidden">
                                <label><?php _e('Frequency:', 'zim-donations'); ?></label>
                                <select name="recurring_interval">
                                    <option value="monthly"><?php _e('Monthly', 'zim-donations'); ?></option>
                                    <option value="quarterly"><?php _e('Quarterly', 'zim-donations'); ?></option>
                                    <option value="yearly"><?php _e('Yearly', 'zim-donations'); ?></option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="step-navigation">
                        <button type="button" class="btn btn-primary next-step" data-next="2">
                            <?php _e('Continue', 'zim-donations'); ?>
                        </button>
                    </div>
                </div>
                <!-- Step 2: Personal Information -->
                <div class="form-step hidden" id="step-personal" data-step="2">
                    <h4 class="step-title"><?php _e('Your Information', 'zim-donations'); ?></h4>
                    <div class="form-fields">
                        <?php $this->render_donor_fields($config['fields']['donor_info']); ?>
                        <?php if ($config['fields']['donation_info']['anonymous']['enabled']): ?>
                            <div class="field-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="anonymous" value="1">
                                    <span><?php _e('Make this donation anonymous', 'zim-donations'); ?></span>
                                </label>
                            </div>
                        <?php endif; ?>
                        <?php if ($config['settings']['allow_comments']): ?>
                            <div class="field-group">
                                <label for="donor_message"><?php echo esc_html($config['fields']['donation_info']['message']['label']); ?></label>
                                <textarea name="donor_message" id="donor_message" rows="3" placeholder="<?php _e('Share why you\'re donating (optional)', 'zim-donations'); ?>"></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="step-navigation">
                        <button type="button" class="btn btn-secondary prev-step" data-prev="1">
                            <?php _e('Back', 'zim-donations'); ?>
                        </button>
                        <button type="button" class="btn btn-primary next-step" data-next="3">
                            <?php _e('Continue', 'zim-donations'); ?>
                        </button>
                    </div>
                </div>
                <!-- Step 3: Payment Method -->
                <div class="form-step hidden" id="step-payment" data-step="3">
                    <h4 class="step-title"><?php _e('Choose Payment Method', 'zim-donations'); ?></h4>
                    <div class="payment-methods">
                        <?php $this->render_payment_methods(); ?>
                    </div>
                    <div class="payment-form-container">
                        <!-- Payment gateway forms will be loaded here -->
                    </div>
                    <?php if ($config['settings']['terms_required']): ?>
                        <div class="terms-agreement">
                            <label class="checkbox-label required">
                                <input type="checkbox" name="agree_terms" value="1" required>
                                <span>
                                    <?php
                                    printf(
                                        __('I agree to the <a href="%s" target="_blank">Terms & Conditions</a>', 'zim-donations'),
                                        get_permalink(get_option('zim_donations_terms_page'))
                                    );
                                    ?>
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="step-navigation">
                        <button type="button" class="btn btn-secondary prev-step" data-prev="2">
                            <?php _e('Back', 'zim-donations'); ?>
                        </button>
                        <button type="submit" class="btn btn-primary submit-donation" disabled>
                            <span class="button-text"><?php _e('Complete Donation', 'zim-donations'); ?></span>
                            <span class="button-spinner hidden"></span>
                        </button>
                    </div>
                </div>
                <!-- Step 4: Confirmation -->
                <div class="form-step hidden" id="step-confirmation" data-step="4">
                    <div class="confirmation-content">
                        <!-- Confirmation content will be loaded here -->
                    </div>
                </div>
            </form>
            <style><?php include __DIR__ . '/form-styles.php'; ?></style>
            <script><?php include __DIR__ . '/form-scripts.php'; ?></script>
        </div>
        <?php
    }

    private function render_amount_selection($config) {
        $amounts = $config['amounts'] ?? array(10, 25, 50, 100);
        foreach ($amounts as $amount) {
            echo '<div class="amount-option">';
            echo '<input type="radio" id="amount_' . $amount . '" name="amount" value="' . $amount . '">';
            echo '<label for="amount_' . $amount . '">' . $this->format_currency($amount, 'USD') . '</label>';
            echo '</div>';
        }
        // Custom amount option
        echo '<div class="amount-option custom-amount">';
        echo '<input type="number" class="custom-amount-input" placeholder="' . __('Enter custom amount', 'zim-donations') . '" min="1" step="0.01">';
        echo '</div>';
    }

    private function render_donor_fields($fields) {
        foreach ($fields as $field_name => $field_config) {
            $required = $field_config['required'] ?? false;
            $label = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_name));
            echo '<div class="field-group' . ($required ? ' required' : '') . '">';
            echo '<label for="donor_' . $field_name . '">' . esc_html($label) . '</label>';
            switch ($field_name) {
                case 'email':
                    echo '<input type="email" id="donor_' . $field_name . '" name="donor_' . $field_name . '"' . ($required ? ' required' : '') . '>';
                    break;
                case 'phone':
                    echo '<input type="tel" id="donor_' . $field_name . '" name="donor_' . $field_name . '"' . ($required ? ' required' : '') . '>';
                    break;
                case 'address':
                    echo '<textarea id="donor_' . $field_name . '" name="donor_' . $field_name . '" rows="3"' . ($required ? ' required' : '') . '></textarea>';
                    break;
                default:
                    echo '<input type="text" id="donor_' . $field_name . '" name="donor_' . $field_name . '"' . ($required ? ' required' : '') . '>';
            }
            echo '</div>';
        }
    }

    private function render_payment_methods() {
        $gateways = helpme_donations()->payment_gateways->get_available_gateways();
        foreach ($gateways as $gateway_id => $gateway) {
            echo '<div class="payment-method" data-gateway="' . esc_attr($gateway_id) . '">';
            echo '<input type="radio" name="payment_method" value="' . esc_attr($gateway_id) . '" id="gateway_' . $gateway_id . '">';
            echo '<div class="payment-method-content">';
            echo '<div class="payment-method-name">' . esc_html($gateway->title) . '</div>';
            echo '<div class="payment-method-description">' . esc_html($gateway->description) . '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    private function render_campaign_progress($campaign) {
        $raised = $this->get_campaign_raised_amount($campaign->id);
        $goal = floatval($campaign->goal_amount);
        $percentage = $goal > 0 ? min(100, ($raised / $goal) * 100) : 0;
        ob_start();
        ?>
        <div class="campaign-progress">
            <h4><?php echo esc_html($campaign->title); ?></h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            <div class="progress-stats">
                <span><?php echo $this->format_currency($raised, $campaign->currency); ?> raised</span>
                <?php if ($goal > 0): ?>
                    <span><?php echo round($percentage, 1); ?>% of <?php echo $this->format_currency($goal, $campaign->currency); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_campaign_raised_amount($campaign_id) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}zim_donations WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        return floatval($result);
    }

    private function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_campaigns WHERE id = %d",
            $campaign_id
        ));
    }

    public function enqueue_form_scripts() {
        if (!is_admin()) {
            wp_enqueue_style(
                'helpme-donations-forms',
                HELPME_DONATIONS_PLUGIN_URL . 'assets/css/forms.css',
                array(),
                HELPME_DONATIONS_VERSION
            );
            wp_enqueue_script(
                'helpme-donations-forms',
                HELPME_DONATIONS_PLUGIN_URL . 'assets/js/forms.js',
                array('jquery'),
                HELPME_DONATIONS_VERSION,
                true
            );
        }
    }

    private function format_currency($amount, $currency) {
        if (!class_exists('ZimDonations_Currency_Manager')) {
            require_once dirname(__FILE__) . '/class-currency-manager.php';
        }
        $currency_manager = new ZimDonations_Currency_Manager();
        return $currency_manager->format_currency($amount, $currency);
    }

    // Add any other methods (save_form, load_form, delete_form, etc.) as needed
    // ...
}