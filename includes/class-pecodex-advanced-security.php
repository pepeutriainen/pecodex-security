<?php
/**
 * Advanced Security Class
 *
 * @package Pecodex_Media_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Pecodex_Advanced_Security
 * 
 * Handles Mask Login and Session Protection features.
 */
class Pecodex_Advanced_Security {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Mask Login Hooks
		add_action( 'login_init', array( $this, 'mask_login_area' ) );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'filter_network_site_url' ), 10, 3 );

		// Session Protection Hook
		add_filter( 'auth_cookie_expiration', array( $this, 'modify_cookie_expiration' ), 99, 3 );
		
		// Protect wp-admin
		add_action( 'init', array( $this, 'protect_wp_admin' ) );
	}

	/**
	 * Mask login area by blocking access to wp-login.php unless the custom parameter is present.
	 */
	public function mask_login_area() {
		if ( class_exists( 'Pecodex_Firewall' ) ) {
			$ip = Pecodex_Firewall::get_client_ip();
			if ( Pecodex_Firewall::is_ip_allowed( $ip ) ) {
				return;
			}
		}

		$settings = get_option( 'pmc_advanced_settings', array() );

		// Check if mask login is enabled
		$is_enabled = isset( $settings['mask_enabled'] ) ? filter_var( $settings['mask_enabled'], FILTER_VALIDATE_BOOLEAN ) : false;
		if ( ! $is_enabled ) {
			return;
		}

		$mask_url = ! empty( $settings['mask_url'] ) ? sanitize_text_field( $settings['mask_url'] ) : 'secret-login';

		// Allow logout action to bypass the mask
		if ( isset( $_GET['action'] ) && 'logout' === $_GET['action'] ) {
			return;
		}

		// Check if the custom URL parameter is present in the request
		if ( ! isset( $_GET[ $mask_url ] ) ) {
			$mask_redirect = ! empty( $settings['mask_redirect'] ) ? sanitize_text_field( $settings['mask_redirect'] ) : '';

			if ( empty( $mask_redirect ) ) {
				global $wp_query;
				if ( ! isset( $wp_query ) ) {
					$wp_query = new WP_Query();
				}
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
				include( get_query_template( '404' ) );
				exit;
			} else {
				// Prevent infinite redirect loop
				$redirect_path = wp_parse_url( $mask_redirect, PHP_URL_PATH );
				$current_path  = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

				if ( $redirect_path && $current_path && untrailingslashit( $redirect_path ) === untrailingslashit( $current_path ) ) {
					return;
				}

				wp_safe_redirect( $mask_redirect );
				exit;
			}
		}
	}

	/**
	 * Protect wp-admin by redirecting unauthorized access to the mask redirect or 404 page.
	 */
	public function protect_wp_admin() {
		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() ) {
			$settings = get_option( 'pmc_advanced_settings', array() );
			$is_enabled = isset( $settings['mask_enabled'] ) ? filter_var( $settings['mask_enabled'], FILTER_VALIDATE_BOOLEAN ) : false;
			
			if ( ! $is_enabled ) {
				return;
			}
			
			$mask_redirect = ! empty( $settings['mask_redirect'] ) ? sanitize_text_field( $settings['mask_redirect'] ) : '';
			
			if ( empty( $mask_redirect ) ) {
				global $wp_query;
				if ( ! isset( $wp_query ) ) {
					$wp_query = new WP_Query();
				}
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
				include( get_query_template( '404' ) );
				exit;
			} else {
				wp_safe_redirect( $mask_redirect );
				exit;
			}
		}
	}

	/**
	 * Filter site_url to append the mask URL parameter.
	 *
	 * @param string      $url     The complete site URL including scheme and path.
	 * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
	 * @param string|null $scheme  Scheme to give the site URL context.
	 * @param int|null    $blog_id Site ID.
	 * @return string
	 */
	public function filter_site_url( $url, $path, $scheme, $blog_id ) {
		return $this->update_login_url( $url, $path );
	}

	/**
	 * Filter network_site_url to append the mask URL parameter.
	 *
	 * @param string      $url    The complete network site URL including scheme and path.
	 * @param string      $path   Path relative to the network site URL. Blank string if no path is specified.
	 * @param string|null $scheme Scheme to give the site URL context.
	 * @return string
	 */
	public function filter_network_site_url( $url, $path, $scheme ) {
		return $this->update_login_url( $url, $path );
	}

	/**
	 * Update the login URL with the mask parameter if enabled.
	 *
	 * @param string $url  The original URL.
	 * @param string $path The path of the URL.
	 * @return string
	 */
	private function update_login_url( $url, $path ) {
		// Only modify wp-login.php URLs
		if ( $path && strpos( $path, 'wp-login.php' ) !== false ) {
			$settings = get_option( 'pmc_advanced_settings', array() );
			
			$is_enabled = isset( $settings['mask_enabled'] ) ? filter_var( $settings['mask_enabled'], FILTER_VALIDATE_BOOLEAN ) : false;
			
			if ( $is_enabled && ! empty( $settings['mask_url'] ) ) {
				// Do not modify logout links
				if ( strpos( $url, 'action=logout' ) === false ) {
					$mask_url = sanitize_text_field( $settings['mask_url'] );
					$url = add_query_arg( $mask_url, '1', $url );
				}
			}
		}

		return $url;
	}

	/**
	 * Modify the authentication cookie expiration based on session protection settings.
	 *
	 * @param int  $expiration The duration in seconds the authentication cookie should be valid.
	 * @param int  $user_id    User ID.
	 * @param bool $remember   Whether to remember the user login.
	 * @return int
	 */
	public function modify_cookie_expiration( $expiration, $user_id, $remember ) {
		$settings = get_option( 'pmc_advanced_settings', array() );

		// Check if session protection is enabled
		$is_enabled = isset( $settings['session_enabled'] ) ? filter_var( $settings['session_enabled'], FILTER_VALIDATE_BOOLEAN ) : false;
		if ( ! $is_enabled ) {
			return $expiration;
		}

		$duration = isset( $settings['session_duration'] ) ? intval( $settings['session_duration'] ) : 0;

		// If a valid duration is set, override the default expiration
		if ( $duration > 0 ) {
			// Assuming the duration is in minutes
			return $duration * MINUTE_IN_SECONDS;
		}

		return $expiration;
	}
}

// Optionally instantiate the class if not done elsewhere.
new Pecodex_Advanced_Security();
