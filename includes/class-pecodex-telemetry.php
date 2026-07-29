<?php
/**
 * Pecodex Telemetry
 *
 * @package Pecodex_Media_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Pecodex_Telemetry
 *
 * Audit & Telemetry Tracker.
 */
class Pecodex_Telemetry {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'switch_theme', array( $this, 'log_switch_theme' ), 10, 3 );
		add_action( 'update_option_active_plugins', array( $this, 'log_update_option_active_plugins' ), 10, 3 );
	}

	/**
	 * Log theme switches.
	 *
	 * @param string   $new_name  Name of the new theme.
	 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @param WP_Theme $old_theme WP_Theme instance of the old theme.
	 */
	public function log_switch_theme( $new_name, $new_theme, $old_theme ) {
		$this->log_action( 'theme_switched', array(
			'new_theme' => $new_name,
			'old_theme' => is_a( $old_theme, 'WP_Theme' ) ? $old_theme->get( 'Name' ) : (string) $old_theme,
		) );
	}

	/**
	 * Log plugin activations and deactivations.
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	public function log_update_option_active_plugins( $old_value, $value, $option ) {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$value     = is_array( $value ) ? $value : array();

		$activated   = array_diff( $value, $old_value );
		$deactivated = array_diff( $old_value, $value );

		if ( ! empty( $activated ) ) {
			$this->log_action( 'plugin_activated', array( 'plugins' => array_values( $activated ) ) );
		}

		if ( ! empty( $deactivated ) ) {
			$this->log_action( 'plugin_deactivated', array( 'plugins' => array_values( $deactivated ) ) );
		}
	}

	/**
	 * Helper function to log actions.
	 *
	 * @param string $action Action name.
	 * @param array  $data   Additional data.
	 */
	private function log_action( $action, $data = array() ) {
		$log = get_option( 'pmc_advanced_audit_log', array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$entry = array(
			'timestamp' => time(),
			'action'    => $action,
			'data'      => $data,
		);

		if ( function_exists( 'get_current_user_id' ) ) {
			$entry['user_id'] = get_current_user_id();
		}

		// Prepend the new entry.
		array_unshift( $log, $entry );

		// Limit the log to the 1000 most recent entries to avoid bloating the options table.
		$log = array_slice( $log, 0, 1000 );

		update_option( 'pmc_advanced_audit_log', $log, false ); // Pass false to prevent autoload if large.
	}
}

new Pecodex_Telemetry();
