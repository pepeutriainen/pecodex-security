<?php
/**
 * Zero Trust Security Module
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Zero_Trust {

    public function __construct() {
        add_action( 'wp_login', array( $this, 'verify_device' ), 10, 2 );
        add_filter( 'wp_is_application_passwords_available', '__return_false' );
    }

    /**
     * Verify device fingerprint on login.
     *
     * @param string  $user_login Username.
     * @param WP_User $user       WP_User object of the logged-in user.
     */
    public function verify_device( $user_login, $user ) {
        // Check if Zero Trust is enabled in settings
        $settings = get_option('pmc_advanced_settings', array());
        if (empty($settings['zero_trust_enabled']) || $settings['zero_trust_enabled'] === 'false') {
            return;
        }

        if ( ! isset( $_COOKIE['pmc_device_fp'] ) ) {
            if ( class_exists('Pecodex_Notifications') ) {
                $subject = 'Zero Trust: Uusi laitekirjautuminen';
                $message = sprintf( 'Käyttäjä %s kirjautui sisään uudelta laitteelta. Jos tämä olit sinä, toimenpiteitä ei tarvita.', $user_login );
                Pecodex_Notifications::send_notification('new_device', $subject, $message);
            }
            
            // Set the cookie for future logins (expires in 30 days)
            setcookie( 'pmc_device_fp', md5($user_login . time()), time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        }
    }

    /**
     * Destroy all sessions for a user, or current user.
     * 
     * @param int|null $user_id User ID to destroy sessions for. If null, current user.
     */
    public static function destroy_all_sessions( $user_id = null ) {
        if ( is_null( $user_id ) ) {
            wp_destroy_other_sessions();
        } else {
            $manager = WP_Session_Tokens::get_instance( $user_id );
            $manager->destroy_all();
        }
    }

    /**
     * Create .htaccess file in uploads directory to block PHP execution.
     */
    public static function block_php_in_uploads() {
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return;
        }

        $htaccess_file = trailingslashit( $upload_dir['basedir'] ) . '.htaccess';
        $rules = "<Files *.php>\ndeny from all\n</Files>";
        
        if ( ! file_exists( $htaccess_file ) ) {
            @file_put_contents( $htaccess_file, $rules );
        } else {
            $content = file_get_contents( $htaccess_file );
            if ( strpos( $content, '<Files *.php>' ) === false ) {
                @file_put_contents( $htaccess_file, "\n" . $rules, FILE_APPEND );
            }
        }
    }
}

// Initialize the class
new Pecodex_Zero_Trust();
