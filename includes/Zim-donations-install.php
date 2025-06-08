<?php

/**
 * Installation and activation for Help Me Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Install
{

    /**
     * Plugin activation
     */
    public static function activate()
    {
        // Check requirements
        // if (!self::check_requirements()) {
        //     deactivate_plugins(HELPME_DONATIONS_PLUGIN_BASENAME);
        //     wp_die(__('Help Me Donations plugin requirements not met. Please ensure you have PHP 7.4+ and WordPress 5.0+.', 'helpme-donations'));
        // }

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Create default form
        self::create_default_form();

        // Create pages
        self::create_pages();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Set activation flag
        update_option('helpme_donations_activated', true);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate()
    {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('helpme_donations_process_recurring');
        wp_clear_scheduled_hook('helpme_donations_cleanup_temp_data');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Remove activation flag
        delete_option('helpme_donations_activated');
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall()
    {
        // Check if we should remove data
        $remove_data = get_option('helpme_donations_remove_data_on_uninstall', false);

        if ($remove_data) {
            // Drop database tables
            self::drop_tables();

            // Remove all plugin options
            self::remove_plugin_options();

            // Remove uploaded files
            self::remove_uploaded_files();
        }
    }

    /**
     * Check plugin requirements
     */
    private static function check_requirements()
    {
        return true;
        // Check PHP version
        if (version_compare(PHP_VERSION, HELPME_DONATIONS_MIN_PHP_VERSION, '<')) {
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), HELPME_DONATIONS_MIN_WP_VERSION, '<')) {
            return false;
        }

        // Check for required PHP extensions
        $required_extensions = array('curl', 'json');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create database tables
     */
    private static function create_tables()
    {
        try {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();

            // Donations table
            $donations_table = $wpdb->prefix . 'helpme_donations';

            $donations_sql = "CREATE TABLE $donations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            donation_id varchar(50) DEFAULT NULL,
            campaign_id bigint(20) unsigned DEFAULT NULL,
            form_id bigint(20) unsigned DEFAULT NULL,
            donor_id bigint(20) unsigned DEFAULT NULL,
            amount decimal(15,2) DEFAULT NULL,
            currency varchar(3) DEFAULT NULL,
            gateway varchar(50) DEFAULT NULL,
            poll_url text DEFAULT NULL,
            gateway_transaction_id varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT NULL,
            is_recurring tinyint(1) DEFAULT NULL,
            recurring_interval varchar(20) DEFAULT NULL,
            parent_donation_id bigint(20) unsigned DEFAULT NULL,
            anonymous tinyint(1) DEFAULT NULL,
            donor_name varchar(255) DEFAULT NULL,
            donor_email varchar(255) DEFAULT NULL,
            donor_phone varchar(50) DEFAULT NULL,
            donor_address text DEFAULT NULL,
            donor_message text DEFAULT NULL,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY donation_id (donation_id),
            KEY campaign_id (campaign_id),
            KEY donor_id (donor_id),
            KEY status (status),
            KEY gateway (gateway),
            KEY created_at (created_at)
        ) $charset_collate;";

            // Campaigns table
            $campaigns_table = $wpdb->prefix . 'helpme_campaigns';
            $campaigns_sql = "CREATE TABLE $campaigns_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT NULL,
            goal_amount decimal(15,2) DEFAULT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            raised_amount decimal(15,2) DEFAULT 0,
            donor_count int(11) DEFAULT 0,
            category varchar(100) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            video_url varchar(500) DEFAULT NULL,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 1,
            metadata text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY category (category),
            KEY created_by (created_by)
        ) $charset_collate;";

            // Donors table
            $donors_table = $wpdb->prefix . 'helpme_donors';
            $donors_sql = "CREATE TABLE $donors_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            total_donated decimal(15,2) DEFAULT 0,
            donation_count int(11) DEFAULT 0,
            first_donation_date datetime DEFAULT NULL,
            last_donation_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status)
        ) $charset_collate;";

            // Transactions table
            $transactions_table = $wpdb->prefix . 'helpme_transactions';
            $transactions_sql = "CREATE TABLE $transactions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            donation_id bigint(20) unsigned NOT NULL,
            transaction_id varchar(100) NOT NULL,
            gateway varchar(50) NOT NULL,
            gateway_transaction_id varchar(100) DEFAULT NULL,
            type varchar(20) NOT NULL DEFAULT 'payment',
            status varchar(20) NOT NULL DEFAULT 'pending',
            amount decimal(15,2) NOT NULL,
            currency varchar(3) NOT NULL,
            gateway_response text DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY donation_id (donation_id),
            KEY transaction_id (transaction_id),
            KEY gateway (gateway),
            KEY status (status),
            KEY type (type)
        ) $charset_collate;";

            // Forms table
            $forms_table = $wpdb->prefix . 'helpme_forms';
            $forms_sql = "CREATE TABLE $forms_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            config text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            usage_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            dbDelta($donations_sql);
            dbDelta($campaigns_sql);
            dbDelta($donors_sql);
            dbDelta($transactions_sql);
            dbDelta($forms_sql);

            // Update database version
            update_option('helpme_donations_db_version', HELPME_DONATIONS_DB_VERSION);
        } catch (Exception $error) {
            deactivate_plugins(HELPME_DONATIONS_PLUGIN_BASENAME);
            wp_die(__($error->getMessage(), 'helpme-donations'));
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options()
    {
        $default_options = array(
            // General settings
            'helpme_donations_default_currency' => 'USD',
            'helpme_donations_minimum_amount' => 1,
            'helpme_donations_maximum_amount' => 10000,
            'helpme_donations_test_mode' => true,
            'helpme_donations_remove_data_on_uninstall' => false,

            // Email settings
            'helpme_donations_admin_email' => get_option('admin_email'),
            'helpme_donations_from_email' => get_option('admin_email'),
            'helpme_donations_from_name' => get_bloginfo('name'),
            'helpme_donations_send_admin_notifications' => true,
            'helpme_donations_send_donor_confirmations' => true,

            // Payment gateway settings
            'helpme_donations_enabled_gateways' => array(),

            // Stripe settings
            'helpme_donations_stripe_enabled' => false,
            'helpme_donations_stripe_test_publishable_key' => '',
            'helpme_donations_stripe_test_secret_key' => '',
            'helpme_donations_stripe_live_publishable_key' => '',
            'helpme_donations_stripe_live_secret_key' => '',

            // PayPal settings
            'helpme_donations_paypal_enabled' => false,
            'helpme_donations_paypal_test_client_id' => '',
            'helpme_donations_paypal_test_client_secret' => '',
            'helpme_donations_paypal_live_client_id' => '',
            'helpme_donations_paypal_live_client_secret' => '',

            // Paynow settings
            'helpme_donations_paynow_enabled' => false,
            'helpme_donations_paynow_integration_id' => '',
            'helpme_donations_paynow_integration_key' => '',

            // InBucks settings
            'helpme_donations_inbucks_enabled' => false,
            'helpme_donations_inbucks_api_key' => '',
            'helpme_donations_inbucks_secret_key' => '',

            // ZimSwitch settings
            'helpme_donations_zimswitch_enabled' => false,
            'helpme_donations_zimswitch_merchant_id' => '',
            'helpme_donations_zimswitch_api_key' => '',

            // Form settings
            'helpme_donations_enable_anonymous_donations' => true,
            'helpme_donations_enable_recurring_donations' => true,
            'helpme_donations_require_terms_acceptance' => false,
            'helpme_donations_terms_page' => 0,

            // Style settings
            'helpme_donations_primary_color' => '#007cba',
            'helpme_donations_secondary_color' => '#666666',
            'helpme_donations_button_color' => '#007cba',
            'helpme_donations_font_family' => 'inherit'
        );

        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                update_option($option_name, $default_value);
            }
        }
    }

    /**
     * Create default donation form
     */
    private static function create_default_form()
    {
        // For now, we'll skip creating database forms since the table might not exist
        // The form builder will handle default forms programmatically
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs()
    {
        // Process recurring donations daily
        if (!wp_next_scheduled('helpme_donations_process_recurring')) {
            wp_schedule_event(time(), 'daily', 'helpme_donations_process_recurring');
        }

        // Cleanup temporary data weekly
        if (!wp_next_scheduled('helpme_donations_cleanup_temp_data')) {
            wp_schedule_event(time(), 'weekly', 'helpme_donations_cleanup_temp_data');
        }
    }

    /**
     * Create pages
     */
    private static function create_pages()
    {
        $pages = array(
            'donation-success' => array(
                'title' => 'Donation Success',
                'content' => '[helpme_donation_success]',
                'option' => 'helpme_donations_success_page'
            ),
            'donation-cancelled' => array(
                'title' => 'Donation Cancelled',
                'content' => 'Your donation was cancelled.',
                'option' => 'helpme_donations_cancelled_page'
            )
        );

        foreach ($pages as $slug => $page) {
            $existing_page = get_option($page['option']);

            if (!$existing_page || !get_post($existing_page)) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ));

                if ($page_id && !is_wp_error($page_id)) {
                    update_option($page['option'], $page_id);
                }
            }
        }
    }

    /**
     * Drop database tables
     */
    private static function drop_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'helpme_donations',
            $wpdb->prefix . 'helpme_campaigns',
            $wpdb->prefix . 'helpme_donors',
            $wpdb->prefix . 'helpme_transactions',
            $wpdb->prefix . 'helpme_forms'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Remove all plugin options
     */
    private static function remove_plugin_options()
    {
        global $wpdb;

        // Get all plugin options
        $options = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'helpme_donations_%'"
        );

        // Delete each option
        foreach ($options as $option) {
            delete_option($option->option_name);
        }

        // Remove transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_helpme_donations_%' OR option_name LIKE '_transient_timeout_helpme_donations_%'"
        );
    }

    /**
     * Remove uploaded files
     */
    private static function remove_uploaded_files()
    {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/helpme-donations';

        if (is_dir($plugin_upload_dir)) {
            self::remove_directory_recursive($plugin_upload_dir);
        }
    }

    /**
     * Recursively remove directory
     */
    private static function remove_directory_recursive($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::remove_directory_recursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Database update check
     */
    public static function maybe_update_db()
    {
        $current_version = get_option('helpme_donations_db_version', '0');

        if (version_compare($current_version, HELPME_DONATIONS_DB_VERSION, '<')) {
            self::create_tables();
        }
    }
}
