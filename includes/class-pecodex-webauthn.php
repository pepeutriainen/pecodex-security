<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pecodex_WebAuthn {

	public function __construct() {
		// Login hooks
		add_action( 'login_form', array( $this, 'add_webauthn_login_button' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_webauthn_scripts' ) );

		// Profile hooks for registration
		add_action( 'show_user_profile', array( $this, 'add_webauthn_register_section' ) );
		add_action( 'edit_user_profile', array( $this, 'add_webauthn_register_section' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_webauthn_scripts' ) );

		// AJAX handlers for login
		add_action( 'wp_ajax_nopriv_pecodex_webauthn_login_challenge', array( $this, 'ajax_login_challenge' ) );
		add_action( 'wp_ajax_nopriv_pecodex_webauthn_login_verify', array( $this, 'ajax_login_verify' ) );

		// AJAX handlers for registration
		add_action( 'wp_ajax_pecodex_webauthn_register_challenge', array( $this, 'ajax_register_challenge' ) );
		add_action( 'wp_ajax_pecodex_webauthn_register_verify', array( $this, 'ajax_register_verify' ) );
	}

	public function add_webauthn_login_button() {
		?>
		<p>
			<button type="button" id="pecodex-webauthn-login-btn" class="button button-large" style="width:100%; margin-bottom: 16px;">
				Login with Passkey (WebAuthn)
			</button>
		</p>
		<?php
	}

	public function add_webauthn_register_section( $user ) {
		?>
		<h2>WebAuthn / Passkeys</h2>
		<table class="form-table">
			<tr>
				<th><label for="pecodex-webauthn-register-btn">Register Passkey</label></th>
				<td>
					<button type="button" id="pecodex-webauthn-register-btn" class="button">Register New Passkey</button>
					<p class="description">Register a WebAuthn passkey to log in securely.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function enqueue_webauthn_scripts() {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var webAuthnBtn = document.getElementById('pecodex-webauthn-login-btn');
				if (webAuthnBtn) {
					webAuthnBtn.addEventListener('click', function() {
						if (!window.PublicKeyCredential) {
							alert('WebAuthn is not supported in this browser.');
							return;
						}
						
						fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'action=pecodex_webauthn_login_challenge'
						})
						.then(response => response.json())
						.then(data => {
							if (!data.success) throw new Error('Challenge failed');
							
							const challenge = new Uint8Array(32); 
							const requestOptions = {
								challenge: challenge,
								allowCredentials: [],
								userVerification: "preferred"
							};
							
							return navigator.credentials.get({ publicKey: requestOptions });
						})
						.then(assertion => {
							return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: 'action=pecodex_webauthn_login_verify&assertion=' + encodeURIComponent(JSON.stringify(assertion))
							});
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								alert('Passkey verification successful! (Mock mode)');
							} else {
								alert('Login failed.');
							}
						})
						.catch(err => {
							console.error('WebAuthn error', err);
						});
					});
				}
			});
		</script>
		<?php
	}

	public function enqueue_admin_webauthn_scripts($hook) {
		if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
			return;
		}
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var registerBtn = document.getElementById('pecodex-webauthn-register-btn');
				if (registerBtn) {
					registerBtn.addEventListener('click', function() {
						if (!window.PublicKeyCredential) {
							alert('WebAuthn is not supported in this browser.');
							return;
						}
						
						fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'action=pecodex_webauthn_register_challenge'
						})
						.then(response => response.json())
						.then(data => {
							if (!data.success) throw new Error('Challenge failed');
							
							const challenge = new Uint8Array(32);
							const userId = new Uint8Array(16);
							
							const createOptions = {
								challenge: challenge,
								rp: { name: "WordPress Site", id: window.location.hostname },
								user: {
									id: userId,
									name: "user",
									displayName: "User"
								},
								pubKeyCredParams: [{ alg: -7, type: "public-key" }],
								authenticatorSelection: { userVerification: "preferred" },
								timeout: 60000,
								attestation: "direct"
							};
							
							return navigator.credentials.create({ publicKey: createOptions });
						})
						.then(credential => {
							return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: 'action=pecodex_webauthn_register_verify&credential=' + encodeURIComponent(JSON.stringify(credential))
							});
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								alert('Passkey registered successfully!');
							} else {
								alert('Registration failed.');
							}
						})
						.catch(err => {
							console.error('WebAuthn error', err);
						});
					});
				}
			});
		</script>
		<?php
	}

	public function ajax_login_challenge() {
		wp_send_json_success(array('challenge' => 'dummy_challenge_string'));
	}

	public function ajax_login_verify() {
		wp_send_json_success();
	}

	public function ajax_register_challenge() {
		if (!is_user_logged_in()) {
			wp_send_json_error();
		}
		wp_send_json_success(array('challenge' => 'dummy_challenge_string'));
	}

	public function ajax_register_verify() {
		if (!is_user_logged_in()) {
			wp_send_json_error();
		}
		wp_send_json_success();
	}
}

new Pecodex_WebAuthn();
