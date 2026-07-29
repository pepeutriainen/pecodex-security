<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Honeypot {

    public function __construct() {
        add_action( 'init', array( $this, 'check_honeypot' ) );
        add_action( 'wp_footer', array( $this, 'render_honeypot_link' ) );
        add_action( 'login_form', array( $this, 'add_honeypot_to_login' ) );
        add_filter( 'wp_authenticate_user', array( $this, 'verify_honeypot_login' ), 10, 2 );
    }

    public function check_honeypot() {
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if ( strpos( $request_uri, 'wp-config.php.bak' ) !== false || strpos( $request_uri, '?pmc_honeypot=1' ) !== false ) {
                header( 'HTTP/1.0 444 No Response' );
                exit;
            }
        }
    }

    public function render_honeypot_link() {
        echo '<a href="?pmc_honeypot=1" style="display:none;" aria-hidden="true">Leave this empty</a>';
    }

    public function add_honeypot_to_login() {
        echo '<p style="display:none;"><label for="pecodex_login_honeypot">Leave this field empty</label><br/>';
        echo '<input type="text" name="pecodex_login_honeypot" id="pecodex_login_honeypot" class="input" value="" size="20" tabindex="-1" autocomplete="off" /></p>';
    }

    public function verify_honeypot_login($user, $password) {
        if (isset($_POST['pecodex_login_honeypot']) && !empty($_POST['pecodex_login_honeypot'])) {
            return new WP_Error('honeypot_triggered', __('<strong>Error</strong>: Bot activity detected.'));
        }
        return $user;
    }
}

new Pecodex_Honeypot();
