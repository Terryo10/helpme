<?php
/**
 * Currency management for Zimbabwe Donations
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZimDonations_Currency_Manager {

    /**
     * Supported currencies
     */
    private $supported_currencies = array(
        'USD' => array(
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'decimals' => 2,
            'position' => 'before'
        ),
        'ZIG' => array(
            'name' => 'Zimbabwean Gold',
            'symbol' => 'ZiG',
            'code' => 'ZIG',
            'decimals' => 2,
            'position' => 'before'
        ),
        'EUR' => array(
            'name' => 'Euro',
            'symbol' => '€',
            'code' => 'EUR',
            'decimals' => 2,
            'position' => 'before'
        ),
        'GBP' => array(
            'name' => 'British Pound',
            'symbol' => '£',
            'code' => 'GBP',
            'decimals' => 2,
            'position' => 'before'
        ),
        'ZAR' => array(
            'name' => 'South African Rand',
            'symbol' => 'R',
            'code' => 'ZAR',
            'decimals' => 2,
            'position' => 'before'
        )
    );

    /**
     * Exchange rate cache duration (in seconds)
     */
    private $cache_duration = 3600; // 1 hour

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
        add_action('wp_ajax_update_exchange_rates', array($this, 'ajax_update_exchange_rates'));
        add_action('zim_donations_update_exchange_rates', array($this, 'update_exchange_rates'));
        
        // Schedule daily rate updates
        if (!wp_next_scheduled('zim_donations_update_exchange_rates')) {
            wp_schedule_event(time(), 'daily', 'zim_donations_update_exchange_rates');
        }
    }

    /**
     * Get supported currencies
     */
    public function get_supported_currencies() {
        return apply_filters('zim_donations_supported_currencies', $this->supported_currencies);
    }

    /**
     * Get currency info
     */
    public function get_currency_info($currency_code) {
        $currencies = $this->get_supported_currencies();
        return isset($currencies[$currency_code]) ? $currencies[$currency_code] : false;
    }

    /**
     * Format currency amount
     */
    public function format_currency($amount, $currency_code, $show_code = false) {
        $currency = $this->get_currency_info($currency_code);
        
        if (!$currency) {
            return $amount;
        }

        $formatted_amount = number_format($amount, $currency['decimals']);
        
        if ($currency['position'] === 'before') {
            $formatted = $currency['symbol'] . $formatted_amount;
        } else {
            $formatted = $formatted_amount . $currency['symbol'];
        }

        if ($show_code) {
            $formatted .= ' ' . $currency['code'];
        }

        return $formatted;
    }

    /**
     * Convert currency amount
     */
    public function convert_currency($amount, $from_currency, $to_currency) {
        if ($from_currency === $to_currency) {
            return $amount;
        }

        // Get exchange rates
        $rates = $this->get_exchange_rates();
        
        if (!isset($rates[$from_currency]) || !isset($rates[$to_currency])) {
            return $amount; // Return original amount if rates not available
        }

        // Convert from source currency to USD, then to target currency
        $usd_amount = $amount / $rates[$from_currency];
        $converted_amount = $usd_amount * $rates[$to_currency];

        return round($converted_amount, $this->get_currency_info($to_currency)['decimals']);
    }

    /**
     * Get exchange rates
     */
    public function get_exchange_rates() {
        $rates = get_transient('zim_donations_exchange_rates');
        
        if (false === $rates) {
            $rates = $this->fetch_exchange_rates();
            set_transient('zim_donations_exchange_rates', $rates, $this->cache_duration);
        }

        return $rates;
    }

    /**
     * Fetch exchange rates from API
     */
    private function fetch_exchange_rates() {
        $default_rates = array(
            'USD' => 1.0,
            'ZIG' => 13.5, // Example rate - should be updated from real source
            'EUR' => 0.85,
            'GBP' => 0.73,
            'ZAR' => 18.5
        );

        // Try to get rates from external API
        $api_rates = $this->fetch_rates_from_api();
        
        if ($api_rates) {
            return array_merge($default_rates, $api_rates);
        }

        // Fallback to manual rates if API fails
        $manual_rates = get_option('zim_donations_manual_exchange_rates', array());
        
        return array_merge($default_rates, $manual_rates);
    }

    /**
     * Fetch rates from external API
     */
    private function fetch_rates_from_api() {
        $api_key = get_option('zim_donations_exchange_api_key', '');
        
        if (empty($api_key)) {
            return false;
        }

        // Using exchangerate-api.com as example
        $api_url = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/USD";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'ZimDonations/' . ZIM_DONATIONS_VERSION
            )
        ));

        if (is_wp_error($response)) {
            error_log('ZimDonations Exchange Rate API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || $data['result'] !== 'success') {
            return false;
        }

        $rates = array();
        $currencies = array_keys($this->get_supported_currencies());
        
        foreach ($currencies as $currency) {
            if (isset($data['conversion_rates'][$currency])) {
                $rates[$currency] = floatval($data['conversion_rates'][$currency]);
            }
        }

        return $rates;
    }

    /**
     * Update exchange rates manually
     */
    public function update_exchange_rates() {
        delete_transient('zim_donations_exchange_rates');
        $this->get_exchange_rates(); // This will fetch fresh rates
    }

    /**
     * AJAX handler for updating exchange rates
     */
    public function ajax_update_exchange_rates() {
        check_ajax_referer('zim_donations_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'zim-donations'));
        }

        $this->update_exchange_rates();
        
        wp_send_json_success(array(
            'message' => __('Exchange rates updated successfully.', 'zim-donations'),
            'rates' => $this->get_exchange_rates()
        ));
    }

    /**
     * Get default currency
     */
    public function get_default_currency() {
        return get_option('zim_donations_default_currency', 'USD');
    }

    /**
     * Set manual exchange rate
     */
    public function set_manual_rate($currency, $rate) {
        $manual_rates = get_option('zim_donations_manual_exchange_rates', array());
        $manual_rates[$currency] = floatval($rate);
        update_option('zim_donations_manual_exchange_rates', $manual_rates);
        
        // Clear cache to force refresh
        delete_transient('zim_donations_exchange_rates');
    }

    /**
     * Get manual exchange rates
     */
    public function get_manual_rates() {
        return get_option('zim_donations_manual_exchange_rates', array());
    }

    /**
     * Get currency dropdown options
     */
    public function get_currency_options() {
        $currencies = $this->get_supported_currencies();
        $options = array();
        
        foreach ($currencies as $code => $currency) {
            $options[$code] = $currency['name'] . ' (' . $currency['symbol'] . ')';
        }

        return $options;
    }

    /**
     * Validate currency code
     */
    public function is_valid_currency($currency_code) {
        $currencies = $this->get_supported_currencies();
        return isset($currencies[$currency_code]);
    }

    /**
     * Get minimum donation amount for currency
     */
    public function get_minimum_amount($currency_code) {
        $minimums = array(
            'USD' => 1,
            'ZIG' => 10,
            'EUR' => 1,
            'GBP' => 1,
            'ZAR' => 10
        );

        return isset($minimums[$currency_code]) ? $minimums[$currency_code] : 1;
    }

    /**
     * Get maximum donation amount for currency
     */
    public function get_maximum_amount($currency_code) {
        $maximums = array(
            'USD' => 10000,
            'ZIG' => 100000,
            'EUR' => 10000,
            'GBP' => 10000,
            'ZAR' => 150000
        );

        return isset($maximums[$currency_code]) ? $maximums[$currency_code] : 10000;
    }

    /**
     * Get suggested donation amounts for currency
     */
    public function get_suggested_amounts($currency_code) {
        $suggestions = array(
            'USD' => array(10, 25, 50, 100, 250),
            'ZIG' => array(100, 250, 500, 1000, 2500),
            'EUR' => array(10, 25, 50, 100, 250),
            'GBP' => array(10, 25, 50, 100, 250),
            'ZAR' => array(100, 250, 500, 1000, 2500)
        );

        return isset($suggestions[$currency_code]) ? $suggestions[$currency_code] : array(10, 25, 50, 100);
    }

    /**
     * Format amount for gateway
     */
    public function format_for_gateway($amount, $currency_code, $gateway) {
        $currency = $this->get_currency_info($currency_code);
        
        // Some gateways require amounts in cents
        $gateways_requiring_cents = array('stripe');
        
        if (in_array($gateway, $gateways_requiring_cents)) {
            return intval($amount * pow(10, $currency['decimals']));
        }

        return $amount;
    }

    /**
     * Format amount from gateway
     */
    public function format_from_gateway($amount, $currency_code, $gateway) {
        $currency = $this->get_currency_info($currency_code);
        
        // Some gateways return amounts in cents
        $gateways_requiring_cents = array('stripe');
        
        if (in_array($gateway, $gateways_requiring_cents)) {
            return floatval($amount) / pow(10, $currency['decimals']);
        }

        return floatval($amount);
    }
} 