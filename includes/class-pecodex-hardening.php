<?php
/**
 * Hardening and Security Headers.
 *
 * @package Pecodex_Media_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Pecodex_Hardening
 */
class Pecodex_Hardening {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'send_headers', array( $this, 'send_security_headers' ) );
		$this->apply_security_tweaks();

		// 2. Add methods hooked to send_headers to inject robust security headers
		add_action( 'send_headers', array( $this, 'inject_robust_security_headers' ) );

		// 3. Add methods hooked to init to block XML-RPC
		add_action( 'init', array( $this, 'disable_xmlrpc_via_init' ) );

		// 4. Hide WordPress version (remove wp_generator from wp_head)
		remove_action( 'wp_head', 'wp_generator' );
	}

	/**
	 * Inject robust security headers unconditionally.
	 */
	public function inject_robust_security_headers() {
		if ( ! headers_sent() ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'X-XSS-Protection: 1; mode=block' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
			header( "Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:;" );
		}
	}

	/**
	 * Disable XML-RPC on init.
	 */
	public function disable_xmlrpc_via_init() {
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

	/**
	 * Output security headers.
	 */
	public function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}

		$headers = get_option( 'pmc_security_headers', array() );

		if ( ! is_array( $headers ) ) {
			return;
		}

		if ( ! empty( $headers['sh_strict_transport'] ) ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}

		if ( ! empty( $headers['sh_xframe'] ) ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
		}

		if ( ! empty( $headers['sh_xss_protection'] ) ) {
			header( 'X-XSS-Protection: 1; mode=block' );
		}

		if ( ! empty( $headers['sh_content_type_options'] ) ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		if ( ! empty( $headers['sh_referrer_policy'] ) ) {
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}

		if ( ! empty( $headers['sh_feature_policy'] ) ) {
			header( 'Permissions-Policy: accelerometer=(), autoplay=(), camera=(), encrypted-media=(), fullscreen=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), usb=()' );
		}

		if ( ! empty( $headers['sh_content_security_policy'] ) ) {
			header( "Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:;" );
		}
	}

	/**
	 * Apply security tweaks.
	 */
	private function apply_security_tweaks() {

		$tweaks = get_option( 'pmc_security_tweaks', array() );

		if ( ! is_array( $tweaks ) ) {
			return;
		}

		// Block XML-RPC
		if ( ! empty( $tweaks['xml_rpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_action( 'init', array( $this, 'block_xmlrpc_endpoint' ), 1 );
		}

		// Prevent User Enumeration
		if ( ! empty( $tweaks['prevent_enum'] ) ) {
			add_action( 'init', array( $this, 'block_user_enumeration' ) );
			add_filter( 'rest_endpoints', array( $this, 'block_rest_user_endpoints' ) );
		}

		// Disable Trackbacks
		if ( ! empty( $tweaks['disable_trackbacks'] ) ) {
			add_filter( 'xmlrpc_methods', array( $this, 'disable_trackback_methods' ) );
			add_filter( 'wp_headers', array( $this, 'remove_x_pingback_header' ) );
			add_filter( 'pings_open', '__return_false', 10, 2 );
			add_filter( 'pre_option_default_ping_status', '__return_zero' );
		}

		// Hide WP Version
		if ( ! empty( $tweaks['wp_version'] ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
			if ( ! is_admin() ) {
				add_filter( 'style_loader_src', array( $this, 'remove_wp_version_strings' ), 15, 1 );
				add_filter( 'script_loader_src', array( $this, 'remove_wp_version_strings' ), 15, 1 );
			}
		}

		// Hide PHP Errors
		if ( ! empty( $tweaks['hide_errors'] ) ) {
			if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
				ini_set( 'display_errors', 0 );
			}
		}

		// Login Duration
		if ( ! empty( $tweaks['login_duration'] ) ) {
			add_filter( 'auth_cookie_expiration', array( $this, 'reduce_login_duration' ), 10, 3 );
		}
	}

	/**
	 * Completely block XML-RPC endpoint.
	 */
	public function block_xmlrpc_endpoint() {
		$script_name = isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( $_SERVER['SCRIPT_FILENAME'] ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		
		if ( stripos( $script_name, 'xmlrpc.php' ) !== false || stripos( $request_uri, 'xmlrpc.php' ) !== false ) {
			header( 'HTTP/1.1 403 Forbidden' );
			die( 'XML-RPC on poistettu käytöstä turvallisuussyistä.' );
		}
	}

	/**
	 * Block user enumeration by redirecting author archive requests.
	 */
	public function block_user_enumeration() {
		if ( ! is_admin() && isset( $_SERVER['QUERY_STRING'] ) && preg_match( '/\bauthor=\d+/i', $_SERVER['QUERY_STRING'] ) ) {
			wp_safe_redirect( home_url(), 301 );
			exit;
		}
	}

	/**
	 * Remove users endpoint from REST API for unauthenticated users.
	 */
	public function block_rest_user_endpoints( $endpoints ) {
		if ( isset( $endpoints['/wp/v2/users'] ) && ! current_user_can( 'list_users' ) ) {
			unset( $endpoints['/wp/v2/users'] );
		}
		if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) && ! current_user_can( 'list_users' ) ) {
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	/**
	 * Disable trackback methods in XML-RPC.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array
	 */
	public function disable_trackback_methods( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Remove X-Pingback header.
	 *
	 * @param array $headers HTTP headers.
	 * @return array
	 */
	public function remove_x_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}
	/**
	 * Remove WP version from scripts and styles.
	 */
	public function remove_wp_version_strings( $src ) {
		if ( strpos( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Reduce login duration.
	 */
	public function reduce_login_duration( $length, $user_id, $remember ) {
		if ( $remember ) {
			return 86400; // 1 day
		}
		return 3600; // 1 hour
	}
	/**
	 * Update .htaccess rules for Hardening tweaks.
	 *
	 * @param array $tweaks The hardening settings.
	 */
	public static function update_htaccess_rules( $tweaks ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Root .htaccess rules
		$root_htaccess = get_home_path() . '.htaccess';
		$root_rules = array();

		// Directory Indexing
		if ( ! empty( $tweaks['disable_indexes'] ) ) {
			$root_rules[] = 'Options -Indexes';
		}

		// Protect Info (wp-config.php, readme.html, license.txt)
		if ( ! empty( $tweaks['protect_info'] ) ) {
			$root_rules[] = '<FilesMatch "^(wp-config\.php|readme\.html|license\.txt|error_log)$">';
			$root_rules[] = 'Require all denied';
			$root_rules[] = '</FilesMatch>';
		}

		insert_with_markers( $root_htaccess, 'Pecodex Security Hardening', $root_rules );

		// Uploads .htaccess rules (Prevent PHP)
		$upload_dir = wp_get_upload_dir();
		if ( ! empty( $upload_dir['basedir'] ) ) {
			$uploads_htaccess = $upload_dir['basedir'] . '/.htaccess';
			$uploads_rules = array();

			if ( ! empty( $tweaks['prevent_php'] ) ) {
				$uploads_rules[] = '<Files *.php>';
				$uploads_rules[] = 'Require all denied';
				$uploads_rules[] = '</Files>';
			}

			// If no rules and file exists with markers, insert_with_markers will just remove the block.
			insert_with_markers( $uploads_htaccess, 'Pecodex Security Prevent PHP', $uploads_rules );
		}
	}
}

new Pecodex_Hardening();
