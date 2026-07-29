<?php
/**
 * Pecodex Security - Notifications Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_Notifications {
	
	public function __construct() {
		// WordPress Updates (Core, Themes, Plugins)
		add_action( 'upgrader_process_complete', array( $this, 'handle_updates' ), 10, 2 );
		
		// New User Registration
		add_action( 'user_register', array( $this, 'handle_new_user' ), 10, 1 );
		
		// User Login
		add_action( 'wp_login', array( $this, 'handle_user_login' ), 10, 2 );
	}

	/**
	 * Central function to send notifications based on subscriber preferences.
	 */
	public static function send_notification( $event_type, $subject, $message ) {
		$settings = get_option( 'pmc_notification_settings', array() );
		if ( empty( $settings['subscribers'] ) || ! is_array( $settings['subscribers'] ) ) {
			return; // No subscribers configured
		}

		$site_url = home_url();
		$site_name = get_bloginfo( 'name' );
		
		$email_subject = "[$site_name Security] " . $subject;
		$email_body    = "Sivuston tietoturvailmoitus: $site_url\n\n";
		$email_body   .= $message . "\n\n";
		$email_body   .= "--\nLuo turvallisempaa verkkoa,\nPecodex Security";

		$headers = array('Content-Type: text/plain; charset=UTF-8');

		$charset_filter = function() { return 'UTF-8'; };
		add_filter( 'wp_mail_charset', $charset_filter );

		foreach ( $settings['subscribers'] as $sub ) {
			if ( empty( $sub['email'] ) || empty( $sub['events'] ) || ! is_array( $sub['events'] ) ) {
				continue;
			}
			
			// Check if this subscriber wants this specific event type
			if ( in_array( $event_type, $sub['events'], true ) ) {
				wp_mail( sanitize_email( $sub['email'] ), $email_subject, $email_body, $headers );
			}
		}

		remove_filter( 'wp_mail_charset', $charset_filter );
	}

	/**
	 * Handle core, plugin, and theme updates.
	 */
	public function handle_updates( $upgrader_object, $options ) {
		if ( ! isset( $options['type'] ) ) {
			return;
		}
		
		$type = $options['type']; // 'plugin', 'theme', 'core'
		$action = isset( $options['action'] ) ? $options['action'] : ''; // 'update' or 'install'

		if ( $action !== 'update' ) {
			return; // Only notify on updates
		}

		if ( $type === 'core' ) {
			self::send_notification(
				'core_update',
				'WordPress-ydin päivitetty',
				"WordPress-ydin on juuri päivitetty sivuillasi.\n\nTarkista sivuston toimivuus ja varmista, että kaikki toimii oikein."
			);
		} elseif ( $type === 'plugin' ) {
			self::send_notification(
				'plugin_update',
				'Lisäosa(t) päivitetty',
				"Yksi tai useampi lisäosa on päivitetty sivuillasi.\n\nTarkista sivuston toimivuus."
			);
		} elseif ( $type === 'theme' ) {
			self::send_notification(
				'theme_update',
				'Teema(t) päivitetty',
				"Yksi tai useampi teema on päivitetty sivuillasi.\n\nTarkista sivuston ulkoasu ja toimivuus."
			);
		}
	}

	/**
	 * Handle new user registration.
	 */
	public function handle_new_user( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::send_notification(
			'new_user',
			'Uusi käyttäjä rekisteröitynyt',
			sprintf(
				"Sivustolle on rekisteröitynyt uusi käyttäjä.\n\nKäyttäjätunnus: %s\nSähköposti: %s",
				$user->user_login,
				$user->user_email
			)
		);
	}

	/**
	 * Handle user logins (filter for admins).
	 */
	public function handle_user_login( $user_login, $user ) {
		// Only alert for administrators
		if ( in_array( 'administrator', (array) $user->roles ) ) {
			$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'Tuntematon';
			
			self::send_notification(
				'admin_login',
				'Ylläpitäjän kirjautuminen havaittu',
				sprintf(
					"Ylläpitäjä on kirjautunut sisään sivustollesi.\n\nKäyttäjätunnus: %s\nIP-osoite: %s\nAika: %s",
					$user_login,
					$ip,
					current_time( 'mysql' )
				)
			);
		}
	}
}
