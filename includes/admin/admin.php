<?php

/**
 * Admin Class for Help Me Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_filter('plugin_action_links_' . HELPME_DONATIONS_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // AJAX handlers
        add_action('wp_ajax_export_donations', array($this, 'export_donations'));
        add_action('wp_ajax_test_gateway', array($this, 'test_gateway'));
        add_action('wp_ajax_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_load_campaign', array($this, 'load_campaign'));
        add_action('wp_ajax_delete_campaign', array($this, 'delete_campaign'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        $capability = 'manage_options';

        // Main menu
        add_menu_page(
            __('Help Me Donations', 'helpme-donations'),
            __('Donations', 'helpme-donations'),
            $capability,
            'helpme-donations',
            array($this, 'admin_page_dashboard'),
            'dashicons-heart',
            30
        );

        // Dashboard
        add_submenu_page(
            'helpme-donations',
            __('Dashboard', 'helpme-donations'),
            __('Dashboard', 'helpme-donations'),
            $capability,
            'helpme-donations',
            array($this, 'admin_page_dashboard')
        );

        // Donations
        add_submenu_page(
            'helpme-donations',
            __('All Donations', 'helpme-donations'),
            __('All Donations', 'helpme-donations'),
            $capability,
            'helpme-donations-list',
            array($this, 'admin_page_donations')
        );

        // Campaigns
        add_submenu_page(
            'helpme-donations',
            __('Campaigns', 'helpme-donations'),
            __('Campaigns', 'helpme-donations'),
            $capability,
            'helpme-donations-campaigns',
            array($this, 'admin_page_campaigns')
        );

        // Forms
        add_submenu_page(
            'helpme-donations',
            __('Forms', 'helpme-donations'),
            __('Forms', 'helpme-donations'),
            $capability,
            'helpme-donations-forms',
            array($this, 'admin_page_forms')
        );

        // Donors
        add_submenu_page(
            'helpme-donations',
            __('Donors', 'helpme-donations'),
            __('Donors', 'helpme-donations'),
            $capability,
            'helpme-donations-donors',
            array($this, 'admin_page_donors')
        );

        // Reports
        add_submenu_page(
            'helpme-donations',
            __('Reports', 'helpme-donations'),
            __('Reports', 'helpme-donations'),
            $capability,
            'helpme-donations-reports',
            array($this, 'admin_page_reports')
        );

        // Settings
        add_submenu_page(
            'helpme-donations',
            __('Settings', 'helpme-donations'),
            __('Settings', 'helpme-donations'),
            $capability,
            'helpme-donations-settings',
            array($this, 'admin_page_settings')
        );
    }

    /**
     * Dashboard page
     */
    public function admin_page_dashboard()
    {
        $stats = $this->get_dashboard_stats();

?>
        <div class="wrap">
            <h1><?php _e('Help Me Donations Dashboard', 'helpme-donations'); ?></h1>

            <div class="helpme-dashboard">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <h3><?php echo $this->format_currency($stats['total_raised'], 'USD'); ?></h3>
                            <p><?php _e('Total Raised', 'helpme-donations'); ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_donations']); ?></h3>
                            <p><?php _e('Total Donations', 'helpme-donations'); ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_donors']); ?></h3>
                            <p><?php _e('Total Donors', 'helpme-donations'); ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">📈</div>
                        <div class="stat-content">
                            <h3><?php echo $this->format_currency($stats['average_donation'], 'USD'); ?></h3>
                            <p><?php _e('Average Donation', 'helpme-donations'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-section">
                    <h2><?php _e('Quick Actions', 'helpme-donations'); ?></h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=helpme-donations-campaigns'); ?>" class="action-button">
                            <span class="dashicons dashicons-megaphone"></span>
                            <?php _e('Create Campaign', 'helpme-donations'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=helpme-donations-forms'); ?>" class="action-button">
                            <span class="dashicons dashicons-forms"></span>
                            <?php _e('Create Form', 'helpme-donations'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=helpme-donations-reports'); ?>" class="action-button">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php _e('View Reports', 'helpme-donations'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings'); ?>" class="action-button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Settings', 'helpme-donations'); ?>
                        </a>
                    </div>
                </div>

                <!-- Getting Started -->
                <div class="dashboard-section">
                    <h2><?php _e('Getting Started', 'helpme-donations'); ?></h2>
                    <div class="getting-started">
                        <div class="step">
                            <h3><?php _e('1. Configure Payment Gateways', 'helpme-donations'); ?></h3>
                            <p><?php _e('Set up your payment methods to start accepting donations.', 'helpme-donations'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings'); ?>" class="button button-primary">
                                <?php _e('Configure Now', 'helpme-donations'); ?>
                            </a>
                        </div>

                        <div class="step">
                            <h3><?php _e('2. Create Your First Campaign', 'helpme-donations'); ?></h3>
                            <p><?php _e('Set up a donation campaign to organize your fundraising efforts.', 'helpme-donations'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=helpme-donations-campaigns'); ?>" class="button button-secondary">
                                <?php _e('Create Campaign', 'helpme-donations'); ?>
                            </a>
                        </div>

                        <div class="step">
                            <h3><?php _e('3. Add Donation Form', 'helpme-donations'); ?></h3>
                            <p><?php _e('Use shortcode [helpme_donation_form] to add donation forms to your pages.', 'helpme-donations'); ?></p>
                            <input type="text" readonly value="[helpme_donation_form]" class="shortcode-input" onclick="this.select()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .helpme-dashboard {
                margin-top: 20px;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .stat-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                display: flex;
                align-items: center;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .stat-icon {
                font-size: 48px;
                margin-right: 20px;
            }

            .stat-content h3 {
                margin: 0;
                font-size: 32px;
                color: #333;
            }

            .stat-content p {
                margin: 5px 0 0 0;
                color: #666;
                font-size: 14px;
            }

            .dashboard-section {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .dashboard-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }

            .quick-actions {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .action-button {
                display: flex;
                align-items: center;
                padding: 15px;
                background: #0073aa;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                transition: background 0.3s ease;
            }

            .action-button:hover {
                background: #005a87;
                color: white;
            }

            .action-button .dashicons {
                margin-right: 10px;
            }

            .getting-started {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }

            .step {
                padding: 20px;
                background: #f9f9f9;
                border-radius: 4px;
                border-left: 4px solid #0073aa;
            }

            .step h3 {
                margin-top: 0;
                color: #0073aa;
            }

            .shortcode-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #f9f9f9;
                margin-top: 10px;
            }
        </style>
    <?php
    }

    /**
     * Donations list page
     */
    public function admin_page_donations()
    {
        global $wpdb;
        $donations_table = $wpdb->prefix . 'helpme_donations';
        $donors_table = $wpdb->prefix . 'helpme_donors';

        // Handle delete action
        if (isset($_GET['action'], $_GET['donation']) && $_GET['action'] === 'delete') {
            $donation_id = absint($_GET['donation']);
            $wpdb->delete($donations_table, ['id' => $donation_id]);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Donation deleted.', 'helpme-donations') . '</p></div>';
        }

        $results = $wpdb->get_results("
        SELECT d.*, dn.name as donor_name
        FROM $donations_table d
        LEFT JOIN $donors_table dn ON d.donor_id = dn.id
        ORDER BY d.created_at DESC
        LIMIT 100
    ");

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('All Donations', 'helpme-donations') . '</h1>';

        if (!empty($results)) {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                <th>' . __('ID', 'helpme-donations') . '</th>
                <th>' . __('Donation ID', 'helpme-donations') . '</th>
                <th>' . __('Donor', 'helpme-donations') . '</th>
                <th>' . __('Amount', 'helpme-donations') . '</th>
                <th>' . __('Status', 'helpme-donations') . '</th>
                <th>' . __('Date', 'helpme-donations') . '</th>
                <th>' . __('Actions', 'helpme-donations') . '</th>
              </tr></thead><tbody>';

            foreach ($results as $row) {
                $edit_url = admin_url('admin.php?page=helpme-donation-edit&donation=' . $row->id);
                $delete_url = wp_nonce_url(admin_url('admin.php?page=helpme-donations-list&action=delete&donation=' . $row->id), 'delete_donation_' . $row->id);

                echo '<tr>';
                echo '<td>' . esc_html($row->id) . '</td>';
                echo '<td>' . esc_html($row->donation_id) . '</td>';
                echo '<td>' . esc_html($row->donor_name ?: $row->donor_email) . '</td>';
                echo '<td>' . esc_html(number_format($row->amount, 2)) . '</td>';
                echo '<td>' . esc_html(ucfirst($row->status)) . '</td>';
                echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($row->created_at))) . '</td>';
                echo '<td>
                <a href="' . esc_url($edit_url) . '">' . __('Edit', 'helpme-donations') . '</a> | 
                <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this donation?', 'helpme-donations')) . '\');">' . __('Delete', 'helpme-donations') . '</a>
              </td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No donations found.', 'helpme-donations') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Campaigns page
     */
    public function admin_page_campaigns()
    {
    ?>
        <div class="wrap">
            <h1><?php _e('Campaigns', 'helpme-donations'); ?>
                <a href="#" class="page-title-action" id="add-new-campaign"><?php _e('Add New', 'helpme-donations'); ?></a>
            </h1>

            <div class="campaigns-placeholder">
                <h2><?php _e('No campaigns yet', 'helpme-donations'); ?></h2>
                <p><?php _e('Create your first campaign to organize your fundraising efforts.', 'helpme-donations'); ?></p>
                <button type="button" class="button button-primary" id="create-first-campaign">
                    <?php _e('Create Your First Campaign', 'helpme-donations'); ?>
                </button>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#create-first-campaign, #add-new-campaign').on('click', function(e) {
                    e.preventDefault();
                    alert('<?php _e("Campaign creation feature coming soon!", "helpme-donations"); ?>');
                });
            });
        </script>
    <?php
    }

    /**
     * Forms page
     */
    public function admin_page_forms()
    {
    ?>
        <div class="wrap">
            <h1><?php _e('Donation Forms', 'helpme-donations'); ?></h1>

            <div class="forms-info">
                <h2><?php _e('Default Form Available', 'helpme-donations'); ?></h2>
                <p><?php _e('Use the shortcode below to add a donation form to any page or post:', 'helpme-donations'); ?></p>

                <div class="shortcode-box">
                    <strong><?php _e('Basic Form:', 'helpme-donations'); ?></strong><br>
                    <input type="text" readonly value="[helpme_donation_form]" class="shortcode-input" onclick="this.select()">
                </div>

                <div class="shortcode-box">
                    <strong><?php _e('With Custom Amounts:', 'helpme-donations'); ?></strong><br>
                    <input type="text" readonly value='[helpme_donation_form amounts="10,25,50,100"]' class="shortcode-input" onclick="this.select()">
                </div>

                <div class="shortcode-box">
                    <strong><?php _e('With Campaign:', 'helpme-donations'); ?></strong><br>
                    <input type="text" readonly value='[helpme_donation_form campaign_id="1"]' class="shortcode-input" onclick="this.select()">
                </div>
            </div>
        </div>

        <style>
            .shortcode-box {
                background: #f9f9f9;
                padding: 15px;
                margin: 10px 0;
                border-left: 4px solid #0073aa;
            }

            .shortcode-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-top: 5px;
                background: white;
            }
        </style>
    <?php
    }

    /**
     * Donors page
     */
    public function admin_page_donors()
    {
        global $wpdb;
        $donors_table = $wpdb->prefix . 'helpme_donors';

        // Handle delete
        if (isset($_GET['action'], $_GET['donor']) && $_GET['action'] === 'delete') {
            $donor_id = absint($_GET['donor']);
            check_admin_referer('delete_donor_' . $donor_id);
            $wpdb->delete($donors_table, ['id' => $donor_id]);
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Donor deleted.', 'helpme-donations') . '</p></div>';
        }

        // Fetch donors
        $results = $wpdb->get_results("SELECT * FROM $donors_table ORDER BY created_at DESC LIMIT 100");

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Donors', 'helpme-donations') . '</h1>';

        if (!empty($results)) {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                <th>' . __('ID', 'helpme-donations') . '</th>
                <th>' . __('Name', 'helpme-donations') . '</th>
                <th>' . __('Email', 'helpme-donations') . '</th>
                <th>' . __('Phone', 'helpme-donations') . '</th>
                <th>' . __('Total Donated', 'helpme-donations') . '</th>
                <th>' . __('Donation Count', 'helpme-donations') . '</th>
                <th>' . __('Status', 'helpme-donations') . '</th>
                <th>' . __('Actions', 'helpme-donations') . '</th>
              </tr></thead><tbody>';

            foreach ($results as $row) {
                $edit_url = admin_url('admin.php?page=helpme-donor-edit&donor=' . $row->id);
                $delete_url = wp_nonce_url(admin_url('admin.php?page=helpme-donations-donors&action=delete&donor=' . $row->id), 'delete_donor_' . $row->id);

                echo '<tr>';
                echo '<td>' . esc_html($row->id) . '</td>';
                echo '<td>' . esc_html($row->name) . '</td>';
                echo '<td>' . esc_html($row->email) . '</td>';
                echo '<td>' . esc_html($row->phone) . '</td>';
                echo '<td>' . esc_html(number_format($row->total_donated, 2)) . '</td>';
                echo '<td>' . esc_html($row->donation_count) . '</td>';
                echo '<td>' . esc_html($row->status) . '</td>';
                echo '<td>
                <a href="' . esc_url($edit_url) . '">' . __('Edit', 'helpme-donations') . '</a> | 
                <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this donor?', 'helpme-donations')) . '\');">' . __('Delete', 'helpme-donations') . '</a>
              </td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No donors found.', 'helpme-donations') . '</p>';
        }

        echo '</div>';
    }

    /**
     * Reports page
     */
    public function admin_page_reports()
    {
    ?>
        <div class="wrap">
            <h1><?php _e('Reports', 'helpme-donations'); ?></h1>

            <div class="reports-placeholder">
                <h2><?php _e('Reports & Analytics', 'helpme-donations'); ?></h2>
                <p><?php _e('Detailed reports and analytics will be available once you have donation data.', 'helpme-donations'); ?></p>
            </div>
        </div>
    <?php
    }

    /**
     * Settings page
     */
    public function admin_page_settings()
    {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
        <div class="wrap">
            <h1><?php _e('Donation Settings', 'helpme-donations'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings&tab=general'); ?>"
                    class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'helpme-donations'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings&tab=gateways'); ?>"
                    class="nav-tab <?php echo $current_tab === 'gateways' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Payment Gateways', 'helpme-donations'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=helpme-donations-settings&tab=emails'); ?>"
                    class="nav-tab <?php echo $current_tab === 'emails' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Emails', 'helpme-donations'); ?>
                </a>
            </nav>

            <form method="post" action="">
                <?php wp_nonce_field('helpme_donations_settings', 'helpme_donations_nonce'); ?>

                <?php
                switch ($current_tab) {
                    case 'general':
                        $this->render_general_settings();
                        break;
                    case 'gateways':
                        $this->render_gateway_settings();
                        break;
                    case 'emails':
                        $this->render_email_settings();
                        break;
                    default:
                        $this->render_general_settings();
                }
                ?>

                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings()
    {
    ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Default Currency', 'helpme-donations'); ?></th>
                <td>
                    <select name="helpme_donations_default_currency">
                        <?php
                        $currencies = array(
                            'USD' => 'US Dollar ($)',
                            'ZIG' => 'Zimbabwean Gold (ZiG)',
                            'EUR' => 'Euro (€)',
                            'GBP' => 'British Pound (£)',
                            'ZAR' => 'South African Rand (R)'
                        );
                        $current = get_option('helpme_donations_default_currency', 'USD');
                        foreach ($currencies as $code => $name) {
                            echo '<option value="' . esc_attr($code) . '"' . selected($current, $code, false) . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Test Mode', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_test_mode" value="1"
                            <?php checked(get_option('helpme_donations_test_mode', 1), 1); ?>>
                        <?php _e('Enable test mode (use sandbox/test API keys)', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Minimum Donation Amount', 'helpme-donations'); ?></th>
                <td>
                    <input type="number" name="helpme_donations_minimum_amount"
                        value="<?php echo esc_attr(get_option('helpme_donations_minimum_amount', 1)); ?>"
                        min="0" step="0.01" class="small-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Maximum Donation Amount', 'helpme-donations'); ?></th>
                <td>
                    <input type="number" name="helpme_donations_maximum_amount"
                        value="<?php echo esc_attr(get_option('helpme_donations_maximum_amount', 10000)); ?>"
                        min="1" step="0.01" class="regular-text">
                </td>
            </tr>
        </table>
    <?php
    }

    /**
     * Render gateway settings
     */
    private function render_gateway_settings()
    {
    ?>
        <h3><?php _e('Stripe Settings', 'helpme-donations'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Stripe', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_stripe_enabled" value="1"
                            <?php checked(get_option('helpme_donations_stripe_enabled', 0), 1); ?>>
                        <?php _e('Enable Stripe payments', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Test Publishable Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_stripe_test_publishable_key"
                        value="<?php echo esc_attr(get_option('helpme_donations_stripe_test_publishable_key', '')); ?>"
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Test Secret Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_stripe_test_secret_key"
                        value="<?php echo esc_attr(get_option('helpme_donations_stripe_test_secret_key', '')); ?>"
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Live Publishable Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_stripe_live_publishable_key"
                        value="<?php echo esc_attr(get_option('helpme_donations_stripe_live_publishable_key', '')); ?>"
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Live Secret Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_stripe_live_secret_key"
                        value="<?php echo esc_attr(get_option('helpme_donations_stripe_live_secret_key', '')); ?>"
                        class="regular-text">
                </td>
            </tr>
        </table>

        <h3><?php _e('PayPal Settings', 'helpme-donations'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable PayPal', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_paypal_enabled" value="1"
                            <?php checked(get_option('helpme_donations_paypal_enabled', 0), 1); ?>>
                        <?php _e('Enable PayPal payments', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Test Client ID', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_paypal_test_client_id"
                        value="<?php echo esc_attr(get_option('helpme_donations_paypal_test_client_id', '')); ?>"
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Test Client Secret', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_paypal_test_client_secret"
                        value="<?php echo esc_attr(get_option('helpme_donations_paypal_test_client_secret', '')); ?>"
                        class="regular-text">
                </td>
            </tr>
        </table>

        <h3><?php _e('Paynow Settings (EcoCash/OneMoney)', 'helpme-donations'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Paynow', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_paynow_enabled" value="1"
                            <?php checked(get_option('helpme_donations_paynow_enabled', 0), 1); ?>>
                        <?php _e('Enable Paynow payments (EcoCash/OneMoney)', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Integration ID', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_paynow_integration_id"
                        value="<?php echo esc_attr(get_option('helpme_donations_paynow_integration_id', '')); ?>"
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Integration Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_paynow_integration_key"
                        value="<?php echo esc_attr(get_option('helpme_donations_paynow_integration_key', '')); ?>"
                        class="regular-text">
                </td>
            </tr>
        </table>
    <?php
    }

    /**
     * Render email settings
     */
    private function render_email_settings()
    {
    ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Admin Email', 'helpme-donations'); ?></th>
                <td>
                    <input type="email" name="helpme_donations_admin_email"
                        value="<?php echo esc_attr(get_option('helpme_donations_admin_email', get_option('admin_email'))); ?>"
                        class="regular-text">
                    <p class="description"><?php _e('Email address to receive donation notifications', 'helpme-donations'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Send Admin Notifications', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_send_admin_notifications" value="1"
                            <?php checked(get_option('helpme_donations_send_admin_notifications', 1), 1); ?>>
                        <?php _e('Send email notifications to admin when donations are received', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Send Donor Confirmations', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_send_donor_confirmations" value="1"
                            <?php checked(get_option('helpme_donations_send_donor_confirmations', 1), 1); ?>>
                        <?php _e('Send confirmation emails to donors', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('From Name', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_from_name"
                        value="<?php echo esc_attr(get_option('helpme_donations_from_name', get_bloginfo('name'))); ?>"
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('From Email', 'helpme-donations'); ?></th>
                <td>
                    <input type="email" name="helpme_donations_from_email"
                        value="<?php echo esc_attr(get_option('helpme_donations_from_email', get_option('admin_email'))); ?>"
                        class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('helpme_donations_settings', 'helpme_donations_nonce')) {
            wp_die(__('Unauthorized access', 'helpme-donations'));
        }

        $options = array(
            'helpme_donations_default_currency',
            'helpme_donations_test_mode',
            'helpme_donations_minimum_amount',
            'helpme_donations_maximum_amount',
            'helpme_donations_stripe_enabled',
            'helpme_donations_stripe_test_publishable_key',
            'helpme_donations_stripe_test_secret_key',
            'helpme_donations_stripe_live_publishable_key',
            'helpme_donations_stripe_live_secret_key',
            'helpme_donations_paypal_enabled',
            'helpme_donations_paypal_test_client_id',
            'helpme_donations_paypal_test_client_secret',
            'helpme_donations_paynow_enabled',
            'helpme_donations_paynow_integration_id',
            'helpme_donations_paynow_integration_key',
            'helpme_donations_inbucks_enabled',
            'helpme_donations_zimswitch_enabled',
            'helpme_donations_admin_email',
            'helpme_donations_send_admin_notifications',
            'helpme_donations_send_donor_confirmations',
            'helpme_donations_from_name',
            'helpme_donations_from_email'
        );

        foreach ($options as $option) {
            if (isset($_POST[$option])) {
                $value = sanitize_text_field($_POST[$option]);
                update_option($option, $value);
            } else {
                // Handle checkboxes that aren't set
                if (
                    strpos($option, '_enabled') !== false ||
                    strpos($option, '_test_mode') !== false ||
                    strpos($option, '_notifications') !== false ||
                    strpos($option, '_confirmations') !== false
                ) {
                    update_option($option, 0);
                }
            }
        }

        // Update the enabled gateways array based on individual gateway settings
        $enabled_gateways = array();

        if (get_option('helpme_donations_stripe_enabled', 0)) {
            $enabled_gateways[] = 'stripe';
        }

        if (get_option('helpme_donations_paypal_enabled', 0)) {
            $enabled_gateways[] = 'paypal';
        }

        if (get_option('helpme_donations_paynow_enabled', 0)) {
            $enabled_gateways[] = 'paynow';
        }

        // Check for other gateways that might be enabled
        if (get_option('helpme_donations_inbucks_enabled', 0)) {
            $enabled_gateways[] = 'inbucks';
        }

        if (get_option('helpme_donations_zimswitch_enabled', 0)) {
            $enabled_gateways[] = 'zimswitch';
        }

        // Update the enabled gateways option (this will be stored as a serialized array)
        update_option('helpme_donations_enabled_gateways', $enabled_gateways);

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'helpme-donations') . '</p></div>';
        });
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        // Settings initialization handled in save_settings method
    }

    /**
     * Add plugin action links
     */
    public function add_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=helpme-donations-settings') . '">' . __('Settings', 'helpme-donations') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Admin notices
     */
    public function admin_notices()
    {
        // Check if plugin was just activated
        if (get_option('helpme_donations_activated')) {
            delete_option('helpme_donations_activated');
        ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Help Me Donations plugin activated successfully! Please configure your payment gateways in the settings.', 'helpme-donations'); ?></p>
                <p><a href="<?php echo admin_url('admin.php?page=helpme-donations-settings'); ?>" class="button button-primary"><?php _e('Configure Settings', 'helpme-donations'); ?></a></p>
            </div>
        <?php
        }

        // Check for missing configurations
        $this->check_gateway_configurations();
    }

    /**
     * Check gateway configurations
     */
    private function check_gateway_configurations()
    {
        $stripe_enabled = get_option('helpme_donations_stripe_enabled', 0);
        $paypal_enabled = get_option('helpme_donations_paypal_enabled', 0);
        $paynow_enabled = get_option('helpme_donations_paynow_enabled', 0);
        $enabled_gateways = get_option('helpme_donations_enabled_gateways', array());

        $missing_configs = array();

        if ($stripe_enabled && empty(get_option('helpme_donations_stripe_test_secret_key'))) {
            $missing_configs[] = 'Stripe';
        }

        if ($paypal_enabled && empty(get_option('helpme_donations_paypal_test_client_id'))) {
            $missing_configs[] = 'PayPal';
        }

        if ($paynow_enabled && empty(get_option('helpme_donations_paynow_integration_id'))) {
            $missing_configs[] = 'Paynow';
        }

        if (!empty($missing_configs)) {
        ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    printf(
                        __('Please configure the following payment gateways: %s. <a href="%s">Go to Settings</a>', 'helpme-donations'),
                        implode(', ', $missing_configs),
                        admin_url('admin.php?page=helpme-donations-settings&tab=gateways')
                    );
                    ?>
                </p>
            </div>
        <?php
        }

        // Check if no gateways are enabled
        if (!empty($enabled_gateways) && is_array($enabled_gateways)) {
        ?>
            <div class="notice notice-info">
                <p>
                    <?php
                    printf(
                        __('No payment gateways are currently enabled. <a href="%s">Configure payment gateways</a> to start accepting donations.', 'helpme-donations'),
                        admin_url('admin.php?page=helpme-donations-settings&tab=gateways')
                    );
                    ?>
                </p>
            </div>
<?php
        }
    }

    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats()
    {
        global $wpdb;

        $donations_table = $wpdb->prefix . 'helpme_donations';
        $donors_table = $wpdb->prefix . 'helpme_donors';

        $total_raised = $wpdb->get_var("SELECT SUM(amount) FROM $donations_table WHERE status = 'paid'");
        $total_donations = $wpdb->get_var("SELECT COUNT(*) FROM $donations_table WHERE status = 'paid'");
        $total_donors = $wpdb->get_var("SELECT COUNT(*) FROM $donors_table WHERE status = 'active'");

        $average_donation = ($total_donations > 0) ? $total_raised / $total_donations : 0;

        return array(
            'total_raised' => $total_raised,
            'total_donations' => $total_donations,
            'total_donors' => $total_donors,
            'average_donation' => $average_donation
        );
    }

    /**
     * Format currency amount
     */
    private function format_currency($amount, $currency)
    {
        $symbols = array(
            'USD' => '$',
            'ZIG' => 'ZiG',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format($amount, 2);
    }

    /**
     * AJAX handlers (placeholders for now)
     */
    public function export_donations()
    {
        wp_send_json_error(__('Feature coming soon', 'helpme-donations'));
    }

    public function test_gateway()
    {
        wp_send_json_error(__('Feature coming soon', 'helpme-donations'));
    }

    public function save_campaign()
    {
        wp_send_json_error(__('Feature coming soon', 'helpme-donations'));
    }

    public function load_campaign()
    {
        wp_send_json_error(__('Feature coming soon', 'helpme-donations'));
    }

    public function delete_campaign()
    {
        wp_send_json_error(__('Feature coming soon', 'helpme-donations'));
    }
}
