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
		add_action( 'login_init', array( $this, 'check_login_access' ) );
		add_filter( 'authenticate', array( $this, 'check_admin_login' ), 100, 3 );
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

	public function check_login_access() {
		$login_countries = get_option( 'pmc_geoip_login_countries', array() );
		$login_action = get_option( 'pmc_geoip_login_action', 'hide_form' );
		
		if ( empty( $login_countries ) || ! is_array( $login_countries ) || $login_action !== 'hide_form' ) {
			return;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( class_exists( 'Pecodex_Firewall' ) && method_exists( 'Pecodex_Firewall', 'get_client_ip' ) ) {
			$ip = Pecodex_Firewall::get_client_ip();
		}
		
		$country = self::get_location( $ip );
		if ( $country && in_array( $country, $login_countries, true ) ) {
			wp_die( 'Login access denied from your country.', 'Access Denied', array( 'response' => 403 ) );
		}
	}

	public function check_admin_login( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
			return $user;
		}

		$login_countries = get_option( 'pmc_geoip_login_countries', array() );
		$login_action = get_option( 'pmc_geoip_login_action', 'hide_form' );

		if ( empty( $login_countries ) || ! is_array( $login_countries ) || $login_action !== 'block_admin' ) {
			return $user;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			if ( class_exists( 'Pecodex_Firewall' ) && method_exists( 'Pecodex_Firewall', 'get_client_ip' ) ) {
				$ip = Pecodex_Firewall::get_client_ip();
			}
			$country = self::get_location( $ip );
			if ( $country && in_array( $country, $login_countries, true ) ) {
				return new WP_Error( 'geoip_admin_blocked', 'Administrator login from your country is disabled.' );
			}
		}

		return $user;
	}
}

new Pecodex_GeoIP();
