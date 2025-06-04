<?php
/**
 * Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('plugin_action_links_' . HELPME_DONATIONS_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_ajax_export_donations', array($this, 'export_donations'));
        add_action('wp_ajax_test_gateway', array($this, 'test_gateway'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $capability = 'manage_options';
        
        // Main menu
        add_menu_page(
            __('Zimbabwe Donations', 'helpme-donations'),
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
    public function admin_page_dashboard() {
        $stats = $this->get_dashboard_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Zimbabwe Donations Dashboard', 'helpme-donations'); ?></h1>
            
            <div class="zim-dashboard">
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
                
                <!-- Recent Activity -->
                <div class="dashboard-section">
                    <h2><?php _e('Recent Donations', 'helpme-donations'); ?></h2>
                    <div class="recent-donations">
                        <?php $this->render_recent_donations($stats['recent_donations']); ?>
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
                
                <!-- Campaign Performance -->
                <div class="dashboard-section">
                    <h2><?php _e('Top Performing Campaigns', 'helpme-donations'); ?></h2>
                    <div class="campaign-performance">
                        <?php $this->render_campaign_performance($stats['top_campaigns']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .zim-dashboard {
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        
        .donation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .donation-item:last-child {
            border-bottom: none;
        }
        
        .donor-info {
            flex: 1;
        }
        
        .donor-name {
            font-weight: bold;
            color: #333;
        }
        
        .donation-meta {
            font-size: 12px;
            color: #666;
        }
        
        .donation-amount {
            font-weight: bold;
            color: #28a745;
        }
        
        .campaign-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .campaign-item:last-child {
            border-bottom: none;
        }
        
        .campaign-info {
            flex: 1;
        }
        
        .campaign-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .campaign-stats {
            font-size: 12px;
            color: #666;
        }
        
        .campaign-raised {
            font-weight: bold;
            color: #28a745;
            font-size: 16px;
        }
        </style>
        <?php
    }
    
    /**
     * Donations list page
     */
    public function admin_page_donations() {
        $donations = $this->get_donations_list();
        
        ?>
        <div class="wrap">
            <h1><?php _e('All Donations', 'helpme-donations'); ?></h1>
            
            <div class="donations-filters">
                <form method="get">
                    <input type="hidden" name="page" value="helpme-donations-list">
                    
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'helpme-donations'); ?></option>
                        <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php _e('Completed', 'helpme-donations'); ?></option>
                        <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php _e('Pending', 'helpme-donations'); ?></option>
                        <option value="failed" <?php selected($_GET['status'] ?? '', 'failed'); ?>><?php _e('Failed', 'helpme-donations'); ?></option>
                    </select>
                    
                    <select name="gateway">
                        <option value=""><?php _e('All Gateways', 'helpme-donations'); ?></option>
                        <option value="stripe" <?php selected($_GET['gateway'] ?? '', 'stripe'); ?>><?php _e('Stripe', 'helpme-donations'); ?></option>
                        <option value="paypal" <?php selected($_GET['gateway'] ?? '', 'paypal'); ?>><?php _e('PayPal', 'helpme-donations'); ?></option>
                        <option value="paynow" <?php selected($_GET['gateway'] ?? '', 'paynow'); ?>><?php _e('Paynow', 'helpme-donations'); ?></option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" placeholder="<?php _e('From Date', 'helpme-donations'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="<?php _e('To Date', 'helpme-donations'); ?>">
                    
                    <input type="submit" class="button" value="<?php _e('Filter', 'helpme-donations'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=helpme-donations-list'); ?>" class="button"><?php _e('Reset', 'helpme-donations'); ?></a>
                    
                    <button type="button" class="button button-primary" id="export-donations"><?php _e('Export', 'helpme-donations'); ?></button>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'helpme-donations'); ?></th>
                        <th><?php _e('Donor', 'helpme-donations'); ?></th>
                        <th><?php _e('Amount', 'helpme-donations'); ?></th>
                        <th><?php _e('Gateway', 'helpme-donations'); ?></th>
                        <th><?php _e('Status', 'helpme-donations'); ?></th>
                        <th><?php _e('Date', 'helpme-donations'); ?></th>
                        <th><?php _e('Actions', 'helpme-donations'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation): ?>
                        <tr>
                            <td><?php echo esc_html($donation->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($donation->donor_name); ?></strong><br>
                                <small><?php echo esc_html($donation->donor_email); ?></small>
                            </td>
                            <td><?php echo $this->format_currency($donation->amount, $donation->currency); ?></td>
                            <td><?php echo esc_html(ucfirst($donation->gateway)); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($donation->status); ?>">
                                    <?php echo esc_html(ucfirst($donation->status)); ?>
                                </span>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($donation->created_at)); ?></td>
                            <td>
                                <a href="#" class="button button-small view-donation" data-id="<?php echo esc_attr($donation->id); ?>"><?php _e('View', 'helpme-donations'); ?></a>
                                <?php if ($donation->status === 'completed'): ?>
                                    <a href="#" class="button button-small resend-receipt" data-id="<?php echo esc_attr($donation->id); ?>"><?php _e('Resend Receipt', 'helpme-donations'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .donations-filters {
            background: white;
            padding: 15px;
            margin: 20px 0;
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
            <h1><?php _e('Campaigns', 'helpme-donations'); ?>
                <a href="#" class="page-title-action" id="add-new-campaign"><?php _e('Add New', 'helpme-donations'); ?></a>
            </h1>

            <div id="campaigns-list">
                <?php $this->render_campaigns_list(); ?>
            </div>

            <!-- Campaign Modal -->
            <div id="campaign-modal" class="modal hidden">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="modal-title"><?php _e('Add New Campaign', 'helpme-donations'); ?></h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="campaign-form">
                            <input type="hidden" id="campaign-id" name="campaign_id" value="0">

                            <div class="form-group">
                                <label for="campaign-title"><?php _e('Campaign Title', 'helpme-donations'); ?></label>
                                <input type="text" id="campaign-title" name="title" required>
                            </div>

                            <div class="form-group">
                                <label for="campaign-description"><?php _e('Description', 'helpme-donations'); ?></label>
                                <textarea id="campaign-description" name="description" rows="4"></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="goal-amount"><?php _e('Goal Amount', 'helpme-donations'); ?></label>
                                    <input type="number" id="goal-amount" name="goal_amount" min="0" step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="campaign-currency"><?php _e('Currency', 'helpme-donations'); ?></label>
                                    <select id="campaign-currency" name="currency">
                                        <option value="USD">USD</option>
                                        <option value="ZIG">ZIG</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start-date"><?php _e('Start Date', 'helpme-donations'); ?></label>
                                    <input type="datetime-local" id="start-date" name="start_date">
                                </div>

                                <div class="form-group">
                                    <label for="end-date"><?php _e('End Date', 'helpme-donations'); ?></label>
                                    <input type="datetime-local" id="end-date" name="end_date">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="campaign-category"><?php _e('Category', 'helpme-donations'); ?></label>
                                    <input type="text" id="campaign-category" name="category">
                                </div>

                                <div class="form-group">
                                    <label for="campaign-status"><?php _e('Status', 'helpme-donations'); ?></label>
                                    <select id="campaign-status" name="status">
                                        <option value="active"><?php _e('Active', 'helpme-donations'); ?></option>
                                        <option value="paused"><?php _e('Paused', 'helpme-donations'); ?></option>
                                        <option value="completed"><?php _e('Completed', 'helpme-donations'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="image-url"><?php _e('Image URL', 'helpme-donations'); ?></label>
                                <input type="url" id="image-url" name="image_url">
                            </div>

                            <div class="form-group">
                                <label for="video-url"><?php _e('Video URL', 'helpme-donations'); ?></label>
                                <input type="url" id="video-url" name="video_url">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button" onclick="closeCampaignModal()"><?php _e('Cancel', 'helpme-donations'); ?></button>
                        <button type="button" class="button button-primary" id="save-campaign"><?php _e('Save Campaign', 'helpme-donations'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Add new campaign
            $('#add-new-campaign').on('click', function(e) {
                e.preventDefault();
                openCampaignModal();
            });

            // Edit campaign
            $(document).on('click', '.edit-campaign', function(e) {
                e.preventDefault();
                const campaignId = $(this).data('id');
                loadCampaign(campaignId);
            });

            // Delete campaign
            $(document).on('click', '.delete-campaign', function(e) {
                e.preventDefault();
                if (confirm('<?php _e("Are you sure you want to delete this campaign?", "helpme-donations"); ?>')) {
                    const campaignId = $(this).data('id');
                    deleteCampaign(campaignId);
                }
            });

            // Save campaign
            $('#save-campaign').on('click', function() {
                saveCampaign();
            });

            // Close modal
            $('.modal-close').on('click', function() {
                closeCampaignModal();
            });

            function openCampaignModal(campaignData = null) {
                if (campaignData) {
                    $('#modal-title').text('<?php _e("Edit Campaign", "helpme-donations"); ?>');
                    populateForm(campaignData);
                } else {
                    $('#modal-title').text('<?php _e("Add New Campaign", "helpme-donations"); ?>');
                    $('#campaign-form')[0].reset();
                    $('#campaign-id').val('0');
                }
                $('#campaign-modal').removeClass('hidden');
            }

            function closeCampaignModal() {
                $('#campaign-modal').addClass('hidden');
            }

            function populateForm(data) {
                $('#campaign-id').val(data.id);
                $('#campaign-title').val(data.title);
                $('#campaign-description').val(data.description);
                $('#goal-amount').val(data.goal_amount);
                $('#campaign-currency').val(data.currency);
                $('#start-date').val(data.start_date);
                $('#end-date').val(data.end_date);
                $('#campaign-category').val(data.category);
                $('#campaign-status').val(data.status);
                $('#image-url').val(data.image_url);
                $('#video-url').val(data.video_url);
            }

            function loadCampaign(campaignId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_campaign',
                        nonce: '<?php echo wp_create_nonce("helpme_donations_nonce"); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            openCampaignModal(response.data);
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }

            function saveCampaign() {
                const formData = new FormData($('#campaign-form')[0]);
                formData.append('action', 'save_campaign');
                formData.append('nonce', '<?php echo wp_create_nonce("helpme_donations_nonce"); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            closeCampaignModal();
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }

            function deleteCampaign(campaignId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_campaign',
                        nonce: '<?php echo wp_create_nonce("helpme_donations_nonce"); ?>',
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            }

            window.closeCampaignModal = closeCampaignModal;
        });
        </script>
        <?php
    }

    /**
     * Forms page
     */
    public function admin_page_forms() {
        ?>
        <div class="wrap">
            <h1><?php _e('Donation Forms', 'helpme-donations'); ?>
                <a href="#" class="page-title-action" id="add-new-form"><?php _e('Add New', 'helpme-donations'); ?></a>
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
        $donors = $this->get_donors_list();

        ?>
        <div class="wrap">
            <h1><?php _e('Donors', 'helpme-donations'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'helpme-donations'); ?></th>
                        <th><?php _e('Email', 'helpme-donations'); ?></th>
                        <th><?php _e('Total Donated', 'helpme-donations'); ?></th>
                        <th><?php _e('Donations', 'helpme-donations'); ?></th>
                        <th><?php _e('First Donation', 'helpme-donations'); ?></th>
                        <th><?php _e('Last Donation', 'helpme-donations'); ?></th>
                        <th><?php _e('Actions', 'helpme-donations'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donors as $donor): ?>
                        <tr>
                            <td><strong><?php echo esc_html($donor->name); ?></strong></td>
                            <td><?php echo esc_html($donor->email); ?></td>
                            <td><?php echo $this->format_currency($donor->total_donated, 'USD'); ?></td>
                            <td><?php echo number_format($donor->donation_count); ?></td>
                            <td><?php echo $donor->first_donation_date ? date_i18n(get_option('date_format'), strtotime($donor->first_donation_date)) : '-'; ?></td>
                            <td><?php echo $donor->last_donation_date ? date_i18n(get_option('date_format'), strtotime($donor->last_donation_date)) : '-'; ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=helpme-donations-list&donor_email=' . urlencode($donor->email)); ?>" class="button button-small"><?php _e('View Donations', 'helpme-donations'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Reports page
     */
    public function admin_page_reports() {
        ?>
        <div class="wrap">
            <h1><?php _e('Reports', 'helpme-donations'); ?></h1>

            <div class="reports-dashboard">
                <div class="report-filters">
                    <form id="report-filters">
                        <label><?php _e('Date Range:', 'helpme-donations'); ?></label>
                        <input type="date" name="date_from" id="date_from">
                        <input type="date" name="date_to" id="date_to">

                        <label><?php _e('Campaign:', 'helpme-donations'); ?></label>
                        <select name="campaign_id" id="campaign_filter">
                            <option value=""><?php _e('All Campaigns', 'helpme-donations'); ?></option>
                            <?php
                            $campaigns = helpme_donations()->campaign_manager->get_all_campaigns();
                            foreach ($campaigns as $campaign) {
                                echo '<option value="' . $campaign->id . '">' . esc_html($campaign->title) . '</option>';
                            }
                            ?>
                        </select>

                        <button type="button" id="generate-report" class="button button-primary"><?php _e('Generate Report', 'helpme-donations'); ?></button>
                    </form>
                </div>

                <div id="report-content">
                    <!-- Report content will be loaded here -->
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
            <h1><?php _e('Donation Settings', 'helpme-donations'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('helpme_donations_settings');
                do_settings_sections('helpme_donations_settings');
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
        register_setting('helpme_donations_settings', 'helpme_donations_settings', array($this, 'sanitize_settings'));

        // General Settings
        add_settings_section(
            'general_settings',
            __('General Settings', 'helpme-donations'),
            array($this, 'general_settings_callback'),
            'helpme_donations_settings'
        );

        add_settings_field(
            'default_currency',
            __('Default Currency', 'helpme-donations'),
            array($this, 'currency_field_callback'),
            'helpme_donations_settings',
            'general_settings'
        );

        add_settings_field(
            'test_mode',
            __('Test Mode', 'helpme-donations'),
            array($this, 'test_mode_field_callback'),
            'helpme_donations_settings',
            'general_settings'
        );

        // Payment Gateway Settings
        add_settings_section(
            'gateway_settings',
            __('Payment Gateways', 'helpme-donations'),
            array($this, 'gateway_settings_callback'),
            'helpme_donations_settings'
        );

        // Stripe Settings
        add_settings_field(
            'stripe_settings',
            __('Stripe', 'helpme-donations'),
            array($this, 'stripe_settings_callback'),
            'helpme_donations_settings',
            'gateway_settings'
        );

        // PayPal Settings
        add_settings_field(
            'paypal_settings',
            __('PayPal', 'helpme-donations'),
            array($this, 'paypal_settings_callback'),
            'helpme_donations_settings',
            'gateway_settings'
        );

        // Paynow Settings
        add_settings_field(
            'paynow_settings',
            __('Paynow', 'helpme-donations'),
            array($this, 'paynow_settings_callback'),
            'helpme_donations_settings',
            'gateway_settings'
        );
    }

    /**
     * General settings section callback
     */
    public function general_settings_callback() {
        echo '<p>' . __('Configure general donation settings.', 'helpme-donations') . '</p>';
    }

    /**
     * Gateway settings section callback
     */
    public function gateway_settings_callback() {
        echo '<p>' . __('Configure payment gateway settings.', 'helpme-donations') . '</p>';
    }

    /**
     * Currency field callback
     */
    public function currency_field_callback() {
        $currency = get_option('helpme_donations_default_currency', 'USD');
        ?>
        <select name="helpme_donations_default_currency">
            <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar</option>
            <option value="ZIG" <?php selected($currency, 'ZIG'); ?>>ZIG - Zimbabwean Gold</option>
            <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro</option>
            <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound</option>
        </select>
        <?php
    }

    /**
     * Test mode field callback
     */
    public function test_mode_field_callback() {
        $test_mode = get_option('helpme_donations_test_mode', 1);
        ?>
        <label>
            <input type="checkbox" name="helpme_donations_test_mode" value="1" <?php checked($test_mode, 1); ?>>
            <?php _e('Enable test mode (use sandbox/test API keys)', 'helpme-donations'); ?>
        </label>
        <?php
    }

    /**
     * Stripe settings callback
     */
    public function stripe_settings_callback() {
        $enabled = get_option('helpme_donations_stripe_enabled', 0);
        $publishable_key = get_option('helpme_donations_stripe_publishable_key', '');
        $secret_key = get_option('helpme_donations_stripe_secret_key', '');
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Enable Stripe', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_stripe_enabled" value="1" <?php checked($enabled, 1); ?>>
                        <?php _e('Enable Stripe payments', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php _e('Publishable Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_stripe_publishable_key" value="<?php echo esc_attr($publishable_key); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><?php _e('Secret Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_stripe_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * PayPal settings callback
     */
    public function paypal_settings_callback() {
        $enabled = get_option('helpme_donations_paypal_enabled', 0);
        $client_id = get_option('helpme_donations_paypal_client_id', '');
        $client_secret = get_option('helpme_donations_paypal_client_secret', '');
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Enable PayPal', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_paypal_enabled" value="1" <?php checked($enabled, 1); ?>>
                        <?php _e('Enable PayPal payments', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php _e('Client ID', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_paypal_client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><?php _e('Client Secret', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_paypal_client_secret" value="<?php echo esc_attr($client_secret); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Paynow settings callback
     */
    public function paynow_settings_callback() {
        $enabled = get_option('helpme_donations_paynow_enabled', 0);
        $integration_id = get_option('helpme_donations_paynow_integration_id', '');
        $integration_key = get_option('helpme_donations_paynow_integration_key', '');
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Enable Paynow', 'helpme-donations'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="helpme_donations_paynow_enabled" value="1" <?php checked($enabled, 1); ?>>
                        <?php _e('Enable Paynow payments (EcoCash/OneMoney)', 'helpme-donations'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php _e('Integration ID', 'helpme-donations'); ?></th>
                <td>
                    <input type="text" name="helpme_donations_paynow_integration_id" value="<?php echo esc_attr($integration_id); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><?php _e('Integration Key', 'helpme-donations'); ?></th>
                <td>
                    <input type="password" name="helpme_donations_paynow_integration_key" value="<?php echo esc_attr($integration_key); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'helpme-donations') !== false) {
            wp_enqueue_style(
                'helpme-donations-admin',
                HELPME_DONATIONS_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                HELPME_DONATIONS_VERSION
            );

            wp_enqueue_script(
                'helpme-donations-admin',
                HELPME_DONATIONS_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                HELPME_DONATIONS_VERSION,
                true
            );

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
     * Add plugin action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=helpme-donations-settings') . '">' . __('Settings', 'helpme-donations') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if plugin was just activated
        if (get_option('helpme_donations_activated')) {
            delete_option('helpme_donations_activated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Zimbabwe Donations plugin activated successfully! Please configure your payment gateways in the settings.', 'helpme-donations'); ?></p>
            </div>
            <?php
        }

        // Check for missing configurations
        $this->check_gateway_configurations();
    }

    /**
     * Check gateway configurations
     */
    private function check_gateway_configurations() {
        $enabled_gateways = get_option('helpme_donations_enabled_gateways', array());
        $missing_configs = array();

        if (in_array('stripe', $enabled_gateways)) {
            if (empty(get_option('helpme_donations_stripe_secret_key'))) {
                $missing_configs[] = 'Stripe';
            }
        }

        if (in_array('paypal', $enabled_gateways)) {
            if (empty(get_option('helpme_donations_paypal_client_id'))) {
                $missing_configs[] = 'PayPal';
            }
        }

        if (in_array('paynow', $enabled_gateways)) {
            if (empty(get_option('helpme_donations_paynow_integration_id'))) {
                $missing_configs[] = 'Paynow';
            }
        }

        if (!empty($missing_configs)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    printf(
                        __('Please configure the following payment gateways: %s. <a href="%s">Go to Settings</a>', 'helpme-donations'),
                        implode(', ', $missing_configs),
                        admin_url('admin.php?page=helpme-donations-settings')
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
    private function get_dashboard_stats() {
        global $wpdb;

        $donations_table = $wpdb->prefix . 'helpme_donations';
        $donors_table = $wpdb->prefix . 'helpme_donors';
        $campaigns_table = $wpdb->prefix . 'helpme_campaigns';

        // Total raised
        $total_raised = $wpdb->get_var(
            "SELECT SUM(amount) FROM {$donations_table} WHERE status = 'completed'"
        );

        // Total donations
        $total_donations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$donations_table} WHERE status = 'completed'"
        );

        // Total donors
        $total_donors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$donors_table}"
        );

        // Average donation
        $average_donation = $total_donations > 0 ? $total_raised / $total_donations : 0;

        // Recent donations
        $recent_donations = $wpdb->get_results(
            "SELECT d.*, c.title as campaign_title 
             FROM {$donations_table} d 
             LEFT JOIN {$campaigns_table} c ON d.campaign_id = c.id 
             WHERE d.status = 'completed' 
             ORDER BY d.created_at DESC 
             LIMIT 10"
        );

        // Top campaigns
        $top_campaigns = $wpdb->get_results(
            "SELECT c.*, 
                    SUM(d.amount) as total_raised,
                    COUNT(d.id) as donation_count
             FROM {$campaigns_table} c 
             LEFT JOIN {$donations_table} d ON c.id = d.campaign_id AND d.status = 'completed'
             WHERE c.status = 'active'
             GROUP BY c.id 
             ORDER BY total_raised DESC 
             LIMIT 5"
        );

        return array(
            'total_raised' => floatval($total_raised),
            'total_donations' => intval($total_donations),
            'total_donors' => intval($total_donors),
            'average_donation' => floatval($average_donation),
            'recent_donations' => $recent_donations,
            'top_campaigns' => $top_campaigns
        );
    }

    /**
     * Render recent donations
     */
    private function render_recent_donations($donations) {
        if (empty($donations)) {
            echo '<p>' . __('No recent donations.', 'helpme-donations') . '</p>';
            return;
        }

        foreach ($donations as $donation) {
            ?>
            <div class="donation-item">
                <div class="donor-info">
                    <div class="donor-name"><?php echo esc_html($donation->donor_name); ?></div>
                    <div class="donation-meta">
                        <?php
                        echo date_i18n(get_option('date_format'), strtotime($donation->created_at));
                        if ($donation->campaign_title) {
                            echo ' - ' . esc_html($donation->campaign_title);
                        }
                        ?>
                    </div>
                </div>
                <div class="donation-amount">
                    <?php echo $this->format_currency($donation->amount, $donation->currency); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render campaign performance
     */
    private function render_campaign_performance($campaigns) {
        if (empty($campaigns)) {
            echo '<p>' . __('No campaigns yet.', 'helpme-donations') . '</p>';
            return;
        }

        foreach ($campaigns as $campaign) {
            $raised = floatval($campaign->total_raised);
            $goal = floatval($campaign->goal_amount);
            $percentage = $goal > 0 ? min(100, ($raised / $goal) * 100) : 0;
            ?>
            <div class="campaign-item">
                <div class="campaign-info">
                    <div class="campaign-name"><?php echo esc_html($campaign->title); ?></div>
                    <div class="campaign-stats">
                        <?php echo number_format($campaign->donation_count); ?> <?php _e('donations', 'helpme-donations'); ?>
                        <?php if ($goal > 0): ?>
                            | <?php echo round($percentage, 1); ?>% <?php _e('of goal', 'helpme-donations'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="campaign-raised">
                    <?php echo $this->format_currency($raised, $campaign->currency); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render campaigns list
     */
    private function render_campaigns_list() {
        $campaigns = helpme_donations()->campaign_manager->get_all_campaigns();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Campaign', 'helpme-donations'); ?></th>
                    <th><?php _e('Goal', 'helpme-donations'); ?></th>
                    <th><?php _e('Raised', 'helpme-donations'); ?></th>
                    <th><?php _e('Progress', 'helpme-donations'); ?></th>
                    <th><?php _e('Status', 'helpme-donations'); ?></th>
                    <th><?php _e('Created', 'helpme-donations'); ?></th>
                    <th><?php _e('Actions', 'helpme-donations'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                    <?php
                    $stats = helpme_donations()->campaign_manager->get_campaign_statistics($campaign->id);
                    $goal = floatval($campaign->goal_amount);
                    $raised = floatval($stats['total_raised']);
                    $percentage = $goal > 0 ? min(100, ($raised / $goal) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($campaign->title); ?></strong>
                            <?php if ($campaign->category): ?>
                                <br><small><?php echo esc_html($campaign->category); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $goal > 0 ? $this->format_currency($goal, $campaign->currency) : '-'; ?></td>
                        <td><?php echo $this->format_currency($raised, $campaign->currency); ?></td>
                        <td>
                            <?php if ($goal > 0): ?>
                                <div class="progress-bar-small">
                                    <div class="progress-fill-small" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <small><?php echo round($percentage, 1); ?>%</small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($campaign->status); ?>">
                                <?php echo esc_html(ucfirst($campaign->status)); ?>
                            </span>
                        </td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($campaign->created_at)); ?></td>
                        <td>
                            <a href="#" class="button button-small edit-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php _e('Edit', 'helpme-donations'); ?></a>
                            <a href="#" class="button button-small delete-campaign" data-id="<?php echo esc_attr($campaign->id); ?>"><?php _e('Delete', 'helpme-donations'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
        .progress-bar-small {
            width: 100px;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            display: inline-block;
            margin-right: 10px;
        }

        .progress-fill-small {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }

    /**
     * Render forms list
     */
    private function render_forms_list() {
        $forms = helpme_donations()->form_builder->get_all_forms();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Form Name', 'helpme-donations'); ?></th>
                    <th><?php _e('Description', 'helpme-donations'); ?></th>
                    <th><?php _e('Shortcode', 'helpme-donations'); ?></th>
                    <th><?php _e('Created', 'helpme-donations'); ?></th>
                    <th><?php _e('Actions', 'helpme-donations'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td><strong><?php echo esc_html($form->name); ?></strong></td>
                        <td><?php echo esc_html(wp_trim_words($form->description, 10)); ?></td>
                        <td>
                            <code>[helpme_donation_form form_id="<?php echo $form->id; ?>"]</code>
                            <button type="button" class="button button-small copy-shortcode" data-shortcode='[helpme_donation_form form_id="<?php echo $form->id; ?>"]'><?php _e('Copy', 'helpme-donations'); ?></button>
                        </td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($form->created_at)); ?></td>
                        <td>
                            <a href="#" class="button button-small edit-form" data-id="<?php echo esc_attr($form->id); ?>"><?php _e('Edit', 'helpme-donations'); ?></a>
                            <a href="#" class="button button-small delete-form" data-id="<?php echo esc_attr($form->id); ?>"><?php _e('Delete', 'helpme-donations'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get donations list with filters
     */
    private function get_donations_list() {
        global $wpdb;

        $table = $wpdb->prefix . 'helpme_donations';
        $where = array('1=1');
        $where_values = array();

        // Status filter
        if (!empty($_GET['status'])) {
            $where[] = 'status = %s';
            $where_values[] = sanitize_text_field($_GET['status']);
        }

        // Gateway filter
        if (!empty($_GET['gateway'])) {
            $where[] = 'gateway = %s';
            $where_values[] = sanitize_text_field($_GET['gateway']);
        }

        // Date filters
        if (!empty($_GET['date_from'])) {
            $where[] = 'DATE(created_at) >= %s';
            $where_values[] = sanitize_text_field($_GET['date_from']);
        }

        if (!empty($_GET['date_to'])) {
            $where[] = 'DATE(created_at) <= %s';
            $where_values[] = sanitize_text_field($_GET['date_to']);
        }

        // Donor filter
        if (!empty($_GET['donor_email'])) {
            $where[] = 'donor_email = %s';
            $where_values[] = sanitize_email($_GET['donor_email']);
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 50",
                ...$where_values
            );
        } else {
            $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 50";
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get donors list
     */
    private function get_donors_list() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}helpme_donors ORDER BY total_donated DESC LIMIT 50"
        );
    }

    /**
     * Export donations AJAX handler
     */
    public function export_donations() {
        check_ajax_referer('helpme_donations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'helpme-donations'));
        }

        $donations = $this->get_donations_list();

        // Generate CSV
        $filename = 'donations-export-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;

        $file = fopen($file_path, 'w');

        // CSV headers
        $headers = array(
            'ID',
            'Donor Name',
            'Donor Email',
            'Amount',
            'Currency',
            'Gateway',
            'Status',
            'Transaction ID',
            'Created Date'
        );

        fputcsv($file, $headers);

        // CSV data
        foreach ($donations as $donation) {
            $row = array(
                $donation->id,
                $donation->donor_name,
                $donation->donor_email,
                $donation->amount,
                $donation->currency,
                $donation->gateway,
                $donation->status,
                $donation->transaction_id,
                $donation->created_at
            );

            fputcsv($file, $row);
        }

        fclose($file);

        wp_send_json_success(array(
            'download_url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename
        ));
    }

    /**
     * Test gateway AJAX handler
     */
    public function test_gateway() {
        check_ajax_referer('helpme_donations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'helpme-donations'));
        }

        $gateway_id = sanitize_text_field($_POST['gateway_id']);
        $gateway = helpme_donations()->payment_gateways->get_gateway($gateway_id);

        if (!$gateway) {
            wp_send_json_error(__('Gateway not found.', 'helpme-donations'));
        }

        if (!$gateway->is_available()) {
            wp_send_json_error(__('Gateway is not properly configured.', 'helpme-donations'));
        }

        // Perform basic connectivity test
        $test_result = $this->perform_gateway_test($gateway_id);

        if ($test_result) {
            wp_send_json_success(__('Gateway test successful.', 'helpme-donations'));
        } else {
            wp_send_json_error(__('Gateway test failed.', 'helpme-donations'));
        }
    }

    /**
     * Perform gateway test
     */
    private function perform_gateway_test($gateway_id) {
        switch ($gateway_id) {
            case 'stripe':
                return $this->test_stripe_connection();
            case 'paypal':
                return $this->test_paypal_connection();
            case 'paynow':
                return $this->test_paynow_connection();
            default:
                return false;
        }
    }

    /**
     * Test Stripe connection
     */
    private function test_stripe_connection() {
        $secret_key = get_option('helpme_donations_stripe_secret_key', '');

        if (empty($secret_key)) {
            return false;
        }

        $response = wp_remote_get('https://api.stripe.com/v1/payment_methods', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Stripe-Version' => '2020-08-27'
            ),
            'timeout' => 10
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }

    /**
     * Test PayPal connection
     */
    private function test_paypal_connection() {
        $client_id = get_option('helpme_donations_paypal_client_id', '');
        $client_secret = get_option('helpme_donations_paypal_client_secret', '');

        if (empty($client_id) || empty($client_secret)) {
            return false;
        }

        $test_mode = get_option('helpme_donations_test_mode', 1);
        $base_url = $test_mode ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';

        $response = wp_remote_post($base_url . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => 'grant_type=client_credentials',
            'timeout' => 10
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Test Paynow connection
     */
    private function test_paynow_connection() {
        $integration_id = get_option('helpme_donations_paynow_integration_id', '');
        $integration_key = get_option('helpme_donations_paynow_integration_key', '');

        if (empty($integration_id) || empty($integration_key)) {
            return false;
        }

        // For Paynow, we'll just validate that the credentials are set
        // A real test would require making a test transaction
        return true;
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        foreach ($input as $key => $value) {
            switch ($key) {
                case 'default_currency':
                    $sanitized[$key] = in_array($value, array('USD', 'ZIG', 'EUR', 'GBP')) ? $value : 'USD';
                    break;
                case 'test_mode':
                case 'stripe_enabled':
                case 'paypal_enabled':
                case 'paynow_enabled':
                    $sanitized[$key] = (bool) $value;
                    break;
                case 'stripe_publishable_key':
                case 'stripe_secret_key':
                case 'paypal_client_id':
                case 'paypal_client_secret':
                case 'paynow_integration_id':
                case 'paynow_integration_key':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
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