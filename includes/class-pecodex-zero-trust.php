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
        
        // Hooks for password changes to log out all other devices
        add_action( 'password_reset', array( $this, 'destroy_all_sessions' ) );
        add_action( 'profile_update', array( $this, 'on_profile_update' ), 10, 2 );
        
        // Enforce max 1 concurrent session per user
        add_filter( 'authenticate', array( $this, 'limit_concurrent_sessions' ), 99, 3 );
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
                $message = sprintf( 'Käyttöjä %s kirjautui sisään uudelta laitteelta. Jos tämä olit sinä, toimenpiteitä ei tarvita.', $user_login );
                Pecodex_Notifications::send_notification('new_device', $subject, $message);
            }
            
            // Set the cookie for future logins (expires in 30 days)
            setcookie( 'pmc_device_fp', md5($user_login . time()), time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        }
    }

    /**
     * Destroy all sessions for a user.
     * 
     * @param int|WP_User $user_id User ID or WP_User object to destroy sessions for.
     */
    public function destroy_all_sessions( $user_id ) {
        if ( $user_id instanceof WP_User ) {
            $user_id = $user_id->ID;
        }
        
        if ( ! empty( $user_id ) ) {
            // As requested, call wp_destroy_other_sessions
            // Note: In WP core this does not take $user_id but we safely call it here
            wp_destroy_other_sessions();
            
            // Explicitly destroy all tokens for this specific user
            $manager = WP_Session_Tokens::get_instance( $user_id );
            $manager->destroy_all();
        }
    }

    /**
     * Helper for profile_update hook.
     */
    public function on_profile_update( $user_id, $old_user_data ) {
        $user = get_userdata( $user_id );
        if ( $user && $old_user_data && $user->user_pass !== $old_user_data->user_pass ) {
            $this->destroy_all_sessions( $user_id );
        }
    }

    /**
     * Limit concurrent sessions to 1 by destroying all other sessions on authenticate.
     */
    public function limit_concurrent_sessions( $user, $username, $password ) {
        if ( $user instanceof WP_User ) {
            $this->destroy_all_sessions( $user->ID );
        }
        return $user;
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
