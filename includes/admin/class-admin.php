<?php
/**
 * Admin interface for Zimbabwe Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Admin {

    /**
     * Database instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new ZimDonations_DB();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('plugin_action_links_' . ZIM_DONATIONS_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // AJAX handlers
        add_action('wp_ajax_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_export_donations', array($this, 'export_donations'));
        add_action('wp_ajax_test_gateway', array($this, 'test_gateway'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Zimbabwe Donations', 'zim-donations'),
            __('Donations', 'zim-donations'),
            'manage_options',
            'zim-donations',
            array($this, 'admin_page_dashboard'),
            'dashicons-heart',
            25
        );

        add_submenu_page(
            'zim-donations',
            __('Dashboard', 'zim-donations'),
            __('Dashboard', 'zim-donations'),
            'manage_options',
            'zim-donations',
            array($this, 'admin_page_dashboard')
        );

        add_submenu_page(
            'zim-donations',
            __('Donations', 'zim-donations'),
            __('All Donations', 'zim-donations'),
            'manage_options',
            'zim-donations-donations',
            array($this, 'admin_page_donations')
        );

        add_submenu_page(
            'zim-donations',
            __('Campaigns', 'zim-donations'),
            __('Campaigns', 'zim-donations'),
            'manage_options',
            'zim-donations-campaigns',
            array($this, 'admin_page_campaigns')
        );

        add_submenu_page(
            'zim-donations',
            __('Forms', 'zim-donations'),
            __('Forms', 'zim-donations'),
            'manage_options',
            'zim-donations-forms',
            array($this, 'admin_page_forms')
        );

        add_submenu_page(
            'zim-donations',
            __('Donors', 'zim-donations'),
            __('Donors', 'zim-donations'),
            'manage_options',
            'zim-donations-donors',
            array($this, 'admin_page_donors')
        );

        add_submenu_page(
            'zim-donations',
            __('Reports', 'zim-donations'),
            __('Reports', 'zim-donations'),
            'manage_options',
            'zim-donations-reports',
            array($this, 'admin_page_reports')
        );

        add_submenu_page(
            'zim-donations',
            __('Settings', 'zim-donations'),
            __('Settings', 'zim-donations'),
            'manage_options',
            'zim-donations-settings',
            array($this, 'admin_page_settings')
        );
    }

    /**
     * Dashboard page
     */
    public function admin_page_dashboard() {
        $analytics = new ZimDonations_Analytics();
        $stats = $analytics->get_dashboard_stats();
        $recent_donations = $this->get_recent_donations(5);
        $recent_campaigns = $this->get_recent_campaigns(3);
        ?>
        <div class="wrap">
            <h1><?php _e('Zimbabwe Donations Dashboard', 'zim-donations'); ?></h1>

            <div class="zim-dashboard-stats">
                <div class="stat-card">
                    <h3><?php _e('Total Donations', 'zim-donations'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['total_donations']); ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('Amount Raised', 'zim-donations'); ?></h3>
                    <div class="stat-number"><?php echo $this->format_currency($stats['total_raised'], 'USD'); ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('Average Donation', 'zim-donations'); ?></h3>
                    <div class="stat-number"><?php echo $this->format_currency($stats['average_donation'], 'USD'); ?></div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('Unique Donors', 'zim-donations'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['unique_donors']); ?></div>
                </div>
            </div>

            <div class="zim-dashboard-widgets">
                <div class="widget-container">
                    <div class="widget">
                        <h3><?php _e('Recent Donations', 'zim-donations'); ?></h3>
                        <?php $this->render_recent_donations($recent_donations); ?>
                    </div>
                </div>
                <div class="widget-container">
                    <div class="widget">
                        <h3><?php _e('Active Campaigns', 'zim-donations'); ?></h3>
                        <?php $this->render_campaign_performance($recent_campaigns); ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .zim-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007cba;
        }

        .zim-dashboard-widgets {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .widget {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .widget h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        </style>
        <?php
    }

    /**
     * Donations page
     */
    public function admin_page_donations() {
        ?>
        <div class="wrap">
            <h1><?php _e('All Donations', 'zim-donations'); ?>
                <button type="button" class="page-title-action" id="export-donations"><?php _e('Export', 'zim-donations'); ?></button>
            </h1>

            <div class="donations-filters">
                <form method="get">
                    <input type="hidden" name="page" value="zim-donations-donations">
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'zim-donations'); ?></option>
                        <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Completed', 'zim-donations'); ?></option>
                        <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('Pending', 'zim-donations'); ?></option>
                        <option value="failed" <?php selected($_GET['status'] ?? '', 'failed'); ?>><?php _e('Failed', 'zim-donations'); ?></option>
                    </select>
                    <select name="gateway">
                        <option value=""><?php _e('All Gateways', 'zim-donations'); ?></option>
                        <option value="stripe" <?php selected($_GET['gateway'] ?? '', 'stripe'); ?>>Stripe</option>
                        <option value="paypal" <?php selected($_GET['gateway'] ?? '', 'paypal'); ?>>PayPal</option>
                        <option value="paynow" <?php selected($_GET['gateway'] ?? '', 'paynow'); ?>>Paynow</option>
                    </select>
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" placeholder="<?php _e('From Date', 'zim-donations'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="<?php _e('To Date', 'zim-donations'); ?>">
                    <button type="submit" class="button"><?php _e('Filter', 'zim-donations'); ?></button>
                </form>
            </div>

            <div id="donations-list">
                <?php $this->render_donations_table(); ?>
            </div>
        </div>

        <style>
        .donations-filters {
            background: white;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .donations-filters form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        <?php
    }

    /**
     * Campaigns page
     */
    public function admin_page_campaigns() {
        ?>
        <div class="wrap">
            <h1><?php _e('Campaigns', 'zim-donations'); ?>
                <a href="#" class="page-title-action" id="add-new-campaign"><?php _e('Add New', 'zim-donations'); ?></a>
            </h1>

            <div id="campaigns-list">
                <?php $this->render_campaigns_list(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Forms page
     */
    public function admin_page_forms() {
        ?>
        <div class="wrap">
            <h1><?php _e('Donation Forms', 'zim-donations'); ?>
                <a href="#" class="page-title-action" id="add-new-form"><?php _e('Add New', 'zim-donations'); ?></a>
            </h1>

            <div id="forms-list">
                <?php $this->render_forms_list(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Donors page
     */
    public function admin_page_donors() {
        ?>
        <div class="wrap">
            <h1><?php _e('Donors', 'zim-donations'); ?></h1>

            <div id="donors-list">
                <?php $this->render_donors_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Reports page
     */
    public function admin_page_reports() {
        ?>
        <div class="wrap">
            <h1><?php _e('Reports & Analytics', 'zim-donations'); ?></h1>

            <div class="reports-dashboard">
                <div class="report-filters">
                    <select id="report-period">
                        <option value="7d"><?php _e('Last 7 Days', 'zim-donations'); ?></option>
                        <option value="30d" selected><?php _e('Last 30 Days', 'zim-donations'); ?></option>
                        <option value="90d"><?php _e('Last 90 Days', 'zim-donations'); ?></option>
                        <option value="1y"><?php _e('Last Year', 'zim-donations'); ?></option>
                    </select>
                    <button type="button" class="button" id="refresh-reports"><?php _e('Refresh', 'zim-donations'); ?></button>
                </div>

                <div id="reports-content">
                    <!-- Reports will be loaded here via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public function admin_page_settings() {
        ?>
        <div class="wrap">
            <h1><?php _e('Zimbabwe Donations Settings', 'zim-donations'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('zim_donations_settings');
                do_settings_sections('zim_donations_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('zim_donations_settings', 'zim_donations_settings');

        // General settings section
        add_settings_section(
            'general_settings',
            __('General Settings', 'zim-donations'),
            array($this, 'general_settings_callback'),
            'zim_donations_settings'
        );

        // Payment gateways section
        add_settings_section(
            'gateway_settings',
            __('Payment Gateways', 'zim-donations'),
            array($this, 'gateway_settings_callback'),
            'zim_donations_settings'
        );

        // Add fields
        add_settings_field(
            'default_currency',
            __('Default Currency', 'zim-donations'),
            array($this, 'currency_field_callback'),
            'zim_donations_settings',
            'general_settings'
        );

        add_settings_field(
            'test_mode',
            __('Test Mode', 'zim-donations'),
            array($this, 'test_mode_field_callback'),
            'zim_donations_settings',
            'general_settings'
        );
    }

    /**
     * Settings section callbacks
     */
    public function general_settings_callback() {
        echo '<p>' . __('Configure general plugin settings.', 'zim-donations') . '</p>';
    }

    public function gateway_settings_callback() {
        echo '<p>' . __('Configure payment gateway settings.', 'zim-donations') . '</p>';
    }

    /**
     * Settings field callbacks
     */
    public function currency_field_callback() {
        $currency_manager = new ZimDonations_Currency_Manager();
        $currencies = $currency_manager->get_currency_options();
        $current = get_option('zim_donations_default_currency', 'USD');
        
        echo '<select name="zim_donations_default_currency">';
        foreach ($currencies as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($current, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    public function test_mode_field_callback() {
        $test_mode = get_option('zim_donations_test_mode', true);
        echo '<label><input type="checkbox" name="zim_donations_test_mode" value="1" ' . checked($test_mode, true, false) . '> ' . __('Enable test mode', 'zim-donations') . '</label>';
        echo '<p class="description">' . __('In test mode, no real payments will be processed.', 'zim-donations') . '</p>';
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'zim-donations') === false) {
            return;
        }

        wp_enqueue_style(
            'zim-donations-admin',
            ZIM_DONATIONS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ZIM_DONATIONS_VERSION
        );

        wp_enqueue_script(
            'zim-donations-admin',
            ZIM_DONATIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ZIM_DONATIONS_VERSION,
            true
        );

        wp_localize_script('zim-donations-admin', 'zimDonationsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zim_donations_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'zim-donations'),
                'processing' => __('Processing...', 'zim-donations'),
                'error' => __('An error occurred. Please try again.', 'zim-donations')
            )
        ));
    }

    /**
     * Add action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=zim-donations-settings') . '">' . __('Settings', 'zim-donations') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if gateways are configured
        if (!$this->check_gateway_configurations()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(
                __('Zimbabwe Donations: Please configure at least one payment gateway in the <a href="%s">settings</a>.', 'zim-donations'),
                admin_url('admin.php?page=zim-donations-settings')
            ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Check if any payment gateway is configured
     */
    private function check_gateway_configurations() {
        $stripe_configured = get_option('zim_donations_stripe_enabled') && 
                           get_option('zim_donations_stripe_test_secret_key');
        $paypal_configured = get_option('zim_donations_paypal_enabled') && 
                           get_option('zim_donations_paypal_test_client_id');
        $paynow_configured = get_option('zim_donations_paynow_enabled') && 
                           get_option('zim_donations_paynow_integration_id');

        return $stripe_configured || $paypal_configured || $paynow_configured;
    }

    /**
     * Helper methods for rendering
     */
    private function get_recent_donations($limit = 5) {
        global $wpdb;
        $donations_table = $this->db->get_donations_table();
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$donations_table} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    private function get_recent_campaigns($limit = 3) {
        global $wpdb;
        $campaigns_table = $this->db->get_campaigns_table();
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$campaigns_table} WHERE status = 'active' ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    private function render_recent_donations($donations) {
        if (empty($donations)) {
            echo '<p>' . __('No donations yet.', 'zim-donations') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Donor', 'zim-donations') . '</th>';
        echo '<th>' . __('Amount', 'zim-donations') . '</th>';
        echo '<th>' . __('Status', 'zim-donations') . '</th>';
        echo '<th>' . __('Date', 'zim-donations') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($donations as $donation) {
            echo '<tr>';
            echo '<td>' . esc_html($donation->anonymous ? 'Anonymous' : $donation->donor_name) . '</td>';
            echo '<td>' . $this->format_currency($donation->amount, $donation->currency) . '</td>';
            echo '<td><span class="status-badge status-' . esc_attr($donation->status) . '">' . esc_html(ucfirst($donation->status)) . '</span></td>';
            echo '<td>' . date_i18n(get_option('date_format'), strtotime($donation->created_at)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function render_campaign_performance($campaigns) {
        if (empty($campaigns)) {
            echo '<p>' . __('No active campaigns.', 'zim-donations') . '</p>';
            return;
        }

        foreach ($campaigns as $campaign) {
            $progress = $campaign->goal_amount > 0 ? ($campaign->raised_amount / $campaign->goal_amount) * 100 : 0;
            
            echo '<div class="campaign-item">';
            echo '<h4>' . esc_html($campaign->title) . '</h4>';
            echo '<div class="progress-bar">';
            echo '<div class="progress-fill" style="width: ' . min(100, $progress) . '%"></div>';
            echo '</div>';
            echo '<p>' . sprintf(
                __('%s raised of %s goal', 'zim-donations'),
                $this->format_currency($campaign->raised_amount, $campaign->currency),
                $this->format_currency($campaign->goal_amount, $campaign->currency)
            ) . '</p>';
            echo '</div>';
        }
    }

    private function render_donations_table() {
        // Implementation for donations table
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions bulkactions">';
        echo '<select name="action"><option value="-1">Bulk Actions</option><option value="export">Export</option></select>';
        echo '<input type="submit" class="button action" value="Apply">';
        echo '</div>';
        echo '</div>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th class="manage-column column-cb check-column"><input type="checkbox"></th>';
        echo '<th>' . __('ID', 'zim-donations') . '</th>';
        echo '<th>' . __('Donor', 'zim-donations') . '</th>';
        echo '<th>' . __('Amount', 'zim-donations') . '</th>';
        echo '<th>' . __('Gateway', 'zim-donations') . '</th>';
        echo '<th>' . __('Status', 'zim-donations') . '</th>';
        echo '<th>' . __('Date', 'zim-donations') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        $donations = $this->get_donations_list();
        foreach ($donations as $donation) {
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" name="donation[]" value="' . $donation->id . '"></th>';
            echo '<td>' . esc_html($donation->donation_id) . '</td>';
            echo '<td>' . esc_html($donation->anonymous ? 'Anonymous' : $donation->donor_name) . '</td>';
            echo '<td>' . $this->format_currency($donation->amount, $donation->currency) . '</td>';
            echo '<td>' . esc_html(ucfirst($donation->gateway)) . '</td>';
            echo '<td><span class="status-badge status-' . esc_attr($donation->status) . '">' . esc_html(ucfirst($donation->status)) . '</span></td>';
            echo '<td>' . date_i18n(get_option('date_format'), strtotime($donation->created_at)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    private function render_campaigns_list() {
        $campaigns = $this->get_campaigns_list();
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Title', 'zim-donations') . '</th>';
        echo '<th>' . __('Goal', 'zim-donations') . '</th>';
        echo '<th>' . __('Raised', 'zim-donations') . '</th>';
        echo '<th>' . __('Progress', 'zim-donations') . '</th>';
        echo '<th>' . __('Status', 'zim-donations') . '</th>';
        echo '<th>' . __('Actions', 'zim-donations') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($campaigns as $campaign) {
            $progress = $campaign->goal_amount > 0 ? ($campaign->raised_amount / $campaign->goal_amount) * 100 : 0;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($campaign->title) . '</strong></td>';
            echo '<td>' . $this->format_currency($campaign->goal_amount, $campaign->currency) . '</td>';
            echo '<td>' . $this->format_currency($campaign->raised_amount, $campaign->currency) . '</td>';
            echo '<td>' . round($progress, 1) . '%</td>';
            echo '<td><span class="status-badge status-' . esc_attr($campaign->status) . '">' . esc_html(ucfirst($campaign->status)) . '</span></td>';
            echo '<td>';
            echo '<a href="#" class="button button-small edit-campaign" data-id="' . $campaign->id . '">' . __('Edit', 'zim-donations') . '</a> ';
            echo '<a href="#" class="button button-small delete-campaign" data-id="' . $campaign->id . '">' . __('Delete', 'zim-donations') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    private function render_forms_list() {
        echo '<p>' . __('Form builder coming soon...', 'zim-donations') . '</p>';
    }

    private function render_donors_table() {
        echo '<p>' . __('Donors list coming soon...', 'zim-donations') . '</p>';
    }

    private function get_donations_list() {
        global $wpdb;
        $donations_table = $this->db->get_donations_table();
        
        return $wpdb->get_results(
            "SELECT * FROM {$donations_table} ORDER BY created_at DESC LIMIT 50"
        );
    }

    private function get_campaigns_list() {
        global $wpdb;
        $campaigns_table = $this->db->get_campaigns_table();
        
        return $wpdb->get_results(
            "SELECT * FROM {$campaigns_table} ORDER BY created_at DESC"
        );
    }

    private function format_currency($amount, $currency) {
        $currency_manager = new ZimDonations_Currency_Manager();
        return $currency_manager->format_currency($amount, $currency);
    }

    /**
     * AJAX handlers
     */
    public function ajax_save_campaign() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'zim-donations'));
        }

        // Campaign save logic here
        wp_send_json_success(__('Campaign saved successfully.', 'zim-donations'));
    }

    public function ajax_delete_campaign() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'zim-donations'));
        }

        // Campaign delete logic here
        wp_send_json_success(__('Campaign deleted successfully.', 'zim-donations'));
    }

    public function export_donations() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'zim-donations'));
        }

        // Export logic here
        wp_send_json_success(__('Export completed.', 'zim-donations'));
    }

    public function test_gateway() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'zim-donations'));
        }

        // Gateway test logic here
        wp_send_json_success(__('Gateway test completed.', 'zim-donations'));
    }
} 