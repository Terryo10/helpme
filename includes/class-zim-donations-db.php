<?php
/**
 * Database operations for Help Me Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HelpMeDonations_DB {

    /**
     * Database version
     */
    const DB_VERSION = '1.0';

    /**
     * Table names
     */
    private $donations_table;
    private $campaigns_table;
    private $donors_table;
    private $transactions_table;
    private $forms_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->donations_table = $wpdb->prefix . 'helpme_donations';
        $this->campaigns_table = $wpdb->prefix . 'helpme_campaigns';
        $this->donors_table = $wpdb->prefix . 'helpme_donors';
        $this->transactions_table = $wpdb->prefix . 'helpme_transactions';
        $this->forms_table = $wpdb->prefix . 'helpme_forms';
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Donations table
        $sql = "CREATE TABLE {$this->donations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            donation_id varchar(50) NOT NULL UNIQUE,
            campaign_id bigint(20) unsigned DEFAULT 0,
            form_id bigint(20) unsigned DEFAULT 0,
            donor_id bigint(20) unsigned DEFAULT 0,
            amount decimal(15,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            gateway varchar(50) NOT NULL,
            gateway_transaction_id varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            is_recurring tinyint(1) DEFAULT 0,
            recurring_interval varchar(20) DEFAULT NULL,
            parent_donation_id bigint(20) unsigned DEFAULT NULL,
            anonymous tinyint(1) DEFAULT 0,
            donor_name varchar(255) NOT NULL,
            donor_email varchar(255) NOT NULL,
            donor_phone varchar(50) DEFAULT NULL,
            donor_address text DEFAULT NULL,
            donor_message text DEFAULT NULL,
            metadata text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY donation_id (donation_id),
            KEY campaign_id (campaign_id),
            KEY donor_id (donor_id),
            KEY status (status),
            KEY gateway (gateway),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Campaigns table
        $sql = "CREATE TABLE {$this->campaigns_table} (
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
            created_by bigint(20) unsigned NOT NULL,
            metadata text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY category (category),
            KEY created_by (created_by)
        ) $charset_collate;";

        dbDelta($sql);

        // Donors table
        $sql = "CREATE TABLE {$this->donors_table} (
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

        dbDelta($sql);

        // Transactions table
        $sql = "CREATE TABLE {$this->transactions_table} (
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

        dbDelta($sql);

        // Forms table
        $sql = "CREATE TABLE {$this->forms_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            config text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            usage_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";

        dbDelta($sql);

        // Update database version
        update_option('helpme_donations_db_version', self::DB_VERSION);
    }

    /**
     * Get donations table name
     */
    public function get_donations_table() {
        return $this->donations_table;
    }

    /**
     * Get campaigns table name
     */
    public function get_campaigns_table() {
        return $this->campaigns_table;
    }

    /**
     * Get donors table name
     */
    public function get_donors_table() {
        return $this->donors_table;
    }

    /**
     * Get transactions table name
     */
    public function get_transactions_table() {
        return $this->transactions_table;
    }

    /**
     * Get forms table name
     */
    public function get_forms_table() {
        return $this->forms_table;
    }

    /**
     * Insert donation
     */
    public function insert_donation($data) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->donations_table,
            $data,
            array(
                '%s', // donation_id
                '%d', // campaign_id
                '%d', // form_id
                '%d', // donor_id
                '%f', // amount
                '%s', // currency
                '%s', // gateway
                '%s', // gateway_transaction_id
                '%s', // status
                '%d', // is_recurring
                '%s', // recurring_interval
                '%d', // parent_donation_id
                '%d', // anonymous
                '%s', // donor_name
                '%s', // donor_email
                '%s', // donor_phone
                '%s', // donor_address
                '%s', // donor_message
                '%s', // metadata
                '%s'  // created_at
            )
        );

        if ($result !== false) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update donation
     */
    public function update_donation($donation_id, $data) {
        global $wpdb;

        return $wpdb->update(
            $this->donations_table,
            $data,
            array('id' => $donation_id),
            null,
            array('%d')
        );
    }

    /**
     * Get donation by ID
     */
    public function get_donation($donation_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->donations_table} WHERE id = %d",
                $donation_id
            )
        );
    }

    /**
     * Insert campaign
     */
    public function insert_campaign($data) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->campaigns_table,
            $data
        );

        if ($result !== false) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update campaign
     */
    public function update_campaign($campaign_id, $data) {
        global $wpdb;

        return $wpdb->update(
            $this->campaigns_table,
            $data,
            array('id' => $campaign_id),
            null,
            array('%d')
        );
    }

    /**
     * Get campaign by ID
     */
    public function get_campaign($campaign_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->campaigns_table} WHERE id = %d",
                $campaign_id
            )
        );
    }

    /**
     * Insert or update donor
     */
    public function upsert_donor($email, $data) {
        global $wpdb;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->donors_table} WHERE email = %s",
                $email
            )
        );

        if ($existing) {
            // Update existing donor
            $wpdb->update(
                $this->donors_table,
                $data,
                array('email' => $email),
                null,
                array('%s')
            );
            return $existing->id;
        } else {
            // Insert new donor
            $data['email'] = $email;
            $result = $wpdb->insert($this->donors_table, $data);
            
            if ($result !== false) {
                return $wpdb->insert_id;
            }
        }

        return false;
    }

    /**
     * Insert transaction
     */
    public function insert_transaction($data) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->transactions_table,
            $data
        );

        if ($result !== false) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Drop all tables
     */
    public function drop_tables() {
        global $wpdb;

        $tables = array(
            $this->donations_table,
            $this->campaigns_table,
            $this->donors_table,
            $this->transactions_table,
            $this->forms_table
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('helpme_donations_db_version');
    }
} 