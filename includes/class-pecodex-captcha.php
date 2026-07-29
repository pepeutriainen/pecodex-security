<?php

class Pecodex_Captcha {

    public function __construct() {
        add_action('login_form', array($this, 'add_captcha_to_login'));
        add_filter('wp_authenticate_user', array($this, 'verify_captcha'), 10, 2);
    }

    public function add_captcha_to_login() {
        $settings = get_option('pmc_firewall_settings', array());
        if (!empty($settings['turnstile_sitekey'])) {
            $sitekey = esc_attr($settings['turnstile_sitekey']);
            echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
            echo '<div class="cf-turnstile" data-sitekey="' . $sitekey . '"></div>';
        } else {
            $num1 = rand(1, 9);
            $num2 = rand(1, 9);
            $sum = $num1 + $num2;
            echo '<p><label for="pecodex_math_captcha">What is ' . $num1 . ' + ' . $num2 . '?</label><br/>';
            echo '<input type="text" name="pecodex_math_captcha" id="pecodex_math_captcha" class="input" value="" size="20" required /></p>';
            echo '<input type="hidden" name="pecodex_math_captcha_expected" value="' . base64_encode($sum) . '" />';
        }
    }

    public function verify_captcha($user, $password) {
        if ( is_wp_error( $user ) ) {
            return $user;
        }
        
        $settings = get_option('pmc_firewall_settings', array());
        if (!empty($settings['turnstile_sitekey'])) {
            if (!isset($_POST['cf-turnstile-response']) || empty($_POST['cf-turnstile-response'])) {
                return new WP_Error('empty_captcha', __('<strong>Error</strong>: Please complete the CAPTCHA.'));
            }
            // Turnstile logic
        } else {
            if (!isset($_POST['pecodex_math_captcha']) || !isset($_POST['pecodex_math_captcha_expected'])) {
                return new WP_Error('empty_captcha', __('<strong>Error</strong>: Please complete the math CAPTCHA.'));
            }
            $expected = base64_decode($_POST['pecodex_math_captcha_expected']);
            if (intval($_POST['pecodex_math_captcha']) !== intval($expected)) {
                return new WP_Error('invalid_captcha', __('<strong>Error</strong>: Incorrect math CAPTCHA.'));
            }
        }
        return $user;
    }
}
