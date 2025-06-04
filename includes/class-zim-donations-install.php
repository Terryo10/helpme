<?php
/**
 * Installation and activation for Help Me Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Install {

    /**
     * Plugin activation
     */
    public static function activate() {
        // Check requirements
        if (!self::check_requirements()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Help Me Donations plugin requirements not met.', 'helpme-donations'));
        }

        // Create database tables
        $db = new HelpMeDonations_DB();
        $db->create_tables();

        // Set default options
        self::set_default_options();

        // Create default form
        self::create_default_form();

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
    public static function deactivate() {
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
    public static function uninstall() {
        // Check if we should remove data
        $remove_data = get_option('helpme_donations_remove_data_on_uninstall', false);

        if ($remove_data) {
            // Drop database tables
            $db = new HelpMeDonations_DB();
            $db->drop_tables();

            // Remove all plugin options
            self::remove_plugin_options();

            // Remove uploaded files
            self::remove_uploaded_files();
        }
    }

    /**
     * Check plugin requirements
     */
    private static function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, HELPME_DONATIONS_MIN_PHP_VERSION, '<')) {
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), HELPME_DONATIONS_MIN_WP_VERSION, '<')) {
            return false;
        }

        // Check for required PHP extensions
        $required_extensions = array('curl', 'json', 'openssl');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
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
            'helpme_donations_enabled_gateways' => array('stripe'),

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
    private static function create_default_form() {
        global $wpdb;

        $db = new HelpMeDonations_DB();
        $forms_table = $db->get_forms_table();

        // Check if default form already exists
        $existing = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$forms_table} WHERE name = 'Default Donation Form'"
        );

        if ($existing > 0) {
            return;
        }

        $default_config = array(
            'title' => 'Make a Donation',
            'description' => 'Your donation helps us make a difference in Zimbabwe.',
            'styling' => array(
                'primary_color' => '#007cba',
                'secondary_color' => '#666666',
                'font_family' => 'inherit',
                'border_radius' => '4px'
            ),
            'settings' => array(
                'multi_step' => true,
                'show_progress' => true,
                'allow_comments' => true,
                'require_address' => false,
                'terms_required' => false
            ),
            'fields' => array(
                'donation_info' => array(
                    'amount' => array(
                        'enabled' => true,
                        'required' => true,
                        'preset_amounts' => array(10, 25, 50, 100),
                        'allow_custom' => true,
                        'currency' => 'USD'
                    ),
                    'recurring' => array(
                        'enabled' => true,
                        'default_interval' => 'monthly'
                    ),
                    'anonymous' => array(
                        'enabled' => true
                    ),
                    'message' => array(
                        'enabled' => true,
                        'label' => 'Message (Optional)',
                        'placeholder' => 'Share why you\'re donating...'
                    )
                ),
                'donor_info' => array(
                    'name' => array(
                        'enabled' => true,
                        'required' => true,
                        'label' => 'Full Name'
                    ),
                    'email' => array(
                        'enabled' => true,
                        'required' => true,
                        'label' => 'Email Address'
                    ),
                    'phone' => array(
                        'enabled' => false,
                        'required' => false,
                        'label' => 'Phone Number'
                    ),
                    'address' => array(
                        'enabled' => false,
                        'required' => false,
                        'label' => 'Address'
                    )
                )
            )
        );

        $wpdb->insert(
            $forms_table,
            array(
                'name' => 'Default Donation Form',
                'config' => json_encode($default_config),
                'status' => 'active',
                'created_by' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Schedule cron jobs
     */
    private static function schedule_cron_jobs() {
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
     * Remove all plugin options
     */
    private static function remove_plugin_options() {
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
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/helpme-donations';

        if (is_dir($plugin_upload_dir)) {
            self::remove_directory_recursive($plugin_upload_dir);
        }
    }

    /**
     * Recursively remove directory
     */
    private static function remove_directory_recursive($dir) {
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
    public static function maybe_update_db() {
        $current_version = get_option('helpme_donations_db_version', '0');
        
        if (version_compare($current_version, HelpMeDonations_DB::DB_VERSION, '<')) {
            $db = new HelpMeDonations_DB();
            $db->create_tables();
        }
    }

    /**
     * Create pages
     */
    private static function create_pages() {
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
} 