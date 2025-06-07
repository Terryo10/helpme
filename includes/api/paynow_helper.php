<?php

/**
 * WooCommerce PayNow Helper Class.
 *
 * This class provides helper methods for integrating with the PayNow payment gateway in WooCommerce.
 */
class WC_Paynow_Helper {

	/**
	 * Parse a PayNow message into an associative array.
	 *
	 * @param string $msg The PayNow message string.
	 * @return array An associative array representing the parsed message.
	 */
	public function parseMsg( $msg ) {
		// Convert to array data
		$parts = explode('&', $msg);
		$result = array();

		foreach ($parts as $i => $value) {
			$bits = explode('=', $value, 2);
			$result[$bits[0]] = urldecode($bits[1]);
		}

		return $result;
	}

	/**
	 * Convert an associative array to a URL-encoded string.
	 *
	 * @param array $fields An associative array of data to be URL-encoded.
	 * @return string A URL-encoded string.
	 */
	public function urlIfy( $fields ) {
		// URL-ify the data for the POST
		$delim = '';
		$fields_string = '';

		foreach ($fields as $key => $value) {
			$fields_string .= $delim . $key . '=' . $value;
			$delim = '&';
		}

		return $fields_string;
	}

	/**
	 * Create a hash for PayNow using provided values and the MerchantKey.
	 *
	 * @param array $values An associative array of values.
	 * @param string $MerchantKey The PayNow Merchant Key.
	 * @return string The generated hash.
	 */
	public function createHash( $values, $MerchantKey ) {
		$string = '';

		foreach ($values as $key => $value) {
			if (strtoupper($key) != 'HASH') {
				$string .= $value;
			}
		}

		$string .= $MerchantKey;
		$hash = hash('sha512', $string);

		return strtoupper($hash);
	}

	/**
	 * Create a PayNow message string with URL-encoded values and a hash.
	 *
	 * @param array $values An associative array of values.
	 * @param string $MerchantKey The PayNow Merchant Key.
	 * @return string A URL-encoded PayNow message string.
	 */
	public function createMsg( $values, $MerchantKey ) {
		$fields = array();

		foreach ($values as $key => $value) {
			$fields[$key] = urlencode($value);
		}

		$fields['hash'] = urlencode($this->createHash($values, $MerchantKey));
		$fields_string = $this->urlIfy($fields);

		return $fields_string;
	}
	public function checkNetwork($phoneNumber) {
		// Remove any non-digit characters from the phone number
		$phoneNumber = preg_replace('/\D/', '', $phoneNumber);
	
		// Perform network detection based on the phone number format
		if (preg_match('/^(078|77|077)/', $phoneNumber)) {
			return 'ecocash';
		} elseif (preg_match('/^(71|071)/', $phoneNumber)) {
			return 'onemoney';
		} else {
			return 'unknown';
		}
	}
}
