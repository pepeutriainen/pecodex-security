$file = "c:\Users\pepeu\Local Sites\tem-kumpulanspy\app\public\wp-content\plugins\pecodex-media-control\pecodex-media-control.php"
$content = Get-Content $file -Raw -Encoding Default

$search1 = "	private function authorize_private_file_request( `$attachment_id, `$relative_path, `$force_protected = false ) {
		// Share token ohittaa kirjautumistarkistuksen.
		if ( isset( `$_GET['share_token'] ) && `$attachment_id ) {
			`$token = sanitize_text_field( wp_unslash( `$_GET['share_token'] ) );
			if ( `$this->verify_and_consume_share_token( `$attachment_id, `$token ) ) {
				return;
			}
			// Token annettu mutta ei kelpaa - ei ohjata kirjautumiseen vaan estet`ä`n.
			`$this->deny_request( 'Jakolinkki on vanhentunut tai jo k`ä`ytetty.', 403 );
		}

		if ( ! is_user_logged_in() ) {
			`$this->mark_private_response_uncacheable();
			wp_safe_redirect( `$this->build_login_start_url( `$attachment_id, `$relative_path, `$force_protected ) );
			exit;
		}"

$replace1 = "	private function authorize_private_file_request( `$attachment_id, `$relative_path, `$force_protected = false ) {
		if ( ! is_user_logged_in() ) {
			`$this->mark_private_response_uncacheable();
			`$login_url = `$this->build_login_start_url( `$attachment_id, `$relative_path, `$force_protected );
			if ( isset( `$_GET['share_token'] ) ) {
				`$login_url = add_query_arg( 'share_token', rawurlencode( sanitize_text_field( wp_unslash( `$_GET['share_token'] ) ) ), `$login_url );
			}
			wp_safe_redirect( `$login_url );
			exit;
		}

		// Share token ohittaa kansiotarkistukset mutta vaatii kirjautumisen.
		if ( isset( `$_GET['share_token'] ) && `$attachment_id ) {
			`$token = sanitize_text_field( wp_unslash( `$_GET['share_token'] ) );
			if ( `$this->verify_and_consume_share_token( `$attachment_id, `$token ) ) {
				return;
			}
			`$this->deny_request( 'Jakolinkki on vanhentunut tai jo k`ä`ytetty.', 403 );
		}"

$search2 = "		`$return_data  = array(
			'file'  => `$relative_path,
			'id'    => `$attachment_id,
			'force' => `$force_protected ? 1 : 0,
		);
		`$continue_url = `$this->build_login_return_url( `$token );"

$replace2 = "		`$return_data  = array(
			'file'  => `$relative_path,
			'id'    => `$attachment_id,
			'force' => `$force_protected ? 1 : 0,
		);
		if ( isset( `$_GET['share_token'] ) ) {
			`$return_data['share_token'] = sanitize_text_field( wp_unslash( `$_GET['share_token'] ) );
		}
		`$continue_url = `$this->build_login_return_url( `$token );"

$search3 = "		`$this->mark_private_response_uncacheable();
		wp_safe_redirect(
			`$this->build_private_file_url(
				absint( `$return_data['id'] ),
				`$relative_path,
				! empty( `$return_data['force'] )
			)
		);
		exit;"

$replace3 = "		`$this->mark_private_response_uncacheable();
		`$private_url = `$this->build_private_file_url(
			absint( `$return_data['id'] ),
			`$relative_path,
			! empty( `$return_data['force'] )
		);
		if ( ! empty( `$return_data['share_token'] ) ) {
			`$private_url = add_query_arg( 'share_token', rawurlencode( `$return_data['share_token'] ), `$private_url );
		}
		wp_safe_redirect( `$private_url );
		exit;"

$search4 = "		if ( is_user_logged_in() ) {
			`$this->mark_private_response_uncacheable();
			`$private_url = `$this->build_private_file_url( `$attachment_id, `$relative_path, `$force_protected );
			`$this->debug_log("

$replace4 = "		if ( is_user_logged_in() ) {
			`$this->mark_private_response_uncacheable();
			`$private_url = `$this->build_private_file_url( `$attachment_id, `$relative_path, `$force_protected );
			if ( isset( `$_GET['share_token'] ) ) {
				`$private_url = add_query_arg( 'share_token', rawurlencode( sanitize_text_field( wp_unslash( `$_GET['share_token'] ) ) ), `$private_url );
			}
			`$this->debug_log("

# Fix encoding issues in source strings manually for reliability
$search1 = $search1 -replace 'k`ä`ytetty', 'kytetty' -replace 'estet`ä`n', 'estetn'
$replace1 = $replace1 -replace 'k`ä`ytetty', 'kytetty' -replace 'estet`ä`n', 'estetn'

if ($content.Contains($search1)) { Write-Host "Found search1" } else { Write-Host "Failed to find search1" }
if ($content.Contains($search2)) { Write-Host "Found search2" } else { Write-Host "Failed to find search2" }
if ($content.Contains($search3)) { Write-Host "Found search3" } else { Write-Host "Failed to find search3" }
if ($content.Contains($search4)) { Write-Host "Found search4" } else { Write-Host "Failed to find search4" }

$content = $content.Replace($search1, $replace1)
$content = $content.Replace($search2, $replace2)
$content = $content.Replace($search3, $replace3)
$content = $content.Replace($search4, $replace4)

Set-Content $file -Value $content -Encoding Default
