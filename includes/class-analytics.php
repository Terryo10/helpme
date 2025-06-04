<?php
/**
 * Analytics and reporting for Zimbabwe Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Analytics {

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
        add_action('wp_ajax_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_export_analytics', array($this, 'ajax_export_analytics'));
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($period = '30d') {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $campaigns_table = $this->db->get_campaigns_table();
        $donors_table = $this->db->get_donors_table();

        // Date range calculation
        $date_condition = $this->get_date_condition($period);

        // Total donations
        $total_donations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$donations_table} WHERE status = 'completed' {$date_condition}"
        );

        // Total amount raised
        $total_raised = $wpdb->get_var(
            "SELECT SUM(amount) FROM {$donations_table} WHERE status = 'completed' {$date_condition}"
        );

        // Average donation
        $average_donation = $total_donations > 0 ? ($total_raised / $total_donations) : 0;

        // Unique donors
        $unique_donors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT donor_email) FROM {$donations_table} WHERE status = 'completed' {$date_condition}"
        );

        // Active campaigns
        $active_campaigns = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$campaigns_table} WHERE status = 'active'"
        );

        // Top performing campaign
        $top_campaign = $wpdb->get_row(
            "SELECT c.title, SUM(d.amount) as total_raised 
             FROM {$campaigns_table} c 
             LEFT JOIN {$donations_table} d ON c.id = d.campaign_id 
             WHERE d.status = 'completed' {$date_condition}
             GROUP BY c.id 
             ORDER BY total_raised DESC 
             LIMIT 1"
        );

        return array(
            'total_donations' => intval($total_donations),
            'total_raised' => floatval($total_raised ?: 0),
            'average_donation' => floatval($average_donation),
            'unique_donors' => intval($unique_donors),
            'active_campaigns' => intval($active_campaigns),
            'top_campaign' => $top_campaign ? array(
                'title' => $top_campaign->title,
                'amount' => floatval($top_campaign->total_raised)
            ) : null
        );
    }

    /**
     * Get donation trends
     */
    public function get_donation_trends($period = '30d', $group_by = 'day') {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $date_condition = $this->get_date_condition($period);

        $date_format = $this->get_mysql_date_format($group_by);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, %s) as period_date,
                        COUNT(*) as donation_count,
                        SUM(amount) as total_amount,
                        AVG(amount) as avg_amount
                 FROM {$donations_table} 
                 WHERE status = 'completed' {$date_condition}
                 GROUP BY period_date 
                 ORDER BY period_date ASC",
                $date_format
            )
        );

        return array_map(function($row) {
            return array(
                'date' => $row->period_date,
                'count' => intval($row->donation_count),
                'total' => floatval($row->total_amount),
                'average' => floatval($row->avg_amount)
            );
        }, $results);
    }

    /**
     * Get payment method statistics
     */
    public function get_payment_method_stats($period = '30d') {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $date_condition = $this->get_date_condition($period);

        $results = $wpdb->get_results(
            "SELECT gateway,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_count,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count
             FROM {$donations_table} 
             WHERE 1=1 {$date_condition}
             GROUP BY gateway 
             ORDER BY total_amount DESC"
        );

        return array_map(function($row) {
            $success_rate = $row->transaction_count > 0 ? 
                ($row->successful_count / $row->transaction_count) * 100 : 0;

            return array(
                'gateway' => $row->gateway,
                'total_transactions' => intval($row->transaction_count),
                'total_amount' => floatval($row->total_amount),
                'average_amount' => floatval($row->avg_amount),
                'successful_transactions' => intval($row->successful_count),
                'failed_transactions' => intval($row->failed_count),
                'success_rate' => round($success_rate, 2)
            );
        }, $results);
    }

    /**
     * Get campaign performance
     */
    public function get_campaign_performance($period = '30d') {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $campaigns_table = $this->db->get_campaigns_table();
        $date_condition = $this->get_date_condition($period);

        $results = $wpdb->get_results(
            "SELECT c.id, c.title, c.goal_amount, c.currency,
                    COUNT(d.id) as donation_count,
                    SUM(d.amount) as raised_amount,
                    COUNT(DISTINCT d.donor_email) as unique_donors,
                    AVG(d.amount) as avg_donation
             FROM {$campaigns_table} c
             LEFT JOIN {$donations_table} d ON c.id = d.campaign_id AND d.status = 'completed' {$date_condition}
             GROUP BY c.id
             ORDER BY raised_amount DESC"
        );

        return array_map(function($row) {
            $progress_percentage = $row->goal_amount > 0 ? 
                ($row->raised_amount / $row->goal_amount) * 100 : 0;

            return array(
                'campaign_id' => intval($row->id),
                'title' => $row->title,
                'goal_amount' => floatval($row->goal_amount ?: 0),
                'raised_amount' => floatval($row->raised_amount ?: 0),
                'currency' => $row->currency,
                'donation_count' => intval($row->donation_count),
                'unique_donors' => intval($row->unique_donors),
                'average_donation' => floatval($row->avg_donation ?: 0),
                'progress_percentage' => round($progress_percentage, 2)
            );
        }, $results);
    }

    /**
     * Get donor analytics
     */
    public function get_donor_analytics($period = '30d') {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $date_condition = $this->get_date_condition($period);

        // Top donors
        $top_donors = $wpdb->get_results(
            "SELECT donor_name, donor_email,
                    COUNT(*) as donation_count,
                    SUM(amount) as total_donated,
                    AVG(amount) as avg_donation,
                    MAX(created_at) as last_donation
             FROM {$donations_table} 
             WHERE status = 'completed' {$date_condition}
             GROUP BY donor_email 
             ORDER BY total_donated DESC 
             LIMIT 10"
        );

        // New vs returning donors
        $donor_types = $wpdb->get_row(
            "SELECT 
                COUNT(CASE WHEN donation_count = 1 THEN 1 END) as new_donors,
                COUNT(CASE WHEN donation_count > 1 THEN 1 END) as returning_donors
             FROM (
                 SELECT donor_email, COUNT(*) as donation_count
                 FROM {$donations_table} 
                 WHERE status = 'completed' {$date_condition}
                 GROUP BY donor_email
             ) as donor_stats"
        );

        // Retention rate
        $retention_stats = $this->calculate_donor_retention($period);

        return array(
            'top_donors' => array_map(function($row) {
                return array(
                    'name' => $row->donor_name,
                    'email' => $row->donor_email,
                    'donation_count' => intval($row->donation_count),
                    'total_donated' => floatval($row->total_donated),
                    'average_donation' => floatval($row->avg_donation),
                    'last_donation' => $row->last_donation
                );
            }, $top_donors),
            'new_donors' => intval($donor_types->new_donors ?: 0),
            'returning_donors' => intval($donor_types->returning_donors ?: 0),
            'retention_rate' => $retention_stats['retention_rate']
        );
    }

    /**
     * Calculate donor retention rate
     */
    private function calculate_donor_retention($period) {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();

        // Get donors from previous period
        $previous_period_condition = $this->get_date_condition($this->get_previous_period($period));
        $current_period_condition = $this->get_date_condition($period);

        $previous_donors = $wpdb->get_col(
            "SELECT DISTINCT donor_email FROM {$donations_table} 
             WHERE status = 'completed' {$previous_period_condition}"
        );

        if (empty($previous_donors)) {
            return array('retention_rate' => 0);
        }

        $returning_donors = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT donor_email) FROM {$donations_table} 
                 WHERE status = 'completed' {$current_period_condition}
                 AND donor_email IN (" . implode(',', array_fill(0, count($previous_donors), '%s')) . ")",
                ...$previous_donors
            )
        );

        $retention_rate = (intval($returning_donors) / count($previous_donors)) * 100;

        return array(
            'retention_rate' => round($retention_rate, 2),
            'previous_period_donors' => count($previous_donors),
            'returning_donors' => intval($returning_donors)
        );
    }

    /**
     * Get geographic distribution
     */
    public function get_geographic_distribution($period = '30d') {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $date_condition = $this->get_date_condition($period);

        // This is a simplified version - in a real implementation,
        // you might extract country from donor address or use IP geolocation
        $results = $wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN donor_email LIKE '%.zw' THEN 'Zimbabwe'
                    WHEN donor_email LIKE '%.za' THEN 'South Africa'
                    WHEN donor_email LIKE '%.uk' OR donor_email LIKE '%.co.uk' THEN 'United Kingdom'
                    WHEN donor_email LIKE '%.us' OR donor_email LIKE '%.com' THEN 'United States'
                    ELSE 'Other'
                END as country,
                COUNT(*) as donation_count,
                SUM(amount) as total_amount
             FROM {$donations_table} 
             WHERE status = 'completed' {$date_condition}
             GROUP BY country
             ORDER BY total_amount DESC"
        );

        return array_map(function($row) {
            return array(
                'country' => $row->country,
                'donation_count' => intval($row->donation_count),
                'total_amount' => floatval($row->total_amount)
            );
        }, $results);
    }

    /**
     * Export analytics data
     */
    public function export_analytics($format = 'csv', $data_type = 'donations', $period = '30d') {
        switch ($data_type) {
            case 'donations':
                $data = $this->get_donations_export_data($period);
                break;
            case 'campaigns':
                $data = $this->get_campaign_performance($period);
                break;
            case 'donors':
                $data = $this->get_donor_analytics($period);
                break;
            default:
                $data = array();
        }

        if ($format === 'csv') {
            return $this->export_to_csv($data, $data_type);
        } elseif ($format === 'json') {
            return $this->export_to_json($data);
        }

        return false;
    }

    /**
     * Get donations export data
     */
    private function get_donations_export_data($period) {
        global $wpdb;

        $donations_table = $this->db->get_donations_table();
        $date_condition = $this->get_date_condition($period);

        return $wpdb->get_results(
            "SELECT donation_id, amount, currency, gateway, status, 
                    donor_name, donor_email, anonymous, 
                    is_recurring, created_at
             FROM {$donations_table} 
             WHERE 1=1 {$date_condition}
             ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Export to CSV
     */
    private function export_to_csv($data, $filename_prefix) {
        if (empty($data)) {
            return false;
        }

        $filename = $filename_prefix . '_export_' . date('Y-m-d') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;

        $file = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($file, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);

        return $filepath;
    }

    /**
     * Export to JSON
     */
    private function export_to_json($data) {
        $filename = 'analytics_export_' . date('Y-m-d') . '.json';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return $filepath;
    }

    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this data.', 'zim-donations'));
        }

        $data_type = sanitize_text_field($_POST['data_type'] ?? 'dashboard');
        $period = sanitize_text_field($_POST['period'] ?? '30d');

        switch ($data_type) {
            case 'dashboard':
                $data = $this->get_dashboard_stats($period);
                break;
            case 'trends':
                $data = $this->get_donation_trends($period, $_POST['group_by'] ?? 'day');
                break;
            case 'payment_methods':
                $data = $this->get_payment_method_stats($period);
                break;
            case 'campaigns':
                $data = $this->get_campaign_performance($period);
                break;
            case 'donors':
                $data = $this->get_donor_analytics($period);
                break;
            case 'geographic':
                $data = $this->get_geographic_distribution($period);
                break;
            default:
                $data = array();
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX handler for analytics export
     */
    public function ajax_export_analytics() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data.', 'zim-donations'));
        }

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $data_type = sanitize_text_field($_POST['data_type'] ?? 'donations');
        $period = sanitize_text_field($_POST['period'] ?? '30d');

        $filepath = $this->export_analytics($format, $data_type, $period);

        if ($filepath) {
            wp_send_json_success(array(
                'download_url' => wp_upload_dir()['url'] . '/' . basename($filepath),
                'filename' => basename($filepath)
            ));
        } else {
            wp_send_json_error(__('Export failed.', 'zim-donations'));
        }
    }

    /**
     * Helper: Get date condition for SQL queries
     */
    private function get_date_condition($period) {
        $condition = '';
        
        switch ($period) {
            case '7d':
                $condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30d':
                $condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90d':
                $condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case '1y':
                $condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            case 'all':
                $condition = '';
                break;
        }

        return $condition;
    }

    /**
     * Helper: Get MySQL date format for grouping
     */
    private function get_mysql_date_format($group_by) {
        switch ($group_by) {
            case 'hour':
                return '%Y-%m-%d %H:00:00';
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            case 'year':
                return '%Y';
            default:
                return '%Y-%m-%d';
        }
    }

    /**
     * Helper: Get previous period string
     */
    private function get_previous_period($period) {
        switch ($period) {
            case '7d':
                return '14d_to_7d'; // Previous 7 days
            case '30d':
                return '60d_to_30d'; // Previous 30 days
            case '90d':
                return '180d_to_90d'; // Previous 90 days
            default:
                return '60d_to_30d';
        }
    }
} 