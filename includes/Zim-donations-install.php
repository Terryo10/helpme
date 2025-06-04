<?php
/**
 * Installation and database setup
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Install {

    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_options();
        self::create_pages();
        self::schedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('zim_donations_activated', true);
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        if (get_option('zim_donations_delete_data_on_uninstall')) {
            self::delete_tables();
            self::delete_options();
            self::delete_pages();
        }
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Donations table
        $donations_table = $wpdb->prefix . 'zim_donations';
        $donations_sql = "CREATE TABLE $donations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned DEFAULT 0,
            form_id bigint(20) unsigned DEFAULT 0,
            donor_email varchar(100) NOT NULL,
            donor_name varchar(100) NOT NULL,
            donor_phone varchar(20) DEFAULT '',
            donor_address text DEFAULT '',
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            gateway varchar(50) NOT NULL,
            transaction_id varchar(100) DEFAULT '',
            gateway_transaction_id varchar(100) DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            is_recurring tinyint(1) DEFAULT 0,
            recurring_interval varchar(20) DEFAULT '',
            next_payment_date datetime DEFAULT NULL,
            anonymous tinyint(1) DEFAULT 0,
            donor_message text DEFAULT '',
            gateway_response text DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY donor_email (donor_email),
            KEY status (status),
            KEY gateway (gateway),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Campaigns table
        $campaigns_table = $wpdb->prefix . 'zim_campaigns';
        $campaigns_sql = "CREATE TABLE $campaigns_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            description text DEFAULT '',
            goal_amount decimal(10,2) DEFAULT 0,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            category varchar(50) DEFAULT '',
            image_url varchar(500) DEFAULT '',
            video_url varchar(500) DEFAULT '',
            slug varchar(200) NOT NULL,
            settings text DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY category (category),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";

        // Donors table
        $donors_table = $wpdb->prefix . 'zim_donors';
        $donors_sql = "CREATE TABLE $donors_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            phone varchar(20) DEFAULT '',
            address text DEFAULT '',
            total_donated decimal(10,2) DEFAULT 0,
            donation_count int(11) DEFAULT 0,
            first_donation_date datetime DEFAULT NULL,
            last_donation_date datetime DEFAULT NULL,
            preferences text DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY name (name),
            KEY total_donated (total_donated),
            KEY donation_count (donation_count)
        ) $charset_collate;";

        // Transaction logs table
        $transactions_table = $wpdb->prefix . 'zim_transactions';
        $transactions_sql = "CREATE TABLE $transactions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            donation_id bigint(20) unsigned NOT NULL,
            gateway varchar(50) NOT NULL,
            transaction_type varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL,
            gateway_transaction_id varchar(100) DEFAULT '',
            status varchar(20) NOT NULL,
            gateway_response text DEFAULT '',
            notes text DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY donation_id (donation_id),
            KEY gateway (gateway),
            KEY transaction_type (transaction_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Forms table
        $forms_table = $wpdb->prefix . 'zim_forms';
        $forms_sql = "CREATE TABLE $forms_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text DEFAULT '',
            form_fields text NOT NULL,
            styling text DEFAULT '',
            settings text DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY status (status)
        ) $charset_collate;";

        // Currency rates table
        $rates_table = $wpdb->prefix . 'zim_currency_rates';
        $rates_sql = "CREATE TABLE $rates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            from_currency varchar(3) NOT NULL,
            to_currency varchar(3) NOT NULL,
            rate decimal(10,6) NOT NULL,
            source varchar(50) DEFAULT 'manual',
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY currency_pair (from_currency, to_currency),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($donations_sql);
        dbDelta($campaigns_sql);
        dbDelta($donors_sql);
        dbDelta($transactions_sql);
        dbDelta($forms_table);
        dbDelta($rates_sql);

        // Update database version
        update_option('zim_donations_db_version', HELPME_DONATIONS_DB_VERSION);
    }

    /**
     * Create default options
     */
    private static function create_options() {
        $default_options = array(
            'zim_donations_default_currency' => 'USD',
            'zim_donations_test_mode' => 1,
            'zim_donations_email_notifications' => 1,
            'zim_donations_admin_email' => get_option('admin_email'),
            'zim_donations_success_page' => 0,
            'zim_donations_cancel_page' => 0,
            'zim_donations_terms_page' => 0,
            'zim_donations_privacy_page' => 0,
            'zim_donations_delete_data_on_uninstall' => 0,
            'zim_donations_currency_update_frequency' => 'daily',
            'zim_donations_minimum_amount' => 1,
            'zim_donations_maximum_amount' => 10000,
            'zim_donations_enabled_gateways' => array('stripe', 'paypal'),
            'zim_donations_default_amounts' => '10,25,50,100,250',
            'zim_donations_allow_custom_amounts' => 1,
            'zim_donations_allow_recurring' => 1,
            'zim_donations_allow_anonymous' => 1,
            'zim_donations_gdpr_compliance' => 1,
            'zim_donations_data_retention_days' => 2555, // 7 years
        );

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }

        // Gateway-specific options
        $gateway_options = array(
            // Stripe
            'zim_donations_stripe_enabled' => 0,
            'zim_donations_stripe_publishable_key' => '',
            'zim_donations_stripe_secret_key' => '',
            'zim_donations_stripe_webhook_secret' => '',

            // PayPal
            'zim_donations_paypal_enabled' => 0,
            'zim_donations_paypal_client_id' => '',
            'zim_donations_paypal_client_secret' => '',
            'zim_donations_paypal_webhook_id' => '',

            // Paynow
            'zim_donations_paynow_enabled' => 0,
            'zim_donations_paynow_integration_id' => '',
            'zim_donations_paynow_integration_key' => '',

            // InBucks
            'zim_donations_inbucks_enabled' => 0,
            'zim_donations_inbucks_api_key' => '',
            'zim_donations_inbucks_secret_key' => '',

            // ZimSwitch
            'zim_donations_zimswitch_enabled' => 0,
            'zim_donations_zimswitch_merchant_id' => '',
            'zim_donations_zimswitch_api_key' => '',
        );

        foreach ($gateway_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Create default pages
     */
    private static function create_pages() {
        $pages = array(
            'donation_success' => array(
                'title' => __('Donation Successful', 'zim-donations'),
                'content' => __('Thank you for your generous donation! Your contribution makes a difference.', 'zim-donations'),
                'option' => 'zim_donations_success_page'
            ),
            'donation_cancelled' => array(
                'title' => __('Donation Cancelled', 'zim-donations'),
                'content' => __('Your donation was cancelled. You can try again anytime.', 'zim-donations'),
                'option' => 'zim_donations_cancel_page'
            ),
            'privacy_policy' => array(
                'title' => __('Donation Privacy Policy', 'zim-donations'),
                'content' => self::get_privacy_policy_content(),
                'option' => 'zim_donations_privacy_page'
            ),
            'terms_conditions' => array(
                'title' => __('Donation Terms & Conditions', 'zim-donations'),
                'content' => self::get_terms_content(),
                'option' => 'zim_donations_terms_page'
            )
        );

        foreach ($pages as $slug => $page_data) {
            $existing_page = get_option($page_data['option']);

            if (!$existing_page || !get_post($existing_page)) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));

                if ($page_id && !is_wp_error($page_id)) {
                    update_option($page_data['option'], $page_id);
                }
            }
        }
    }

    /**
     * Schedule recurring events
     */
    private static function schedule_events() {
        // Schedule currency rate updates
        if (!wp_next_scheduled('zim_donations_update_currency_rates')) {
            wp_schedule_event(time(), 'daily', 'zim_donations_update_currency_rates');
        }

        // Schedule recurring donation processing
        if (!wp_next_scheduled('zim_donations_process_recurring')) {
            wp_schedule_event(time(), 'daily', 'zim_donations_process_recurring');
        }

        // Schedule cleanup old data
        if (!wp_next_scheduled('zim_donations_cleanup_data')) {
            wp_schedule_event(time(), 'weekly', 'zim_donations_cleanup_data');
        }
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook('zim_donations_update_currency_rates');
        wp_clear_scheduled_hook('zim_donations_process_recurring');
        wp_clear_scheduled_hook('zim_donations_cleanup_data');
    }

    /**
     * Delete database tables
     */
    private static function delete_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'zim_donations',
            $wpdb->prefix . 'zim_campaigns',
            $wpdb->prefix . 'zim_donors',
            $wpdb->prefix . 'zim_transactions',
            $wpdb->prefix . 'zim_forms',
            $wpdb->prefix . 'zim_currency_rates'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Delete plugin options
     */
    private static function delete_options() {
        global $wpdb;

        // Delete all plugin options
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'zim_donations_%'"
        );
    }

    /**
     * Delete created pages
     */
    private static function delete_pages() {
        $page_options = array(
            'zim_donations_success_page',
            'zim_donations_cancel_page',
            'zim_donations_privacy_page',
            'zim_donations_terms_page'
        );

        foreach ($page_options as $option) {
            $page_id = get_option($option);
            if ($page_id) {
                wp_delete_post($page_id, true);
                delete_option($option);
            }
        }
    }

    /**
     * Get privacy policy content
     */
    private static function get_privacy_policy_content() {
        return '<h2>' . __('Information We Collect', 'zim-donations') . '</h2>
        <p>' . __('When you make a donation, we collect the following information:', 'zim-donations') . '</p>
        <ul>
            <li>' . __('Personal information (name, email address, phone number)', 'zim-donations') . '</li>
            <li>' . __('Billing information (address for tax receipts)', 'zim-donations') . '</li>
            <li>' . __('Donation amount and payment method', 'zim-donations') . '</li>
            <li>' . __('Transaction details and payment processor information', 'zim-donations') . '</li>
        </ul>
        
        <h2>' . __('How We Use Your Information', 'zim-donations') . '</h2>
        <p>' . __('We use your information to:', 'zim-donations') . '</p>
        <ul>
            <li>' . __('Process your donation', 'zim-donations') . '</li>
            <li>' . __('Send donation receipts and confirmations', 'zim-donations') . '</li>
            <li>' . __('Provide updates on campaigns you\'ve supported', 'zim-donations') . '</li>
            <li>' . __('Comply with legal and regulatory requirements', 'zim-donations') . '</li>
        </ul>
        
        <h2>' . __('Data Protection', 'zim-donations') . '</h2>
        <p>' . __('We implement appropriate security measures to protect your personal information. Payment processing is handled by secure, PCI-compliant payment processors.', 'zim-donations') . '</p>
        
        <h2>' . __('Your Rights', 'zim-donations') . '</h2>
        <p>' . __('You have the right to access, update, or delete your personal information. Contact us to exercise these rights.', 'zim-donations') . '</p>';
    }

    /**
     * Get terms and conditions content
     */
    private static function get_terms_content() {
        return '<h2>' . __('Donation Terms', 'zim-donations') . '</h2>
        <p>' . __('By making a donation, you agree to the following terms:', 'zim-donations') . '</p>
        
        <h3>' . __('Donation Processing', 'zim-donations') . '</h3>
        <ul>
            <li>' . __('All donations are processed securely through our payment partners', 'zim-donations') . '</li>
            <li>' . __('You will receive an email confirmation for your donation', 'zim-donations') . '</li>
            <li>' . __('Donations are typically processed immediately', 'zim-donations') . '</li>
        </ul>
        
        <h3>' . __('Refunds', 'zim-donations') . '</h3>
        <p>' . __('Donations are generally non-refundable. In exceptional circumstances, please contact us to discuss your situation.', 'zim-donations') . '</p>
        
        <h3>' . __('Recurring Donations', 'zim-donations') . '</h3>
        <ul>
            <li>' . __('Recurring donations will be charged automatically at the specified interval', 'zim-donations') . '</li>
            <li>' . __('You can cancel recurring donations at any time by contacting us', 'zim-donations') . '</li>
            <li>' . __('Changes to recurring donations may take one billing cycle to take effect', 'zim-donations') . '</li>
        </ul>
        
        <h3>' . __('Tax Receipts', 'zim-donations') . '</h3>
        <p>' . __('Tax-deductible receipts will be provided where applicable according to local laws and regulations.', 'zim-donations') . '</p>';
    }
}