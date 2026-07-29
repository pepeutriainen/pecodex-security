<?php
/**
 * Authentication handling
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-pecodex-totp.php';

class Pecodex_Authentication {

	public function __construct() {
		add_filter( 'wp_authenticate_user', array( $this, 'check_pwned_password' ), 10, 2 );
		add_action( 'user_profile_update_errors', array( $this, 'check_profile_password' ), 10, 3 );
		add_action( 'validate_password_reset', array( $this, 'check_reset_password' ), 10, 2 );

		// 2FA hooks
		add_action( 'show_user_profile', array( $this, 'user_profile_2fa' ) );
		add_action( 'edit_user_profile', array( $this, 'user_profile_2fa' ) );
		add_action( 'personal_options_update', array( $this, 'update_user_profile_2fa' ) );
		add_action( 'edit_user_profile_update', array( $this, 'update_user_profile_2fa' ) );
		add_action( 'wp_ajax_pecodex_generate_totp', array( $this, 'ajax_generate_totp' ) );

		add_filter( 'authenticate', array( $this, 'intercept_login_for_2fa' ), 30, 3 );
		add_action( 'login_init', array( $this, 'handle_2fa_form' ) );
	}

	/**
	 * Check if the password has been breached using HaveIBeenPwned API.
	 *
	 * @param WP_User|WP_Error $user     User object or WP_Error.
	 * @param string           $password Password to check.
	 * @return WP_User|WP_Error
	 */
	public function check_pwned_password( $user, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( empty( $password ) ) {
			return $user;
		}

		$sha1_password = strtoupper( sha1( $password ) );
		$prefix        = substr( $sha1_password, 0, 5 );
		$suffix        = substr( $sha1_password, 5 );

		$api_url = 'https://api.pwnedpasswords.com/range/' . $prefix;

		$response = wp_remote_get( $api_url );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body  = wp_remote_retrieve_body( $response );
			$lines = explode( "\r\n", $body );
			
			foreach ( $lines as $line ) {
				if ( strpos( $line, $suffix . ':' ) === 0 ) {
					return new WP_Error(
						'pwned_password',
						__( '<strong>ERROR</strong>: Your password has been found in a data breach. Please use a different password for security reasons.', 'pecodex-media-control' )
					);
				}
			}
		}

		return $user;
	}

	public function check_profile_password( $errors, $update, $user ) {
		if ( ! empty( $_POST['pass1'] ) ) {
			$password = $_POST['pass1'];
			$result = $this->check_pwned_password( $user, $password );
			if ( is_wp_error( $result ) ) {
				$errors->add( 'pwned_password', $result->get_error_message() );
			}
		}
	}

	public function check_reset_password( $errors, $user ) {
		if ( ! empty( $_POST['pass1'] ) ) {
			$password = $_POST['pass1'];
			$result = $this->check_pwned_password( $user, $password );
			if ( is_wp_error( $result ) ) {
				$errors->add( 'pwned_password', $result->get_error_message() );
			}
		}
	}

	public function intercept_login_for_2fa( $user, $username, $password ) {
		if ( $user instanceof WP_User ) {
			$secret = get_user_meta( $user->ID, 'pecodex_2fa_secret', true );
			if ( ! empty( $secret ) ) {
				wp_clear_auth_cookie();

				$token = wp_generate_password( 32, false );
				set_transient( 'pecodex_2fa_' . $token, $user->ID, 5 * MINUTE_IN_SECONDS );

				$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url();
				
				$url = add_query_arg( array(
					'pecodex_2fa_token' => $token,
					'redirect_to'       => urlencode( $redirect_to ),
					'rememberme'        => isset( $_POST['rememberme'] ) ? '1' : '0'
				), wp_login_url() );

				wp_safe_redirect( $url );
				exit;
			}
		}
		return $user;
	}

	public function handle_2fa_form() {
		if ( isset( $_GET['pecodex_2fa_token'] ) ) {
			$token = sanitize_text_field( $_GET['pecodex_2fa_token'] );
			$user_id = get_transient( 'pecodex_2fa_' . $token );
			
			if ( ! $user_id ) {
				wp_die( 'Invalid or expired 2FA token. Please log in again.', 'Error', array( 'response' => 403 ) );
			}

			$error = '';
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['pecodex_2fa_code'] ) ) {
				$code = sanitize_text_field( $_POST['pecodex_2fa_code'] );
				$secret = get_user_meta( $user_id, 'pecodex_2fa_secret', true );
				
				if ( Pecodex_TOTP::verify_code( $secret, $code ) ) {
					delete_transient( 'pecodex_2fa_' . $token );
					$remember = isset( $_GET['rememberme'] ) && $_GET['rememberme'] === '1';
					wp_set_auth_cookie( $user_id, $remember );
					
					$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : admin_url();
					wp_safe_redirect( $redirect_to );
					exit;
				} else {
					$error = 'Invalid 2FA code.';
				}
			}

			login_header( 'Two-Factor Authentication' );
			?>
			<form name="loginform" id="loginform" action="" method="post">
				<p>
					<label for="pecodex_2fa_code">Authenticator Code<br />
					<input type="text" name="pecodex_2fa_code" id="pecodex_2fa_code" class="input" value="" size="20" autocomplete="off" /></label>
				</p>
				<?php if ( ! empty( $error ) ) : ?>
					<div id="login_error"><?php echo esc_html( $error ); ?></div>
				<?php endif; ?>
				<p class="submit">
					<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Verify" />
				</p>
			</form>
			<?php
			login_footer();
			exit;
		}
	}

	public function user_profile_2fa( $user ) {
		$secret = get_user_meta( $user->ID, 'pecodex_2fa_secret', true );
		?>
		<h2>Two-Factor Authentication</h2>
		<table class="form-table">
			<tr>
				<th><label>TOTP 2FA</label></th>
				<td>
					<?php if ( $secret ) : ?>
						<p style="color:green;">2FA is currently <strong>enabled</strong>.</p>
						<p>
							<input type="checkbox" name="pecodex_disable_2fa" value="1" id="pecodex_disable_2fa" />
							<label for="pecodex_disable_2fa">Disable 2FA</label>
						</p>
					<?php else : ?>
						<p>2FA is disabled. Click the button below to generate a secret.</p>
						<button type="button" class="button" id="pecodex_generate_2fa">Generate 2FA Secret</button>
						<div id="pecodex_2fa_setup" style="display:none; margin-top:15px;">
							<p>1. Scan this QR code with your authenticator app:</p>
							<img id="pecodex_2fa_qr" src="" alt="QR Code" />
							<p>2. Enter the code from your app to verify and enable:</p>
							<input type="text" name="pecodex_2fa_code" id="pecodex_2fa_code" value="" autocomplete="off" />
							<input type="hidden" name="pecodex_2fa_new_secret" id="pecodex_2fa_new_secret" value="" />
							<p class="description">You must click "Update Profile" at the bottom of the page to save.</p>
						</div>
						<script>
							document.getElementById('pecodex_generate_2fa').addEventListener('click', function() {
								var btn = this;
								btn.disabled = true;
								btn.innerText = 'Generating...';
								
								var data = new FormData();
								data.append('action', 'pecodex_generate_totp');
								data.append('user_id', '<?php echo $user->ID; ?>');
								data.append('nonce', '<?php echo wp_create_nonce("pecodex_generate_totp"); ?>');
								
								fetch(ajaxurl, {
									method: 'POST',
									body: data
								}).then(r => r.json()).then(res => {
									if (res.success) {
										document.getElementById('pecodex_2fa_qr').src = res.data.qr_url;
										document.getElementById('pecodex_2fa_new_secret').value = res.data.secret;
										document.getElementById('pecodex_2fa_setup').style.display = 'block';
										btn.style.display = 'none';
									} else {
										alert('Error generating secret.');
										btn.disabled = false;
										btn.innerText = 'Generate 2FA Secret';
									}
								});
							});
						</script>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public function update_user_profile_2fa( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( isset( $_POST['pecodex_disable_2fa'] ) && $_POST['pecodex_disable_2fa'] == '1' ) {
			delete_user_meta( $user_id, 'pecodex_2fa_secret' );
		}

		if ( ! empty( $_POST['pecodex_2fa_new_secret'] ) && ! empty( $_POST['pecodex_2fa_code'] ) ) {
			$secret = sanitize_text_field( $_POST['pecodex_2fa_new_secret'] );
			$code = sanitize_text_field( $_POST['pecodex_2fa_code'] );

			if ( Pecodex_TOTP::verify_code( $secret, $code ) ) {
				update_user_meta( $user_id, 'pecodex_2fa_secret', $secret );
			} else {
				add_action( 'user_profile_update_errors', function( $errors ) {
					$errors->add( 'pecodex_2fa_error', 'Invalid 2FA code. The 2FA secret was not saved.' );
				} );
			}
		}
	}

	public function ajax_generate_totp() {
		check_ajax_referer( 'pecodex_generate_totp', 'nonce' );
		
		if ( ! current_user_can( 'edit_user', $_POST['user_id'] ) ) {
			wp_send_json_error();
		}

		$user = get_userdata( $_POST['user_id'] );
		$secret = Pecodex_TOTP::generate_secret();
		$issuer = get_bloginfo('name');
		$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode(Pecodex_TOTP::get_provisioning_uri($secret, $user->user_email, $issuer)) . '&size=200x200';

		wp_send_json_success( array(
			'secret' => $secret,
			'qr_url' => $qr_url
		) );
	}
}

new Pecodex_Authentication();
