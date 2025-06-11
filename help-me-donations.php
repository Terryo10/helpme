<?php

/**
 * Plugin Name: Help Me Donations
 * Plugin URI: https://github.com/yourusername/helpme-donations
 * Description: A comprehensive WordPress donation plugin supporting Zimbabwean and international payment methods with customizable forms, multi-currency support, and subscription-based premium features.
 * Version: 1.0.0
 * Author: Tapiwa Tererai And Gene Piki
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: helpme-donations
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package HelpMeDonations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HELPME_DONATIONS_VERSION', '1.0.0');
define('HELPME_DONATIONS_PLUGIN_FILE', __FILE__);
define('HELPME_DONATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HELPME_DONATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HELPME_DONATIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('HELPME_DONATIONS_DB_VERSION', '1.0');
define('HELPME_DONATIONS_MIN_PHP_VERSION', '7.4');
define('HELPME_DONATIONS_MIN_WP_VERSION', '5.0');

// Define backward compatibility constants for gateways
define('ZIM_DONATIONS_VERSION', HELPME_DONATIONS_VERSION);
define('ZIM_DONATIONS_PLUGIN_URL', HELPME_DONATIONS_PLUGIN_URL);
define('ZIM_DONATIONS_PLUGIN_DIR', HELPME_DONATIONS_PLUGIN_DIR);

/**
 * Main Plugin Class
 */
final class HelpMeDonations
{

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $admin;
    public $payment_gateways;
    public $form_builder;
    public $campaign_manager;
    public $currency_manager;
    public $analytics;
    public $db;

    /**
     * Get plugin instance
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('HelpMeDonations_Install', 'uninstall'));

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/api/paynow/autoloader.php';

        // Core classes
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-zim-donations-install.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-zim-donations-db.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/admin/admin.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/Payment-gateways.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/Form-builder.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-campaign-manager.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-currency-manager.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/class-analytics.php';

        // Gateway classes
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/stripe.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/class-paypal.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/paynow.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/class-inbucks.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/class-zimswitch.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/api/api.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/gateways/class-paypal.php';

        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/api/paynow_helper.php';
        require_once HELPME_DONATIONS_PLUGIN_DIR . 'includes/api/stripe-php-master/init.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_components()
    {
        $this->db = new HelpMeDonations_DB();
        $this->admin = new HelpMeDonations_Admin();
        $this->payment_gateways = new ZimDonations_Payment_Gateways();
        $this->form_builder = new HelpMeDonations_Form_Builder();
        $this->campaign_manager = new ZimDonations_Campaign_Manager();
        $this->currency_manager = new ZimDonations_Currency_Manager();
        $this->analytics = new ZimDonations_Analytics();
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        HelpMeDonations_Install::activate();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        HelpMeDonations_Install::deactivate();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'helpme-donations',
            false,
            dirname(HELPME_DONATIONS_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Check for database updates
        HelpMeDonations_Install::maybe_update_db();

        // Register shortcodes
        $this->register_shortcodes();

        // Handle webhook requests
        $this->handle_webhooks();

        // Handle donation processing
        add_action('wp_ajax_process_donation', array($this, 'process_donation'));
        add_action('wp_ajax_nopriv_process_donation', array($this, 'process_donation'));
        add_action('wp_ajax_update_donation_success_payment_status', 'update_donation_success_payment_status');

        add_action('wp_ajax_helpme_submit_paynow_donation', 'helpme_submit_paynow_donation');
        add_action('wp_ajax_paypal_create_order', 'paypal_create_order');
        add_action('wp_ajax_check_paynow_payment_status', 'check_paynow_payment_status');
        add_action('wp_ajax_nopriv_helpme_submit_paynow_donation', 'helpme_submit_paynow_donation');
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes()
    {
        add_shortcode('helpme_donation_form', array($this, 'donation_form_shortcode'));
        add_shortcode('helpme_campaign_progress', array($this, 'campaign_progress_shortcode'));
        add_shortcode('helpme_donation_success', array($this, 'donation_success_shortcode'));
    }

    /**
     * Handle webhook requests
     */
    private function handle_webhooks()
    {
        if (isset($_GET['helpme-donations-webhook']) && isset($_GET['gateway'])) {
            $gateway_id = sanitize_text_field($_GET['gateway']);
            $this->payment_gateways->handle_webhook($gateway_id);
        }
    }

    /**
     * Process donation AJAX
     */
    public function process_donation()
    {
        check_ajax_referer('helpme_donations_nonce', 'nonce');

        $donation_data = array(
            'amount' => floatval($_POST['amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'gateway' => sanitize_text_field($_POST['gateway']),
            'donor_name' => sanitize_text_field($_POST['donor_name']),
            'donor_email' => sanitize_email($_POST['donor_email']),
            'donor_phone' => sanitize_text_field($_POST['donor_phone'] ?? ''),
            'donor_address' => sanitize_textarea_field($_POST['donor_address'] ?? ''),
            'donor_message' => sanitize_textarea_field($_POST['donor_message'] ?? ''),
            'campaign_id' => intval($_POST['campaign_id'] ?? 0),
            'form_id' => intval($_POST['form_id'] ?? 0),
            'is_recurring' => !empty($_POST['is_recurring']),
            'recurring_interval' => sanitize_text_field($_POST['recurring_interval'] ?? ''),
            'anonymous' => !empty($_POST['anonymous'])
        );

        $result = $this->payment_gateways->process_donation($donation_data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Donation form shortcode
     */
    public function donation_form_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'form_id' => 0,
            'campaign_id' => 0,
            'title' => 'Make a Donation',
            'description' => '',
            'amounts' => '10,25,50,100',
            'currency' => 'USD',
            'recurring' => 'true'
        ), $atts, 'helpme_donation_form');

        return $this->form_builder->render_form($atts);
    }

    /**
     * Campaign progress shortcode
     */
    public function campaign_progress_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'campaign_id' => 0,
            'show_amount' => 'true',
            'show_percentage' => 'true',
            'show_donors' => 'true'
        ), $atts, 'helpme_campaign_progress');

        return $this->campaign_manager->render_progress($atts);
    }

    /**
     * Donation success shortcode
     */
    public function donation_success_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'title' => 'Thank You!',
            'message' => 'Your donation has been processed successfully.'
        ), $atts, 'helpme_donation_success');

        ob_start();
?>
        <div class="helpme-donation-success">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <p><?php echo esc_html($atts['message']); ?></p>

            <?php if (isset($_GET['donation_id'])): ?>
                <div class="donation-details">
                    <p><strong><?php _e('Donation ID:', 'helpme-donations'); ?></strong> <?php echo esc_html($_GET['donation_id']); ?></p>
                </div>
            <?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts()
    {
        // CSS
        wp_enqueue_style(
            'helpme-donations-frontend',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            HELPME_DONATIONS_VERSION
        );

        wp_enqueue_script(
            'stripe-js', // Handle
            'https://js.stripe.com/v3/', // Source
            array(), // No dependencies
            null, // No version (Stripe recommends not using a version number)
            true // Load in footer
        );

        // JavaScript
        wp_enqueue_script(
            'helpme-donations-frontend',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            HELPME_DONATIONS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('helpme-donations-frontend', 'helpmeDonations', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helpme_donations_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'helpme-donations'),
                'error' => __('An error occurred. Please try again.', 'helpme-donations'),
                'success' => __('Thank you for your donation!', 'helpme-donations')
            )
        ));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on plugin pages
        if (strpos($hook, 'helpme-donations') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'helpme-donations-admin',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            HELPME_DONATIONS_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'helpme-donations-admin',
            HELPME_DONATIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            HELPME_DONATIONS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('helpme-donations-admin', 'helpmeDonationsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helpme_donations_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this item?', 'helpme-donations'),
                'saving' => __('Saving...', 'helpme-donations'),
                'saved' => __('Saved!', 'helpme-donations'),
                'error' => __('Error occurred', 'helpme-donations')
            )
        ));
    }
}

/**
 * Get main plugin instance
 */
function helpme_donations()
{
    return HelpMeDonations::instance();
}

// Initialize the plugin
helpme_donations();
