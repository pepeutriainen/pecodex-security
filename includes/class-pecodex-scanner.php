<?php
/**
 * Pecodex Scanner functionality.
 *
 * @package Pecodex_Media_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Pecodex_Scanner
 */
class Pecodex_Scanner {

	/**
	 * Initialize the scanner and hook into WordPress.
	 */
	public function __construct() {
		// Hooking scanner functionality into WordPress
		add_action( 'init', array( $this, 'init_hooks' ) );
	}

	/**
	 * Initialize additional hooks (e.g., cron jobs, admin actions).
	 */
	public function init_hooks() {
		// Example: Register a custom cron action for periodic scanning
		add_action( 'pecodex_run_checksum_scan', array( $this, 'verify_core_checksums' ) );
	}

	/**
	 * Fetches core checksums and compares local ABSPATH files.
	 *
	 * @return array List of modified or missing files, or array with 'error' key on failure.
	 */
	public function verify_core_checksums() {
		global $wp_version;

		$locale = get_locale();
		
		$url = add_query_arg(
			array(
				'version' => $wp_version,
				'locale'  => $locale,
			),
			'https://api.wordpress.org/core/checksums/1.0/'
		);

		$request = wp_remote_get( $url );

		if ( is_wp_error( $request ) ) {
			return array( 'error' => 'Unable to connect to WordPress.org checksum API.' );
		}

		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['checksums'] ) ) {
			return array( 'error' => 'Invalid checksum data returned from API.' );
		}

		$modified_files = array();

		foreach ( $data['checksums'] as $file => $expected_checksum ) {
			$file_path = ABSPATH . $file;

			if ( ! file_exists( $file_path ) ) {
				// File is missing, consider it modified/tampered.
				$modified_files[] = $file;
				continue;
			}

			$actual_checksum = md5_file( $file_path );

			if ( $actual_checksum !== $expected_checksum ) {
				$modified_files[] = $file;
			}
		}

		return $modified_files;
	}
}
