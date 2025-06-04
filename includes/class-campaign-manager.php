<?php
/**
 * Campaign Manager Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Campaign_Manager {

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
        add_action('wp_ajax_save_campaign', array($this, 'save_campaign'));
        add_action('wp_ajax_load_campaign', array($this, 'load_campaign'));
        add_action('wp_ajax_delete_campaign', array($this, 'delete_campaign'));
        add_action('wp_ajax_get_campaign_stats', array($this, 'get_campaign_stats'));
        add_action('zim_donations_campaign_donation_received', array($this, 'update_campaign_stats'), 10, 2);
        add_action('init', array($this, 'register_campaign_post_type'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_campaign_scripts'));
    }

    /**
     * Register campaign custom post type
     */
    public function register_campaign_post_type() {
        $labels = array(
            'name' => __('Campaigns', 'zim-donations'),
            'singular_name' => __('Campaign', 'zim-donations'),
            'menu_name' => __('Campaigns', 'zim-donations'),
            'add_new' => __('Add New Campaign', 'zim-donations'),
            'add_new_item' => __('Add New Campaign', 'zim-donations'),
            'edit_item' => __('Edit Campaign', 'zim-donations'),
            'new_item' => __('New Campaign', 'zim-donations'),
            'view_item' => __('View Campaign', 'zim-donations'),
            'search_items' => __('Search Campaigns', 'zim-donations'),
            'not_found' => __('No campaigns found', 'zim-donations'),
            'not_found_in_trash' => __('No campaigns found in trash', 'zim-donations')
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it to our custom admin menu
            'query_var' => true,
            'rewrite' => array('slug' => 'campaign'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true
        );

        register_post_type('zim_campaign', $args);
    }

    /**
     * Render campaign progress
     */
    public function render_progress($atts) {
        $campaign_id = intval($atts['campaign_id']);

        if ($campaign_id <= 0) {
            return '<p>' . __('Invalid campaign ID.', 'zim-donations') . '</p>';
        }

        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return '<p>' . __('Campaign not found.', 'zim-donations') . '</p>';
        }

        $stats = $this->get_campaign_statistics($campaign_id);

        ob_start();
        $this->render_progress_html($campaign, $stats, $atts);
        return ob_get_clean();
    }

    /**
     * Render progress HTML
     */
    private function render_progress_html($campaign, $stats, $atts) {
        $show_amount = $atts['show_amount'] === 'true';
        $show_percentage = $atts['show_percentage'] === 'true';
        $show_donors = $atts['show_donors'] === 'true';

        $goal = floatval($campaign->goal_amount);
        $raised = floatval($stats['total_raised']);
        $percentage = $goal > 0 ? min(100, ($raised / $goal) * 100) : 0;
        $donor_count = intval($stats['donor_count']);

        ?>
        <div class="zim-campaign-progress" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
            <div class="campaign-header">
                <h3 class="campaign-title"><?php echo esc_html($campaign->title); ?></h3>
                <?php if (!empty($campaign->description)): ?>
                    <div class="campaign-description">
                        <?php echo wp_kses_post(wp_trim_words($campaign->description, 30)); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%" data-percentage="<?php echo $percentage; ?>">
                        <span class="progress-text">
                            <?php if ($show_percentage): ?>
                                <?php echo round($percentage, 1); ?>%
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="progress-stats">
                    <?php if ($show_amount): ?>
                        <div class="stat-item raised-amount">
                            <span class="stat-value"><?php echo $this->format_currency($raised, $campaign->currency); ?></span>
                            <span class="stat-label"><?php _e('raised', 'zim-donations'); ?></span>
                            <?php if ($goal > 0): ?>
                                <span class="goal-amount">of <?php echo $this->format_currency($goal, $campaign->currency); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_donors): ?>
                        <div class="stat-item donor-count">
                            <span class="stat-value"><?php echo number_format($donor_count); ?></span>
                            <span class="stat-label"><?php echo _n('donor', 'donors', $donor_count, 'zim-donations'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($campaign->end_date && strtotime($campaign->end_date) > time()): ?>
                        <div class="stat-item time-remaining">
                            <span class="stat-value"><?php echo $this->get_time_remaining($campaign->end_date); ?></span>
                            <span class="stat-label"><?php _e('remaining', 'zim-donations'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($goal > 0 && $raised >= $goal): ?>
                    <div class="goal-achieved">
                        <span class="achievement-icon">🎉</span>
                        <span class="achievement-text"><?php _e('Goal Achieved!', 'zim-donations'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .zim-campaign-progress {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 25px;
                margin: 20px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .campaign-header {
                margin-bottom: 20px;
            }

            .campaign-title {
                color: #333;
                margin: 0 0 10px 0;
                font-size: 24px;
                font-weight: bold;
            }

            .campaign-description {
                color: #666;
                line-height: 1.5;
            }

            .progress-container {
                position: relative;
            }

            .progress-bar {
                width: 100%;
                height: 30px;
                background: #e9ecef;
                border-radius: 15px;
                overflow: hidden;
                margin-bottom: 20px;
                position: relative;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #28a745, #20c997);
                border-radius: 15px;
                transition: width 1s ease-in-out;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 30px;
            }

            .progress-text {
                color: white;
                font-weight: bold;
                font-size: 14px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            }

            .progress-stats {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 15px;
            }

            .stat-item {
                text-align: center;
                flex: 1;
                min-width: 120px;
            }

            .stat-value {
                display: block;
                font-size: 20px;
                font-weight: bold;
                color: #333;
                margin-bottom: 5px;
            }

            .stat-label {
                display: block;
                font-size: 14px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .goal-amount {
                display: block;
                font-size: 14px;
                color: #888;
                font-weight: normal;
                margin-top: 2px;
            }

            .goal-achieved {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                padding: 10px;
                text-align: center;
                margin-top: 15px;
                color: #155724;
            }

            .achievement-icon {
                font-size: 20px;
                margin-right: 8px;
            }

            .achievement-text {
                font-weight: bold;
                font-size: 16px;
            }

            .time-remaining .stat-value {
                color: #dc3545;
            }

            @media (max-width: 600px) {
                .progress-stats {
                    flex-direction: column;
                    text-align: center;
                }

                .stat-item {
                    border-bottom: 1px solid #eee;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
                }

                .stat-item:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                }
            }
        </style>
        <?php
    }

    /**
     * Create new campaign
     */
    public function create_campaign($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'zim_campaigns';

        $campaign_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'goal_amount' => floatval($data['goal_amount']),
            'currency' => sanitize_text_field($data['currency']),
            'start_date' => !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'status' => sanitize_text_field($data['status']) ?: 'active',
            'category' => sanitize_text_field($data['category']),
            'image_url' => esc_url_raw($data['image_url']),
            'video_url' => esc_url_raw($data['video_url']),
            'slug' => $this->generate_campaign_slug($data['title']),
            'settings' => json_encode($this->sanitize_campaign_settings($data['settings'] ?? array())),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table, $campaign_data);

        if ($result) {
            $campaign_id = $wpdb->insert_id;

            // Create corresponding WordPress post for SEO and frontend display
            $this->create_campaign_post($campaign_id, $campaign_data);

            return $campaign_id;
        }

        return false;
    }

    /**
     * Update campaign
     */
    public function update_campaign($campaign_id, $data) {
        global $wpdb;

        $table = $wpdb->prefix . 'zim_campaigns';

        $campaign_data = array(
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'goal_amount' => floatval($data['goal_amount']),
            'currency' => sanitize_text_field($data['currency']),
            'start_date' => !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'status' => sanitize_text_field($data['status']),
            'category' => sanitize_text_field($data['category']),
            'image_url' => esc_url_raw($data['image_url']),
            'video_url' => esc_url_raw($data['video_url']),
            'settings' => json_encode($this->sanitize_campaign_settings($data['settings'] ?? array())),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->update(
            $table,
            $campaign_data,
            array('id' => $campaign_id),
            array('%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Update corresponding WordPress post
            $this->update_campaign_post($campaign_id, $campaign_data);
            return true;
        }

        return false;
    }

    /**
     * Get campaign
     */
    public function get_campaign($campaign_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_campaigns WHERE id = %d",
            $campaign_id
        ));
    }

    /**
     * Get all campaigns
     */
    public function get_all_campaigns($status = 'active') {
        global $wpdb;

        $where = $status ? $wpdb->prepare("WHERE status = %s", $status) : "";

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}zim_campaigns {$where} ORDER BY created_at DESC"
        );
    }

    /**
     * Get campaign statistics
     */
    public function get_campaign_statistics($campaign_id) {
        global $wpdb;

        $donations_table = $wpdb->prefix . 'zim_donations';

        // Get total raised
        $total_raised = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$donations_table} WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));

        // Get donor count
        $donor_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT donor_email) FROM {$donations_table} WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));

        // Get donation count
        $donation_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$donations_table} WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));

        // Get average donation
        $average_donation = $total_raised > 0 && $donation_count > 0 ? $total_raised / $donation_count : 0;

        // Get recent donations
        $recent_donations = $wpdb->get_results($wpdb->prepare(
            "SELECT donor_name, amount, currency, anonymous, created_at 
             FROM {$donations_table} 
             WHERE campaign_id = %d AND status = 'completed' 
             ORDER BY created_at DESC LIMIT 10",
            $campaign_id
        ));

        return array(
            'total_raised' => floatval($total_raised),
            'donor_count' => intval($donor_count),
            'donation_count' => intval($donation_count),
            'average_donation' => floatval($average_donation),
            'recent_donations' => $recent_donations
        );
    }

    /**
     * Update campaign stats when donation received
     */
    public function update_campaign_stats($campaign_id, $amount) {
        // This hook is called when a donation is completed
        // We can use this to trigger real-time updates, send notifications, etc.

        do_action('zim_donations_campaign_stats_updated', $campaign_id, $amount);

        // Check if campaign goal has been reached
        $campaign = $this->get_campaign($campaign_id);
        if ($campaign && $campaign->goal_amount > 0) {
            $stats = $this->get_campaign_statistics($campaign_id);

            if ($stats['total_raised'] >= $campaign->goal_amount) {
                // Goal reached!
                do_action('zim_donations_campaign_goal_reached', $campaign_id, $stats);

                // Update campaign status if desired
                // $this->update_campaign($campaign_id, array('status' => 'completed'));
            }
        }
    }

    /**
     * Save campaign via AJAX
     */
    public function save_campaign() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'zim-donations'));
        }

        $campaign_id = intval($_POST['campaign_id']);
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description']),
            'goal_amount' => floatval($_POST['goal_amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'status' => sanitize_text_field($_POST['status']),
            'category' => sanitize_text_field($_POST['category']),
            'image_url' => esc_url_raw($_POST['image_url']),
            'video_url' => esc_url_raw($_POST['video_url']),
            'settings' => $_POST['settings'] ?? array()
        );

        if ($campaign_id > 0) {
            // Update existing campaign
            $result = $this->update_campaign($campaign_id, $data);

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Campaign updated successfully.', 'zim-donations'),
                    'campaign_id' => $campaign_id
                ));
            }
        } else {
            // Create new campaign
            $new_campaign_id = $this->create_campaign($data);

            if ($new_campaign_id) {
                wp_send_json_success(array(
                    'message' => __('Campaign created successfully.', 'zim-donations'),
                    'campaign_id' => $new_campaign_id
                ));
            }
        }

        wp_send_json_error(__('Failed to save campaign.', 'zim-donations'));
    }

    /**
     * Load campaign via AJAX
     */
    public function load_campaign() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'zim-donations'));
        }

        $campaign_id = intval($_POST['campaign_id']);
        $campaign = $this->get_campaign($campaign_id);

        if ($campaign) {
            $campaign->settings = json_decode($campaign->settings, true);
            $campaign->statistics = $this->get_campaign_statistics($campaign_id);

            wp_send_json_success($campaign);
        } else {
            wp_send_json_error(__('Campaign not found.', 'zim-donations'));
        }
    }

    /**
     * Delete campaign via AJAX
     */
    public function delete_campaign() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'zim-donations'));
        }

        global $wpdb;
        $campaign_id = intval($_POST['campaign_id']);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'zim_campaigns',
            array('status' => 'deleted'),
            array('id' => $campaign_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            // Also delete the corresponding WordPress post
            $this->delete_campaign_post($campaign_id);

            wp_send_json_success(__('Campaign deleted successfully.', 'zim-donations'));
        } else {
            wp_send_json_error(__('Failed to delete campaign.', 'zim-donations'));
        }
    }

    /**
     * Get campaign stats via AJAX
     */
    public function get_campaign_stats() {
        check_ajax_referer('zim_donations_nonce', 'nonce');

        $campaign_id = intval($_POST['campaign_id']);
        $stats = $this->get_campaign_statistics($campaign_id);

        wp_send_json_success($stats);
    }

    /**
     * Generate unique campaign slug
     */
    private function generate_campaign_slug($title) {
        $slug = sanitize_title($title);

        global $wpdb;
        $table = $wpdb->prefix . 'zim_campaigns';

        $original_slug = $slug;
        $counter = 1;

        while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug)) > 0) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create WordPress post for campaign
     */
    private function create_campaign_post($campaign_id, $campaign_data) {
        $post_data = array(
            'post_title' => $campaign_data['title'],
            'post_content' => $campaign_data['description'],
            'post_status' => $campaign_data['status'] === 'active' ? 'publish' : 'draft',
            'post_type' => 'zim_campaign',
            'post_name' => $campaign_data['slug'],
            'meta_input' => array(
                'campaign_id' => $campaign_id,
                'goal_amount' => $campaign_data['goal_amount'],
                'currency' => $campaign_data['currency'],
                'start_date' => $campaign_data['start_date'],
                'end_date' => $campaign_data['end_date'],
                'category' => $campaign_data['category'],
                'image_url' => $campaign_data['image_url'],
                'video_url' => $campaign_data['video_url']
            )
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id && !is_wp_error($post_id)) {
            // Update campaign with post ID
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'zim_campaigns',
                array('post_id' => $post_id),
                array('id' => $campaign_id),
                array('%d'),
                array('%d')
            );
        }

        return $post_id;
    }

    /**
     * Update WordPress post for campaign
     */
    private function update_campaign_post($campaign_id, $campaign_data) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}zim_campaigns WHERE id = %d",
            $campaign_id
        ));

        if ($post_id) {
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $campaign_data['title'],
                'post_content' => $campaign_data['description'],
                'post_status' => $campaign_data['status'] === 'active' ? 'publish' : 'draft'
            );

            wp_update_post($post_data);

            // Update meta fields
            update_post_meta($post_id, 'goal_amount', $campaign_data['goal_amount']);
            update_post_meta($post_id, 'currency', $campaign_data['currency']);
            update_post_meta($post_id, 'start_date', $campaign_data['start_date']);
            update_post_meta($post_id, 'end_date', $campaign_data['end_date']);
            update_post_meta($post_id, 'category', $campaign_data['category']);
            update_post_meta($post_id, 'image_url', $campaign_data['image_url']);
            update_post_meta($post_id, 'video_url', $campaign_data['video_url']);
        }
    }

    /**
     * Delete WordPress post for campaign
     */
    private function delete_campaign_post($campaign_id) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}zim_campaigns WHERE id = %d",
            $campaign_id
        ));

        if ($post_id) {
            wp_delete_post($post_id, true);
        }
    }

    /**
     * Sanitize campaign settings
     */
    private function sanitize_campaign_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }

        $sanitized = array();

        // Email notifications
        if (isset($settings['email_notifications'])) {
            $sanitized['email_notifications'] = (bool) $settings['email_notifications'];
        }

        // Notification emails
        if (isset($settings['notification_emails'])) {
            $emails = array();
            foreach ((array) $settings['notification_emails'] as $email) {
                if (is_email($email)) {
                    $emails[] = sanitize_email($email);
                }
            }
            $sanitized['notification_emails'] = $emails;
        }

        // Show donor names
        if (isset($settings['show_donor_names'])) {
            $sanitized['show_donor_names'] = (bool) $settings['show_donor_names'];
        }

        // Show donation amounts
        if (isset($settings['show_donation_amounts'])) {
            $sanitized['show_donation_amounts'] = (bool) $settings['show_donation_amounts'];
        }

        // Allow anonymous donations
        if (isset($settings['allow_anonymous'])) {
            $sanitized['allow_anonymous'] = (bool) $settings['allow_anonymous'];
        }

        // Custom thank you message
        if (isset($settings['thank_you_message'])) {
            $sanitized['thank_you_message'] = wp_kses_post($settings['thank_you_message']);
        }

        // Social sharing
        if (isset($settings['enable_social_sharing'])) {
            $sanitized['enable_social_sharing'] = (bool) $settings['enable_social_sharing'];
        }

        return $sanitized;
    }

    /**
     * Get time remaining for campaign
     */
    private function get_time_remaining($end_date) {
        $end_timestamp = strtotime($end_date);
        $current_timestamp = current_time('timestamp');

        if ($end_timestamp <= $current_timestamp) {
            return __('Ended', 'zim-donations');
        }

        $diff = $end_timestamp - $current_timestamp;

        $days = floor($diff / (60 * 60 * 24));
        $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));

        if ($days > 0) {
            return sprintf(_n('%d day', '%d days', $days, 'zim-donations'), $days);
        } elseif ($hours > 0) {
            return sprintf(_n('%d hour', '%d hours', $hours, 'zim-donations'), $hours);
        } else {
            $minutes = floor(($diff % (60 * 60)) / 60);
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'zim-donations'), $minutes);
        }
    }

    /**
     * Enqueue campaign scripts
     */
    public function enqueue_campaign_scripts() {
        if (!is_admin()) {
            wp_enqueue_style(
                'helpme-donations-campaigns',
                HELPME_DONATIONS_PLUGIN_URL . 'assets/css/campaigns.css',
                array(),
                HELPME_DONATIONS_VERSION
            );

            wp_enqueue_script(
                'helpme-donations-campaigns',
                HELPME_DONATIONS_PLUGIN_URL . 'assets/js/campaigns.js',
                array('jquery'),
                HELPME_DONATIONS_VERSION,
                true
            );

            wp_localize_script('helpme-donations-campaigns', 'zimCampaigns', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zim_donations_nonce'),
                'strings' => array(
                    'loading' => __('Loading...', 'zim-donations'),
                    'error' => __('An error occurred.', 'zim-donations'),
                    'goalReached' => __('Goal Reached!', 'zim-donations'),
                    'daysLeft' => __('days left', 'zim-donations'),
                    'hoursLeft' => __('hours left', 'zim-donations'),
                    'minutesLeft' => __('minutes left', 'zim-donations')
                )
            ));
        }
    }

    /**
     * Get campaigns by category
     */
    public function get_campaigns_by_category($category, $limit = -1) {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}zim_campaigns WHERE status = 'active'";

        if (!empty($category)) {
            $query .= $wpdb->prepare(" AND category = %s", $category);
        }

        $query .= " ORDER BY created_at DESC";

        if ($limit > 0) {
            $query .= $wpdb->prepare(" LIMIT %d", $limit);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get featured campaigns
     */
    public function get_featured_campaigns($limit = 3) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, 
                    (SELECT SUM(amount) FROM {$wpdb->prefix}zim_donations WHERE campaign_id = c.id AND status = 'completed') as raised_amount
             FROM {$wpdb->prefix}zim_campaigns c 
             WHERE c.status = 'active' 
             ORDER BY raised_amount DESC, c.created_at DESC 
             LIMIT %d",
            $limit
        ));
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
     * Get campaign by slug
     */
    public function get_campaign_by_slug($slug) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_campaigns WHERE slug = %s AND status = 'active'",
            $slug
        ));
    }
    
    /**
     * Search campaigns
     */
    public function search_campaigns($search_term, $limit = 10) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zim_campaigns 
             WHERE status = 'active' 
             AND (title LIKE %s OR description LIKE %s OR category LIKE %s)
             ORDER BY created_at DESC 
             LIMIT %d",
            $search_term,
            $search_term,
            $search_term,
            $limit
        ));
    }
    
    /**
     * Get campaign leaderboard
     */
    public function get_campaign_leaderboard($campaign_id, $limit = 10) {
        global $wpdb;
        
        $donations_table = $wpdb->prefix . 'zim_donations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                donor_name,
                SUM(amount) as total_donated,
                COUNT(*) as donation_count,
                MAX(created_at) as last_donation,
                anonymous
             FROM {$donations_table} 
             WHERE campaign_id = %d AND status = 'completed' AND anonymous = 0
             GROUP BY donor_email 
             ORDER BY total_donated DESC 
             LIMIT %d",
            $campaign_id,
            $limit
        ));
    }
}