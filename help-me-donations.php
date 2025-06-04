<?php
/**
 * Plugin Name: Help Me Donations
 * Plugin URI: https://github.com/Terryo10/helpme.git
 * Description: A comprehensive WordPress donation plugin supporting Zimbabwean and international payment methods with customizable forms, multi-currency support, and subscription-based premium features.
 * Version: 1.0.0
 * Author: Tapiwa Tererai
 * Author URI: https://designave.co.za
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: helpme-donations
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HELPME_DONATIONS_VERSION', '1.0.0');
define('HELPME_DONATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HELPME_DONATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HELPME_DONATIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('HELPME_DONATIONS_DB_VERSION', '1.0');
define('HELPME_DONATIONS_MIN_PHP_VERSION', '7.4');
define('HELPME_DONATIONS_MIN_WP_VERSION', '5.0');

// Main plugin class
final class HelpMeDonations {

    /**
     * Single instance of this class
     */
    private static $instance = null;

    /**
     * Payment gateways instance
     */
    public $payment_gateways;

    /**
     * Form builder instance
     */
    public $form_builder;

    /**
     * Campaign manager instance
     */
    public $campaign_manager;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-zim-donations-db.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-zim-donations-install.php';
        
        // Check if classes exist before loading dependent classes
        if (class_exists('HelpMeDonations_DB')) {
            require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/Payment-gateways.php';
            require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/Form-builder.php';
            require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-campaign-manager.php';
            require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-currency-manager.php';
            require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-analytics.php';
        }

        // Admin classes
        if (is_admin()) {
            require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/admin/admin.php';
        }

        // Payment gateway classes - only if core is loaded
        if (class_exists('HelpMeDonations_DB')) {
            $gateway_files = array(
                'includes/gateways/paynow.php',
                'includes/gateways/stripe.php',
                'includes/gateways/class-paypal.php',
                'includes/gateways/class-inbucks.php',
                'includes/gateways/class-zimswitch.php'
            );

            foreach ($gateway_files as $file) {
                $file_path = HELPME_DONATIONS_PLUGIN_DIR . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array('HelpMeDonations_Install', 'activate'));
        register_deactivation_hook(__FILE__, array('HelpMeDonations_Install', 'deactivate'));
        register_uninstall_hook(__FILE__, array('HelpMeDonations_Install', 'uninstall'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'), 0);

        // Load plugin textdomain
        add_action('init', array($this, 'load_textdomain'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));

        // AJAX hooks
        add_action('wp_ajax_process_donation', array($this, 'process_donation'));
        add_action('wp_ajax_nopriv_process_donation', array($this, 'process_donation'));

        // Handle webhooks
        add_action('init', array($this, 'handle_webhooks'));

        // Add admin notices for missing dependencies
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check requirements
        if (!$this->check_requirements()) {
            return;
        }

        // Initialize classes only if dependencies are available
        try {
            if (class_exists('ZimDonations_Payment_Gateways')) {
                $this->payment_gateways = new ZimDonations_Payment_Gateways();
            }
            
            if (class_exists('HelpMeDonations_Form_Builder')) {
                $this->form_builder = new HelpMeDonations_Form_Builder();
            }
            
            if (class_exists('ZimDonations_Campaign_Manager')) {
                $this->campaign_manager = new ZimDonations_Campaign_Manager();
            }

            // Initialize admin
            if (is_admin() && class_exists('ZimDonations_Admin')) {
                new HelpMeDonations_Admin();
            }

            do_action('helpme_donations_loaded');
        } catch (Exception $e) {
            error_log('HelpMe Donations initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, HELPME_DONATIONS_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('Help Me Donations requires PHP version %s or higher. You are running version %s.', 'helpme-donations'),
                    HELPME_DONATIONS_MIN_PHP_VERSION,
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), HELPME_DONATIONS_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('Help Me Donations requires WordPress version %s or higher. You are running version %s.', 'helpme-donations'),
                    HELPME_DONATIONS_MIN_WP_VERSION,
                    get_bloginfo('version')
                );
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('helpme-donations', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'helpme-donations-style',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            HELPME_DONATIONS_VERSION
        );

        wp_enqueue_script(
            'helpme-donations-script',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            HELPME_DONATIONS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('helpme-donations-script', 'helpmeDonations', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helpme_donations_nonce'),
            'currency' => get_option('helpme_donations_default_currency', 'USD'),
            'strings' => array(
                'processing' => __('Processing...', 'helpme-donations'),
                'error' => __('An error occurred. Please try again.', 'helpme-donations'),
                'success' => __('Thank you for your donation!', 'helpme-donations')
            )
        ));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'helpme-donations') === false) {
            return;
        }

        wp_enqueue_style(
            'helpme-donations-admin-style',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            HELPME_DONATIONS_VERSION
        );

        wp_enqueue_script(
            'helpme-donations-admin-script',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            HELPME_DONATIONS_VERSION,
            true
        );

        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('helpme_donation_form', array($this, 'donation_form_shortcode'));
        add_shortcode('helpme_campaign_progress', array($this, 'campaign_progress_shortcode'));
        add_shortcode('helpme_recent_donations', array($this, 'recent_donations_shortcode'));
    }

    /**
     * Donation form shortcode
     */
    public function donation_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'form_id' => 0,
            'title' => '',
            'description' => '',
            'amounts' => '10,25,50,100',
            'currency' => 'USD',
            'recurring' => 'true'
        ), $atts, 'helpme_donation_form');

        if ($this->form_builder) {
            return $this->form_builder->render_form($atts);
        }
        
        return '<p>' . __('Donation form is not available.', 'helpme-donations') . '</p>';
    }

    /**
     * Campaign progress shortcode
     */
    public function campaign_progress_shortcode($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'show_amount' => 'true',
            'show_percentage' => 'true',
            'show_donors' => 'true'
        ), $atts, 'helpme_campaign_progress');

        if ($this->campaign_manager) {
            return $this->campaign_manager->render_progress($atts);
        }
        
        return '<p>' . __('Campaign progress is not available.', 'helpme-donations') . '</p>';
    }

    /**
     * Recent donations shortcode
     */
    public function recent_donations_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'campaign_id' => 0,
            'show_amount' => 'false',
            'show_date' => 'true',
            'anonymous_text' => 'Anonymous'
        ), $atts, 'helpme_recent_donations');

        return $this->render_recent_donations($atts);
    }

    /**
     * Process donation AJAX handler
     */
    public function process_donation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'helpme_donations_nonce')) {
            wp_die(__('Security check failed', 'helpme-donations'));
        }

        // Only proceed if payment gateways are available
        if (!$this->payment_gateways) {
            wp_send_json_error(array('message' => __('Payment processing is not available.', 'helpme-donations')));
            return;
        }

        // Sanitize and validate input
        $donation_data = array(
            'amount' => floatval($_POST['amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'gateway' => sanitize_text_field($_POST['gateway']),
            'campaign_id' => intval($_POST['campaign_id']),
            'donor_email' => sanitize_email($_POST['donor_email']),
            'donor_name' => sanitize_text_field($_POST['donor_name']),
            'is_recurring' => isset($_POST['is_recurring']) ? true : false,
            'anonymous' => isset($_POST['anonymous']) ? true : false
        );

        // Process the donation
        $result = $this->payment_gateways->process_donation($donation_data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle payment webhooks
     */
    public function handle_webhooks() {
        if (isset($_GET['helpme-donations-webhook'])) {
            $gateway = sanitize_text_field($_GET['gateway']);
            if ($this->payment_gateways) {
                $this->payment_gateways->handle_webhook($gateway);
            }
            exit;
        }
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check for missing classes
        $missing_classes = array();
        
        if (!class_exists('HelpMeDonations_DB')) {
            $missing_classes[] = 'Database';
        }
        
        if (!class_exists('ZimDonations_Payment_Gateways')) {
            $missing_classes[] = 'Payment Gateways';
        }
        
        if (!class_exists('HelpMeDonations_Form_Builder')) {
            $missing_classes[] = 'Form Builder';
        }

        if (!empty($missing_classes)) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                __('Help Me Donations: Some components failed to load: %s. Please check file permissions and PHP error logs.', 'helpme-donations'),
                implode(', ', $missing_classes)
            );
            echo '</p></div>';
        }
    }

    /**
     * Render recent donations
     */
    private function render_recent_donations($atts) {
        global $wpdb;

        $table = $wpdb->prefix . 'helpme_donations';
        $limit = intval($atts['limit']);
        $campaign_id = intval($atts['campaign_id']);

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return '<p>' . __('No donations table found.', 'helpme-donations') . '</p>';
        }

        $where = "WHERE status = 'completed'";
        if ($campaign_id > 0) {
            $where .= $wpdb->prepare(" AND campaign_id = %d", $campaign_id);
        }

        $donations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));

        if (empty($donations)) {
            return '<p>' . __('No donations yet.', 'helpme-donations') . '</p>';
        }

        $output = '<div class="helpme-recent-donations">';
        foreach ($donations as $donation) {
            $output .= '<div class="donation-item">';

            // Donor name
            $name = $donation->anonymous ? $atts['anonymous_text'] : $donation->donor_name;
            $output .= '<span class="donor-name">' . esc_html($name) . '</span>';

            // Amount
            if ($atts['show_amount'] === 'true') {
                $output .= ' <span class="donation-amount">' .
                    $this->format_currency($donation->amount, $donation->currency) .
                    '</span>';
            }

            // Date
            if ($atts['show_date'] === 'true') {
                $output .= ' <span class="donation-date">' .
                    date_i18n(get_option('date_format'), strtotime($donation->created_at)) .
                    '</span>';
            }

            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
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

/**
 * Initialize the plugin
 */
function helpme_donations() {
    return HelpMeDonations::get_instance();
}

// Initialize the plugin
helpme_donations();