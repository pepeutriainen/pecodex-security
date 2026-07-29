<?php
/**
 * GeoIP Access Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_GeoIP {

	public function __construct() {
		add_action( 'login_init', array( $this, 'check_country_access' ) );
	}

	public static function get_location( $ip ) {
		$country = '';
		$transient_key = 'pmc_geoip_' . md5( $ip );
		$cached_country = get_transient( $transient_key );
		
		if ( false !== $cached_country ) {
			if ( is_array( $cached_country ) && isset( $cached_country['countryCode'] ) ) {
				$country = $cached_country['countryCode'];
			} else {
				$country = $cached_country;
			}
		} else {
			$response = wp_remote_get( 'http://ip-api.com/json/' . $ip );
			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $body['countryCode'] ) ) {
					$country = $body['countryCode'];
					set_transient( $transient_key, $country, DAY_IN_SECONDS );
				}
			}
		}
		return $country;
	}

	public function check_country_access() {
		// Define blocked countries from settings
		$settings = get_option( 'pmc_firewall_settings', array() );
		$blocked_countries = isset( $settings['blocked_countries'] ) && is_array( $settings['blocked_countries'] ) ? $settings['blocked_countries'] : array();

		if ( empty( $blocked_countries ) ) {
			return;
		}

		if ( class_exists( 'Pecodex_Firewall' ) && Pecodex_Firewall::is_ip_allowed( Pecodex_Firewall::get_client_ip() ) ) {
			return;
		}

		$country = '';

		// Check if Cloudflare IP Country header is set
		if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$country = $_SERVER['HTTP_CF_IPCOUNTRY'];
		} else {
			// Fallback to IP-API
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
			if ( $ip ) {
				$country = self::get_location( $ip );
			}
		}

		if ( $country && in_array( $country, $blocked_countries, true ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'pmc_lockout_log';
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
				$wpdb->insert( $table, array(
					'ip' => $ip,
					'type' => 'geoip_block',
					'reason' => 'Country blocked: ' . $country,
					'date' => current_time( 'mysql' )
				) );
			}
			wp_die( 'GeoIP Blocked', 'Access Denied', array( 'response' => 403 ) );
		}
	}
}

new Pecodex_GeoIP();
