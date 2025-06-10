<?php
class HelpMeAppKeys
{
    /**
     * Payment gateways instance
     */
    public $payment_gateways;

    /**
     * Available payment gateways array
     */
    public $available_gateways = array();

    public function __construct()
    {
        $this->get_payment_gateways();
    }

    /**
     * Get array of available payment gateways
     */
    public function get_payment_gateways()
    {
        // Check if payment gateways class exists
        if (class_exists('ZimDonations_Payment_Gateways')) {
            $this->payment_gateways = new ZimDonations_Payment_Gateways();
            $this->available_gateways = $this->payment_gateways->get_available_gateways();
        } else {
            $this->available_gateways = array();
        }

        return $this->available_gateways;
    }

    /**
     * Get available gateways (public method)
     */
    public function get_available_gateways()
    {
        return $this->available_gateways;
    }

    public function is_gateway_enabled($gateway_id)
    {
        $enabled_gateways = get_option('helpme_donations_enabled_gateways', array());
        return in_array($gateway_id, $enabled_gateways);
    }

    /**
     * Get Stripe publishable key
     */
    public function get_stripe_publishable_key()
    {
        if (!$this->is_gateway_enabled('stripe')) {
            return '';
        }

        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_stripe_test_publishable_key', '') :
            get_option('helpme_donations_stripe_live_publishable_key', '');
    }
    public function get_stripe_secret_key()
    {
        if (!$this->is_gateway_enabled('stripe')) {
            return '';
        }

        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_stripe_test_secret_key', '') :
            get_option('helpme_donations_stripe_live_secret_key', '');
    }

    /**
     * Get PayPal client ID
     */
    public function get_paypal_client_id()
    {
        if (!$this->is_gateway_enabled('paypal')) {
            return '';
        }

        $test_mode = get_option('helpme_donations_test_mode', true);
        return $test_mode ?
            get_option('helpme_donations_paypal_test_client_id', '') :
            get_option('helpme_donations_paypal_live_client_id', '');
    }
}
