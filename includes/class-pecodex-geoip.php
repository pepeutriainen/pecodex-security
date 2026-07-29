<?php
/**
 * GeoIP Access Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_GeoIP {

	public function __construct() {
		add_action( 'init', array( $this, 'check_country_access' ), 5 );
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
			// If localhost, mock a location for local testing
			if ( $ip === '127.0.0.1' || $ip === '::1' ) {
				$mock = array(
					'status'      => 'success',
					'countryCode' => 'US',
					'city'        => 'New York (Local Test)',
					'lat'         => 40.7128,
					'lon'         => -74.0060,
					'query'       => $ip
				);
				set_transient( $transient_key, $mock, DAY_IN_SECONDS );
				return 'US';
			}

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
		$blocked_countries = get_option( 'pmc_blocked_countries', array() );
		
		if ( empty( $blocked_countries ) || ! is_array( $blocked_countries ) ) {
			return;
		}

		$ip = '';
		if ( class_exists( 'Pecodex_Firewall' ) && method_exists( 'Pecodex_Firewall', 'get_client_ip' ) ) {
			$ip = Pecodex_Firewall::get_client_ip();
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		if ( ! $ip ) {
			return;
		}

		$country = self::get_location( $ip );

		if ( $country && in_array( $country, $blocked_countries, true ) ) {
			wp_die( 'Access denied from your country.', 'Access Denied', array( 'response' => 403 ) );
		}
	}
}

new Pecodex_GeoIP();
