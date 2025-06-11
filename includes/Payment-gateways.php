<?php
/**
 * Payment Gateways Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/stripe.php';
require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/paynow.php';
require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/class-inbucks.php';
require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/class-zimswitch.php';

class ZimDonations_Payment_Gateways {

    /**
     * Available payment gateways
     */
    private $gateways = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_gateways();
        $this->init_hooks();
    }

    /**
     * Initialize payment gateways
     */
    private function init_gateways() {
        $this->gateways = array(
            'stripe' => new ZimDonations_Gateway_Stripe(),
            'paypal' => new ZimDonations_Gateway_PayPal(),
            'paynow' => new ZimDonations_Gateway_Paynow(),
            'inbucks' => new ZimDonations_Gateway_InBucks(),
            'zimswitch' => new HelpMeDonations_Gateway_ZimSwitch()
        );

        // Filter available gateways
        $this->gateways = apply_filters('zim_donations_payment_gateways', $this->gateways);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_get_gateway_form', array($this, 'ajax_get_gateway_form'));
        add_action('wp_ajax_nopriv_get_gateway_form', array($this, 'ajax_get_gateway_form'));
    }

    /**
     * Get available gateways
     */
    public function get_available_gateways() {
        $available = array();
        $enabled_gateways = get_option('helpme_donations_enabled_gateways', array());


        foreach ($this->gateways as $gateway) {
            if(in_array($gateway->id, $enabled_gateways)){

                $available[] = $gateway;
            }

        
        }

        return $available;
    }

    /**
     * Get gateway by ID
     */
    public function get_gateway($gateway_id) {
        if (isset($this->gateways[$gateway_id])) {
            return $this->gateways[$gateway_id];
        }
        return false;
    }

    /**
     * Process donation
     */
    public function process_donation($donation_data) {
        try {
            // Validate donation data
            $validation = $this->validate_donation_data($donation_data);
            if (!$validation['valid']) {
                return array(
                    'success' => false,
                    'message' => $validation['message']
                );
            }

            // Get the gateway
            $gateway = $this->get_gateway($donation_data['gateway']);
            if (!$gateway) {
                return array(
                    'success' => false,
                    'message' => __('Invalid payment gateway selected.', 'zim-donations')
                );
            }

            // Check if gateway is available
            if (!$gateway->is_available()) {
                return array(
                    'success' => false,
                    'message' => __('Selected payment gateway is not available.', 'zim-donations')
                );
            }

            // Create donation record
            $donation_id = $this->create_donation_record($donation_data);
            if (!$donation_id) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create donation record.', 'zim-donations')
                );
            }

            // Add donation ID to data
            $donation_data['donation_id'] = $donation_id;

            // Process payment through gateway
            $result = $gateway->process_payment($donation_data);

            // Update donation record with result
            $this->update_donation_record($donation_id, $result);

            // Send notifications if successful
            if ($result['success']) {
                $this->send_notifications($donation_id, $donation_data);
            }

            return $result;

        } catch (Exception $e) {
            error_log('ZimDonations Payment Error: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => __('An error occurred while processing your donation. Please try again.', 'zim-donations')
            );
        }
    }

    /**
     * Validate donation data
     */
    private function validate_donation_data($data) {
        $errors = array();

        // Required fields
        $required_fields = array(
            'amount' => __('Donation amount is required.', 'zim-donations'),
            'currency' => __('Currency is required.', 'zim-donations'),
            'gateway' => __('Payment method is required.', 'zim-donations'),
            'donor_email' => __('Email address is required.', 'zim-donations'),
            'donor_name' => __('Name is required.', 'zim-donations')
        );

        foreach ($required_fields as $field => $message) {
            if (empty($data[$field])) {
                $errors[] = $message;
            }
        }

        // Validate amount
        if (!empty($data['amount'])) {
            $min_amount = get_option('zim_donations_minimum_amount', 1);
            $max_amount = get_option('zim_donations_maximum_amount', 10000);

            if ($data['amount'] < $min_amount) {
                $errors[] = sprintf(
                    __('Minimum donation amount is %s.', 'zim-donations'),
                    $this->format_currency($min_amount, $data['currency'])
                );
            }

            if ($data['amount'] > $max_amount) {
                $errors[] = sprintf(
                    __('Maximum donation amount is %s.', 'zim-donations'),
                    $this->format_currency($max_amount, $data['currency'])
                );
            }
        }

        // Validate email
        if (!empty($data['donor_email']) && !is_email($data['donor_email'])) {
            $errors[] = __('Please enter a valid email address.', 'zim-donations');
        }

        // Validate currency
        $supported_currencies = array('USD', 'ZIG', 'EUR', 'GBP');
        if (!empty($data['currency']) && !in_array($data['currency'], $supported_currencies)) {
            $errors[] = __('Unsupported currency selected.', 'zim-donations');
        }

        if (empty($errors)) {
            return array('valid' => true);
        } else {
            return array(
                'valid' => false,
                'message' => implode(' ', $errors)
            );
        }
    }

    /**
     * Create donation record
     */
    private function create_donation_record($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'zim_donations';

        $donation_data = array(
            'campaign_id' => intval($data['campaign_id']),
            'form_id' => intval($data['form_id']),
            'donor_email' => sanitize_email($data['donor_email']),
            'donor_name' => sanitize_text_field($data['donor_name']),
            'donor_phone' => sanitize_text_field($data['donor_phone'] ?? ''),
            'donor_address' => sanitize_textarea_field($data['donor_address'] ?? ''),
            'amount' => floatval($data['amount']),
            'currency' => sanitize_text_field($data['currency']),
            'gateway' => sanitize_text_field($data['gateway']),
            'transaction_id' => $this->generate_transaction_id(),
            'status' => 'pending',
            'is_recurring' => $data['is_recurring'] ? 1 : 0,
            'recurring_interval' => sanitize_text_field($data['recurring_interval'] ?? ''),
            'anonymous' => $data['anonymous'] ? 1 : 0,
            'donor_message' => sanitize_textarea_field($data['donor_message'] ?? ''),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table, $donation_data);

        if ($result === false) {
            error_log('Failed to create donation record: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update donation record
     */
    private function update_donation_record($donation_id, $result) {
        global $wpdb;

        $table = $wpdb->prefix . 'zim_donations';

        $update_data = array(
            'status' => $result['success'] ? 'completed' : 'failed',
            'gateway_transaction_id' => $result['transaction_id'] ?? '',
            'gateway_response' => json_encode($result),
            'updated_at' => current_time('mysql')
        );

        if ($result['success'] && !empty($result['next_payment_date'])) {
            $update_data['next_payment_date'] = $result['next_payment_date'];
        }

        $wpdb->update(
            $table,
            $update_data,
            array('id' => $donation_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        // Update donor record
        $this->update_donor_record($donation_id);

        // Update campaign progress
        if ($result['success']) {
            $this->update_campaign_progress($donation_id);
        }
    }

    /**
     * Update donor record
     */
    private function update_donor_record($donation_id) {
        global $wpdb;

        // Get donation details
        $donation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_donations WHERE id = %d",
            $donation_id
        ));

        if (!$donation || $donation->status !== 'completed') {
            return;
        }

        $donors_table = $wpdb->prefix . 'zim_donors';

        // Check if donor exists
        $existing_donor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$donors_table} WHERE email = %s",
            $donation->donor_email
        ));

        if ($existing_donor) {
            // Update existing donor
            $wpdb->query($wpdb->prepare(
                "UPDATE {$donors_table} SET 
                total_donated = total_donated + %f,
                donation_count = donation_count + 1,
                last_donation_date = %s,
                updated_at = %s
                WHERE email = %s",
                $donation->amount,
                $donation->created_at,
                current_time('mysql'),
                $donation->donor_email
            ));
        } else {
            // Create new donor record
            $wpdb->insert($donors_table, array(
                'email' => $donation->donor_email,
                'name' => $donation->donor_name,
                'phone' => $donation->donor_phone,
                'address' => $donation->donor_address,
                'total_donated' => $donation->amount,
                'donation_count' => 1,
                'first_donation_date' => $donation->created_at,
                'last_donation_date' => $donation->created_at,
                'created_at' => current_time('mysql')
            ));
        }
    }

    /**
     * Update campaign progress
     */
    private function update_campaign_progress($donation_id) {
        global $wpdb;

        $donation = $wpdb->get_row($wpdb->prepare(
            "SELECT campaign_id, amount FROM {$wpdb->prefix}zim_donations WHERE id = %d",
            $donation_id
        ));

        if (!$donation || !$donation->campaign_id) {
            return;
        }

        // Trigger campaign progress update
        do_action('zim_donations_campaign_donation_received', $donation->campaign_id, $donation->amount);
    }

    /**
     * Send notifications
     */
    private function send_notifications($donation_id, $donation_data) {
        // Send donor confirmation email
        $this->send_donor_confirmation($donation_id, $donation_data);

        // Send admin notification
        if (get_option('zim_donations_email_notifications', 1)) {
            $this->send_admin_notification($donation_id, $donation_data);
        }

        // Trigger custom notifications
        do_action('zim_donations_after_successful_donation', $donation_id, $donation_data);
    }

    /**
     * Send donor confirmation email
     */
    private function send_donor_confirmation($donation_id, $donation_data) {
        $to = $donation_data['donor_email'];
        $subject = sprintf(
            __('Thank you for your donation - %s', 'zim-donations'),
            get_bloginfo('name')
        );

        $message = $this->get_donor_email_template($donation_id, $donation_data);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send admin notification
     */
    private function send_admin_notification($donation_id, $donation_data) {
        $admin_email = get_option('zim_donations_admin_email', get_option('admin_email'));

        $subject = sprintf(
            __('New donation received - %s', 'zim-donations'),
            $this->format_currency($donation_data['amount'], $donation_data['currency'])
        );

        $message = sprintf(
            __('A new donation has been received:

Donor: %s <%s>
Amount: %s
Payment Method: %s
Transaction ID: %s
Date: %s

View donation details in the admin dashboard.', 'zim-donations'),
            $donation_data['donor_name'],
            $donation_data['donor_email'],
            $this->format_currency($donation_data['amount'], $donation_data['currency']),
            ucfirst($donation_data['gateway']),
            $donation_data['donation_id'],
            current_time('mysql')
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Get donor email template
     */
    private function get_donor_email_template($donation_id, $donation_data) {
        $template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #333;">Thank you for your generous donation!</h2>
            
            <p>Dear ' . esc_html($donation_data['donor_name']) . ',</p>
            
            <p>Thank you for your donation of <strong>' . $this->format_currency($donation_data['amount'], $donation_data['currency']) . '</strong>. Your contribution makes a real difference.</p>
            
            <div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0;">Donation Details</h3>
                <p><strong>Amount:</strong> ' . $this->format_currency($donation_data['amount'], $donation_data['currency']) . '</p>
                <p><strong>Transaction ID:</strong> ' . esc_html($donation_data['donation_id']) . '</p>
                <p><strong>Date:</strong> ' . current_time('F j, Y g:i a') . '</p>
                <p><strong>Payment Method:</strong> ' . ucfirst($donation_data['gateway']) . '</p>';

        if (!empty($donation_data['campaign_id'])) {
            $campaign = $this->get_campaign($donation_data['campaign_id']);
            if ($campaign) {
                $template .= '<p><strong>Campaign:</strong> ' . esc_html($campaign->title) . '</p>';
            }
        }

        $template .= '</div>
            
            <p>This email serves as your donation receipt. Please keep it for your records.</p>
            
            <p>If you have any questions about your donation, please don\'t hesitate to contact us.</p>
            
            <p>With gratitude,<br>' . esc_html(get_bloginfo('name')) . '</p>
        </div>';

        return apply_filters('zim_donations_donor_email_template', $template, $donation_id, $donation_data);
    }

    /**
     * Handle payment webhooks
     */
    public function handle_webhook($gateway_id) {
        $gateway = $this->get_gateway($gateway_id);

        if ($gateway && method_exists($gateway, 'handle_webhook')) {
            $gateway->handle_webhook();
        } else {
            status_header(404);
            exit;
        }
    }

    /**
     * AJAX get gateway form
     */
    public function ajax_get_gateway_form() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        $gateway_id = sanitize_text_field($_POST['gateway']);
        $amount = floatval($_POST['amount']);
        $currency = sanitize_text_field($_POST['currency']);

        $gateway = $this->get_gateway($gateway_id);

        if (!$gateway) {
            wp_send_json_error(__('Invalid gateway selected.', 'zim-donations'));
        }

        $form_html = $gateway->get_payment_form($amount, $currency);

        wp_send_json_success(array(
            'form_html' => $form_html
        ));
    }

    /**
     * Generate unique transaction ID
     */
    private function generate_transaction_id() {
        return 'ZD_' . time() . '_' . wp_rand(1000, 9999);
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