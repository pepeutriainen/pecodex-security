<?php
/**
 * Cache handling for Pecodex Media Control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Pecodex_Cache {

	/**
	 * In-memory cache for IP address to avoid repeated lookups.
	 *
	 * @var array
	 */
	private static $ip_cache = [];

	/**
	 * Get the cached IP address.
	 *
	 * @return string|null The IP address or null if not found.
	 */
	public static function get_cached_ip() {
		if ( isset( self::$ip_cache['ip'] ) ) {
			return self::$ip_cache['ip'];
		}

		$ip = null;

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Get first IP in case of multiple IPs
			$ip_list = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip = trim( $ip_list[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		self::$ip_cache['ip'] = $ip;

		return self::$ip_cache['ip'];
	}
}
