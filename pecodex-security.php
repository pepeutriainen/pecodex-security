<?php
/**
 * Plugin Name: Pecodex Security
 * Plugin URI: https://pecodex.fi/
 * Description: Pecodex Security järjestää mediakirjaston kansioihin, suojaa uploads-linkit kirjautumis- ja oikeustarkistuksilla ja tarjoaa reaaliaikaisen tietoturvadashboardin Leaflet-kartalla.
 * Version: 0.8.68
 * Author: Pepe Utriainen / Pecodex
 * Author URI: https://pecodex.fi/
 * Company: Pecodex
 * Text Domain: private-gutenberg-media
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-pgm-media-organizer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pgm-visibility-api.php';

/**
 * Lisäosan pääluokka.
 *
 * Tärkein idea:
 * - Media Library, kansiot ja editori käyttävät samaa tiedostotason suojausta.
 * - Julkisella puolella suojatun median upload-linkki muutetaan admin-post.php endpointiksi.
 * - Endpoint tarkistaa kirjautumisen, käyttäjän oikeuden ja WordPress-noncen.
 * - Suojattu media ja suojatut kansiot siirretään pois julkisesta uploads-kansiosta.
 */
final class PGM_Private_Gutenberg_Media {
	const VERSION       = '0.8.68';
	const OPTION_NAME   = 'pgm_options';
	const VERSION_OPTION = 'pgm_plugin_version';
	const PUBLIC_UPLOAD_HTACCESS_BACKUP_OPTION = 'pgm_public_upload_htaccess_backup';
	const PUBLIC_UPLOAD_HTACCESS_CREATED_OPTION = 'pgm_public_upload_htaccess_created';
	const PUBLIC_UPLOAD_HTACCESS_STATUS_OPTION = 'pgm_public_upload_htaccess_status';
	const META_PRIVATE  = '_pgm_private';
	const META_PRIVATE_MANUAL = '_pgm_private_manual';
	const META_PUBLIC_OVERRIDE = '_pgm_public_override';
	const META_BLOCK_SOURCES = '_pgm_private_block_sources';
	const META_FOLDER_SOURCES = '_pgm_private_folder_sources';
	const META_ORIGINAL_PUBLIC_URL = '_pgm_original_public_url';
	const META_ACTIVE_PROTECTED_URL = '_pgm_active_protected_url';
	const META_CONTENT_LINKS_PRIVATE = '_pgm_content_links_private';
	const META_CONTENT_LINKS_SYNCED_AT = '_pgm_content_links_synced_at';
	const META_PRIVATE_STORAGE_PATHS = '_pgm_private_storage_paths';
	const POST_META_BLOCK_PATHS = '_pgm_private_block_paths';
	const LEGACY_DEFAULT_PROTECTED_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,zip';
	const ACTION        = 'pgm_private_media';
	const ACTION_LOGIN  = 'pgm_private_media_login';
	const ACTION_RETURN = 'pgm_private_media_return';
	const ACTION_SYNC_STORAGE = 'pgm_sync_private_storage';
	const ACTION_DIAGNOSTIC_REPAIR = 'pgm_private_media_diagnostic_repair';
	const PECODEX_ACTION        = 'pecodex_private_media';
	const PECODEX_ACTION_LOGIN  = 'pecodex_private_media_login';
	const PECODEX_ACTION_RETURN = 'pecodex_private_media_return';
	const PECODEX_ACTION_SYNC_STORAGE = 'pecodex_sync_private_storage';
	const PECODEX_ACTION_DIAGNOSTIC_REPAIR = 'pecodex_private_media_diagnostic_repair';
	const AJAX_TOGGLE_ATTACHMENT_PRIVACY = 'pgm_toggle_attachment_privacy';
	const AJAX_BULK_TOGGLE_ATTACHMENT_PRIVACY = 'pgm_bulk_toggle_attachment_privacy';
	const AJAX_BULK_DELETE_ATTACHMENTS = 'pgm_bulk_delete_attachments';
	const PECODEX_AJAX_TOGGLE_ATTACHMENT_PRIVACY = 'pecodex_toggle_attachment_privacy';
	const PECODEX_AJAX_BULK_TOGGLE_ATTACHMENT_PRIVACY = 'pecodex_bulk_toggle_attachment_privacy';
	const PECODEX_AJAX_BULK_DELETE_ATTACHMENTS = 'pecodex_bulk_delete_attachments';
	const RETURN_COOKIE = 'pgm_private_media_return_token';
	const DOWNLOAD_TOKEN_QUERY_ARG = 'pct';
	const STORAGE_DIR_NAME = 'private-gutenberg-media';
	const SETTINGS_PAGE_SLUG = 'pecodex-media-library-settings';
	const LEGACY_SETTINGS_PAGE_SLUG = 'private-gutenberg-media';
	const STORAGE_HEADER = "PGMENC1\n";
	const STORAGE_PHP_GUARD = "<?php\n// Pecodex Media Control encrypted storage.\ndefined('ABSPATH') || exit;\n__halt_compiler();\n";
	const STORAGE_LEGACY_PHP_GUARD = "<?php\n// Private Gutenberg Media encrypted storage.\ndefined('ABSPATH') || exit;\n__halt_compiler();\n";
	const STORAGE_KEY_OPTION = 'pgm_private_storage_key';
	const STORAGE_LEGACY_KEY_OPTION = 'pgm_private_storage_legacy_key';

	/**
	 * Pyynnön aikainen välimuisti upload-polkujen ja attachment-ID:iden välille.
	 *
	 * Tämä vähentää tietokantakyselyitä, kun samalla sivulla on monta linkkiä
	 * samaan mediaan tai saman kuvan eri kokoversioihin.
	 *
	 * @var array<string,int>
	 */
	private $attachment_lookup_cache = array();

	/**
	 * Estää save_post-käsittelijän rekursion, kun lisäosa siivoaa vanhoja
	 * nonce-linkkejä pois tallennetusta sisällöstä wp_update_post()-kutsulla.
	 *
	 * @var bool
	 */
	private $cleaning_post_content = false;

	/**
	 * Request-scoped id for correlating Pecodex debug lines.
	 *
	 * @var string
	 */
	private $debug_request_id = '';

	/**
	 * Defers uploads .htaccess refreshes so bulk operations write rules once.
	 *
	 * @var bool
	 */
	private $needs_public_upload_protection_rules_refresh = false;

	/**
	 * Runtime cache for public upload copy checks.
	 *
	 * @var array
	 */
	private $attachment_public_copy_cache = array();

	/**
	 * Runtime cache for private storage copy checks.
	 *
	 * @var array
	 */
	private $attachment_private_copy_cache = array();

	public static function instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	private function __construct() {
		PGM_Media_Organizer::instance( __FILE__ );

		// Hallintapuoli: asetussivu, asetusten tallennus, varoitukset ja editorin oma sivupalkkikenttä.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_settings_page' ) );
		add_action( 'admin_init', array( $this, 'migrate_legacy_default_extension_policy' ), 4 );
		add_action( 'admin_init', array( $this, 'migrate_gutenberg_link_sources_to_file_level_protection' ), 5 );
		// add_action( 'admin_init', array( $this, 'repair_policy_only_private_attachments_once' ), 6 );
		// add_action( 'admin_init', array( $this, 'repair_source_locked_private_upload_copies' ), 7 );
		// add_action( 'admin_init', array( $this, 'repair_stale_public_private_storage_meta' ), 8 );
		add_action( 'admin_init', array( $this, 'refresh_upload_protection_rules_after_update' ), 9 );
		add_action( 'admin_notices', array( $this, 'show_private_storage_notice' ) );
		add_action( 'init', array( $this, 'ensure_private_storage_key_options' ), 5 );
		add_action( 'init', array( $this, 'register_post_meta_fields' ) );
		add_action( 'template_redirect', array( $this, 'enforce_page_visibility_protection' ) );
		add_action( 'template_redirect', array( $this, 'handle_frontend_media_access_denied' ), 0 );
		add_action( 'admin_head', array( $this, 'print_admin_status_styles' ) );
		add_action( 'admin_post_' . self::ACTION_SYNC_STORAGE, array( $this, 'handle_sync_private_storage_request' ) );
		add_action( 'admin_post_' . self::PECODEX_ACTION_SYNC_STORAGE, array( $this, 'handle_sync_private_storage_request' ) );
		add_action( 'admin_post_' . self::ACTION_DIAGNOSTIC_REPAIR, array( $this, 'handle_diagnostic_repair_request' ) );
		add_action( 'admin_post_' . self::PECODEX_ACTION_DIAGNOSTIC_REPAIR, array( $this, 'handle_diagnostic_repair_request' ) );
		add_action( 'wp_ajax_' . self::AJAX_TOGGLE_ATTACHMENT_PRIVACY, array( $this, 'handle_toggle_attachment_privacy_ajax' ) );
		add_action( 'wp_ajax_' . self::PECODEX_AJAX_TOGGLE_ATTACHMENT_PRIVACY, array( $this, 'handle_toggle_attachment_privacy_ajax' ) );
		add_action( 'wp_ajax_' . self::AJAX_BULK_TOGGLE_ATTACHMENT_PRIVACY, array( $this, 'handle_bulk_toggle_attachment_privacy_ajax' ) );
		add_action( 'wp_ajax_' . self::PECODEX_AJAX_BULK_TOGGLE_ATTACHMENT_PRIVACY, array( $this, 'handle_bulk_toggle_attachment_privacy_ajax' ) );
		add_action( 'wp_ajax_' . self::AJAX_BULK_DELETE_ATTACHMENTS, array( $this, 'handle_bulk_delete_attachments_ajax' ) );
		add_action( 'wp_ajax_' . self::PECODEX_AJAX_BULK_DELETE_ATTACHMENTS, array( $this, 'handle_bulk_delete_attachments_ajax' ) );
		add_action( 'wp_ajax_pgm_deactivation_batch_restore', array( $this, 'handle_deactivation_batch_restore_ajax' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'add_block_editor_badge_styles' ), 10, 2 );
		add_action( 'save_post', array( $this, 'mark_private_block_media_on_save' ), 20, 3 );

		// WAF Extended Protection (auto_prepend_file) -ilmoitus ja käsittely
		add_action( 'admin_notices', array( $this, 'show_waf_optimization_notice' ) );
		add_action( 'admin_post_pecodex_optimize_waf', array( $this, 'handle_optimize_waf_request' ) );

		// Tausta-ajot
		add_action( 'pecodex_background_sync_cron', array( $this, 'process_background_sync_batch' ) );
		add_action( 'wp_ajax_pecodex_check_sync_status', array( $this, 'ajax_check_sync_status' ) );
		add_action( 'wp_ajax_pecodex_trigger_sync_batch', array( $this, 'ajax_trigger_sync_batch' ) );

		// Media Library: yksityinen media -metatieto ja valinnainen tiedostojen siirto yksityiseen varastoon.
		add_action( 'add_attachment', array( $this, 'maybe_mark_new_attachment_private' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'maybe_move_generated_private_files' ), 9999, 2 );
		add_filter( 'get_attached_file', array( $this, 'filter_private_attached_file' ), 10, 2 );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_private_attachment_url' ), 20, 2 );
		// add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_private_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_private_field' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_attachment_private_status_to_js' ), 10, 3 );
		
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_roles_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_roles_field' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'add_media_visibility_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_visibility_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_admin_assets' ) );
		add_action( 'updated_option', array( $this, 'maybe_sync_private_attachments_after_settings_update' ), 10, 3 );
		add_action( 'pgm_mo_attachment_folder_changed', array( $this, 'sync_attachment_folder_privacy' ), 10, 3 );
		add_action( 'pgm_mo_folder_access_changed', array( $this, 'sync_folder_privacy_for_folder_tree' ), 10, 3 );
		add_filter( 'pgm_mo_duplicate_attachment_source_contents', array( $this, 'provide_duplicate_attachment_source_contents' ), 10, 3 );
		add_filter( 'pgm_mo_export_attachment_source_contents', array( $this, 'provide_duplicate_attachment_source_contents' ), 10, 3 );
		add_action( 'pgm_mo_attachment_duplicated', array( $this, 'sync_duplicated_attachment_privacy' ), 10, 2 );
		add_action( 'delete_attachment', array( $this, 'cleanup_private_storage_on_delete' ) );
		add_action( 'admin_footer-plugins.php', array( $this, 'add_deactivation_warning_script' ) );

		// Julkinen puoli: suojattujen mediatiedostojen muunto endpointiksi ja FloMembers-paluu kirjautumisen jälkeen.
		add_filter( 'the_content', array( $this, 'rewrite_content_upload_urls' ), 20 );
		add_filter( 'widget_text_content', array( $this, 'rewrite_content_upload_urls' ), 20 );
		add_filter( 'render_block', array( $this, 'rewrite_private_block_upload_urls' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_legacy_hidden_upload_request' ), 0 );
		add_action( 'template_redirect', array( $this, 'start_frontend_private_media_output_buffer' ), 1 );
		add_filter( 'floauth_modify_redirect_path', array( $this, 'modify_floauth_redirect_path' ), 20, 2 );
		add_filter( 'floauth_restrict_extranet_block_redirect', array( $this, 'override_floauth_native_redirect' ), 20 );

		// Suojattu latausendpoint. Nopriv-reitti tarvitaan, jotta kirjautumaton voidaan ohjata kirjautumissivulle.
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_file_request' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_file_request' ) );
		add_action( 'admin_post_' . self::PECODEX_ACTION, array( $this, 'handle_file_request' ) );
		add_action( 'admin_post_nopriv_' . self::PECODEX_ACTION, array( $this, 'handle_file_request' ) );
		add_action( 'admin_post_' . self::ACTION_LOGIN, array( $this, 'handle_login_start_request' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_LOGIN, array( $this, 'handle_login_start_request' ) );
		add_action( 'admin_post_' . self::PECODEX_ACTION_LOGIN, array( $this, 'handle_login_start_request' ) );
		add_action( 'admin_post_nopriv_' . self::PECODEX_ACTION_LOGIN, array( $this, 'handle_login_start_request' ) );
		add_action( 'admin_post_' . self::ACTION_RETURN, array( $this, 'handle_login_return_request' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_RETURN, array( $this, 'handle_login_return_request' ) );
		add_action( 'admin_post_' . self::PECODEX_ACTION_RETURN, array( $this, 'handle_login_return_request' ) );
		add_action( 'admin_post_nopriv_' . self::PECODEX_ACTION_RETURN, array( $this, 'handle_login_return_request' ) );
		add_action( 'shutdown', array( $this, 'flush_queued_public_upload_protection_rules_refresh' ), 20 );

		// Share link -toiminnot
		add_action( 'wp_ajax_pgm_create_share_link',  array( $this, 'handle_create_share_link_ajax' ) );
		add_action( 'wp_ajax_pgm_delete_share_link',  array( $this, 'handle_delete_share_link_ajax' ) );
		add_action( 'wp_ajax_pgm_revoke_share_link',  array( $this, 'handle_revoke_share_link_ajax' ) );
		add_action( 'wp_ajax_pgm_revoke_all_share_links', array( $this, 'handle_revoke_all_share_links_ajax' ) );
		add_action( 'wp_ajax_pgm_load_shared_files',  array( $this, 'handle_load_shared_files_ajax' ) );
		add_action( 'wp_ajax_pgm_search_users',       array( $this, 'handle_search_users_ajax' ) );
		add_action( 'admin_post_pgm_shared_files_ui', array( $this, 'render_shared_files_ui_iframe' ) );
		add_action( 'admin_menu',                     array( $this, 'add_shared_files_page' ) );
		add_action( 'admin_enqueue_scripts',          array( $this, 'enqueue_shared_files_admin_assets' ) );

		// Requires will happen below

		// Initialize Pecodex Security Engine
		require_once __DIR__ . '/includes/class-pecodex-firewall.php';
		require_once __DIR__ . '/includes/class-pecodex-hardening.php';
		require_once __DIR__ . '/includes/class-pecodex-advanced-security.php';
		require_once __DIR__ . '/includes/class-pecodex-scanner.php';
		require_once __DIR__ . '/includes/class-pecodex-bot-protection.php';
		require_once __DIR__ . '/includes/class-pecodex-authentication.php';
		require_once __DIR__ . '/includes/class-pecodex-audit.php';
		
		// Pecodex V3 Enterprise Modules
		require_once __DIR__ . '/includes/class-pecodex-waf.php';
		require_once __DIR__ . '/includes/class-pecodex-geoip.php';
		require_once __DIR__ . '/includes/class-pecodex-deep-scanner.php';
		require_once __DIR__ . '/includes/class-pecodex-webauthn.php';
		require_once __DIR__ . '/includes/class-pecodex-captcha.php';
		require_once __DIR__ . '/includes/class-pecodex-wizard.php';
		require_once __DIR__ . '/includes/class-pecodex-telemetry.php';
		require_once __DIR__ . '/includes/class-pecodex-cache.php';

		// Pecodex V4 Military Grade
		require_once __DIR__ . '/includes/class-pecodex-honeypot.php';
		require_once __DIR__ . '/includes/class-pecodex-zero-trust.php';
		require_once __DIR__ . '/includes/class-pecodex-encryption.php';
		require_once __DIR__ . '/includes/class-pecodex-lockdown.php';
		require_once __DIR__ . '/includes/class-pecodex-app-sec.php';
		require_once __DIR__ . '/includes/class-pecodex-rate-limit.php';
		require_once __DIR__ . '/includes/class-pecodex-cron.php';
		require_once __DIR__ . '/includes/class-pecodex-db-security.php';
		require_once __DIR__ . '/includes/class-pecodex-vulnerabilities.php';
		require_once __DIR__ . '/includes/class-pecodex-hardening.php';
		require_once __DIR__ . '/includes/class-pecodex-bot-protection.php';
		require_once __DIR__ . '/includes/class-pecodex-captcha.php';
		require_once __DIR__ . '/includes/class-pecodex-honeypot.php';
		require_once __DIR__ . '/includes/class-pecodex-zero-trust.php';

		// Initialize new security layers
		Pecodex_Rate_Limit::init();
		Pecodex_Cron::init();
		Pecodex_Vulnerabilities::init();

		$active_modules = get_option('pmc_active_modules', array());
		// Treat as first run if empty or if it's a numeric array instead of associative
		$is_first_run = empty($active_modules) || isset($active_modules[0]);

		// Helper to check if a module is active
		$is_active = function($id) use ($active_modules, $is_first_run) {
			if ($is_first_run) return true;
			return !empty($active_modules[$id]);
		};

		if ($is_active('firewall'))    new Pecodex_Firewall();
		if ($is_active('hardening'))   new Pecodex_Hardening();
		if ($is_active('advanced'))    new Pecodex_Advanced_Security();
		if ($is_active('scanner'))     new Pecodex_Scanner();
		if ($is_active('bot'))         new Pecodex_Bot_Protection();
		
		// Unconditional security modules
		new Pecodex_Captcha();
		new Pecodex_Honeypot();
		new Pecodex_Zero_Trust();
		if ($is_active('auth'))        new Pecodex_Authentication();
		if ($is_active('audit'))       new Pecodex_Audit();
		if ($is_active('waf'))         new Pecodex_WAF();
		if ($is_active('geoip'))       new Pecodex_GeoIP();
		if ($is_active('deepscanner')) new Pecodex_Deep_Scanner();
		if ($is_active('webauthn'))    new Pecodex_WebAuthn();
		if ($is_active('captcha'))     new Pecodex_Captcha();
		if ($is_active('wizard'))      new Pecodex_Wizard();
		if ($is_active('telemetry'))   new Pecodex_Telemetry();
		if ($is_active('cache'))       new Pecodex_Cache();
		if ($is_active('honeypot'))    new Pecodex_Honeypot();
		if ($is_active('zerotrust'))   new Pecodex_Zero_Trust();
		if ($is_active('encryption'))  new Pecodex_Encryption();
		if ($is_active('lockdown'))    new Pecodex_Lockdown();
		if ($is_active('appsec'))      new Pecodex_App_Sec();

		// Initialize Pecodex Notifications Manager
		require_once __DIR__ . '/includes/class-pecodex-notifications.php';
		new Pecodex_Notifications();

		// Initialize Pecodex Security Dashboard API
		require_once __DIR__ . '/includes/class-pecodex-security-api.php';
		new Pecodex_Security_API();
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_options() );
		}

		if ( class_exists( 'PGM_Media_Organizer' ) ) {
			PGM_Media_Organizer::activate();
		}

		require_once __DIR__ . '/includes/class-pecodex-firewall.php';
		Pecodex_Firewall::install();

		self::instance()->refresh_public_upload_protection_rules();
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	public static function deactivate() {
		self::restore_media_to_public_wordpress_state_on_exit( false );
	}

	public static function uninstall() {
		self::restore_media_to_public_wordpress_state_on_exit( true );
	}

	private static function restore_media_to_public_wordpress_state_on_exit( $delete_plugin_data = false ) {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$instance = self::instance();
		if ( class_exists( 'PGM_Media_Organizer' ) ) {
			PGM_Media_Organizer::instance( __FILE__ )->register_taxonomy();
		}

		$attachment_ids = $instance->attachment_ids_for_exit_restore();
		$restored       = 0;
		$failed         = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$result = $instance->restore_attachment_to_plain_wordpress_media( $attachment_id );
			if ( is_wp_error( $result ) ) {
				$failed[ $attachment_id ] = $result->get_error_message();
				continue;
			}

			$restored++;
		}

		$instance->remove_public_upload_protection_rules();

		if ( $delete_plugin_data ) {
			$instance->delete_plugin_data_after_exit_restore();
		} else {
			update_option(
				'pgm_last_exit_restore_report',
				array(
					'ran_at'   => time(),
					'total'    => count( $attachment_ids ),
					'restored' => $restored,
					'failed'   => $failed,
				),
				false
			);
		}
	}

	public function register_post_meta_fields() {
		$post_types = array( 'page', 'post' );
		foreach ( $post_types as $post_type ) {
			register_post_meta( $post_type, '_pgm_protection_type', array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				}
			) );

			register_post_meta( $post_type, '_pgm_visibility_roles', array(
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string'
						),
					),
				),
				'single'       => true,
				'type'         => 'array',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				}
			) );

			register_post_meta( $post_type, '_pgm_no_access_message', array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				}
			) );
		}
	}

	public function ensure_private_storage_key_options() {
		if ( ! $this->private_storage_crypto_available() ) {
			return;
		}

		$this->private_storage_key();
		$this->store_legacy_private_storage_key();
	}

	/**
	 * Oletusasetukset.
	 *
	 * enabled on tarkoituksella erillinen hätäkatkaisin: ylläpitäjä voi pysäyttää
	 * lisäosan toiminnan asetuksista ilman, että pluginia tarvitsee poistaa.
	 */
	private static function default_options() {
		return array(
			'enabled'                 => 1,
			'login_mode'              => 'auto',
			'custom_login_url'        => '',
			'protect_mode'            => 'marked',
			'protected_extensions'    => self::LEGACY_DEFAULT_PROTECTED_EXTENSIONS,
			'protect_unknown_uploads' => 0,
			'move_files_to_private_storage' => 0,
			'required_capability'     => 'read',
		);
	}

	private function get_options() {
		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::default_options() );
	}

	public function refresh_upload_protection_rules_after_update() {
		$stored_version = (string) get_option( self::VERSION_OPTION, '' );
		if ( self::VERSION === $stored_version ) {
			return;
		}

		if ( false !== $this->refresh_public_upload_protection_rules() ) {
			update_option( self::VERSION_OPTION, self::VERSION, false );
		}
	}

	/**
	 * Kertoo, onko suojaus käytössä.
	 *
	 * Tätä tarkistusta käytetään kaikissa ulospäin vaikuttavissa kohdissa:
	 * editoripaneeli, linkkien muunto, mahdollinen tiedostojen siirto ja latausendpoint.
	 */
	private function is_enabled() {
		$options = $this->get_options();

		return ! empty( $options['enabled'] );
	}

	private function debug_enabled() {
		if ( defined( 'PGM_DEBUG_LOG' ) && PGM_DEBUG_LOG ) {
			return true;
		}

		return defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	private function debug_request_id() {
		if ( '' === $this->debug_request_id ) {
			$this->debug_request_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'pgm-', true );
		}

		return $this->debug_request_id;
	}

	private function debug_log( $event, $context = array() ) {
		return; // Disable debug logging for production
		if ( ! $this->debug_enabled() ) {
			return;
		}

		$payload = array(
			'rid'     => $this->debug_request_id(),
			'event'   => sanitize_key( (string) $event ),
			'filter'  => current_filter(),
			'user_id' => get_current_user_id(),
			'uri'     => $this->debug_redact_string( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ),
			'context' => $this->debug_clean_context( $context ),
		);

		error_log( '[Pecodex Media Control DEBUG] ' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private function debug_clean_context( $context ) {
		if ( ! is_array( $context ) ) {
			return $this->debug_clean_value( $context, '' );
		}

		$clean = array();
		foreach ( $context as $key => $value ) {
			$key = is_scalar( $key ) ? (string) $key : 'value';
			if ( preg_match( '/(?:nonce|token|cookie|secret|salt|password|pass|auth)/i', $key ) ) {
				$clean[ $key ] = '[redacted]';
				continue;
			}

			$clean[ $key ] = $this->debug_clean_value( $value, $key );
		}

		return $clean;
	}

	private function debug_clean_value( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			return $this->debug_clean_context( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_object( $value ) ) {
			return method_exists( $value, 'get_error_message' ) ? $value->get_error_message() : get_class( $value );
		}

		if ( preg_match( '/(?:nonce|token|cookie|secret|salt|password|pass|auth)/i', (string) $key ) ) {
			return '[redacted]';
		}

		return $this->debug_redact_string( (string) $value );
	}

	private function debug_redact_string( $value ) {
		$value = preg_replace( '/([?&](?:_wpnonce|nonce|pgm_token|token|key|secret|password|pass|auth)[^=]*=)[^&#]*/i', '$1[redacted]', $value );

		if ( strlen( $value ) > 500 ) {
			$value = substr( $value, 0, 500 ) . '...';
		}

		return $value;
	}

	private function debug_attachment_state( $attachment_id, $relative_path = null ) {
		$attachment_id = absint( $attachment_id );

		return array(
			'attachment_id'       => $attachment_id,
			'relative_path'       => $relative_path,
			'is_private'          => $attachment_id ? (bool) get_post_meta( $attachment_id, self::META_PRIVATE, true ) : false,
			'is_manual_private'   => $attachment_id ? (bool) get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true ) : false,
			'has_protected_content_links' => $attachment_id ? $this->attachment_has_protected_content_links( $attachment_id ) : false,
			'block_sources'       => array(),
			'folder_sources'      => $attachment_id ? $this->attachment_folder_source_ids( $attachment_id ) : array(),
			'folder_access'       => $attachment_id ? $this->attachment_effective_folder_access( $attachment_id ) : PGM_Media_Organizer::ACCESS_PUBLIC,
			'strict_move'         => $this->should_move_files_to_private_storage(),
			'requires_private_storage' => $attachment_id ? $this->attachment_requires_private_storage( $attachment_id ) : false,
			'public_copy_exists'  => $attachment_id ? $this->attachment_has_public_upload_copy( $attachment_id ) : false,
			'private_copy_exists' => $attachment_id ? $this->attachment_has_private_storage_copy( $attachment_id ) : false,
		);
	}

	/**
	 * Kertoo, saako lisäosa siirtää mediatiedostoja pois julkisesta uploads-kansiosta.
	 *
	 * Oletus on yhteensopivuuden vuoksi false: monet sivunrakentajat, kuten
	 * Elementor, voivat viitata samaan tiedostoon omissa rakenteissaan. Linkkien
	 * nonce-suojaus toimii ilman tiedoston siirtoa, mutta suora uploads-URL jää
	 * silloin palvelimen näkökulmasta julkiseksi.
	 */
	private function should_move_files_to_private_storage() {
		$options = $this->get_options();

		return ! empty( $options['move_files_to_private_storage'] );
	}

	public function register_settings() {
		register_setting(
			'pgm_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::default_options(),
			)
		);
	}

	/**
	 * Puhdistaa asetussivun syötteet ennen tallennusta.
	 *
	 * Huomaa, että capability-kenttä on tarkoituksella vapaa tekstikenttä:
	 * WordPress-oikeudet kuten read, edit_posts ja manage_options ovat merkkijonoja.
	 */
	public function sanitize_options( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::default_options();

		$mode = isset( $input['protect_mode'] ) ? sanitize_key( $input['protect_mode'] ) : $defaults['protect_mode'];
		if ( ! in_array( $mode, array( 'marked', 'marked_or_extension', 'all' ), true ) ) {
			$mode = $defaults['protect_mode'];
		}

		$login_mode = isset( $input['login_mode'] ) ? sanitize_key( $input['login_mode'] ) : $defaults['login_mode'];
		if ( ! in_array( $login_mode, array( 'auto', 'floauth', 'custom', 'wordpress' ), true ) ) {
			$login_mode = $defaults['login_mode'];
		}

		$custom_login_url = isset( $input['custom_login_url'] ) ? esc_url_raw( wp_unslash( $input['custom_login_url'] ) ) : '';

		$extensions = isset( $input['protected_extensions'] ) ? (string) wp_unslash( $input['protected_extensions'] ) : $defaults['protected_extensions'];
		$extensions = $this->normalize_extensions_string( $extensions );

		$capability = isset( $input['required_capability'] ) ? sanitize_key( $input['required_capability'] ) : $defaults['required_capability'];
		if ( '' === $capability ) {
			$capability = $defaults['required_capability'];
		}

		return array(
			'enabled'                 => empty( $input['enabled'] ) ? 0 : 1,
			'login_mode'              => $login_mode,
			'custom_login_url'        => $custom_login_url,
			'protect_mode'            => $mode,
			'protected_extensions'    => $extensions,
			'protect_unknown_uploads' => empty( $input['protect_unknown_uploads'] ) ? 0 : 1,
			'move_files_to_private_storage' => empty( $input['move_files_to_private_storage'] ) ? 0 : 1,
			'required_capability'     => $capability,
		);
	}

	private function normalize_extensions_string( $extensions ) {
		$items = preg_split( '/[\s,]+/', strtolower( (string) $extensions ) );
		$items = array_filter(
			array_map(
				static function ( $extension ) {
					return preg_replace( '/[^a-z0-9]+/', '', $extension );
				},
				(array) $items
			)
		);
		$items = array_values( array_unique( $items ) );

		return implode( ',', $items );
	}

	private function protected_extensions() {
		$options    = $this->get_options();
		$extensions = array_filter( explode( ',', (string) $options['protected_extensions'] ) );

		return array_values( array_unique( array_map( 'sanitize_key', $extensions ) ) );
	}

	/**
	 * Kun tiedostojen siirtotila vaihdetaan asetuksista, synkronoidaan jo
	 * yksityiseksi merkityt mediat uuteen malliin heti. Näin ylläpitäjän ei
	 * tarvitse muistaa avata ja tallentaa jokaista mediaa erikseen.
	 */
	public function maybe_sync_private_attachments_after_settings_update( $option, $old_value, $value ) {
		if ( self::OPTION_NAME !== $option || ! is_array( $value ) ) {
			return;
		}

		$old_move = is_array( $old_value ) && ! empty( $old_value['move_files_to_private_storage'] );
		$new_move = ! empty( $value['move_files_to_private_storage'] );

		if ( $old_move === $new_move || empty( $value['enabled'] ) ) {
			return;
		}

		$this->sync_all_private_attachments_for_current_mode();
	}

	public function migrate_legacy_default_extension_policy() {
		$migration_option = 'pgm_0711_legacy_default_extension_policy_migrated';

		if ( ! is_admin() || get_option( $migration_option ) ) {
			return;
		}

		$options = get_option( self::OPTION_NAME, array() );
		$options = is_array( $options ) ? $options : array();
		$extensions = isset( $options['protected_extensions'] )
			? $this->normalize_extensions_string( $options['protected_extensions'] )
			: self::LEGACY_DEFAULT_PROTECTED_EXTENSIONS;
		$uses_legacy_default = isset( $options['protect_mode'] )
			&& 'marked_or_extension' === $options['protect_mode']
			&& self::LEGACY_DEFAULT_PROTECTED_EXTENSIONS === $extensions;
		$restored = 0;

		if ( $uses_legacy_default ) {
			$options['protect_mode']         = 'marked';
			$options['protected_extensions'] = $extensions;
			update_option( self::OPTION_NAME, wp_parse_args( $options, self::default_options() ), false );
			$restored = $this->restore_policy_only_private_attachments();
		}

		update_option(
			$migration_option,
			array(
				'time'     => time(),
				'changed'  => $uses_legacy_default ? 1 : 0,
				'restored' => $restored,
			),
			false
		);
	}

	private function restore_policy_only_private_attachments() {
		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_PRIVATE,
						'value' => '1',
					),
					array(
						'key'     => self::META_PRIVATE_MANUAL,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_BLOCK_SOURCES,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_FOLDER_SOURCES,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$restored = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$result        = $this->restore_attachment_files_to_uploads( $attachment_id );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			delete_post_meta( $attachment_id, self::META_PRIVATE );
			$restored++;
		}

		return $restored;
	}

	public function repair_policy_only_private_attachments_once() {
		$repair_option = 'pgm_046_policy_only_restore_done';

		if ( ! is_admin() || get_option( $repair_option ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_PRIVATE,
						'value' => '1',
					),
					array(
						'key'     => self::META_PRIVATE_MANUAL,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_BLOCK_SOURCES,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_FOLDER_SOURCES,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$restored = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$result        = $this->restore_attachment_files_to_uploads( $attachment_id );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			delete_post_meta( $attachment_id, self::META_PRIVATE );
			$restored++;
		}

		update_option(
			$repair_option,
			array(
				'time'  => time(),
				'count' => $restored,
			),
			false
		);

		$this->debug_log(
			'policy_only_private_attachments_repaired',
			array(
				'count' => $restored,
			)
		);
	}

	/**
	 * Lisää asetussivun WordPressin Asetukset-valikkoon.
	 */
	public function repair_source_locked_private_upload_copies() {
		if ( ! is_admin() || ! $this->is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$transient_key = 'pgm_source_locked_storage_repair_recent';
		if ( get_transient( $transient_key ) ) {
			return;
		}

		set_transient( $transient_key, '1', 5 * MINUTE_IN_SECONDS );

		$content_link_sources = $this->repair_protected_content_link_sources();

		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => self::META_PRIVATE,
						'value' => '1',
					),
					array(
						'key'   => self::META_CONTENT_LINKS_PRIVATE,
						'value' => '1',
					),
					array(
						'key'     => self::META_ACTIVE_PROTECTED_URL,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$checked = 0;
		$synced  = 0;
		$pending = 0;
		$errors  = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( $this->should_move_files_to_private_storage() && $this->attachment_has_protected_content_links( $attachment_id ) ) {
				update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			}

			$requires_private_storage = $this->should_move_files_to_private_storage()
				|| $this->attachment_requires_private_storage( $attachment_id );

			if ( ! $requires_private_storage || ! $this->attachment_has_public_upload_copy( $attachment_id ) ) {
				continue;
			}

			$checked++;
			$result = $this->sync_attachment_storage_for_current_mode( $attachment_id );

			if ( is_wp_error( $result ) ) {
				$pending++;
				$errors[] = sprintf(
					'Media #%1$d: %2$s',
					$attachment_id,
					$result->get_error_message()
				);
				continue;
			}

			if ( $this->attachment_has_public_upload_copy( $attachment_id ) ) {
				$pending++;
			} else {
				$synced++;
			}
		}

		if ( $checked || $pending ) {
			$this->debug_log(
				'source_locked_private_upload_copies_repaired',
				array(
					'checked' => $checked,
					'synced'  => $synced,
					'pending' => $pending,
					'content_link_sources' => $content_link_sources,
					'errors'  => $errors,
				)
			);
		}
	}

	public function repair_stale_public_private_storage_meta() {
		if ( ! is_admin() || ! $this->is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$transient_key = 'pgm_stale_public_storage_meta_repair_recent';
		if ( get_transient( $transient_key ) ) {
			return;
		}

		set_transient( $transient_key, '1', 5 * MINUTE_IN_SECONDS );

		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => 250,
				'meta_query'     => array(
					array(
						'key'     => self::META_PRIVATE_STORAGE_PATHS,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$cleaned = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;

			if ( get_post_meta( $attachment_id, self::META_PRIVATE, true ) ) {
				continue;
			}

			if ( get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true ) ) {
				continue;
			}

			if ( get_post_meta( $attachment_id, self::META_CONTENT_LINKS_PRIVATE, true ) ) {
				continue;
			}

			if ( get_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL, true ) ) {
				continue;
			}

			if ( $this->attachment_has_folder_sources( $attachment_id ) ) {
				continue;
			}

			if ( PGM_Media_Organizer::ACCESS_PUBLIC !== $this->attachment_effective_folder_access( $attachment_id ) ) {
				continue;
			}

			if ( ! $this->attachment_has_public_upload_copy( $attachment_id ) ) {
				continue;
			}

			delete_post_meta( $attachment_id, self::META_PRIVATE_STORAGE_PATHS );
			delete_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL );
			$cleaned++;
		}

		if ( $cleaned ) {
			$this->debug_log(
				'stale_public_private_storage_meta_repaired',
				array(
					'cleaned' => $cleaned,
				)
			);
		}
	}

	private function repair_protected_content_link_sources() {
		if ( ! $this->should_move_files_to_private_storage() ) {
			return 0;
		}

		global $wpdb;

		$likes = array();
		foreach ( $this->private_media_action_names() as $action ) {
			$likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( 'action=' . $action ) . '%' );
		}

		$likes = array_values( array_unique( array_filter( $likes ) ) );
		if ( empty( $likes ) ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status NOT IN ('auto-draft', 'trash') AND (" . implode( ' OR ', $likes ) . ')'
		);

		$count = 0;
		foreach ( (array) $rows as $row ) {
			$post_id = isset( $row->ID ) ? absint( $row->ID ) : 0;
			if ( ! $post_id || ! isset( $row->post_content ) || '' === $row->post_content ) {
				continue;
			}

			foreach ( $this->extract_private_media_paths_from_markup( (string) $row->post_content ) as $relative_path ) {
				$attachment_id = $this->find_attachment_for_relative_path( $relative_path );
				if ( ! $attachment_id ) {
					continue;
				}

				update_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, '1' );
				update_post_meta( $attachment_id, self::META_CONTENT_LINKS_PRIVATE, '1' );
				update_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL, esc_url_raw( $this->build_login_start_url( $attachment_id, $relative_path, true ) ) );
				update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
				$count++;
			}
		}

		return $count;
	}

	public function migrate_gutenberg_link_sources_to_file_level_protection() {
		$migration_option = 'pgm_0820_gutenberg_sources_file_level_migrated';
		if ( get_option( $migration_option ) ) {
			return;
		}

		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_BLOCK_SOURCES,
						'compare' => 'EXISTS',
					),
					array(
						'key'   => self::META_CONTENT_LINKS_PRIVATE,
						'value' => '1',
					),
					array(
						'key'     => self::META_ACTIVE_PROTECTED_URL,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$migrated = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id || ! get_post_meta( $attachment_id, self::META_PRIVATE, true ) ) {
				continue;
			}

			if (
				$this->attachment_has_folder_sources( $attachment_id )
				|| PGM_Media_Organizer::ACCESS_PUBLIC !== $this->attachment_effective_folder_access( $attachment_id )
			) {
				continue;
			}

			update_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, '1' );
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			$this->sync_attachment_storage_for_current_mode( $attachment_id );
			$migrated++;
		}

		delete_post_meta_by_key( self::META_BLOCK_SOURCES );
		delete_post_meta_by_key( self::POST_META_BLOCK_PATHS );
		update_option( $migration_option, time(), false );

		if ( $migrated ) {
			$this->debug_log(
				'gutenberg_link_sources_migrated_to_file_level',
				array(
					'migrated' => $migrated,
				)
			);
		}
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Pecodex Media Control', 'private-gutenberg-media' ),
			__( 'Pecodex Media Control', 'private-gutenberg-media' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
		$visibility_page = add_menu_page(
			'Sivuston Näkyvyyskirjasto',
			'Näkyvyys',
			'manage_options',
			'pgm-visibility-library',
			array( $this, 'render_visibility_library_page' ),
			'dashicons-visibility',
			30
		);
		add_action( "admin_enqueue_scripts", array( $this, 'enqueue_visibility_library_assets' ) );

		// ── Pecodex Security top-level page ──────────────────────────
		add_menu_page(
			'Pecodex Tietoturvakeskus',
			'Tietoturva',
			'manage_options',
			'pecodex-security',
			array( $this, 'render_security_dashboard_page' ),
			'dashicons-shield',
			29
		);

		// Submenus
		add_submenu_page( 'pecodex-security', 'Yhteenveto', 'Yhteenveto', 'manage_options', 'pecodex-security', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Palomuuri ja Lukitukset', 'Palomuuri', 'manage_options', 'pecodex-security-firewall', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Järjestelmän Suojaus', 'Suojaus', 'manage_options', 'pecodex-security-hardening', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Haittaohjelmaskanneri', 'Skanneri', 'manage_options', 'pecodex-security-scanner', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Lisätyökalut', 'Lisätyökalut', 'manage_options', 'pecodex-security-advanced', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Turvaotsakkeet', 'Turvaotsakkeet', 'manage_options', 'pecodex-security-headers', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Tarkastusloki', 'Tarkastusloki', 'manage_options', 'pecodex-security-audit-log', array( $this, 'render_security_dashboard_page' ) );
		add_submenu_page( 'pecodex-security', 'Ilmoitukset', 'Ilmoitukset', 'manage_options', 'pecodex-security-notifications', array( $this, 'render_security_dashboard_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_security_dashboard_assets' ) );
	}

	public function enqueue_visibility_library_assets( $hook ) {
		// Only enqueue on our specific settings page.
		if ( strpos( $hook, 'pgm-visibility-library' ) === false ) {
			return;
		}

		$plugin_url = plugin_dir_url( __FILE__ );
		
		wp_enqueue_style(
			'pgm-visibility-react-css',
			$plugin_url . 'admin-ui/dist/assets/index.css',
			array(),
			time()
		);

		wp_enqueue_script(
			'pgm-visibility-react-js',
			$plugin_url . 'admin-ui/dist/assets/index.js',
			array(),
			time(),
			true
		);

		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$has_floauth = is_plugin_active('FloAuth/FloAuth.php') || is_plugin_active('FloAuth/floauth.php') || is_plugin_active('floauth/floauth.php');

		wp_localize_script( 'pgm-visibility-react-js', 'pgmVisibilityApi', array(
			'root'        => esc_url_raw( rest_url() ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'hasFloAuth'  => $has_floauth
		) );
	}

	public function render_visibility_library_page() {
		echo '<div class="wrap"><div id="root"></div></div>';
	}

	/**
	 * Enqueue scripts/styles for the Security Dashboard page.
	 * ALL external assets must be enqueued here (before wp_head fires).
	 * Calling wp_enqueue_* inside the template is too late.
	 */
	public function enqueue_security_dashboard_assets( $hook ) {
		if ( strpos( $hook, 'pecodex-security' ) === false ) {
			return;
		}

		// ── CDN styles ────────────────────────────────────────────────
		wp_enqueue_style(
			'pecodex-leaflet-css',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(), '1.9.4'
		);
		wp_enqueue_style(
			'pecodex-inter-font',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
			array(), null
		);
		wp_enqueue_style(
			'pecodex-material-symbols',
			'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
			array(), null
		);

		// ── CDN scripts (in <head> so L is defined when template script runs) ──
		wp_enqueue_script(
			'pecodex-leaflet-js',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(), '1.9.4', false   // false = load in <head>, not footer
		);
		wp_enqueue_script(
			'pecodex-sortablejs',
			'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js',
			array(), null, false
		);
		wp_enqueue_script(
			'pecodex-tailwindcss',
			'https://cdn.tailwindcss.com',
			array(), null, false
		);

		// ── Admin chrome cleanup ──────────────────────────────────────
		add_action( 'admin_head', function() {
			echo '<style>
				#wpfooter { display: none !important; }
				.update-nag, .notice, .notice-info, .notice-warning,
				.notice-success, .notice-error { display: none !important; }
				#wpcontent { padding-left: 0 !important; }
				#wpbody-content { padding-bottom: 0 !important; }
			</style>';
		} );

		// Localize data for security dashboard JS
		wp_localize_script( 'pecodex-sortablejs', 'pmcSecurityConfig', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'pmc_security_nonce' ),
			'siteUrl'    => get_site_url(),
			'pluginUrl'  => plugin_dir_url( __FILE__ ),
		) );
	}

	/**
	 * Render the Security Dashboard page — loads the self-contained template.
	 */
	public function render_security_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div id="pecodex-security-dashboard-wrap">';
		$template = plugin_dir_path( __FILE__ ) . 'templates/security-dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="wrap"><p>Security dashboard template not found.</p></div>';
		}
		echo '</div>';
	}

	public $no_access_message = '';
	public $no_access_title = 'Pääsy evätty';

	public function handle_frontend_media_access_denied() {
		if ( isset( $_GET['pgm_error'] ) && $_GET['pgm_error'] === 'media_access_denied' ) {
			$message = isset( $_GET['pgm_message'] ) ? base64_decode( sanitize_text_field( wp_unslash( $_GET['pgm_message'] ) ) ) : '';
			if ( empty( $message ) ) {
				$message = 'Sinulla ei ole oikeutta avata tätä tiedostoa.';
			}
			
			$this->no_access_message = esc_html( $message );
			$this->no_access_title   = 'Pääsy evätty';
			
			status_header( 403 );
			
			add_filter( 'the_content', array( $this, 'filter_protected_content' ), 9999 );
		}
	}

	public function filter_protected_content( $content ) {
		if ( is_main_query() && in_the_loop() && get_queried_object_id() === get_the_ID() ) {
			return $this->get_no_access_html();
		}
		return $content;
	}

	private function get_no_access_html() {
		ob_start();
		?>
		<div class="pgm-no-access-container" style="max-width: 800px; margin: 40px auto; padding: 40px; text-align: center; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); border: 1px solid #e2e8f0; font-family: system-ui, -apple-system, sans-serif;">
			<div style="margin-bottom: 24px;">
				<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#db2777" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto; display: block;">
					<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
					<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
				</svg>
			</div>
			<h1 style="font-size: 2.25rem; color: #0f172a; margin-bottom: 16px; font-weight: 700; letter-spacing: -0.025em;">
				<?php echo esc_html( $this->no_access_title ); ?>
			</h1>
			<p style="font-size: 1.125rem; color: #475569; margin-bottom: 32px; line-height: 1.6;">
				<?php echo wp_kses_post( $this->no_access_message ); ?>
			</p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 28px; background-color: #db2777; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(219, 39, 119, 0.2);">
				Palaa etusivulle
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	public function enforce_page_visibility_protection() {
		if ( ! is_page() ) {
			return;
		}

		$page_id = get_queried_object_id();
		if ( ! $page_id ) {
			return;
		}

		$protection_type = get_post_meta( $page_id, '_pgm_protection_type', true );
		$roles = get_post_meta( $page_id, '_pgm_visibility_roles', true );
		
		if ( empty( $roles ) || ! is_array( $roles ) ) {
			$roles = array();
		}

		if ( empty( $roles ) && $protection_type !== 'logged_in' && $protection_type !== 'roles' && $protection_type !== 'floauth' ) {
			return; // Ei suojattu
		}

		// Jos käyttäjä on admin, sallitaan aina
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			// Ei kirjautunut - ohjataan kirjautumissivulle sen sijaan että näytettäisiin 403-virhe.
			global $wp;
			$current_url = home_url( add_query_arg( array(), $wp->request ) );
			setcookie( 'pgm_page_continue_url', $current_url, time() + 3600, '/' );
			wp_redirect( esc_url_raw( $this->frontend_login_url( $current_url ) ) );
			exit;
		}

		// Jos suojaustyyppi on vain 'logged_in' tai 'floauth', sallitaan pääsy kaikille kirjautuneille.
		// FloAuth-sivuilla FloMembers itse hoitaa tarkemman jäsenyyden tarkistuksen (jos asennettu),
		// mutta me varmistamme tässä peruskirjautumisen turvaverkkona.
		if ( ( $protection_type === 'logged_in' || $protection_type === 'floauth' ) && empty( $roles ) ) {
			return;
		}

		$has_access = false;
		foreach ( $roles as $required_role ) {
			if ( in_array( $required_role, (array) $user->roles ) ) {
				$has_access = true;
				break;
			}
		}

		if ( ! $has_access && ! empty( $roles ) ) {
			$no_access_message = get_post_meta( $page_id, '_pgm_no_access_message', true );
			if ( ! empty( $no_access_message ) ) {
				$message = wp_kses_post( $no_access_message );
			} else {
				$message = 'Sinulla ei ole vaadittua roolia nähdäksesi tämän sivun.';
			}
			
			$this->no_access_message = $message;
			$this->no_access_title   = 'Pääsy evätty';
			
			status_header( 403 );
			
			add_filter( 'the_content', array( $this, 'filter_protected_content' ), 9999 );
			
			// Prevent comments on protected pages
			add_filter( 'comments_open', '__return_false', 9999 );
			add_filter( 'pings_open', '__return_false', 9999 );
		}
	}

	public function redirect_legacy_settings_page() {
		global $pagenow;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'options-general.php' !== $pagenow || self::LEGACY_SETTINGS_PAGE_SLUG !== $page ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG ) );
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->get_options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pecodex Media Control', 'private-gutenberg-media' ); ?></h1>
			<?php $this->render_sync_report_notice(); ?>
			<?php $this->render_diagnostic_action_notice(); ?>
			<p><?php esc_html_e( 'Pecodex Media Control suojaa mediatiedostot tiedostotasolla Media Libraryssa, kansioissa ja editorissa samalla tavalla. Suojattu media kulkee WordPressin kautta vasta kirjautumisen, käyttöoikeuden ja nonce-tarkistuksen jälkeen.', 'private-gutenberg-media' ); ?></p>
			<p><?php esc_html_e( 'Yksityinen varasto sijoitetaan ensisijaisesti WordPressin julkisen webrootin ulkopuolelle. Jos hosting ei salli sitä, lisäosa käyttää salattua uploads-fallbackia ja lisää Apache/LiteSpeed/IIS-lisäsuojan aina kun se on mahdollista.', 'private-gutenberg-media' ); ?></p>
			<p><?php esc_html_e( 'Sisäiset sivulinkit ohjataan kirjautumis- ja nonce-portin kautta, mutta kohdesivun suora URL pitää suojata erillisellä jäsenalueella tai sivun omilla käyttöoikeuksilla, jos sen ei kuulu näkyä julkisesti.', 'private-gutenberg-media' ); ?></p>
			<p><strong><?php esc_html_e( 'Tekijä:', 'private-gutenberg-media' ); ?></strong> <?php esc_html_e( 'Pepe Utriainen', 'private-gutenberg-media' ); ?></p>
			<p><strong><?php esc_html_e( 'Yritys:', 'private-gutenberg-media' ); ?></strong> <a href="https://pecodex.fi/" target="_blank" rel="noopener noreferrer">Pecodex</a></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'pgm_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Lisäosan tila', 'private-gutenberg-media' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $options['enabled'], 1 ); ?> />
								<?php esc_html_e( 'Ota suojaus käyttöön', 'private-gutenberg-media' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Poista valinta, jos lisäosa aiheuttaa ongelmia. Tällöin editoripaneeli, linkkien muunto, mahdollinen tiedostojen siirto ja suojattu endpoint poistuvat käytöstä. Jo siirretyt tiedostot voi palauttaa pitämällä tiedostojen siirron pois päältä ja tallentamalla kyseisen median tai sivun uudelleen.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pgm-login-mode"><?php esc_html_e( 'Kirjautumaton käyttäjä ohjataan', 'private-gutenberg-media' ); ?></label>
						</th>
						<td>
							<select id="pgm-login-mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[login_mode]">
								<option value="auto" <?php selected( $options['login_mode'], 'auto' ); ?>><?php esc_html_e( 'Automaattinen: FloAuth jos käytössä, muuten WordPress-login', 'private-gutenberg-media' ); ?></option>
								<option value="floauth" <?php selected( $options['login_mode'], 'floauth' ); ?>><?php esc_html_e( 'FloMembers / FloAuth -kirjautumiseen', 'private-gutenberg-media' ); ?></option>
								<option value="custom" <?php selected( $options['login_mode'], 'custom' ); ?>><?php esc_html_e( 'Omaan kirjautumisosoitteeseen', 'private-gutenberg-media' ); ?></option>
								<option value="wordpress" <?php selected( $options['login_mode'], 'wordpress' ); ?>><?php esc_html_e( 'WordPressin wp-login.php-kirjautumiseen', 'private-gutenberg-media' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Automaattinen tila tekee lisäosasta siirrettävän eri sivustoille: FloAuthia käytetään vain, jos se on aktiivinen ja asetettu oikein. WordPressin normaali admin-kirjautuminen jää edelleen admineille.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pgm-custom-login-url"><?php esc_html_e( 'Oma kirjautumisosoite', 'private-gutenberg-media' ); ?></label>
						</th>
						<td>
							<input id="pgm-custom-login-url" class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[custom_login_url]" value="<?php echo esc_attr( $options['custom_login_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/kirjaudu/' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Täytä vain, jos valitset oman kirjautumisosoitteen. Lisäosa lisää osoitteeseen redirect_to-parametrin, jotta käyttäjä voidaan palauttaa tiedostoon kirjautumisen jälkeen.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pgm-protect-mode"><?php esc_html_e( 'Suojaustapa', 'private-gutenberg-media' ); ?></label>
						</th>
						<td>
							<select id="pgm-protect-mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protect_mode]">
								<option value="marked" <?php selected( $options['protect_mode'], 'marked' ); ?>><?php esc_html_e( 'Vain yksityiseksi merkitty media', 'private-gutenberg-media' ); ?></option>
								<option value="marked_or_extension" <?php selected( $options['protect_mode'], 'marked_or_extension' ); ?>><?php esc_html_e( 'Yksityiseksi merkitty media tai valitut tiedostopäätteet', 'private-gutenberg-media' ); ?></option>
								<option value="all" <?php selected( $options['protect_mode'], 'all' ); ?>><?php esc_html_e( 'Kaikki uploads-tiedostot', 'private-gutenberg-media' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Voit hallita suojausta Media Libraryn yksityinen media -valinnalla tai suojata tavalliset dokumenttityypit automaattisesti.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pgm-protected-extensions"><?php esc_html_e( 'Suojattavat tiedostopäätteet', 'private-gutenberg-media' ); ?></label>
						</th>
						<td>
							<input id="pgm-protected-extensions" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protected_extensions]" value="<?php echo esc_attr( $options['protected_extensions'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Pilkuilla erotettu lista. Käytetään, kun suojaustapa huomioi tiedostopäätteet.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pgm-required-capability"><?php esc_html_e( 'Vaadittu käyttöoikeus', 'private-gutenberg-media' ); ?></label>
						</th>
						<td>
							<input id="pgm-required-capability" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[required_capability]" value="<?php echo esc_attr( $options['required_capability'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Oletus on read, jolloin kuka tahansa kirjautunut käyttäjä voi avata tiedoston. Käytä tiukempaa oikeutta, kuten edit_posts, jos tiedostot ovat vain henkilökunnalle.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tuntemattomat upload-tiedostot', 'private-gutenberg-media' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[protect_unknown_uploads]" value="1" <?php checked( $options['protect_unknown_uploads'], 1 ); ?> />
								<?php esc_html_e( 'Vaadi kirjautuminen ja nonce myös silloin, kun Media Library -liitettä ei löydy.', 'private-gutenberg-media' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Tämä koskee lisäosan kautta kulkevaa linkkiä. Jos tiedosto jätetään uploads-kansioon, sen suora URL voi edelleen olla julkinen.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tiedostojen siirto', 'private-gutenberg-media' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[move_files_to_private_storage]" value="1" <?php checked( $options['move_files_to_private_storage'], 1 ); ?> />
								<?php esc_html_e( 'Piilota yksityiset mediatiedostot julkisilta yksityiseen varastoon', 'private-gutenberg-media' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Tietoturvan kannalta tämä on vahvin tila: tiedoston nimen tietovä käyttäjä ei saa sitä auki suorasta uploads-URLista. Tiedosto tallennetaan ensisijaisesti webrootin ulkopuolelle, tai tarvittaessa salattuun uploads-fallbackiin, ja puretaan vain lisäosan PHP-endpointin kautta. Jos samaa mediaa käytetään julkisessa Elementorissa, teemassa tai muussa builderissa, kyseinen julkinen käyttö voi lakata toimimasta.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Yksityinen tiedostovarasto', 'private-gutenberg-media' ); ?></h2>
			<p>
				<?php
				if ( ! $this->is_enabled() ) {
					esc_html_e( 'Suojaus on pois käytöstä, joten yksityistä tiedostovarastoa ei tarkisteta juuri nyt.', 'private-gutenberg-media' );
				} elseif ( ! $this->should_move_files_to_private_storage() ) {
					esc_html_e( 'Tiedostovaraston siirto ei ole käytössä. Lisäosa ohjaa suojatut mediaosoitteet suojatun endpointin kautta ja pitää uploads-tiedostot paikoillaan.', 'private-gutenberg-media' );
				} elseif ( ! $this->private_storage_crypto_available() ) {
					esc_html_e( 'PHP OpenSSL -laajennus puuttuu. Salattua yksityisvarastoa ei voi käyttää ennen kuin OpenSSL on käytössä palvelimella.', 'private-gutenberg-media' );
				} else {
					$storage = $this->ensure_private_storage();
					if ( is_wp_error( $storage ) ) {
						echo esc_html( $storage->get_error_message() );
					} else {
						printf(
							/* translators: %s is an absolute filesystem path. */
							esc_html__( 'Yksityiset tiedostot tallennetaan tänne: %s', 'private-gutenberg-media' ),
							esc_html( $storage )
						);
					}
				}
				?>
			</p>
			<?php if ( $this->is_enabled() && $this->should_move_files_to_private_storage() ) : ?>
				<p class="description">
					<?php echo esc_html( $this->private_storage_hardening_status_text() ); ?>
				</p>
			<?php endif; ?>
			<?php if ( $this->is_enabled() && $this->should_move_files_to_private_storage() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::PECODEX_ACTION_SYNC_STORAGE ); ?>" />
					<?php wp_nonce_field( self::PECODEX_ACTION_SYNC_STORAGE ); ?>
					<?php submit_button( __( 'Siirrä odottavat tiedostot nyt', 'private-gutenberg-media' ), 'secondary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( 'Ajaa läpi kaikki yksityiseksi merkityt mediat ja siirtää vielä julkisessa uploads-kansiossa olevat tiedostot yksityiseen varastoon.', 'private-gutenberg-media' ); ?></p>
				</form>

			<?php endif; ?>

			<?php $this->render_diagnostics_section(); ?>
		</div>
		<?php
	}

	private function current_admin_action_is( $action ) {
		$current_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return $action === $current_action;
	}

	public function handle_sync_private_storage_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sinulla ei ole oikeutta suorittaa tiedostojen siirtoa.', 'private-gutenberg-media' ),
				esc_html__( 'Pecodex Media Control', 'private-gutenberg-media' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $this->current_admin_action_is( self::PECODEX_ACTION_SYNC_STORAGE ) ? self::PECODEX_ACTION_SYNC_STORAGE : self::ACTION_SYNC_STORAGE );

		$report = $this->sync_all_private_attachments_for_current_mode();
		set_transient( 'pgm_sync_report_' . get_current_user_id(), $report, MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				'pgm_sync_done',
				'1',
				admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG )
			)
		);
		exit;
	}

	public function handle_diagnostic_repair_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sinulla ei ole oikeutta suorittaa diagnostiikan korjaustoimintoa.', 'private-gutenberg-media' ),
				esc_html__( 'Pecodex Media Control', 'private-gutenberg-media' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $this->current_admin_action_is( self::PECODEX_ACTION_DIAGNOSTIC_REPAIR ) ? self::PECODEX_ACTION_DIAGNOSTIC_REPAIR : self::ACTION_DIAGNOSTIC_REPAIR );

		$operation     = isset( $_POST['pgm_operation'] ) ? sanitize_key( wp_unslash( $_POST['pgm_operation'] ) ) : '';
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$message       = '';
		$type          = 'success';
		$result        = true;

		if ( 'sync_all' === $operation ) {
			$report  = $this->sync_all_private_attachments_for_current_mode();
			$message = sprintf(
				/* translators: 1: total, 2: synced, 3: pending. */
				__( 'Kaikki yksityiset mediat tarkistettiin. Tarkistettu: %1$d, kunnossa: %2$d, odottaa: %3$d.', 'private-gutenberg-media' ),
				isset( $report['total'] ) ? absint( $report['total'] ) : 0,
				isset( $report['synced'] ) ? absint( $report['synced'] ) : 0,
				isset( $report['pending'] ) ? absint( $report['pending'] ) : 0
			);
			if ( ! empty( $report['errors'] ) ) {
				$type    = 'warning';
				$message = $message . ' ' . __( 'Osa tiedostoista tarvitsee vielä käsin tarkistuksen.', 'private-gutenberg-media' );
			}
		} elseif ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			$type    = 'error';
			$message = __( 'Tuntematon medialiite.', 'private-gutenberg-media' );
		} elseif ( 'sync_one' === $operation ) {
			$result  = $this->sync_attachment_storage_for_current_mode( $attachment_id );
			$message = __( 'Median sijainti synkronoitiin nykyisten asetusten mukaan.', 'private-gutenberg-media' );
		} elseif ( 'restore_public' === $operation ) {
			$result  = $this->restore_attachment_files_to_uploads( $attachment_id );
			$message = __( 'Julkinen uploads-kopio yritettiin palauttaa yksityisestä varastosta.', 'private-gutenberg-media' );
		} elseif ( 'hide_private' === $operation ) {
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			$result  = $this->move_attachment_files_to_private_storage( $attachment_id );
			$message = __( 'Media yritettiin piilottaa uudelleen salattuun yksityisvarastoon.', 'private-gutenberg-media' );
		} else {
			$type    = 'error';
			$message = __( 'Tuntematon korjaustoiminto.', 'private-gutenberg-media' );
		}

		if ( is_wp_error( $result ) ) {
			$type    = 'error';
			$message = $result->get_error_message();
		} elseif ( $attachment_id ) {
			$audit = $this->audit_private_attachment( $attachment_id );
			if ( is_array( $audit ) && 'ok' !== $audit['status_key'] ) {
				$type = 'warning' === $type ? 'warning' : 'warning';
				$message .= ' ' . sprintf(
					/* translators: %s: current audit status. */
					__( 'Nykyinen tila: %s.', 'private-gutenberg-media' ),
					$audit['status_label']
				);
			}
		}

		set_transient(
			'pgm_diagnostic_action_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				'pgm_diagnostic_done',
				'1',
				admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG . '#pgm-diagnostics' )
			)
		);
		exit;
	}

	private function render_sync_report_notice() {
		if ( empty( $_GET['pgm_sync_done'] ) ) {
			return;
		}

		$key    = 'pgm_sync_report_' . get_current_user_id();
		$report = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $report ) ) {
			return;
		}

		$total   = isset( $report['total'] ) ? absint( $report['total'] ) : 0;
		$synced  = isset( $report['synced'] ) ? absint( $report['synced'] ) : 0;
		$pending = isset( $report['pending'] ) ? absint( $report['pending'] ) : 0;
		$errors  = isset( $report['errors'] ) && is_array( $report['errors'] ) ? $report['errors'] : array();
		$class   = $pending || ! empty( $errors ) ? 'notice notice-warning' : 'notice notice-success';

		printf(
			'<div class="%1$s"><p>%2$s</p>',
			esc_attr( $class ),
			esc_html(
				sprintf(
					/* translators: 1: total private attachments, 2: synced attachments, 3: pending attachments. */
					__( 'Yksityisten tiedostojen tarkistus valmis. Tarkistettu: %1$d, piilossa tai siirretty: %2$d, vielä odottaa: %3$d.', 'private-gutenberg-media' ),
					$total,
					$synced,
					$pending
				)
			)
		);

		if ( ! empty( $errors ) ) {
			echo '<ul>';
			foreach ( array_slice( $errors, 0, 5 ) as $error ) {
				printf( '<li>%s</li>', esc_html( $error ) );
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	private function render_diagnostic_action_notice() {
		if ( empty( $_GET['pgm_diagnostic_done'] ) ) {
			return;
		}

		$key    = 'pgm_diagnostic_action_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		$type  = isset( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'success';
		$class = 'notice notice-success';
		if ( 'warning' === $type ) {
			$class = 'notice notice-warning';
		} elseif ( 'error' === $type ) {
			$class = 'notice notice-error';
		}

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	private function render_diagnostics_section() {
		$audit_rows = $this->audit_private_attachments();
		$db_checks  = $this->audit_database_health();
		?>
		<hr />
		<h2 id="pgm-diagnostics"><?php esc_html_e( 'Diagnostiikka ja korjaus', 'private-gutenberg-media' ); ?></h2>
		<p><?php esc_html_e( 'Tämä tarkistus lukee live-palvelimen tietokannan ja tiedostot suoraan. Se ei muuta mitään ennen kuin painat erillistä korjausnappia.', 'private-gutenberg-media' ); ?></p>
		<p class="description"><?php esc_html_e( 'Tarkoitus on erottaa kolme asiaa: tietokannan canonical media-polku, julkinen uploads-kopio ja salattu private-varastokopio. Suojauksen aikana sisältö voi käyttää login-start-suojalinkkiä, mutta alkuperäinen media-polku säilyy liitteen metassa palautusta varten.', 'private-gutenberg-media' ); ?></p>

		<h3><?php esc_html_e( 'Ympäristö ja tietokanta', 'private-gutenberg-media' ); ?></h3>
		<table class="widefat striped pgm-diagnostic-env-table">
			<tbody>
				<?php foreach ( $db_checks as $check ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $check['label'] ); ?></th>
						<td>
							<span class="<?php echo esc_attr( $check['class'] ); ?>"><?php echo esc_html( $check['status'] ); ?></span>
							<?php if ( ! empty( $check['detail'] ) ) : ?>
								<p class="description"><?php echo esc_html( $check['detail'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p>
			<?php $this->render_diagnostic_action_button( 'sync_all', 0, __( 'Aja kaikki yksityiset mediat läpi', 'private-gutenberg-media' ), 'button button-secondary' ); ?>
		</p>

		<h3><?php esc_html_e( 'Yksityiseksi merkityt mediat', 'private-gutenberg-media' ); ?></h3>
		<?php if ( empty( $audit_rows ) ) : ?>
			<p><?php esc_html_e( 'Yksityiseksi merkittyjä medioita ei löytynyt.', 'private-gutenberg-media' ); ?></p>
		<?php else : ?>
			<table class="widefat striped pgm-diagnostic-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Media', 'private-gutenberg-media' ); ?></th>
						<th><?php esc_html_e( 'Tietokannan polku', 'private-gutenberg-media' ); ?></th>
						<th><?php esc_html_e( 'Tiedostot', 'private-gutenberg-media' ); ?></th>
						<th><?php esc_html_e( 'Meta ja käyttö', 'private-gutenberg-media' ); ?></th>
						<th><?php esc_html_e( 'Tila', 'private-gutenberg-media' ); ?></th>
						<th><?php esc_html_e( 'Korjaus', 'private-gutenberg-media' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $audit_rows as $row ) : ?>
						<tr>
							<td>
								<strong>#<?php echo esc_html( (string) $row['id'] ); ?></strong>
								<?php echo esc_html( $row['title'] ); ?>
								<?php if ( ! empty( $row['edit_url'] ) ) : ?>
									<br /><a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php esc_html_e( 'Muokkaa mediaa', 'private-gutenberg-media' ); ?></a>
								<?php endif; ?>
							</td>
							<td>
								<code><?php echo esc_html( $row['attached_file'] ); ?></code>
								<?php if ( ! empty( $row['expected_private_relative'] ) ) : ?>
									<p class="description">
										<?php esc_html_e( 'Odotettu private-varasto:', 'private-gutenberg-media' ); ?>
										<code><?php echo esc_html( $row['expected_private_relative'] ); ?></code>
									</p>
								<?php endif; ?>
							</td>
							<td>
								<?php $this->render_diagnostic_file_fact( __( 'Public uploads', 'private-gutenberg-media' ), $row['public_exists'], $row['public_size'] ); ?>
								<?php $this->render_diagnostic_file_fact( __( 'Private .pgm', 'private-gutenberg-media' ), $row['private_exists'], $row['private_size'] ); ?>
								<p class="description">
									<?php esc_html_e( 'Private purettavissa:', 'private-gutenberg-media' ); ?>
									<strong><?php echo esc_html( $row['private_readable_label'] ); ?></strong>
								</p>
								<p class="description">
									<?php esc_html_e( 'DB filesize:', 'private-gutenberg-media' ); ?>
									<?php echo null === $row['db_filesize'] ? esc_html__( 'ei tiedossa', 'private-gutenberg-media' ) : esc_html( size_format( $row['db_filesize'], 1 ) ); ?>
									<?php if ( null !== $row['size_matches'] ) : ?>
										<br /><?php esc_html_e( 'Koko täsmää:', 'private-gutenberg-media' ); ?>
										<strong><?php echo $row['size_matches'] ? esc_html__( 'kyllä', 'private-gutenberg-media' ) : esc_html__( 'ei', 'private-gutenberg-media' ); ?></strong>
									<?php endif; ?>
								</p>
							</td>
							<td>
								<p>
									<code>_pgm_private</code>: <?php echo $row['is_private'] ? esc_html__( '1', 'private-gutenberg-media' ) : esc_html__( 'ei', 'private-gutenberg-media' ); ?><br />
									<code>_pgm_private_manual</code>: <?php echo $row['is_manual_private'] ? esc_html__( '1', 'private-gutenberg-media' ) : esc_html__( 'ei', 'private-gutenberg-media' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Legacy-lähteet:', 'private-gutenberg-media' ); ?>
									<?php echo empty( $row['source_labels'] ) ? esc_html__( 'ei vanhoja lähteitä', 'private-gutenberg-media' ) : wp_kses_post( implode( ', ', $row['source_labels'] ) ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Sisällöstä löytyi:', 'private-gutenberg-media' ); ?>
									<?php echo empty( $row['usage_labels'] ) ? esc_html__( 'ei muita osumia', 'private-gutenberg-media' ) : wp_kses_post( implode( ', ', $row['usage_labels'] ) ); ?>
								</p>
							</td>
							<td>
								<span class="<?php echo esc_attr( $row['status_class'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
								<p class="description"><?php echo esc_html( $row['status_description'] ); ?></p>
							</td>
							<td class="pgm-diagnostic-actions">
								<?php $this->render_diagnostic_action_button( 'sync_one', $row['id'], __( 'Synkronoi tämä tiedosto', 'private-gutenberg-media' ) ); ?>
								<?php $this->render_diagnostic_action_button( 'restore_public', $row['id'], __( 'Palauta public uploads -kopio', 'private-gutenberg-media' ) ); ?>
								<?php $this->render_diagnostic_action_button( 'hide_private', $row['id'], __( 'Piilota uudelleen private-varastoon', 'private-gutenberg-media' ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_diagnostic_file_fact( $label, $exists, $size ) {
		?>
		<p class="description">
			<?php echo esc_html( $label ); ?>:
			<strong><?php echo $exists ? esc_html__( 'löytyy', 'private-gutenberg-media' ) : esc_html__( 'puuttuu', 'private-gutenberg-media' ); ?></strong>
			<?php if ( $exists && null !== $size ) : ?>
				(<?php echo esc_html( size_format( $size, 1 ) ); ?>)
			<?php endif; ?>
		</p>
		<?php
	}

	private function render_diagnostic_action_button( $operation, $attachment_id, $label, $class = 'button button-small' ) {
		?>
		<form class="pgm-diagnostic-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::PECODEX_ACTION_DIAGNOSTIC_REPAIR ); ?>" />
			<input type="hidden" name="pgm_operation" value="<?php echo esc_attr( $operation ); ?>" />
			<input type="hidden" name="attachment_id" value="<?php echo esc_attr( (string) absint( $attachment_id ) ); ?>" />
			<?php wp_nonce_field( self::PECODEX_ACTION_DIAGNOSTIC_REPAIR ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	private function audit_private_attachments() {
		$ids = $this->diagnostic_attachment_ids();
		$out = array();

		foreach ( $ids as $attachment_id ) {
			$audit = $this->audit_private_attachment( $attachment_id );
			if ( is_array( $audit ) ) {
				$out[] = $audit;
			}
		}

		return $out;
	}

	public function admin_diagnostic_summary_payload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		global $wpdb;

		$rows   = $this->audit_private_attachments();
		$counts = array(
			'total'                => count( $rows ),
			'hidden'               => 0,
			'linkOnly'             => 0,
			'publicCopies'         => 0,
			'privateCopies'        => 0,
			'directUploadsBlocked' => 0,
			'directUploadsOpen'    => 0,
			'attention'            => 0,
			'manual'               => 0,
			'folderOrBlock'        => 0,
			'originalUrlMemory'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_ORIGINAL_PUBLIC_URL ) ),
			'activeProtected'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_ACTIVE_PROTECTED_URL ) ),
			'contentPrivate'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", self::META_CONTENT_LINKS_PRIVATE, '1' ) ),
		);

		$attention_keys = array( 'db_mismatch', 'both_missing', 'private_broken', 'private_missing', 'public_still_exists' );

		foreach ( $rows as $row ) {
			if ( ! empty( $row['public_exists'] ) ) {
				$counts['publicCopies']++;
			}
			if ( ! empty( $row['private_exists'] ) ) {
				$counts['privateCopies']++;
			}
			if ( ! empty( $row['is_private'] ) && empty( $row['public_exists'] ) && ! empty( $row['private_exists'] ) ) {
				$counts['directUploadsBlocked']++;
			}
			if ( ! empty( $row['is_private'] ) && ! empty( $row['public_exists'] ) ) {
				$counts['directUploadsOpen']++;
			}
			if ( ! empty( $row['is_manual_private'] ) ) {
				$counts['manual']++;
			}
			if ( ! empty( $row['source_labels'] ) ) {
				$counts['folderOrBlock']++;
			}
			if ( 'ok' === $row['status_key'] && ! empty( $row['private_exists'] ) && empty( $row['public_exists'] ) ) {
				$counts['hidden']++;
			}
			if ( 'ok' === $row['status_key'] && ! empty( $row['public_exists'] ) && empty( $row['private_exists'] ) ) {
				$counts['linkOnly']++;
			}
			if ( in_array( $row['status_key'], $attention_keys, true ) ) {
				$counts['attention']++;
			}
		}

		$diagnostics_url = add_query_arg(
			array( 'page' => self::SETTINGS_PAGE_SLUG ),
			admin_url( 'options-general.php' )
		) . '#pgm-diagnostics';

		$sync_url = wp_nonce_url(
			add_query_arg( 'action', self::PECODEX_ACTION_SYNC_STORAGE, admin_url( 'admin-post.php' ) ),
			self::PECODEX_ACTION_SYNC_STORAGE
		);

		return array(
			'counts'              => $counts,
			'health'              => $counts['attention'] > 0 ? 'warning' : 'ok',
			'enabled'             => $this->is_enabled(),
			'moveFilesToPrivateStorage' => $this->should_move_files_to_private_storage(),
			'opensslAvailable'    => $this->private_storage_crypto_available(),
			'storageExists'       => is_dir( $this->private_storage_dir() ),
			'storageStatusText'   => $this->private_storage_hardening_status_text(),
			'diagnosticsUrl'      => $diagnostics_url,
			'syncStorageUrl'      => $sync_url,
		);
	}

	private function diagnostic_attachment_ids() {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_PRIVATE,
						'value' => '1',
					),
				),
			)
		);

		global $wpdb;

		$source_meta_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_BLOCK_SOURCES
			)
		);
		$ids             = array_merge( $ids, array_map( 'absint', (array) $source_meta_ids ) );

		$block_path_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::POST_META_BLOCK_PATHS
			)
		);

		foreach ( (array) $block_path_rows as $row ) {
			$paths = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $paths ) ) {
				continue;
			}

			foreach ( $paths as $relative_path ) {
				$attachment_id = $this->find_attachment_for_relative_path( $relative_path );
				if ( $attachment_id ) {
					$ids[] = $attachment_id;
				}
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids );

		return $ids;
	}

	private function audit_private_attachment( $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return null;
		}

		$attached_file = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$public_path   = null === $attached_file ? false : $this->public_upload_file_path_if_exists( $attached_file );
		$private_path  = null === $attached_file ? false : $this->private_file_path_if_exists( $attached_file );
		$db_filesize   = $this->attachment_db_filesize( $attachment_id );
		$private_check = $this->private_file_readability_info( $private_path );
		$public_size   = $public_path ? filesize( $public_path ) : null;
		$private_size  = isset( $private_check['size'] ) ? $private_check['size'] : null;
		$size_matches  = null;

		if ( null !== $db_filesize ) {
			$size_matches = ( null !== $public_size && (int) $public_size === (int) $db_filesize )
				|| ( null !== $private_size && (int) $private_size === (int) $db_filesize );
		}

		$is_private        = (bool) get_post_meta( $attachment_id, self::META_PRIVATE, true );
		$is_manual_private = (bool) get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true );
		$status            = $this->diagnostic_status_for_attachment( $attachment_id, $attached_file, $is_private, (bool) $public_path, (bool) $private_path, $private_check );

		return array(
			'id'                        => $attachment_id,
			'title'                     => get_the_title( $attachment_id ),
			'edit_url'                  => current_user_can( 'edit_post', $attachment_id ) ? get_edit_post_link( $attachment_id, 'raw' ) : '',
			'attached_file'             => null === $attached_file ? '' : $attached_file,
			'expected_private_relative' => null === $attached_file ? '' : $this->private_storage_relative_path( $attached_file ),
			'public_exists'             => (bool) $public_path,
			'private_exists'            => (bool) $private_path,
			'public_size'               => $public_size,
			'private_size'              => $private_size,
			'private_readable_label'    => $private_check['label'],
			'db_filesize'               => $db_filesize,
			'size_matches'              => $size_matches,
			'is_private'                => $is_private,
			'is_manual_private'         => $is_manual_private,
			'source_labels'             => $this->diagnostic_source_labels( $attachment_id ),
			'usage_labels'              => $this->diagnostic_usage_labels( $attached_file, $attachment_id ),
			'status_key'                => $status['key'],
			'status_label'              => $status['label'],
			'status_description'        => $status['description'],
			'status_class'              => $status['class'],
		);
	}

	private function diagnostic_status_for_attachment( $attachment_id, $attached_file, $is_private, $public_exists, $private_exists, $private_check ) {
		$expects_private_storage = $attachment_id && ( $this->should_move_files_to_private_storage() || $this->attachment_requires_private_storage( $attachment_id ) );

		if ( null === $attached_file || '' === $attached_file ) {
			return array(
				'key'         => 'db_mismatch',
				'label'       => __( 'DB-polku ei täsmää', 'private-gutenberg-media' ),
				'description' => __( '_wp_attached_file puuttuu tai ei ole kelvollinen suhteellinen uploads-polku.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-missing',
			);
		}

		if ( ! $public_exists && ! $private_exists ) {
			return array(
				'key'         => 'both_missing',
				'label'       => __( 'Molemmat puuttuvat', 'private-gutenberg-media' ),
				'description' => __( 'Tiedostoa ei löytynyt public uploads -polusta eikä salatusta private-varastosta.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-missing',
			);
		}

		if ( $private_exists && empty( $private_check['readable'] ) ) {
			return array(
				'key'         => 'private_broken',
				'label'       => __( 'Private ei purkaudu', 'private-gutenberg-media' ),
				'description' => __( 'Private-varastotiedosto löytyy, mutta sitä ei voitu lukea tai purkaa nykyisillä sivuston avaimilla.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-missing',
			);
		}

		if ( $is_private && $public_exists && ! $private_exists && $expects_private_storage ) {
			return array(
				'key'         => 'private_missing',
				'label'       => __( 'Private puuttuu', 'private-gutenberg-media' ),
				'description' => __( 'Public uploads -kopio löytyy, mutta salattu private-varastokopio puuttuu. Synkronoi tai piilota tiedosto uudelleen.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-warning',
			);
		}

		if ( $is_private && ! $public_exists && $private_exists ) {
			return array(
				'key'         => 'ok',
				'label'       => __( 'OK', 'private-gutenberg-media' ),
				'description' => __( 'Tiedosto on piilotettu julkisesta uploads-kansiosta ja private-varasto on luettavissa.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-hidden',
			);
		}

		if ( $is_private && $public_exists && ! $expects_private_storage ) {
			return array(
				'key'         => 'ok',
				'label'       => __( 'OK', 'private-gutenberg-media' ),
				'description' => __( 'Linkkisuojaus on käytössä ja tiedosto pidetään tarkoituksella public uploads -kansiossa.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-link-only',
			);
		}

		if ( $is_private && $public_exists && $private_exists ) {
			return array(
				'key'         => 'public_still_exists',
				'label'       => __( 'Public kopio löytyy', 'private-gutenberg-media' ),
				'description' => __( 'Sekä public että private kopio löytyvät. Aja synkronointi, jos public kopio halutaan poistaa.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-warning',
			);
		}

		return array(
			'key'         => 'ok',
			'label'       => __( 'OK', 'private-gutenberg-media' ),
			'description' => __( 'Media ei näytä olevan ristiriitaisessa tilassa.', 'private-gutenberg-media' ),
			'class'       => 'pgm-status-public',
		);
	}

	private function attachment_db_filesize( $attachment_id ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && isset( $metadata['filesize'] ) ) {
			return absint( $metadata['filesize'] );
		}

		return null;
	}

	private function private_file_readability_info( $private_path ) {
		if ( ! $private_path || ! is_file( $private_path ) ) {
			return array(
				'readable' => false,
				'label'    => __( 'ei private-tiedostoa', 'private-gutenberg-media' ),
				'size'     => null,
			);
		}

		if ( $this->is_encrypted_private_file( $private_path ) ) {
			$contents = $this->decrypt_private_file_contents( $private_path );
			if ( is_wp_error( $contents ) ) {
				return array(
					'readable' => false,
					'label'    => $contents->get_error_message(),
					'size'     => null,
				);
			}

			return array(
				'readable' => true,
				'label'    => __( 'kyllä', 'private-gutenberg-media' ),
				'size'     => strlen( $contents ),
			);
		}

		return array(
			'readable' => is_readable( $private_path ),
			'label'    => is_readable( $private_path ) ? __( 'kyllä, legacy/plain', 'private-gutenberg-media' ) : __( 'ei luettavissa', 'private-gutenberg-media' ),
			'size'     => filesize( $private_path ),
		);
	}

	private function diagnostic_source_labels( $attachment_id ) {
		$sources = get_post_meta( $attachment_id, self::META_BLOCK_SOURCES, true );
		$sources = is_array( $sources ) ? array_values( array_unique( array_filter( array_map( 'absint', $sources ) ) ) ) : array();
		$out     = array();

		foreach ( $sources as $source_id ) {
			$title = get_the_title( $source_id );
			if ( '' === $title ) {
				$title = sprintf(
					/* translators: %d: post ID. */
					__( 'Sisältö #%d', 'private-gutenberg-media' ),
					$source_id
				);
			}

			$url = current_user_can( 'edit_post', $source_id ) ? get_edit_post_link( $source_id, 'raw' ) : '';
			$out[] = $url ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $title ) ) : esc_html( $title );
		}

		return $out;
	}

	private function diagnostic_usage_labels( $relative_path, $attachment_id ) {
		if ( null === $relative_path || '' === $relative_path ) {
			return array();
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision') AND post_content LIKE %s ORDER BY ID ASC LIMIT 25",
				'%' . $wpdb->esc_like( $relative_path ) . '%'
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$title = '' === $row->post_title ? sprintf( __( 'Sisältö #%d', 'private-gutenberg-media' ), $row->ID ) : $row->post_title;
			$url   = current_user_can( 'edit_post', $row->ID ) ? get_edit_post_link( $row->ID, 'raw' ) : '';
			$label = sprintf(
				/* translators: 1: post title, 2: post type. */
				__( '%1$s (%2$s)', 'private-gutenberg-media' ),
				$title,
				$row->post_type
			);

			$out[] = $url ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) ) : esc_html( $label );
		}

		if ( empty( $out ) ) {
			$out = $this->diagnostic_source_labels( $attachment_id );
		}

		return $out;
	}

	private function audit_database_health() {
		$uploads = wp_get_upload_dir();
		$checks  = array(
			array(
				'label'  => __( 'Sivuston URL', 'private-gutenberg-media' ),
				'status' => __( 'OK', 'private-gutenberg-media' ),
				'detail' => sprintf( 'home: %1$s, siteurl: %2$s', home_url(), site_url() ),
				'class'  => 'pgm-status-public',
			),
			array(
				'label'  => __( 'Uploads base', 'private-gutenberg-media' ),
				'status' => empty( $uploads['basedir'] ) ? __( 'Tarkista', 'private-gutenberg-media' ) : __( 'OK', 'private-gutenberg-media' ),
				'detail' => empty( $uploads['basedir'] ) ? __( 'wp_get_upload_dir() ei palauttanut basedir-arvoa.', 'private-gutenberg-media' ) : $uploads['basedir'],
				'class'  => empty( $uploads['basedir'] ) ? 'pgm-status-missing' : 'pgm-status-public',
			),
			array(
				'label'  => __( 'Private-varasto', 'private-gutenberg-media' ),
				'status' => is_dir( $this->private_storage_dir() ) ? __( 'OK', 'private-gutenberg-media' ) : __( 'Ei vielä luotu', 'private-gutenberg-media' ),
				'detail' => $this->private_storage_dir(),
				'class'  => is_dir( $this->private_storage_dir() ) ? 'pgm-status-public' : 'pgm-status-warning',
			),
			array(
				'label'  => __( 'OpenSSL', 'private-gutenberg-media' ),
				'status' => $this->private_storage_crypto_available() ? __( 'OK', 'private-gutenberg-media' ) : __( 'Puuttuu', 'private-gutenberg-media' ),
				'detail' => $this->private_storage_crypto_available() ? __( 'Salattuja .pgm.php-tiedostoja voidaan kirjoittaa ja purkaa.', 'private-gutenberg-media' ) : __( 'Salattu varasto ei toimi ennen kuin PHP OpenSSL on käytössä.', 'private-gutenberg-media' ),
				'class'  => $this->private_storage_crypto_available() ? 'pgm-status-public' : 'pgm-status-missing',
			),
		);

		$checks[] = array(
			'label'  => __( 'Pecodex debug log', 'private-gutenberg-media' ),
			'status' => $this->debug_enabled() ? __( 'Käytössä', 'private-gutenberg-media' ) : __( 'Pois', 'private-gutenberg-media' ),
			'detail' => $this->debug_enabled() ? __( 'Debug kirjoittaa WordPressin debug.log-tiedostoon prefixillä [Pecodex Media Control DEBUG].', 'private-gutenberg-media' ) : __( 'Aseta WP_DEBUG ja WP_DEBUG_LOG trueksi tai pidä vanha PGM_DEBUG_LOG trueksi yhteensopivuuden vuoksi.', 'private-gutenberg-media' ),
			'class'  => $this->debug_enabled() ? 'pgm-status-warning' : 'pgm-status-public',
		);
		$checks[] = $this->audit_catfolders_table();
		$checks[] = $this->audit_persistent_private_links_in_content();
		$checks[] = $this->audit_attachment_guid_domains();

		return $checks;
	}

	private function audit_persistent_private_links_in_content() {
		global $wpdb;

		$login_likes = array();
		foreach ( array( self::ACTION_LOGIN, self::PECODEX_ACTION_LOGIN ) as $action ) {
			$login_likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( 'action=' . $action ) . '%' );
		}

		$direct_likes = array();
		foreach ( array( self::ACTION, self::PECODEX_ACTION ) as $action ) {
			foreach ( array( '&', '&#038;', '&amp;' ) as $separator ) {
				$direct_likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( 'action=' . $action . $separator ) . '%' );
			}
		}

		$login_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status NOT IN ('auto-draft', 'trash') AND (" . implode( ' OR ', $login_likes ) . ')'
		);

		$direct_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status NOT IN ('auto-draft', 'trash') AND (" . implode( ' OR ', $direct_likes ) . ')'
		);

		if ( 0 === $direct_count ) {
			if ( $login_count > 0 ) {
				return array(
					'label'  => __( 'Tallennetut suojalinkit', 'private-gutenberg-media' ),
					'status' => __( 'OK', 'private-gutenberg-media' ),
					'detail' => sprintf(
						/* translators: %d: post count. */
						__( '%d sisällössä vanha uploads-linkki on korvattu suojatulla login-linkillä. Alkuperäinen media-polku säilyy liitteen metassa ja palautuu, kun media tehdään julkiseksi.', 'private-gutenberg-media' ),
						$login_count
					),
					'class'  => 'pgm-status-public',
				);
			}

			return array(
				'label'  => __( 'Tallennetut suojalinkit', 'private-gutenberg-media' ),
				'status' => __( 'OK', 'private-gutenberg-media' ),
				'detail' => __( 'Sisältöön ei ole jäänyt suojauksen aikaisia media-linkkejä.', 'private-gutenberg-media' ),
				'class'  => 'pgm-status-public',
			);
		}

		return array(
			'label'  => __( 'Tallennetut suojalinkit', 'private-gutenberg-media' ),
			'status' => __( 'Tarkista', 'private-gutenberg-media' ),
			'detail' => sprintf(
				/* translators: %d: post count. */
				__( '%d sisällössä näyttäää olevan suora nonce-latauslinkki. Vaihda se login-start-suojalinkiksi tai tallenna media uudelleen, jotta linkki ei vanhene.', 'private-gutenberg-media' ),
				$direct_count
			),
			'class'  => 'pgm-status-missing',
		);
	}

	private function audit_attachment_guid_domains() {
		global $wpdb;

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $home_host ) {
			return array(
				'label'  => __( 'Attachment GUID -domainit', 'private-gutenberg-media' ),
				'status' => __( 'Tarkista', 'private-gutenberg-media' ),
				'detail' => __( 'Nykyisen sivuston domainia ei voitu lukea.', 'private-gutenberg-media' ),
				'class'  => 'pgm-status-warning',
			);
		}

		$bad_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE 'http%' AND guid NOT LIKE %s",
				'%' . $wpdb->esc_like( '://' . $home_host . '/' ) . '%'
			)
		);

		if ( 0 === $bad_count ) {
			return array(
				'label'  => __( 'Attachment GUID -domainit', 'private-gutenberg-media' ),
				'status' => __( 'OK', 'private-gutenberg-media' ),
				'detail' => __( 'Attachmentien GUID-domainit vastaavat nykyistä sivustoa tai niitä ei tarvitse muuttaa.', 'private-gutenberg-media' ),
				'class'  => 'pgm-status-public',
			);
		}

		return array(
			'label'  => __( 'Attachment GUID -domainit', 'private-gutenberg-media' ),
			'status' => __( 'Huomio', 'private-gutenberg-media' ),
			'detail' => sprintf(
				/* translators: %d: attachment count. */
				__( '%d attachmentin GUID osoittaa muulle domainille. GUID ei yleensä ratkaise tiedostopolkuja, mutta migraatiohistoria kannattaa huomioida auditissa.', 'private-gutenberg-media' ),
				$bad_count
			),
			'class'  => 'pgm-status-warning',
		);
	}

	private function audit_catfolders_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'catfolders_posts';
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		if ( ! $found ) {
			return array(
				'label'  => __( 'CatFolders-taulu', 'private-gutenberg-media' ),
				'status' => __( 'Ei käytössä', 'private-gutenberg-media' ),
				'detail' => __( 'wp_catfolders_posts-taulua ei löytynyt. Tämä on OK, jos CatFolders ei ole käytössä.', 'private-gutenberg-media' ),
				'class'  => 'pgm-status-public',
			);
		}

		$safe_table = '`' . str_replace( '`', '``', $table ) . '`';
		$indexes    = $wpdb->get_results( "SHOW INDEX FROM {$safe_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$names      = array();

		foreach ( (array) $indexes as $index ) {
			if ( isset( $index->Key_name ) ) {
				$names[] = (string) $index->Key_name;
			}
		}

		$missing = array();
		foreach ( array( 'PRIMARY', 'post_id' ) as $required_index ) {
			if ( ! in_array( $required_index, $names, true ) ) {
				$missing[] = $required_index;
			}
		}

		if ( empty( $missing ) ) {
			return array(
				'label'  => __( 'CatFolders-taulu', 'private-gutenberg-media' ),
				'status' => __( 'OK', 'private-gutenberg-media' ),
				'detail' => __( 'Taulusta löytyvät PRIMARY- ja post_id-indeksit.', 'private-gutenberg-media' ),
				'class'  => 'pgm-status-public',
			);
		}

		return array(
			'label'  => __( 'CatFolders-taulu', 'private-gutenberg-media' ),
			'status' => __( 'Tarkista', 'private-gutenberg-media' ),
			'detail' => sprintf(
				/* translators: %s: missing index list. */
				__( 'Taulusta puuttuu indeksejä: %s. Updraft-palautus on voinut jättää taulurakenteen vajaaksi.', 'private-gutenberg-media' ),
				implode( ', ', $missing )
			),
			'class'  => 'pgm-status-missing',
		);
	}

	public function show_private_storage_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->is_plugin_admin_context() ) {
			return;
		}

		if ( $this->should_move_files_to_private_storage() ) {
			if ( ! $this->private_storage_crypto_available() ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Pecodex Media Control tarvitsee PHP OpenSSL -laajennuksen, jotta tiedostot voidaan tallentaa salattuun yksityisvarastoon.', 'private-gutenberg-media' )
			);
			} else {
				$storage = $this->ensure_private_storage();
				if ( is_wp_error( $storage ) ) {
					printf(
						'<div class="notice notice-warning"><p>%s</p></div>',
						esc_html( $storage->get_error_message() )
					);
				}
			}
		}

		$options = $this->get_options();
		if ( 'floauth' === $options['login_mode'] && ! $this->floauth_is_available() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'FloAuth-kirjautuminen on valittu, mutta FloAuth ei ole aktiivinen tai sen asetukset puuttuvat. Lisäosa käyttää varmuuden vuoksi WordPressin kirjautumista.', 'private-gutenberg-media' )
			);
		}
	}

	public function print_admin_status_styles() {
		if ( ! is_admin() ) {
			return;
		}
		?>
		<style>
			.pgm-status-public,
			.pgm-status-hidden,
			.pgm-status-warning,
			.pgm-status-policy-pending,
			.pgm-status-link-only,
			.pgm-status-missing {
				display: inline-block;
				font-weight: 600;
				line-height: 1.4;
			}

			.pgm-status-hidden {
				color: #008a20;
			}

			.pgm-status-warning,
			.pgm-status-missing {
				color: #b32d2e;
			}

			.pgm-status-link-only {
				color: #996800;
			}

			.pgm-status-policy-pending {
				color: #2271b1;
			}

			.attachment.pgm-media-is-private .attachment-preview {
				position: relative;
				box-shadow: inset 0 0 0 2px rgba(179, 45, 46, 0.5);
			}

			.attachment .pgm-media-badge {
				position: absolute;
				top: 7px;
				right: 7px;
				z-index: 20;
				display: inline-flex;
				align-items: center;
				gap: 4px;
				box-sizing: border-box;
				max-width: calc(100% - 14px);
				padding: 4px 7px;
				border-radius: 999px;
				background: #b32d2e;
				color: #fff;
				font-size: 11px;
				font-weight: 700;
				line-height: 1;
				text-align: center;
				white-space: nowrap;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.22);
				pointer-events: none;
			}

			.attachment .pgm-media-badge-icon {
				width: 13px;
				height: 13px;
				flex: 0 0 13px;
				fill: none;
				stroke: currentColor;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
			}

			.attachment .pgm-media-badge.pgm-status-warning,
			.attachment .pgm-media-badge.pgm-status-missing {
				background: #b32d2e;
			}

			.attachment .pgm-media-badge.pgm-status-link-only {
				background: #b32d2e;
			}

			.attachment .pgm-media-badge.pgm-status-policy-pending {
				background: #b32d2e;
			}

			.pgm-media-details-notice {
				box-sizing: border-box;
				margin: 0 0 12px;
				padding: 10px 12px;
				border-left: 4px solid #008a20;
				background: #f0f6f1;
			}

			.pgm-media-details-notice.pgm-status-warning,
			.pgm-media-details-notice.pgm-status-missing {
				border-left-color: #b32d2e;
				background: #fcf0f1;
			}

			.pgm-media-details-notice.pgm-status-link-only {
				border-left-color: #996800;
				background: #fcf9e8;
			}

			.pgm-media-details-notice.pgm-status-policy-pending {
				border-left-color: #2271b1;
				background: #f0f6fc;
			}

			.pgm-media-details-notice strong {
				display: block;
				margin-bottom: 4px;
			}

			.pgm-media-details-notice p,
			.pgm-media-url-help {
				margin: 4px 0 0;
				color: #50575e;
			}

			.setting.pgm-private-protected-url input.attachment-details-copy-link {
				border-color: #008a20;
				background: #f6fff7;
			}

			.pgm-media-action-panel {
				box-sizing: border-box;
				margin: 0 0 16px;
				padding: 14px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				background: #fff;
			}

			.pgm-media-privacy-card.is-private {
				border-left: 4px solid #008a20;
				background: #fbfffb;
			}

			.pgm-media-privacy-card.is-public {
				border-left: 4px solid #2271b1;
			}

			.pgm-media-privacy-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 10px;
				margin-bottom: 8px;
			}

			.pgm-media-privacy-header h3 {
				margin: 0;
				color: #1d2327;
				font-size: 14px;
				font-weight: 600;
				line-height: 1.4;
			}

			.pgm-media-privacy-pill {
				display: inline-flex;
				align-items: center;
				min-height: 22px;
				border-radius: 999px;
				background: #e7f5ff;
				color: #135e96;
				font-size: 11px;
				font-weight: 700;
				line-height: 1;
				padding: 0 9px;
				white-space: nowrap;
			}

			.pgm-media-privacy-pill.is-private {
				background: #e8f5e9;
				color: #008a20;
			}

			.pgm-media-privacy-summary {
				margin: 0;
				color: #3c434a;
				line-height: 1.45;
			}

			.pgm-media-source-list {
				display: grid;
				gap: 6px;
				margin-top: 10px;
			}

			.pgm-media-source-item {
				display: grid;
				grid-template-columns: 76px minmax(0, 1fr);
				align-items: start;
				gap: 8px;
				min-width: 0;
				color: #3c434a;
			}

			.pgm-media-source-label {
				display: inline-flex;
				justify-content: center;
				border-radius: 999px;
				background: #f0f0f1;
				color: #50575e;
				font-size: 11px;
				font-weight: 700;
				line-height: 20px;
				padding: 0 8px;
			}

			.pgm-media-source-item a,
			.pgm-media-source-item span:last-child {
				min-width: 0;
				overflow-wrap: anywhere;
			}

			.pgm-media-action-row {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				margin-top: 12px;
			}

			.pgm-media-action-row .button {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 6px;
				margin: 0;
			}

			.pgm-media-action-row .button .pgm-media-badge-icon {
				width: 14px;
				height: 14px;
				flex: 0 0 14px;
				fill: none;
				stroke: currentColor;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-linejoin: round;
			}

			.pgm-media-action-panel .description {
				margin: 10px 0 0;
				color: #646970;
			}

			.pgm-manual-privacy-field {
				display: inline-flex;
				align-items: flex-start;
				gap: 8px;
			}

			.pgm-media-action-message {
				display: block;
				margin-top: 8px;
				font-weight: 600;
			}

			.pgm-diagnostic-table {
				margin-top: 10px;
			}

			.pgm-diagnostic-table th,
			.pgm-diagnostic-table td {
				vertical-align: top;
			}

			.pgm-diagnostic-table code {
				word-break: break-word;
			}

			.pgm-diagnostic-action-form {
				display: inline-block;
				margin: 0 4px 6px 0;
			}

			.pgm-diagnostic-env-table {
				max-width: 980px;
			}
		</style>
		<?php
	}

	private function is_plugin_admin_context() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return in_array( $page, array( self::SETTINGS_PAGE_SLUG, self::LEGACY_SETTINGS_PAGE_SLUG ), true ) || 'plugins.php' === $GLOBALS['pagenow'];
	}

	/**
	 * Lataa Gutenberg-editorin JavaScriptin, joka lisää tuettuihin lohkoihin
	 * "Vaadi kirjautuminen suojattaville linkeille" -valinnan.
	 */
	public function enqueue_block_editor_assets() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$asset_path = plugin_dir_path( __FILE__ ) . 'editor.js';
		$version    = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : '0.1.0';
		$badge_data = $this->editor_private_attachment_badge_data();

		wp_enqueue_script(
			'pecodex-media-library-editor',
			plugin_dir_url( __FILE__ ) . 'editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-plugins', 'wp-edit-post' ),
			$version,
			true
		);

		// Editorin popup-nappi tarvitsee julkisen sivuston URLin, jotta se osaa
		// erottaa saman sivuston sisäiset linkit ulkoisista linkeistä adminissa.
		wp_add_inline_script(
			'pecodex-media-library-editor',
			'window.pecodexMediaLibraryEditor = window.pgmPrivateGutenbergMedia = ' . wp_json_encode(
				array(
					'brandName'    => __( 'Pecodex Media Control', 'private-gutenberg-media' ),
					'companyName'  => 'Pecodex',
					'companyUrl'   => 'https://pecodex.fi/',
					'homeUrl'      => home_url( '/' ),
					'adminPostUrl' => admin_url( 'admin-post.php' ),
					'wpRoles'      => wp_roles()->get_names(),
					'privateAttachmentIds' => $badge_data['ids'],
					'privateAttachmentUrls' => $badge_data['urls'],
					'privateAttachmentPaths' => $badge_data['paths'],
					'i18n'         => array(
						'protectedBadge' => __( 'Suojattu', 'private-gutenberg-media' ),
					),
				)
			) . ';',
			'before'
		);
	}

	public function add_block_editor_badge_styles( $editor_settings, $editor_context ) {
		if ( ! $this->is_enabled() ) {
			return $editor_settings;
		}

		if ( ! isset( $editor_settings['styles'] ) || ! is_array( $editor_settings['styles'] ) ) {
			$editor_settings['styles'] = array();
		}

		$editor_settings['styles'][] = array(
			'css' => $this->editor_badge_css(),
		);

		return $editor_settings;
	}

	private function editor_badge_css() {
		$lock_icon   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='11' width='18' height='10' rx='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 10 0v4'/%3E%3C/svg%3E";

		return '
.block-editor-block-list__block.is-pecodex-private-media {
	position: relative !important;
}
.block-editor-block-list__block.is-pecodex-private-media > .pecodex-private-media-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	position: absolute;
	top: 8px;
	right: 8px;
	z-index: 9999;
	box-sizing: border-box;
	width: auto !important;
	height: auto !important;
	min-height: 24px;
	min-width: 0;
	max-width: calc(100% - 16px);
	margin: 0 !important;
	padding: 4px 9px;
	border: 1px solid #991b1b;
	border-radius: 999px;
	background-color: #b91c1c;
	color: #fff;
	font-size: 12px;
	font-weight: 700;
	line-height: 1.2;
	letter-spacing: 0;
	text-align: left;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	pointer-events: none;
	transform: none !important;
}
.block-editor-block-list__block.is-pecodex-private-media > .pecodex-private-media-badge::before {
	content: "";
	display: block;
	width: 14px;
	height: 14px;
	flex: 0 0 14px;
	background-image: url("' . $lock_icon . '");
	background-repeat: no-repeat;
	background-position: center;
	background-size: 14px 14px;
}
.block-editor-block-list__block.is-pecodex-private-media.is-pecodex-file-no-preview > .pecodex-private-media-badge {
	top: -12px;
	right: 6px;
}
.is-pecodex-file-no-preview .wp-block-file__preview,
.is-pecodex-file-no-preview .wp-block-file__preview-overlay,
.is-pecodex-file-no-preview .wp-block-file__embed,
.is-pecodex-file-no-preview object[type="application/pdf"],
.is-pecodex-file-no-preview .wp-block-file > .components-resizable-box__container {
	display: none !important;
}
.is-pecodex-file-no-preview .wp-block-file {
	align-items: center;
	gap: 8px;
}
.is-pecodex-file-no-preview .wp-block-file__content-wrapper {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}';
	}

	private function editor_private_attachment_ids() {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_PRIVATE,
						'value' => '1',
					),
				),
			)
		);

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private function editor_private_attachment_badge_data() {
		$ids        = $this->editor_private_attachment_ids();
		$urls       = array();
		$paths      = array();
		$upload_dir = wp_get_upload_dir();

		foreach ( $ids as $attachment_id ) {
			$relative_path = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			$this->add_editor_private_attachment_path_reference( $relative_path, $upload_dir, $paths, $urls );

			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				$base_dir = '' !== $relative_path ? trailingslashit( dirname( $relative_path ) ) : '';
				if ( './' === $base_dir ) {
					$base_dir = '';
				}

				foreach ( $metadata['sizes'] as $size ) {
					if ( is_array( $size ) && ! empty( $size['file'] ) ) {
						$this->add_editor_private_attachment_path_reference( $base_dir . $size['file'], $upload_dir, $paths, $urls );
					}
				}
			}

			$this->add_editor_private_attachment_url_reference( (string) get_post_meta( $attachment_id, self::META_ORIGINAL_PUBLIC_URL, true ), $urls );
			$this->add_editor_private_attachment_url_reference( (string) get_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL, true ), $urls );
			$this->add_editor_private_attachment_url_reference( (string) wp_get_attachment_url( $attachment_id ), $urls );
		}

		return array(
			'ids'   => $ids,
			'urls'  => array_values( array_unique( array_filter( $urls ) ) ),
			'paths' => array_values( array_unique( array_filter( $paths ) ) ),
		);
	}

	private function add_editor_private_attachment_path_reference( $relative_path, $upload_dir, &$paths, &$urls ) {
		$relative_path = ltrim( str_replace( '\\', '/', (string) $relative_path ), '/' );
		if ( '' === $relative_path ) {
			return;
		}

		$paths[] = strtolower( rawurldecode( $relative_path ) );

		if ( ! empty( $upload_dir['baseurl'] ) ) {
			$urls[] = trailingslashit( $upload_dir['baseurl'] ) . str_replace( '%2F', '/', rawurlencode( $relative_path ) );
			$urls[] = trailingslashit( $upload_dir['baseurl'] ) . $relative_path;
		}
	}

	private function add_editor_private_attachment_url_reference( $url, &$urls ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return;
		}

		$urls[] = html_entity_decode( esc_url_raw( $url ), ENT_QUOTES );
	}

	/**
	 * Lataa Media Libraryn admin-näkymään pienen apuskriptin, joka merkitsee
	 * ruudukkonäkymässä yksityiset mediat samalla statuksella kuin listan
	 * "Julkinen näkyvyys" -sarake.
	 */
	public function add_deactivation_warning_script() {
		?>
		<div id="pgm-deactivate-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99998;"></div>
		<div id="pgm-deactivate-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:450px; background:#fff; padding:20px; box-shadow:0 3px 6px rgba(0,0,0,0.3); z-index:99999; border-radius:4px;">
			<h2 style="margin-top:0;">Varoitus: Lisäosan poistaminen käytöstä</h2>
			<p>Kun poistat lisäosan käytöstä, kaikki järjestelmän suojatut tiedostot siirretään takaisin julkiseen kansioon ja niistä tulee jälleen julkisia.</p>
			<p>Jos tiedostoja on paljon, tämä toimenpide voi kestää hetken.</p>
			<div id="pgm-deactivate-progress-container" style="display:none; margin: 20px 0;">
				<div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
					<div id="pgm-deactivate-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.3s;"></div>
				</div>
				<p id="pgm-deactivate-status" style="text-align:center; font-size:13px; margin-top:5px;">Valmistellaan...</p>
			</div>
			<div id="pgm-deactivate-buttons" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
				<button type="button" id="pgm-deactivate-cancel" class="button">Peruuta</button>
				<button type="button" id="pgm-deactivate-confirm" class="button button-primary">Poista käytöstä ja palauta tiedostot</button>
			</div>
		</div>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var links = document.querySelectorAll('tr[data-slug="pecodex-security"] .deactivate a');
				if (!links.length) return;

				var overlay = document.getElementById('pgm-deactivate-modal-overlay');
				var modal = document.getElementById('pgm-deactivate-modal');
				var btnCancel = document.getElementById('pgm-deactivate-cancel');
				var btnConfirm = document.getElementById('pgm-deactivate-confirm');
				var buttonsDiv = document.getElementById('pgm-deactivate-buttons');
				var progressContainer = document.getElementById('pgm-deactivate-progress-container');
				var progressBar = document.getElementById('pgm-deactivate-progress-bar');
				var statusText = document.getElementById('pgm-deactivate-status');
				
				var deactivateUrl = '';

				for (var i = 0; i < links.length; i++) {
					links[i].addEventListener('click', function(e) {
						e.preventDefault();
						deactivateUrl = this.href;
						overlay.style.display = 'block';
						modal.style.display = 'block';
					});
				}

				btnCancel.addEventListener('click', function() {
					overlay.style.display = 'none';
					modal.style.display = 'none';
				});

				btnConfirm.addEventListener('click', function() {
					buttonsDiv.style.display = 'none';
					progressContainer.style.display = 'block';
					
					function processBatch(offset) {
						var data = new URLSearchParams({
							action: 'pgm_deactivation_batch_restore',
							nonce: '<?php echo wp_create_nonce("pgm_deactivation_batch"); ?>',
							offset: offset
						});
						
						fetch(ajaxurl, {
							method: 'POST',
							body: data,
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
						}).then(function(res) { return res.json(); }).then(function(response) {
							if (response.success) {
								if (response.data.done) {
									progressBar.style.width = '100%';
									statusText.innerText = 'Valmis! Ohjataan...';
									window.location.href = deactivateUrl;
								} else {
									var p = Math.round((response.data.processed / response.data.total) * 100);
									progressBar.style.width = p + '%';
									statusText.innerText = 'Siirretään tiedostoja... ' + response.data.processed + ' / ' + response.data.total;
									processBatch(offset + response.data.batch_size);
								}
							} else {
								alert('Virhe: ' + (response.data ? response.data : 'Tuntematon virhe'));
								window.location.href = deactivateUrl;
							}
						}).catch(function(err) {
							alert('Yhteysvirhe. Siirrytään normaaliin deaktivointiin.');
							window.location.href = deactivateUrl;
						});
					}

					processBatch(0);
				});
			});
		</script>
		<?php
	}

	public function handle_deactivation_batch_restore_ajax() {
		check_ajax_referer( 'pgm_deactivation_batch', 'nonce' );
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( 'Ei oikeuksia.' );
		}

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = 50;

		if ( $offset === 0 ) {
			$ids = $this->attachment_ids_for_exit_restore();
			set_transient( 'pgm_deactivation_queue', $ids, HOUR_IN_SECONDS );
		} else {
			$ids = get_transient( 'pgm_deactivation_queue' );
			if ( ! is_array( $ids ) ) {
				$ids = $this->attachment_ids_for_exit_restore();
			}
		}

		$total = count( $ids );
		if ( $total === 0 || $offset >= $total ) {
			$this->remove_public_upload_protection_rules();
			delete_transient( 'pgm_deactivation_queue' );
			wp_send_json_success( array( 'done' => true ) );
		}

		$batch = array_slice( $ids, $offset, $batch_size );
		foreach ( $batch as $attachment_id ) {
			$this->restore_attachment_to_plain_wordpress_media( $attachment_id );
		}

		$processed = $offset + count( $batch );
		$done = ( $processed >= $total );

		if ( $done ) {
			$this->remove_public_upload_protection_rules();
			delete_transient( 'pgm_deactivation_queue' );
		}

		wp_send_json_success( array(
			'done'       => $done,
			'processed'  => $processed,
			'total'      => $total,
			'batch_size' => count( $batch ),
		) );
	}

	public function enqueue_media_admin_assets( $hook_suffix ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! in_array( $hook_suffix, array( 'upload.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$asset_path = plugin_dir_path( __FILE__ ) . 'admin-media.js';
		$version    = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : '0.3.4';

		wp_enqueue_script(
			'pecodex-media-library-admin-media',
			plugin_dir_url( __FILE__ ) . 'admin-media.js',
			array( 'jquery', 'media-views' ),
			$version,
			true
		);

		wp_add_inline_script(
			'pecodex-media-library-admin-media',
			'window.pecodexMediaLibraryAdmin = window.pgmPrivateGutenbergMediaAdmin = ' . wp_json_encode(
				array(
					'brandName' => __( 'Pecodex Media Control', 'private-gutenberg-media' ),
					'companyName' => 'Pecodex',
					'companyUrl' => 'https://pecodex.fi/',
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'pgm_toggle_attachment_privacy' ),
					'shareNonce' => wp_create_nonce( 'pgm_toggle_attachment_privacy' ),
					'ajaxActions' => array(
						'toggleAttachmentPrivacy' => self::PECODEX_AJAX_TOGGLE_ATTACHMENT_PRIVACY,
					),
					'pdfWorkerUrl' => plugins_url( 'assets/vendor/pdf.worker.min.js', __FILE__ ),
					'settingsUrl' => admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG ),
					'i18n'    => array(
						'visibilityTitle'  => __( 'Julkisuus', 'private-gutenberg-media' ),
						'publicState'      => __( 'Julkinen', 'private-gutenberg-media' ),
						'protectedState'   => __( 'Suojattu', 'private-gutenberg-media' ),
						'hide'             => __( 'Piilota tiedosto', 'private-gutenberg-media' ),
						'protect'          => __( 'Suojaa tiedosto', 'private-gutenberg-media' ),
						'makePublic'       => __( 'Poista suojaus', 'private-gutenberg-media' ),
						'unhide'           => __( 'Poista suojaus', 'private-gutenberg-media' ),
						'unhideManual'     => __( 'Poista käsin tehty suojaus', 'private-gutenberg-media' ),
						'saving'           => __( 'Tallennetaan...', 'private-gutenberg-media' ),
						'hideDescription'  => __( 'Tiedosto siirretään pois julkisesta uploads-osoitteesta ja avataan jatkossa vain suojatulla linkillä.', 'private-gutenberg-media' ),
						'protectDescription' => __( 'Tiedosto merkitään suojatuksi ja avataan jatkossa vain suojatun linkin kautta.', 'private-gutenberg-media' ),
						'makePublicDescription' => __( 'Tiedosto palautetaan julkiseen uploads-kansioon ja asetussääntö ohitetaan vain tälle tiedostolle.', 'private-gutenberg-media' ),
						'unhideDescription' => __( 'Palauttaa tiedoston julkiseksi ja ohittaa asetussäännön tälle tiedostolle.', 'private-gutenberg-media' ),
						'unhideSourceLockedDescription' => __( 'Poistaa vain tämän median käsin tehdyn suojauksen. Media jää edelleen suojatuksi, jos kansio tai asetussääntö suojaa sitä.', 'private-gutenberg-media' ),
						'detachMakePublic' => __( 'Irrota kansiosta ja tee julkiseksi', 'private-gutenberg-media' ),
						'detachMakePublicDescription' => __( 'Kansio lukitsee tämän median. Poista suojaus siirtämällä media pois suojatusta kansiosta; tiedosto palautetaan samalla julkiseksi WordPress-mediaksi.', 'private-gutenberg-media' ),
						'folderManaged'    => __( 'Kansio lukitsee tämän median. Yksittäistä tiedostoa ei voi avata julkiseksi, ellei sitä irroteta suojatusta kansiosta tai kansion suojausta muuteta.', 'private-gutenberg-media' ),
						'blockManaged'     => __( 'Tiedosto on suojattu kansion kautta. Poista suojaus muuttamalla kansion näkyvyys tai siirtämällä tiedosto julkiseen kansioon.', 'private-gutenberg-media' ),
						'openSource'       => __( 'Avaa lähdesivu', 'private-gutenberg-media' ),
						'openFolder'       => __( 'Avaa kansio', 'private-gutenberg-media' ),
						'openSettings'     => __( 'Avaa suojausasetukset', 'private-gutenberg-media' ),
						'openSecurely'     => __( 'Avaa suojatusti', 'private-gutenberg-media' ),
						'error'            => __( 'Toimintoa ei voitu suorittaa.', 'private-gutenberg-media' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Kun sivu tallennetaan, vanhat suojatut endpoint-linkit palautetaan canonical
	 * uploads-linkeiksi. Varsinainen suojaus määräytyy aina mediatiedoston tilasta.
	 */
	public function mark_private_block_media_on_save( $post_id, $post, $update ) {
		unset( $update );

		if ( $this->cleaning_post_content ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || empty( $post->post_content ) ) {
			return;
		}

		if ( is_admin() && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$content       = (string) $post->post_content;
		$legacy_paths  = $this->extract_private_media_paths_from_markup( $content );
		$clean_content = $this->restore_private_media_urls_in_content( $content );

		if ( $clean_content !== $content ) {
			$this->cleaning_post_content = true;
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $clean_content,
				)
			);
			$this->cleaning_post_content = false;
			$content = $clean_content;
		}

		unset( $legacy_paths );

		delete_post_meta( $post_id, self::POST_META_BLOCK_PATHS );
	}

	private function collect_all_block_upload_paths( $blocks ) {
		$paths = array();

		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$paths = array_merge( $paths, $this->extract_upload_paths_from_markup( $this->block_markup_for_upload_scan( $block ) ) );

			if ( ! empty( $block['innerBlocks'] ) ) {
				$paths = array_merge( $paths, $this->collect_all_block_upload_paths( $block['innerBlocks'] ) );
			}
		}

		return array_values( array_unique( $paths ) );
	}

	private function restore_private_media_urls_in_content( $content ) {
		if ( ! $this->has_private_media_action_token( $content ) && ! $this->has_legacy_private_link_action_token( $content ) ) {
			return $content;
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );

			while ( $processor->next_tag() ) {
				foreach ( array( 'href', 'src', 'poster' ) as $attribute ) {
					$value = $processor->get_attribute( $attribute );
					if ( is_string( $value ) ) {
						$restored = $this->restore_private_media_url( $value );
						if ( $restored !== $value ) {
							$processor->set_attribute( $attribute, $restored );
						}
					}
				}

				$srcset = $processor->get_attribute( 'srcset' );
				if ( is_string( $srcset ) ) {
					$restored_srcset = $this->restore_private_media_srcset( $srcset );
					if ( $restored_srcset !== $srcset ) {
						$processor->set_attribute( 'srcset', $restored_srcset );
					}
				}
			}

			return $processor->get_updated_html();
		}

		return preg_replace_callback(
			'/\b(href|src|poster|srcset)=([\'"])(.*?)\2/i',
			function ( $matches ) {
				$attribute = strtolower( $matches[1] );
				$value     = 'srcset' === $attribute ? $this->restore_private_media_srcset( $matches[3] ) : $this->restore_private_media_url( $matches[3] );

				return $matches[1] . '=' . $matches[2] . esc_attr( $value ) . $matches[2];
			},
			$content
		);
	}

	private function restore_private_media_srcset( $srcset ) {
		$items = explode( ',', $srcset );
		$out   = array();

		foreach ( $items as $item ) {
			if ( ! preg_match( '/^\s*(\S+)(.*)$/', $item, $matches ) ) {
				$out[] = $item;
				continue;
			}

			$out[] = $this->restore_private_media_url( $matches[1] ) . $matches[2];
		}

		return implode( ', ', $out );
	}

	private function restore_private_media_url( $url ) {
		$relative_path = $this->private_media_url_to_upload_relative_path( $url );

		if ( null !== $relative_path ) {
			$attachment_id = $this->find_attachment_for_relative_path( $relative_path );
			if ( $attachment_id && $this->is_protected_upload( $attachment_id, $relative_path, 'content_restore' ) ) {
				return $this->build_login_start_url( $attachment_id, $relative_path, true );
			}

			$public_url = $this->upload_relative_path_to_url( $relative_path );

			return '' === $public_url ? $url : $public_url;
		}

		$legacy_link_target = $this->legacy_private_site_link_url_to_target( $url );
		if ( null !== $legacy_link_target ) {
			return $legacy_link_target;
		}

		return $url;
	}

	private function extract_private_media_paths_from_markup( $markup ) {
		$paths = array();

		if ( ! $this->has_private_media_action_token( $markup ) ) {
			return $paths;
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $markup );

			while ( $processor->next_tag() ) {
				foreach ( array( 'href', 'src', 'poster' ) as $attribute ) {
					$value = $processor->get_attribute( $attribute );
					if ( is_string( $value ) ) {
						$path = $this->private_media_url_to_upload_relative_path( $value );
						if ( null !== $path ) {
							$paths[] = $path;
						}
					}
				}

				$srcset = $processor->get_attribute( 'srcset' );
				if ( is_string( $srcset ) ) {
					foreach ( explode( ',', $srcset ) as $item ) {
						if ( preg_match( '/^\s*(\S+)/', $item, $matches ) ) {
							$path = $this->private_media_url_to_upload_relative_path( $matches[1] );
							if ( null !== $path ) {
								$paths[] = $path;
							}
						}
					}
				}
			}

			return array_values( array_unique( $paths ) );
		}

		preg_match_all( '/\b(?:href|src|poster|srcset)=([\'"])(.*?)\1/i', $markup, $matches );
		foreach ( $matches[2] as $value ) {
			foreach ( explode( ',', $value ) as $maybe_url ) {
				if ( preg_match( '/^\s*(\S+)/', $maybe_url, $url_match ) ) {
					$path = $this->private_media_url_to_upload_relative_path( $url_match[1] );
					if ( null !== $path ) {
						$paths[] = $path;
					}
				}
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Koostaa lohkon tallennetun HTML:n skannausta varten.
	 *
	 * serialize_block() otetaan mukaan, jotta myös lohkokommentteihin tai
	 * attribuutteihin tallentuneet upload-URLit löytyvät kehityksen aikana.
	 */
	private function block_markup_for_upload_scan( $block ) {
		$markup = '';

		if ( ! empty( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
			$markup .= "\n" . $block['innerHTML'];
		}

		if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $content ) {
				if ( is_string( $content ) ) {
					$markup .= "\n" . $content;
				}
			}
		}

		if ( function_exists( 'serialize_block' ) ) {
			$markup .= "\n" . serialize_block( $block );
		}

		return $markup;
	}

	private function extract_upload_paths_from_markup( $markup ) {
		$paths = array();

		if ( ! is_string( $markup ) || ( false === strpos( $markup, 'wp-content/uploads' ) && ! $this->has_private_media_action_token( $markup ) ) ) {
			return $paths;
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $markup );

			while ( $processor->next_tag() ) {
				foreach ( array( 'href', 'src', 'poster' ) as $attribute ) {
					$value = $processor->get_attribute( $attribute );
					if ( is_string( $value ) ) {
						$path = $this->url_to_managed_upload_relative_path( $value );
						if ( null !== $path ) {
							$paths[] = $path;
						}
					}
				}

				$srcset = $processor->get_attribute( 'srcset' );
				if ( is_string( $srcset ) ) {
					$paths = array_merge( $paths, $this->extract_upload_paths_from_srcset( $srcset ) );
				}
			}

			return array_values( array_unique( $paths ) );
		}

		preg_match_all( '/\b(?:href|src|poster)=([\'"])(.*?)\1/i', $markup, $matches );
		foreach ( $matches[2] as $url ) {
			$path = $this->url_to_managed_upload_relative_path( $url );
			if ( null !== $path ) {
				$paths[] = $path;
			}
		}

		preg_match_all( '/\bsrcset=([\'"])(.*?)\1/i', $markup, $srcset_matches );
		foreach ( $srcset_matches[2] as $srcset ) {
			$paths = array_merge( $paths, $this->extract_upload_paths_from_srcset( $srcset ) );
		}

		return array_values( array_unique( $paths ) );
	}

	private function extract_upload_paths_from_srcset( $srcset ) {
		$paths = array();

		foreach ( explode( ',', $srcset ) as $item ) {
			if ( ! preg_match( '/^\s*(\S+)/', $item, $matches ) ) {
				continue;
			}

			$path = $this->url_to_managed_upload_relative_path( $matches[1] );
			if ( null !== $path ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	public function maybe_mark_new_attachment_private( $attachment_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return;
		}

		$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		if ( null === $relative_path ) {
			$relative_path = (string) $file;
		}

		if ( ! $this->attachment_has_public_override( $attachment_id ) && $this->attachment_matches_protected_policy( $attachment_id, $relative_path ) ) {
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
		}
	}

	/**
	 * Siirtää uuden mediatiedoston ja sen luodut kokoversiot yksityiseen varastoon
	 * vain, jos ylläpitäjä on erikseen ottanut tiukan tiedostosuojausmallin käyttöön.
	 *
	 * Oletustilassa tiedostot jätetään uploadsiin, jotta Elementor ja muut builderit
	 * eivät menetä omia tiedostoviittauksiaan.
	 */
	public function maybe_move_generated_private_files( $metadata, $attachment_id ) {
		if ( ! $this->is_enabled() ) {
			return $metadata;
		}

		if (
			get_post_meta( $attachment_id, self::META_PRIVATE, true )
			&& ( $this->should_move_files_to_private_storage() || $this->attachment_requires_private_storage( $attachment_id ) )
		) {
			$this->move_attachment_files_to_private_storage( $attachment_id, $metadata );
		}

		return $metadata;
	}

	/**
	 * Palauttaa get_attached_file()-kutsulle yksityisen polun vain, jos julkinen
	 * tiedosto puuttuu ja tiedosto on aiemmin siirretty.
	 *
	 * Oletustilassa suositaan aina normaalia uploads-polkuun osoittavaa arvoa.
	 * Tämä on tärkeää page buildereille ja muille lisäosille, jotka odottavat
	 * mediatiedoston löytyvän WordPressin tavallisesta sijainnista.
	 */
	public function filter_private_attached_file( $file, $attachment_id ) {
		if ( ! get_post_meta( $attachment_id, self::META_PRIVATE, true ) ) {
			return $file;
		}

		if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
			return $file;
		}

		$relative_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return $file;
		}

		$private_path = $this->private_file_path_if_exists( $relative_path );

		return $private_path ? $private_path : $file;
	}

	/**
	 * Korvaa WordPressin vanhan uploads-URLin suojatulla portilla, kun tiedosto on
	 * oikeasti siirretty pois julkisesta uploads-kansiosta.
	 *
	 * _wp_attached_file jää tarkoituksella WordPressin omaan suhteelliseen muotoon,
	 * esimerkiksi "2025/10/tiedosto.pdf". Sitä ei muuteta yksityiseksi poluksi,
	 * jotta Media Library ja muut lisäosat tunnistavat edelleen saman attachmentin.
	 * Julkinen URL ei kuitenkaan saa osoittaa puuttuvaan uploads-tiedostoon.
	 */
	public function filter_private_attachment_url( $url, $attachment_id ) {
		if ( ! $this->is_enabled() || ! $attachment_id ) {
			$this->debug_log(
				'wp_get_attachment_url_skip',
				array(
					'reason'        => 'disabled_or_missing_attachment',
					'attachment_id' => $attachment_id,
					'input_url'     => $url,
				)
			);
			return $url;
		}

		if ( wp_doing_ajax() && ! isset( $_REQUEST['pgm_force'] ) ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( 'query-attachments' === $action || 'upload-attachment' === $action ) {
				$this->debug_log(
					'wp_get_attachment_url_skip',
					array(
						'reason' => $action . '_ajax',
						'input_url' => $url,
					) + $this->debug_attachment_state( $attachment_id, null )
				);
				return $url;
			}
		}

		$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		if ( null === $relative_path ) {
			$this->debug_log(
				'wp_get_attachment_url_skip',
				array(
					'reason' => 'missing_relative_path',
					'input_url' => $url,
				) + $this->debug_attachment_state( $attachment_id, null )
			);
			return $url;
		}

		$prefer_protected_url = $this->attachment_should_prefer_protected_url( $attachment_id, $relative_path );
		$force_protected      = $this->attachment_url_should_force_protection( $attachment_id );

		if ( $prefer_protected_url && $force_protected ) {
			$storage_ready = $this->ensure_private_storage_before_protected_url( $attachment_id, $relative_path );
			if ( ! $storage_ready && $this->attachment_has_public_upload_copy( $attachment_id ) ) {
				$this->debug_log(
					'wp_get_attachment_url_decision',
					array(
						'decision'  => 'original_upload_url_storage_sync_failed',
						'input_url' => $url,
						'output_url' => $url,
						'force_protected' => $force_protected,
					) + $this->debug_attachment_state( $attachment_id, $relative_path )
				);
				return $url;
			}
		}

		if ( ! $prefer_protected_url ) {
			$public_url = $this->upload_relative_path_to_url( $relative_path );
			$output_url = '' === $public_url ? $url : $public_url;
			$this->debug_log(
				'wp_get_attachment_url_decision',
				array(
					'decision'  => 'original_upload_url',
					'input_url' => $url,
					'output_url' => $output_url,
					'force_protected' => $force_protected,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			return $output_url;
		}

		$protected_url = $this->build_login_start_url( $attachment_id, $relative_path, $force_protected );
		$this->debug_log(
			'wp_get_attachment_url_decision',
			array(
				'decision'  => 'protected_url',
				'input_url' => $url,
				'output_url' => $protected_url,
				'force_protected' => $force_protected,
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		return $protected_url;
	}

	private function ensure_private_storage_before_protected_url( $attachment_id, $relative_path ) {
		$requires_private_storage = $this->attachment_requires_private_storage( $attachment_id ) || $this->should_move_files_to_private_storage();
		if ( ! $requires_private_storage ) {
			return true;
		}

		if ( ! $this->attachment_has_public_upload_copy( $attachment_id ) && $this->attachment_has_private_storage_copy( $attachment_id ) ) {
			return true;
		}

		$result = $this->sync_attachment_storage_for_current_mode( $attachment_id );
		$ready  = ! $this->attachment_has_public_upload_copy( $attachment_id ) && $this->attachment_has_private_storage_copy( $attachment_id );

		$this->debug_log(
			'protected_url_storage_sync',
			array(
				'result'        => is_wp_error( $result ) ? 'error' : 'ok',
				'error_code'    => is_wp_error( $result ) ? $result->get_error_code() : '',
				'error_message' => is_wp_error( $result ) ? $result->get_error_message() : '',
				'ready'         => $ready,
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		return $ready;
	}

	public function add_media_visibility_column( $columns ) {
		$columns['pgm_public_visibility'] = __( 'Julkinen näkyvyys', 'private-gutenberg-media' );

		return $columns;
	}

	public function render_media_visibility_column( $column_name, $attachment_id ) {
		if ( 'pgm_public_visibility' !== $column_name ) {
			return;
		}

		$status = $this->attachment_public_visibility_status( $attachment_id );

		printf(
			'<span class="%1$s" title="%2$s">%3$s</span>',
			esc_attr( $status['class'] ),
			esc_attr( $status['description'] ),
			esc_html( $status['label'] )
		);
	}

	/**
	 * Lisää media-attachmentin JSON-vastaukseen saman julkisuusstatuksen, jota
	 * listanäkymän sarake käyttää. WordPressin ruudukkonäkymä rakennetaan JS:llä,
	 * joten pelkkä manage_media_columns-koukku ei näy siellä.
	 */
	public function add_attachment_private_status_to_js( $response, $attachment, $meta ) {
		unset( $meta );

		if ( ! $attachment instanceof WP_Post ) {
			return $response;
		}

		$attachment_id = (int) $attachment->ID;
		if (
			is_admin()
			&& current_user_can( 'edit_post', $attachment_id )
			&& ( get_post_meta( $attachment_id, self::META_PRIVATE, true ) || $this->attachment_has_protected_content_links( $attachment_id ) )
			&& $this->attachment_requires_private_storage( $attachment_id )
			&& $this->attachment_has_public_upload_copy( $attachment_id )
		) {
			$this->sync_attachment_storage_for_current_mode( $attachment_id );
		}

		$status        = $this->attachment_public_visibility_status( $attachment_id );
		$source_ids    = array();
		$folder_source_ids = $this->attachment_folder_source_ids( $attachment_id );
		$folder_access = $this->attachment_effective_folder_access( $attachment_id );
		$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$is_private_meta = (bool) get_post_meta( $attachment_id, self::META_PRIVATE, true );
		$is_manual_private = (bool) get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true );
		$is_public_override = $this->attachment_has_public_override( $attachment_id );
		$public_exists = $this->attachment_has_public_upload_copy( $attachment_id );
		$private_exists = $this->attachment_has_private_storage_copy( $attachment_id );
		$can_manage_attachment = is_admin() && current_user_can( 'edit_post', $attachment_id );
		$admin_download_url = '';
		$admin_preview_url = '';
		$prefer_protected_url = false;
		$is_policy_protected = null !== $relative_path && $this->attachment_matches_protected_policy( $attachment_id, $relative_path );
		$has_protected_content_links = $this->attachment_has_protected_content_links( $attachment_id );
		$has_source_lock = ! empty( $folder_source_ids ) || PGM_Media_Organizer::ACCESS_PUBLIC !== $folder_access;
		$is_private = $is_private_meta || PGM_Media_Organizer::ACCESS_PUBLIC !== $folder_access || ( $this->should_move_files_to_private_storage() && $has_protected_content_links ) || ( $is_policy_protected && ! $is_public_override );
		if ( $is_public_override && ! $is_manual_private && ! $has_source_lock ) {
			$is_private = false;
		}

		if ( $can_manage_attachment && $is_private && null !== $relative_path ) {
			$prefer_protected_url = $this->attachment_should_prefer_protected_url( $attachment_id, $relative_path );
			$admin_download_url   = $this->build_private_file_url( $attachment_id, $relative_path, $this->attachment_url_should_force_protection( $attachment_id ) );
			$admin_preview_url    = $admin_download_url;
			$response['filename'] = wp_basename( $relative_path );

			if ( $prefer_protected_url ) {
				$response = $this->apply_admin_private_urls_to_attachment_js_response( $response, $attachment_id, $relative_path );
			}
		}

		$response['pgmPrivateMedia'] = array(
			'isPrivate'         => $is_private,
			'isManuallyPrivate' => $is_manual_private,
			'isPublicOverride'  => $is_public_override,
			'isPolicyProtected' => $is_policy_protected,
			'hasBlockSources'   => false,
			'hasFolderSources'  => ! empty( $folder_source_ids ),
			'hasSourceLocks'    => $has_source_lock,
			'sourceCount'       => count( $source_ids ),
			'folderSourceCount' => count( $folder_source_ids ),
			'sources'           => array(),
			'folderSources'     => $can_manage_attachment ? $this->attachment_folder_sources_for_js( $folder_source_ids ) : array(),
			'folderAccess'      => $folder_access,
			'hasPublicCopy'     => $public_exists,
			'hasPrivateCopy'    => $private_exists,
			'canManage'         => $can_manage_attachment,
			'canHide'           => $can_manage_attachment && ! $is_private,
			'canMakePublic'     => $can_manage_attachment && $is_private && ! $has_source_lock,
			'canDetachMakePublic' => $can_manage_attachment && $is_private && $has_source_lock,
			'canUnhide'         => $can_manage_attachment && $is_private && $is_manual_private && ! $has_source_lock,
			'adminDownloadUrl'  => $admin_download_url,
			'adminPreviewUrl'   => $admin_preview_url,
			'preferProtectedUrl' => $prefer_protected_url,
			'originalFilename'  => null !== $relative_path ? wp_basename( $relative_path ) : '',
			'fileExtension'     => null !== $relative_path ? strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) ) : '',
			'fileType'          => $this->attachment_file_type_key( $attachment_id, $relative_path ),
			'label'             => wp_strip_all_tags( $status['label'] ),
			'description'       => wp_strip_all_tags( $status['description'] ),
			'className'         => sanitize_html_class( $status['class'] ),
			'shareLinks'        => $can_manage_attachment ? ( is_array( get_post_meta( $attachment_id, '_pgm_share_links', true ) ) ? get_post_meta( $attachment_id, '_pgm_share_links', true ) : array() ) : array(),
		);

		$this->debug_log(
			'prepare_attachment_for_js',
			array(
				'admin_download_url' => $admin_download_url,
				'prefer_protected_url' => $prefer_protected_url,
				'can_manage' => $can_manage_attachment,
				'status_label' => isset( $status['label'] ) ? wp_strip_all_tags( $status['label'] ) : '',
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		return $response;
	}

	private function apply_admin_private_urls_to_attachment_js_response( $response, $attachment_id, $relative_path ) {
		$force_protected = $this->attachment_url_should_force_protection( $attachment_id );

		if ( empty( $response['sizes'] ) || ! is_array( $response['sizes'] ) ) {
			return $response;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$base_dir = '';
		if ( is_array( $metadata ) && ! empty( $metadata['file'] ) ) {
			$base_dir = dirname( $metadata['file'] );
			$base_dir = '.' === $base_dir ? '' : trailingslashit( $base_dir );
		}

		foreach ( $response['sizes'] as $size_name => $size_data ) {
			$size_path = null;

			if ( 'full' === $size_name ) {
				$size_path = $relative_path;
			} elseif ( is_array( $metadata ) && ! empty( $metadata['sizes'][ $size_name ]['file'] ) ) {
				$size_path = $this->normalize_relative_path( $base_dir . $metadata['sizes'][ $size_name ]['file'] );
			}

			if ( null === $size_path || ! $this->attachment_owns_relative_path( $attachment_id, $size_path ) ) {
				continue;
			}

			$response['sizes'][ $size_name ]['url'] = $this->build_private_file_url( $attachment_id, $size_path, $force_protected );
		}

		return $response;
	}

	/**
	 * Lisää Media Libraryn liitetietoihin statuksen ja käsin asetettavan rastin.
	 */
	public function add_attachment_private_field( $form_fields, $post ) {
		$is_manually_private = (bool) get_post_meta( $post->ID, self::META_PRIVATE_MANUAL, true );
		$status              = $this->attachment_public_visibility_status( $post->ID );
		$source_ids          = array();
		$folder_source_ids   = $this->attachment_folder_source_ids( $post->ID );
		$relative_path       = $this->normalize_relative_path( get_post_meta( $post->ID, '_wp_attached_file', true ) );
		$is_policy_protected = null !== $relative_path && $this->attachment_matches_protected_policy( $post->ID, $relative_path );
		$is_public_override  = $this->attachment_has_public_override( $post->ID );
		$has_source_lock     = ! empty( $folder_source_ids ) || ( $is_policy_protected && ! $is_public_override );

		$status_html = sprintf(
			'<strong>%1$s</strong><p class="description">%2$s</p>',
			esc_html( $status['label'] ),
			esc_html( $status['description'] )
		);

		if ( ! empty( $folder_source_ids ) ) {
			$folder_names = array();
			foreach ( $folder_source_ids as $folder_id ) {
				$term = get_term( (int) $folder_id, PGM_Media_Organizer::TAXONOMY );
				if ( $term && ! is_wp_error( $term ) ) {
					$folder_names[] = esc_html( $term->name );
				}
			}

			if ( ! empty( $folder_names ) ) {
				$status_html .= sprintf(
					'<p class="description">%1$s %2$s</p><p class="description">%3$s</p>',
					esc_html__( 'Piilotus tulee kansiosta:', 'private-gutenberg-media' ),
					implode( ', ', $folder_names ),
					esc_html__( 'Pura piilotus avaamalla Pecodex Media Controlin kansion Ominaisuudet ja muuttamalla näkyvyys julkiseksi.', 'private-gutenberg-media' )
				);
			}
		}

		if ( $is_policy_protected && $is_public_override ) {
			$status_html .= sprintf(
				'<p class="description">%1$s</p>',
				esc_html__( 'Tiedostotyyppisääntö on ohitettu tämän yksittäisen tiedoston osalta.', 'private-gutenberg-media' )
			);
		} elseif ( $is_policy_protected ) {
			$status_html .= sprintf(
				'<p class="description">%1$s</p>',
				esc_html__( 'Piilotus tulee myös lisäosan asetussäännöstä tälle tiedostotyypille.', 'private-gutenberg-media' )
			);
		}

		$form_fields['pgm_private_status'] = array(
			'label' => __( 'Julkisuus', 'private-gutenberg-media' ),
			'input' => 'html',
			'html'  => $status_html,
		);

		if ( $is_manually_private ) {
			$manual_help = __( 'Poista rasti ja tallenna, jos haluat poistaa käsin tehdyn suojauksen. Tiedosto palautuu julkiseksi vain, jos mikään muu lähde ei enää suojaa sitä.', 'private-gutenberg-media' );
		} elseif ( $has_source_lock ) {
			$manual_help = __( 'Tiedosto on jo suojattu kansion tai asetussäännön kautta. Lisää tämä rasti vain, jos haluat pitää tiedoston suojattuna myös sen jälkeen, kun muu suojaus poistetaan.', 'private-gutenberg-media' );
		} else {
			$manual_help = __( 'Lisää rasti, jos haluat suojata juuri tämän tiedoston ja avata sen jatkossa vain suojatun linkin kautta.', 'private-gutenberg-media' );
		}

		$form_fields['pgm_private'] = array(
			'label' => __( 'Käsin tehty suojaus', 'private-gutenberg-media' ),
			'input' => 'html',
			'html'  => sprintf(
				'<label class="pgm-manual-privacy-field"><input type="checkbox" name="attachments[%1$d][pgm_private]" value="1" %2$s /> <span>%3$s</span></label>',
				(int) $post->ID,
				checked( $is_manually_private, true, false ),
				esc_html__( 'Pidä tämä tiedosto suojattuna käsin', 'private-gutenberg-media' )
			),
			'helps' => $manual_help,
		);

		return $form_fields;
	}

	/**
	 * Tallentaa Media Libraryn yksityinen media -rastin.
	 *
	 * Kun rasti laitetaan päälle Media Libraryssa, tiedosto siirretään pois
	 * julkisesta uploads-kansiosta riippumatta yleisestä yhteensopivuusasetuksesta.
	 * Kun rasti poistetaan, mahdollisesti aiemmin siirretty tiedosto palautetaan.
	 */
	public function save_attachment_private_field( $post, $attachment ) {
		$post_id = isset( $post['ID'] ) ? absint( $post['ID'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return $post;
		}

		if ( ! empty( $attachment['pgm_private'] ) ) {
			delete_post_meta( $post_id, self::META_PUBLIC_OVERRIDE );
			update_post_meta( $post_id, self::META_PRIVATE_MANUAL, '1' );
			update_post_meta( $post_id, self::META_PRIVATE, '1' );
			if ( $this->is_enabled() ) {
				$this->sync_attachment_storage_for_current_mode( $post_id );
			}
		} else {
			if (
				! $this->attachment_has_folder_sources( $post_id )
				&& PGM_Media_Organizer::ACCESS_PUBLIC === $this->attachment_effective_folder_access( $post_id )
			) {
				$this->make_attachment_public( $post_id );
			} else {
				delete_post_meta( $post_id, self::META_PRIVATE_MANUAL );
				$this->sync_attachment_storage_for_current_mode( $post_id );
			}
		}

		$this->mark_origin_cache_clear_response();
		$this->flush_queued_public_upload_protection_rules_refresh();

		return $post;
	}

	public function add_attachment_roles_field( $form_fields, $post ) {
		// Hae kaikki saatavilla olevat roolit
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		
		$all_roles = $wp_roles->roles;
		$current_roles = get_post_meta( $post->ID, '_pgm_visibility_roles', true );
		$current_roles = is_array( $current_roles ) ? $current_roles : array();

		$html = '<div class="pgm-roles-checklist" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 5px; background: #fff;">';
		foreach ( $all_roles as $role_id => $role_details ) {
			$checked = in_array( $role_id, $current_roles, true ) ? 'checked="checked"' : '';
			$html .= sprintf(
				'<label style="display:block; margin-bottom:4px;"><input type="checkbox" name="attachments[%1$d][pgm_visibility_roles][]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $post->ID ),
				esc_attr( $role_id ),
				$checked,
				esc_html( translate_user_role( $role_details['name'] ) )
			);
		}
		// Piilotettu kenttä
		$html .= sprintf( '<input type="hidden" name="attachments[%1$d][pgm_visibility_roles_present]" value="1" />', esc_attr( $post->ID ) );
		$html .= '</div>';

		$form_fields['pgm_visibility_roles'] = array(
			'label' => __( 'Sallitut Roolit (Yksityinen)', 'private-gutenberg-media' ),
			'input' => 'html',
			'html'  => $html,
			'helps' => __( 'Jos valitset rooleja, VAIKUTTAA VAIN jos tiedosto on Yksityinen.', 'private-gutenberg-media' )
		);

		return $form_fields;
	}

	public function save_attachment_roles_field( $post, $attachment ) {
		if ( isset( $attachment['pgm_visibility_roles_present'] ) ) {
			$roles = isset( $attachment['pgm_visibility_roles'] ) ? $attachment['pgm_visibility_roles'] : array();
			$roles = array_map( 'sanitize_text_field', (array) $roles );
			update_post_meta( $post['ID'], '_pgm_visibility_roles', $roles );
		}
		return $post;
	}

	/**
	 * Päivittää mediatiedoston tiedostotason suojauksen adminin AJAX-pyynnöstä.
	 */
	public function handle_toggle_attachment_privacy_ajax() {
		check_ajax_referer( 'pgm_toggle_attachment_privacy', 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$intent        = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : '';

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Sinulla ei ole oikeutta muokata tätä mediaa.', 'private-gutenberg-media' ),
				),
				403
			);
		}

		if ( ! $this->is_enabled() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Lisäosan suojaus ei ole käytössä.', 'private-gutenberg-media' ),
				),
				400
			);
		}

		$lock_key = 'pgm_toggle_lock_' . $attachment_id;
		if ( false !== get_transient( $lock_key ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Toimintoa käsitellöyn jo, odota hetki.', 'private-gutenberg-media' ),
				),
				429
			);
		}
		set_transient( $lock_key, '1', 15 );

		$send_error = function( $message, $status = 400 ) use ( $lock_key ) {
			delete_transient( $lock_key );
			wp_send_json_error( array( 'message' => $message ), $status );
		};

		$cache_clear_urls = $this->attachment_public_url_cache_candidates( $attachment_id );

		$this->debug_log(
			'attachment_privacy_toggle_start',
			array(
				'attachment_id' => $attachment_id,
				'intent'        => $intent,
			) + $this->debug_attachment_state( $attachment_id )
		);

		if ( 'hide' === $intent ) {
			$was_source_locked = $this->attachment_has_folder_sources( $attachment_id )
				|| PGM_Media_Organizer::ACCESS_PUBLIC !== $this->attachment_effective_folder_access( $attachment_id );
			delete_post_meta( $attachment_id, self::META_PUBLIC_OVERRIDE );
			update_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, '1' );
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			$result  = $this->sync_attachment_storage_for_current_mode( $attachment_id );
			$message = $was_source_locked
				? __( 'Käsin tehty suojaus lisättiin. Media pysyy suojattuna myös, jos lähdesuojaus myöhemmin poistetaan.', 'private-gutenberg-media' )
				: __( 'Media suojattiin julkisilta.', 'private-gutenberg-media' );
		} elseif ( 'make_public' === $intent ) {
			$has_source_lock = $this->attachment_has_folder_sources( $attachment_id )
				|| PGM_Media_Organizer::ACCESS_PUBLIC !== $this->attachment_effective_folder_access( $attachment_id );

			if ( $has_source_lock ) {
				$send_error( __( 'Tiedosto on suojattu kansiosta. Avaa kansio ja poista kansion suojaus tai siirrä tiedosto julkiseen kansioon.', 'private-gutenberg-media' ), 400 );
			}

			$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
			$is_policy_protected = null !== $relative_path && $this->attachment_matches_protected_policy( $attachment_id, $relative_path );

			$result = $this->make_attachment_public( $attachment_id );
			$message = $is_policy_protected
				? __( 'Media asetettiin julkiseksi. Asetussääntö ohitetaan tämän tiedoston osalta.', 'private-gutenberg-media' )
				: __( 'Media palautettiin julkiseksi.', 'private-gutenberg-media' );
		} elseif ( 'detach_make_public' === $intent ) {
			$previous_terms = $this->detach_attachment_from_media_folders( $attachment_id );

			if ( is_wp_error( $previous_terms ) ) {
				$send_error( $previous_terms->get_error_message(), 500 );
			}

			$result = $this->make_attachment_public( $attachment_id );
			$message = ! empty( $previous_terms )
				? __( 'Media irrotettiin suojatusta kansiosta ja palautettiin julkiseksi.', 'private-gutenberg-media' )
				: __( 'Media palautettiin julkiseksi.', 'private-gutenberg-media' );
		} elseif ( 'unhide' === $intent ) {
			if (
				! $this->attachment_has_folder_sources( $attachment_id )
				&& PGM_Media_Organizer::ACCESS_PUBLIC === $this->attachment_effective_folder_access( $attachment_id )
			) {
				$result = $this->make_attachment_public( $attachment_id );
				$message = __( 'Suojaus poistettiin ja media palautettiin julkiseksi.', 'private-gutenberg-media' );
			} else {
				delete_post_meta( $attachment_id, self::META_PRIVATE_MANUAL );
				$result = $this->sync_attachment_storage_for_current_mode( $attachment_id );
				$message = __( 'Käsin asetettu suojaus poistettiin. Kansio pitää median edelleen suojattuna.', 'private-gutenberg-media' );
			}
		} else {
			$send_error( __( 'Tuntematon toiminto.', 'private-gutenberg-media' ), 400 );
		}

		if ( is_wp_error( $result ) ) {
			$send_error( $result->get_error_message(), 500 );
		}

		$this->mark_origin_cache_clear_response();
		$this->flush_queued_public_upload_protection_rules_refresh();
		$cache_clear_urls = array_values(
			array_unique(
				array_merge(
					$cache_clear_urls,
					$this->attachment_public_url_cache_candidates( $attachment_id )
				)
			)
		);
		$prepared_attachment = wp_prepare_attachment_for_js( $attachment_id );

		$this->debug_log(
			'attachment_privacy_toggle_result',
			array(
				'attachment_id' => $attachment_id,
				'intent'        => $intent,
				'message'       => $message,
			) + $this->debug_attachment_state( $attachment_id )
		);

		$state_payload = class_exists( 'PGM_Media_Organizer' ) ? PGM_Media_Organizer::instance()->state_payload( 0 ) : array();

		delete_transient( $lock_key );

		wp_send_json_success(
			array_merge(
				$state_payload,
				array(
					'message'        => $message,
					'attachment'     => $prepared_attachment,
					'cacheClearUrls' => $cache_clear_urls,
				)
			)
		);
	}

	public function handle_bulk_toggle_attachment_privacy_ajax() {
		check_ajax_referer( 'pgm_toggle_attachment_privacy', 'nonce' );

		$intent  = isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : '';
		$raw_ids = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : array();

		if ( is_string( $raw_ids ) ) {
			$raw_ids = preg_split( '/[\s,]+/', $raw_ids );
		}

		$attachment_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw_ids ) ) ) );

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Valitse ensin mediatiedostoja.', 'private-gutenberg-media' ) ), 400 );
		}

		if ( ! $this->is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Lisäosan suojaus ei ole käytössä.', 'private-gutenberg-media' ) ), 400 );
		}

		$updated = 0;
		$skipped = 0;
		$errors  = array();
		$cache_clear_urls = array();
		$prepared_attachments = array();

		foreach ( $attachment_ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				$skipped++;
				$errors[] = sprintf( 'ID %d: ei oikeutta muokata tiedostoa.', (int) $attachment_id );
				continue;
			}

			$cache_clear_urls = array_merge( $cache_clear_urls, $this->attachment_public_url_cache_candidates( $attachment_id ) );
			$result = $this->apply_bulk_attachment_privacy_intent( $attachment_id, $intent );
			if ( is_wp_error( $result ) ) {
				$skipped++;
				$errors[] = sprintf( 'ID %1$d: %2$s', (int) $attachment_id, $result->get_error_message() );
				continue;
			}

			$cache_clear_urls = array_merge( $cache_clear_urls, $this->attachment_public_url_cache_candidates( $attachment_id ) );
			$prepared_attachment = wp_prepare_attachment_for_js( $attachment_id );
			if ( $prepared_attachment ) {
				$prepared_attachments[ $attachment_id ] = $prepared_attachment;
			}
			$updated++;
		}

		if ( ! $updated ) {
			wp_send_json_error(
				array(
					'message' => ! empty( $errors ) ? reset( $errors ) : __( 'Yhtä tiedostoa ei voitu päivittää.', 'private-gutenberg-media' ),
					'errors'  => $errors,
				),
				400
			);
		}

		if ( 'hide' === $intent ) {
			$message = sprintf(
				_n( '%d tiedosto suojattiin.', '%d tiedostoa suojattiin.', $updated, 'private-gutenberg-media' ),
				$updated
			);
		} else {
			$message = sprintf(
				_n( '%d tiedosto palautettiin julkiseksi.', '%d tiedostoa palautettiin julkiseksi.', $updated, 'private-gutenberg-media' ),
				$updated
			);
		}

		if ( $skipped ) {
			$message .= ' ' . sprintf(
				_n( '%d tiedosto ohitettiin.', '%d tiedostoa ohitettiin.', $skipped, 'private-gutenberg-media' ),
				$skipped
			);
		}

		$this->mark_origin_cache_clear_response();
		$this->flush_queued_public_upload_protection_rules_refresh();

		$state_payload = class_exists( 'PGM_Media_Organizer' ) ? PGM_Media_Organizer::instance()->state_payload( 0 ) : array();

		wp_send_json_success(
			array_merge(
				$state_payload,
				array(
					'message'        => $message,
					'updated'        => $updated,
					'skipped'        => $skipped,
					'errors'         => $errors,
					'attachments'    => $prepared_attachments,
					'cacheClearUrls' => array_values( array_unique( array_filter( $cache_clear_urls ) ) ),
				)
			)
		);
	}

	public function cleanup_private_storage_on_delete( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		// WordPress natively deletes files returned by get_attached_file() (which we filter to private storage),
		// but if metadata was corrupted, it might miss some thumbnails.
		// We use our stored META_PRIVATE_STORAGE_PATHS to guarantee all known private files are deleted.
		$stored_paths = get_post_meta( $attachment_id, self::META_PRIVATE_STORAGE_PATHS, true );
		if ( is_array( $stored_paths ) ) {
			foreach ( $stored_paths as $relative_path ) {
				$private_file = $this->private_file_path_if_exists( $relative_path );
				if ( $private_file ) {
					wp_delete_file( $private_file );
				}
			}
		}

		// Also clean up our custom privacy metadata so it doesn't pollute the database
		$this->delete_attachment_privacy_meta( $attachment_id );
	}

	public function handle_bulk_delete_attachments_ajax() {
		check_ajax_referer( 'pgm_delete_attachments', 'nonce' );

		$raw_ids = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : array();

		if ( is_string( $raw_ids ) ) {
			$raw_ids = preg_split( '/[\s,]+/', $raw_ids );
		}

		$attachment_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw_ids ) ) ) );

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Valitse ensin mediatiedostoja.', 'private-gutenberg-media' ) ), 400 );
		}

		$deleted = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $attachment_ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'delete_post', $attachment_id ) ) {
				$skipped++;
				$errors[] = sprintf( 'ID %d: ei oikeutta poistaa tiedostoa.', (int) $attachment_id );
				continue;
			}

			$relative_paths = $this->attachment_relative_paths( $attachment_id );
			$result         = wp_delete_attachment( $attachment_id, true );

			if ( ! $result ) {
				$skipped++;
				$errors[] = sprintf( 'ID %d: poistaminen epäonnistui.', (int) $attachment_id );
				continue;
			}

			foreach ( $relative_paths as $relative_path ) {
				$this->delete_public_converted_upload_copies( $relative_path );

				$private_path = $this->private_file_path_if_exists( $relative_path );
				if ( $private_path ) {
					wp_delete_file( $private_path );
				}
			}

			$deleted++;
		}

		if ( ! $deleted ) {
			wp_send_json_error(
				array(
					'message' => ! empty( $errors ) ? reset( $errors ) : __( 'Yhtä tiedostoa ei voitu poistaa.', 'private-gutenberg-media' ),
					'errors'  => $errors,
				),
				400
			);
		}

		$message = sprintf(
			_n( '%d tiedosto poistettiin pysyvästi.', '%d tiedostoa poistettiin pysyvästi.', $deleted, 'private-gutenberg-media' ),
			$deleted
		);

		if ( $skipped ) {
			$message .= ' ' . sprintf(
				_n( '%d tiedosto ohitettiin.', '%d tiedostoa ohitettiin.', $skipped, 'private-gutenberg-media' ),
				$skipped
			);
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'deleted' => $deleted,
				'skipped' => $skipped,
				'errors'  => $errors,
			)
		);
	}

	private function apply_bulk_attachment_privacy_intent( $attachment_id, $intent ) {
		if ( 'hide' === $intent ) {
			delete_post_meta( $attachment_id, self::META_PUBLIC_OVERRIDE );
			update_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, '1' );
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );

			return $this->sync_attachment_storage_for_current_mode( $attachment_id );
		}

		if ( 'make_public' === $intent ) {
			$has_source_lock = $this->attachment_has_folder_sources( $attachment_id )
				|| PGM_Media_Organizer::ACCESS_PUBLIC !== $this->attachment_effective_folder_access( $attachment_id );

			if ( $has_source_lock ) {
				return new WP_Error( 'pgm_source_locked', __( 'Tiedosto on suojattu kansiosta.', 'private-gutenberg-media' ) );
			}

			return $this->make_attachment_public( $attachment_id );
		}

		return new WP_Error( 'pgm_unknown_privacy_intent', __( 'Tuntematon toiminto.', 'private-gutenberg-media' ) );
	}

	private function attachment_public_visibility_status( $attachment_id ) {
		$is_private_meta = (bool) get_post_meta( $attachment_id, self::META_PRIVATE, true );
		$is_public_override = $this->attachment_has_public_override( $attachment_id );
		$public_exists  = $this->attachment_has_public_upload_copy( $attachment_id );
		$private_exists = $this->attachment_has_private_storage_copy( $attachment_id );
		$folder_access  = $this->attachment_effective_folder_access( $attachment_id );
		$has_folder_sources = $this->attachment_has_folder_sources( $attachment_id );
		$has_block_sources = false;
		$has_protected_content_links = $this->attachment_has_protected_content_links( $attachment_id );
		$is_manual_private = (bool) get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true );
		$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$is_policy_protected = null !== $relative_path && $this->attachment_matches_protected_policy( $attachment_id, $relative_path );
		$has_source_lock = $has_folder_sources || PGM_Media_Organizer::ACCESS_PUBLIC !== $folder_access;
		$is_policy_only = $is_policy_protected && ! $is_manual_private && ! $has_block_sources && ! $has_folder_sources && ! $has_protected_content_links && PGM_Media_Organizer::ACCESS_PUBLIC === $folder_access;
		$is_private = $is_private_meta || PGM_Media_Organizer::ACCESS_PUBLIC !== $folder_access || ( $is_policy_protected && ! $is_public_override );
		$expects_private_storage = $this->should_move_files_to_private_storage() || $this->attachment_requires_private_storage( $attachment_id );

		if ( $is_public_override && ! $is_manual_private && ! $has_source_lock ) {
			$is_private = false;
		}

		if ( ! $is_private && $is_public_override ) {
			return array(
				'label'       => __( 'Julkinen poikkeus', 'private-gutenberg-media' ),
				'description' => __( 'Tiedosto on asetettu julkiseksi, vaikka lisäosan asetussääntö suojaisi tämän tiedostotyypin.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-public',
			);
		}

		if ( $is_private && $is_policy_only && $this->attachment_is_hidden_from_public_uploads( $attachment_id ) ) {
			return array(
				'label'       => __( 'Asetuksella suojattu', 'private-gutenberg-media' ),
				'description' => __( 'Tiedosto suojataan lisäosan asetussäännöllä ja avataan vain suojatun linkin kautta.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-hidden',
			);
		}

		if ( $is_private && $is_policy_only && $public_exists && $this->should_move_files_to_private_storage() ) {
			return array(
				'label'       => __( 'Asetussääntö', 'private-gutenberg-media' ),
				'description' => __( 'Tämä tiedosto oli aiemmin julkinen ja osuu nyt lisäosan tiedostotyyppisääntöön. Poista suojaus jättää sen julkiseksi poikkeuksena; suojausasetusten synkronointi voi piilottaa uploads-kopion.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-policy-pending',
			);
		}

		if ( $is_private && $is_policy_only && $public_exists ) {
			return array(
				'label'       => __( 'Asetuksella suojattu', 'private-gutenberg-media' ),
				'description' => __( 'Tiedosto suojataan lisäosan asetussäännöllä. Koska tiedostojen siirto ei ole käytössä, alkuperäinen uploads-osoite jää teknisesti julkiseksi.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-link-only',
			);
		}

		if ( ! $is_private ) {
			if ( $is_public_override ) {
				return array(
					'label'       => __( 'Julkinen poikkeus', 'private-gutenberg-media' ),
					'description' => __( 'Tiedosto on asetettu julkiseksi, vaikka lisäosan asetussääntö suojaisi tämän tiedostotyypin.', 'private-gutenberg-media' ),
					'class'       => 'pgm-status-public',
				);
			}

			return array(
				'label'       => __( 'Julkinen', 'private-gutenberg-media' ),
				'description' => __( 'Tiedostoa ei ole merkitty yksityiseksi tässä lisäosassa.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-public',
			);
		}

		if ( $has_folder_sources && $this->attachment_is_hidden_from_public_uploads( $attachment_id ) ) {
			return array(
				'label'       => __( 'Kansion suojaama', 'private-gutenberg-media' ),
				'description' => PGM_Media_Organizer::ACCESS_ADMIN_ONLY === $folder_access
					? __( 'Kansio sallii tämän tiedoston vain ylläpitäjille. Tiedosto tarjoillaan suojatun endpointin kautta.', 'private-gutenberg-media' )
					: __( 'Kansio sallii tämän tiedoston vain kirjautuneille. Tiedosto tarjoillaan suojatun endpointin kautta.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-hidden',
			);
		}

		if ( $this->attachment_is_hidden_from_public_uploads( $attachment_id ) ) {
			return array(
				'label'       => __( 'Piilotettu julkisilta', 'private-gutenberg-media' ),
				'description' => __( 'Uploads-kopiot on poistettu ja tiedosto tarjoillaan vain suojatun endpointin kautta.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-hidden',
			);
		}

		if ( $has_protected_content_links && $public_exists && $expects_private_storage ) {
			return array(
				'label'       => __( 'Suojattu linkki odottaa siirtoa', 'private-gutenberg-media' ),
				'description' => __( 'Sisällössä on jo suojattu Pecodex-linkki, mutta vanha uploads-kopio löytyy vielä. Synkronointi siirtää tiedoston pois julkisesta uploads-kansiosta.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-warning',
			);
		}

		if ( $public_exists && $expects_private_storage ) {
			return array(
				'label'       => __( 'Siirtoa odottaa', 'private-gutenberg-media' ),
				'description' => __( 'Tiedosto on merkitty yksityiseksi, mutta julkinen uploads-kopio löytyy vielä. Tallenna media tai sivu uudelleen, jos automaattinen siirto ei ole vielä ehtinyt tapahtua.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-warning',
			);
		}

		if ( $public_exists ) {
			return array(
				'label'       => __( 'Suojattu', 'private-gutenberg-media' ),
				'description' => __( 'Tiedosto on suojattu tiedostotasolla. Suora uploads-osoite ohjataan kirjautumiseen ja tiedosto avataan vain suojatun endpointin kautta.', 'private-gutenberg-media' ),
				'class'       => 'pgm-status-hidden',
			);
		}

		return array(
			'label'       => __( 'Tarkista tiedosto', 'private-gutenberg-media' ),
			'description' => __( 'Media on merkitty yksityiseksi, mutta tiedostoa ei löytynyt uploads-kansiosta eikä yksityisestä varastosta.', 'private-gutenberg-media' ),
			'class'       => 'pgm-status-missing',
		);
	}

	private function attachment_has_public_upload_copy( $attachment_id ) {
		if ( isset( $this->attachment_public_copy_cache[ $attachment_id ] ) ) {
			return $this->attachment_public_copy_cache[ $attachment_id ];
		}

		$has_copy = false;
		foreach ( $this->attachment_relative_paths( $attachment_id ) as $relative_path ) {
			if ( $this->public_upload_file_path_if_exists( $relative_path ) || $this->public_converted_upload_copy_exists( $relative_path ) ) {
				$has_copy = true;
				break;
			}
		}

		$this->attachment_public_copy_cache[ $attachment_id ] = $has_copy;
		return $has_copy;
	}

	private function attachment_primary_relative_path( $attachment_id ) {
		return $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}

	private function attachment_has_primary_public_upload_copy( $attachment_id ) {
		$relative_path = $this->attachment_primary_relative_path( $attachment_id );

		return null !== $relative_path && (bool) $this->public_upload_file_path_if_exists( $relative_path );
	}

	private function attachment_is_hidden_from_public_uploads( $attachment_id ) {
		$primary_path       = $this->attachment_primary_relative_path( $attachment_id );
		$has_public_primary = null !== $primary_path
			? (bool) $this->public_upload_file_path_if_exists( $primary_path )
			: $this->attachment_has_public_upload_copy( $attachment_id );

		return (bool) get_post_meta( $attachment_id, self::META_PRIVATE, true )
			&& ! $has_public_primary
			&& $this->attachment_has_private_storage_copy( $attachment_id );
	}

	/**
	 * Palauttaa true, kun WordPressin generoima media-URL kannattaa näyttäää
	 * suojattuna endpoint-linkkinä. Tietokannan canonical-polku säilyy silti
	 * alkuperäisenä _wp_attached_file-arvona, jotta unlock ja migraatiot pysyvät
	 * korjattavina.
	 */
	private function attachment_should_prefer_protected_url( $attachment_id, $relative_path = null ) {
		$this->debug_log(
			'prefer_protected_url_state',
			$this->debug_attachment_state( $attachment_id, $relative_path )
		);

		if ( ! $this->is_enabled() || ! $attachment_id ) {
			return false;
		}

		if ( ! get_post_meta( $attachment_id, self::META_PRIVATE, true ) ) {
			return false;
		}

		if ( null !== $relative_path && ! $this->attachment_owns_relative_path( $attachment_id, $relative_path ) ) {
			return false;
		}

		if (
			$this->attachment_has_public_override( $attachment_id )
			&& ! get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true )
			&& ! $this->attachment_has_folder_sources( $attachment_id )
		) {
			return false;
		}

		if ( get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true ) ) {
			return true;
		}

		if ( $this->attachment_has_folder_sources( $attachment_id ) ) {
			return true;
		}

		if ( $this->attachment_is_hidden_from_public_uploads( $attachment_id ) ) {
			return true;
		}

		if ( $this->should_move_files_to_private_storage() ) {
			return true;
		}

		// Automaattisesti tiedostopäätteen tai all-tilan kautta merkitty media on
		// tiedostotason yksityinen samalla tavalla kuin käsin suojattu media.
		return true;
	}

	private function attachment_url_should_force_protection( $attachment_id ) {
		return $this->should_move_files_to_private_storage()
			|| $this->attachment_requires_private_storage( $attachment_id )
			|| $this->attachment_is_hidden_from_public_uploads( $attachment_id );
	}

	private function attachment_requires_private_storage( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		if ( get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true ) ) {
			return true;
		}

		if ( $this->attachment_has_folder_sources( $attachment_id ) ) {
			return true;
		}

		if ( $this->should_move_files_to_private_storage() && $this->attachment_has_protected_content_links( $attachment_id ) ) {
			return true;
		}

		return PGM_Media_Organizer::ACCESS_PUBLIC !== $this->attachment_effective_folder_access( $attachment_id );
	}

	private function attachment_has_private_storage_copy( $attachment_id ) {
		if ( isset( $this->attachment_private_copy_cache[ $attachment_id ] ) ) {
			return $this->attachment_private_copy_cache[ $attachment_id ];
		}

		$has_copy = false;
		foreach ( $this->attachment_relative_paths( $attachment_id ) as $relative_path ) {
			if ( $this->private_file_path_if_exists( $relative_path ) ) {
				$has_copy = true;
				break;
			}
		}

		$this->attachment_private_copy_cache[ $attachment_id ] = $has_copy;
		return $has_copy;
	}

	private function clear_attachment_storage_cache( $attachment_id ) {
		unset( $this->attachment_public_copy_cache[ $attachment_id ] );
		unset( $this->attachment_private_copy_cache[ $attachment_id ] );
	}

	/**
	 * Renderöintivaiheessa Gutenberg-lohkojen media-URLit päivitetään suojatuiksi,
	 * jos itse mediatiedosto on suojattu Media Libraryn, kansion tai asetuksen kautta.
	 */
	public function rewrite_private_block_upload_urls( $block_content, $block ) {
		if ( ! $this->is_enabled() ) {
			return $block_content;
		}

		if ( isset( $block['blockName'] ) && 'core/file' === $block['blockName'] ) {
			$block_content = $this->strip_core_file_preview_embed( $block_content );
		}

		return $this->rewrite_content_upload_urls( $block_content, false, 'block_public' );
	}

	public function start_frontend_private_media_output_buffer() {
		if ( ! $this->is_enabled() || is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return;
		}

		ob_start( array( $this, 'rewrite_frontend_private_media_output' ) );
	}

	public function rewrite_frontend_private_media_output( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$has_private_media = false !== strpos( $html, '/wp-content/uploads/' )
			|| false !== strpos( $html, 'wp-content/uploads/' )
			|| $this->has_private_media_action_token( $html )
			|| $this->has_legacy_private_link_action_token( $html );

		if ( ! $has_private_media ) {
			return $html;
		}

		$html = $this->hide_logged_out_private_embed_urls( $html );
		$html = $this->hide_logged_out_private_file_links( $html );

		return $this->rewrite_content_upload_urls( $html, false, 'frontend_output' );
	}

	private function hide_logged_out_private_file_links( $html ) {
		if ( is_user_logged_in() || ! is_string( $html ) ) {
			return $html;
		}

		$updated = preg_replace_callback(
			'/<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>.*?<\/a>/is',
			function ( $matches ) {
				$url = html_entity_decode( $matches[2], ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( ! $this->url_points_to_protected_media( $url, 'frontend_output_link' ) ) {
					return $matches[0];
				}

				return '';
			},
			$html
		);

		return is_string( $updated ) ? $updated : $html;
	}

	private function hide_logged_out_private_embed_urls( $html ) {
		if ( is_user_logged_in() || ! is_string( $html ) || ! $this->has_private_media_action_token( $html ) ) {
			return $html;
		}

		$updated = preg_replace_callback(
			'/<(img|source|video|audio)\b[^>]*>/i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( ! $this->has_private_media_action_token( $tag ) ) {
					return $tag;
				}

				$hidden_media = false;
				foreach ( array( 'src', 'poster' ) as $attribute ) {
					$tag = $this->replace_private_embed_attribute_with_placeholder( $tag, $attribute, $hidden_media );
				}

				$tag = $this->remove_private_embed_srcset_attribute( $tag, $hidden_media );
				if ( $hidden_media ) {
					$tag = preg_replace( '/\s+sizes=(["\']).*?\1/i', '', $tag );
				}

				return is_string( $tag ) ? $tag : $matches[0];
			},
			$html
		);

		return is_string( $updated ) ? $updated : $html;
	}

	private function replace_private_embed_attribute_with_placeholder( $tag, $attribute, &$hidden_media ) {
		$updated = preg_replace_callback(
			'/\s+' . preg_quote( $attribute, '/' ) . '=(["\'])(.*?)\1/i',
			function ( $matches ) use ( $attribute, &$hidden_media ) {
				$value = html_entity_decode( $matches[2], ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( null === $this->private_media_url_to_upload_relative_path( $value ) ) {
					return $matches[0];
				}

				$hidden_media = true;

				return ' ' . $attribute . '=' . $matches[1] . esc_attr( $this->private_media_placeholder_url() ) . $matches[1];
			},
			$tag
		);

		return is_string( $updated ) ? $updated : $tag;
	}

	private function remove_private_embed_srcset_attribute( $tag, &$hidden_media ) {
		$updated = preg_replace_callback(
			'/\s+srcset=(["\'])(.*?)\1/i',
			function ( $matches ) use ( &$hidden_media ) {
				$value = html_entity_decode( $matches[2], ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( false === strpos( $value, 'pecodex_private_media' ) && false === strpos( $value, 'pgm_private_media' ) ) {
					return $matches[0];
				}

				$items = array();
				foreach ( explode( ',', $value ) as $item ) {
					if ( ! preg_match( '/^(\s*)(\S+)(.*)$/', $item, $parts ) ) {
						continue;
					}

					if ( null !== $this->private_media_url_to_upload_relative_path( $parts[2] ) ) {
						$hidden_media = true;
						continue;
					}

					$items[] = $parts[1] . $parts[2] . $parts[3];
				}

				if ( empty( $items ) ) {
					return '';
				}

				return ' srcset=' . $matches[1] . esc_attr( implode( ', ', $items ) ) . $matches[1];
			},
			$tag
		);

		return is_string( $updated ) ? $updated : $tag;
	}

	private function strip_core_file_preview_embed( $block_content ) {
		if ( ! is_string( $block_content ) || false === strpos( $block_content, 'wp-block-file__embed' ) ) {
			return $block_content;
		}

		$updated = preg_replace(
			'#\s*<object\b(?=[^>]*\bwp-block-file__embed\b)[^>]*>.*?</object>\s*#is',
			'',
			$block_content
		);

		return is_string( $updated ) ? $updated : $block_content;
	}

	/**
	 * Muuntaa sisällössä olevat suojatut mediatiedostot endpoint-URLeiksi.
	 */
	/**
	 * Ohjaa vanhat suorat uploads-linkit suojatun latausportin kautta, jos tiedosto
	 * on jo piilotettu pois julkisesta uploads-kansiosta.
	 *
	 * Ilman .htaccess-muutoksia WordPress näkee tällaisen pyynnön vain silloin,
	 * kun staattista tiedostoa ei enää ole. Se on meille hyvä hetki muuttaa vanha
	 * kirjanmerkki tai sähköpostissa oleva public-URL selkeäksi kirjautumisflowksi
	 * 404-sivun sijaan.
	 */
	public function redirect_legacy_hidden_upload_request() {
		if ( ! $this->is_enabled() || is_admin() || wp_doing_ajax() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_uri = preg_replace( '/\.pecodex_protected$/i', '', $request_uri );
		
		if ( '' === $request_uri || false === strpos( $request_uri, 'wp-content/uploads' ) ) {
			return;
		}

		$this->debug_log(
			'legacy_upload_request_enter',
			array(
				'request_uri' => $request_uri,
			)
		);

		$relative_path = $this->url_to_upload_relative_path( home_url( $request_uri ) );
		if ( null === $relative_path ) {
			$this->debug_log(
				'legacy_upload_request_decision',
				array(
					'decision' => 'not_upload_baseurl',
					'request_uri' => $request_uri,
				)
			);
			return;
		}

		$requested_relative_path = $relative_path;
		$attachment_id           = $this->find_attachment_for_relative_path( $relative_path );
		$legacy_alias            = false;
		if ( ! $attachment_id ) {
			$legacy_match = $this->find_attachment_for_legacy_upload_alias( $relative_path );
			if ( $legacy_match ) {
				$attachment_id = (int) $legacy_match['attachment_id'];
				$relative_path = (string) $legacy_match['relative_path'];
				$legacy_alias  = true;
			}
		}

		$protected     = $attachment_id ? $this->is_protected_upload( $attachment_id, $relative_path, 'legacy_upload' ) : false;
		if ( ! $attachment_id || ! $protected ) {
			$this->debug_log(
				'legacy_upload_request_decision',
				array(
					'decision' => 'no_redirect',
					'reason' => ! $attachment_id ? 'attachment_not_found' : 'not_protected_upload',
					'requested_relative_path' => $requested_relative_path,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			return;
		}

		$this->mark_private_response_uncacheable();
		nocache_headers();

		$this->debug_log(
			'legacy_upload_request_decision',
			array(
				'decision' => 'direct_upload_blocked',
				'legacy_alias' => $legacy_alias,
				'requested_relative_path' => $requested_relative_path,
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		$login_url = $this->build_login_start_url( $attachment_id, $requested_relative_path );
		wp_safe_redirect( $login_url );
		exit;
	}

	public function rewrite_content_upload_urls( $content, $force_protected = false, $context = 'global' ) {
		if ( ! $this->is_enabled() ) {
			return $content;
		}

		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$has_rewrite_marker = false !== strpos( $content, '/wp-content/uploads/' )
			|| false !== strpos( $content, 'wp-content/uploads/' )
			|| $this->has_private_media_action_token( $content )
			|| $this->has_legacy_private_link_action_token( $content );

		if ( ! $force_protected && ! $has_rewrite_marker ) {
			return $content;
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $this->rewrite_content_with_html_api( $content, $force_protected, $context );
		}

		return $this->rewrite_content_with_fallback_regex( $content, $force_protected, $context );
	}

	private function rewrite_content_with_html_api( $content, $force_protected = false, $context = 'global' ) {
		$processor = new WP_HTML_Tag_Processor( $content );

		while ( $processor->next_tag() ) {
			$private_href_rewritten = false;
			$private_media_hidden   = false;

			foreach ( array( 'href', 'src', 'poster' ) as $attribute ) {
				$value = $processor->get_attribute( $attribute );
				if ( is_string( $value ) && '' !== $value ) {
					$rewritten = $this->rewrite_single_upload_url( $value, $attribute, $force_protected, $context );
					if ( $rewritten !== $value ) {
						$processor->set_attribute( $attribute, $rewritten );

						if ( 'href' === $attribute && false !== strpos( $rewritten, 'pgm_private_' ) ) {
							$private_href_rewritten = true;
						}
						if ( in_array( $attribute, array( 'src', 'poster' ), true ) && $this->is_private_media_placeholder_url( $rewritten ) ) {
							$private_media_hidden = true;
						}
					}
				}
			}

			if ( $private_href_rewritten && method_exists( $processor, 'remove_attribute' ) ) {
				// Gutenbergin File-lohko voi lisätä linkkiin download-attribuutin.
				// Pecodexin suojattu medialinkki avataan suojatun endpointin kautta, joten
				// poistetaan selainta lataukseen ohjaava attribuutti.
				$processor->remove_attribute( 'download' );
			}

			if ( $private_media_hidden && method_exists( $processor, 'remove_attribute' ) ) {
				$processor->remove_attribute( 'srcset' );
				$processor->remove_attribute( 'sizes' );
				continue;
			}

			$srcset = $processor->get_attribute( 'srcset' );
			if ( is_string( $srcset ) && '' !== $srcset ) {
				$rewritten_srcset = $this->rewrite_srcset( $srcset, $force_protected, $context );
				if ( $rewritten_srcset !== $srcset ) {
					if ( '' === trim( $rewritten_srcset ) && method_exists( $processor, 'remove_attribute' ) ) {
						$processor->remove_attribute( 'srcset' );
						$processor->remove_attribute( 'sizes' );
					} else {
						$processor->set_attribute( 'srcset', $rewritten_srcset );
					}
				}
			}
		}

		return $processor->get_updated_html();
	}

	private function rewrite_content_with_fallback_regex( $content, $force_protected = false, $context = 'global' ) {
		return preg_replace_callback(
			'/\b(href|src|poster|srcset)=([\'"])(.*?)\2/i',
			function ( $matches ) use ( $force_protected, $context ) {
				$attribute = strtolower( $matches[1] );
				$value     = $matches[3];

				if ( 'srcset' === $attribute ) {
					$value = $this->rewrite_srcset( $value, $force_protected, $context );
				} else {
					$value = $this->rewrite_single_upload_url( $value, $attribute, $force_protected, $context );
				}

				return $matches[1] . '=' . $matches[2] . esc_attr( $value ) . $matches[2];
			},
			$content
		);
	}

	private function rewrite_srcset( $srcset, $force_protected = false, $context = 'global' ) {
		$items = explode( ',', $srcset );
		$out   = array();

		foreach ( $items as $item ) {
			if ( ! preg_match( '/^\s*(\S+)(.*)$/', $item, $matches ) ) {
				$out[] = $item;
				continue;
			}

			$url        = $matches[1];
			$descriptor = $matches[2];
			$rewritten = $this->rewrite_single_upload_url( $url, 'srcset', $force_protected, $context );
			if ( '' === $rewritten ) {
				continue;
			}

			$out[] = $rewritten . $descriptor;
		}

		return implode( ', ', $out );
	}

	private function rewrite_single_upload_url( $url, $attribute, $force_protected = false, $context = 'global' ) {
		$this->debug_log(
			'rewrite_single_upload_url_enter',
			array(
				'url' => $url,
				'attribute' => $attribute,
				'context' => $context,
				'force_protected' => $force_protected,
			)
		);

		// render_block-filtteri ajetaan myös sisäkkäisille lohkoille. Jos yksityinen
		// list-item on jo muuttanut linkin suojatuksi, julkinen parent-list ei saa
		// purkaa sitä takaisin uploads-URLiksi.
		if ( 'block_public' === $context && null !== $this->private_media_url_to_upload_relative_path( $url ) ) {
			if ( $this->should_hide_private_embed_for_current_viewer( $attribute ) ) {
				$private_url = 'srcset' === $attribute ? '' : $this->private_media_placeholder_url();
				$this->debug_log(
					'rewrite_single_upload_url_decision',
					array(
						'decision' => 'existing_private_embed_placeholder',
						'url' => $url,
						'output_url' => $private_url,
						'attribute' => $attribute,
						'context' => $context,
					)
				);

				return $private_url;
			}

			$this->debug_log(
				'rewrite_single_upload_url_decision',
				array(
					'decision' => 'keep_existing_private_url',
					'url' => $url,
					'attribute' => $attribute,
					'context' => $context,
				)
			);
			return $url;
		}

		$relative_path = $this->url_to_managed_upload_relative_path( $url );
		if ( null === $relative_path ) {
			$legacy_link_target = $this->legacy_private_site_link_url_to_target( $url );
			if ( null !== $legacy_link_target ) {
				$this->debug_log(
					'rewrite_single_upload_url_decision',
					array(
						'decision' => 'legacy_private_link_restored',
						'url' => $url,
						'output_url' => $legacy_link_target,
						'attribute' => $attribute,
						'context' => $context,
						'force_protected' => $force_protected,
					)
				);
				return $legacy_link_target;
			}

			$this->debug_log(
				'rewrite_single_upload_url_decision',
				array(
					'decision' => 'not_managed_upload',
					'url' => $url,
					'output_url' => $url,
					'attribute' => $attribute,
					'context' => $context,
					'force_protected' => $force_protected,
				)
			);
			return $url;
		}

		$attachment_id = $this->find_attachment_for_relative_path( $relative_path );
		if ( ! $force_protected && ! $this->is_protected_upload( $attachment_id, $relative_path, $context ) ) {
			$public_url = $this->upload_relative_path_to_url( $relative_path );
			$output_url = '' === $public_url ? $url : $public_url;
			$this->debug_log(
				'rewrite_single_upload_url_decision',
				array(
					'decision' => 'public_url',
					'url' => $url,
					'output_url' => $output_url,
					'attribute' => $attribute,
					'context' => $context,
					'force_protected' => $force_protected,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);

			return $output_url;
		}

		if ( $this->should_hide_private_embed_for_current_viewer( $attribute ) ) {
			$private_url = 'srcset' === $attribute ? '' : $this->private_media_placeholder_url();
			$this->debug_log(
				'rewrite_single_upload_url_decision',
				array(
					'decision' => 'protected_embed_placeholder',
					'url' => $url,
					'output_url' => $private_url,
					'attribute' => $attribute,
					'context' => $context,
					'force_protected' => $force_protected,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);

			return $private_url;
		}

		$private_url = $this->build_private_url_for_context( $attachment_id, $relative_path, $attribute, $force_protected );
		$this->debug_log(
			'rewrite_single_upload_url_decision',
			array(
				'decision' => 'protected_url',
				'url' => $url,
				'output_url' => $private_url,
				'attribute' => $attribute,
				'context' => $context,
				'force_protected' => $force_protected,
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		return $private_url;
	}

	private function should_hide_private_embed_for_current_viewer( $attribute ) {
		return ! is_user_logged_in() && in_array( $attribute, array( 'src', 'poster', 'srcset' ), true );
	}

	private function private_media_placeholder_url() {
		return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
	}

	private function is_private_media_placeholder_url( $url ) {
		return $url === $this->private_media_placeholder_url();
	}

	private function url_points_to_protected_media( $url, $context = 'frontend_output' ) {
		$relative_path = $this->url_to_managed_upload_relative_path( $url );
		if ( null === $relative_path ) {
			return false;
		}

		$attachment_id = $this->find_attachment_for_relative_path( $relative_path );

		return (bool) $this->is_protected_upload( $attachment_id, $relative_path, $context );
	}

	/**
	 * Rakentaa lopullisen suojatun URLin.
	 *
	 * HTML:ään jätetään login-start-URL. Se luo käyttäjäkohtaisen noncen vasta
	 * kirjautuneelle käyttäjälle ennen tiedoston varsinaista avausendpointtia.
	 */
	private function build_private_url_for_context( $attachment_id, $relative_path, $attribute, $force_protected = false ) {
		unset( $attribute );

		// HTML:ään ei tallenneta eikä renderöidä noncea. Klikkaus avaa ensin
		// välipisteen, joka luo noncen juuri senhetkiselle kirjautuneelle käyttäjälle.
		return $this->build_login_start_url( $attachment_id, $relative_path, $force_protected );
	}

	private function build_private_file_url( $attachment_id, $relative_path, $force_protected = false ) {
		$args = array(
			'action' => self::PECODEX_ACTION,
			'file'   => $relative_path,
		);

		if ( $attachment_id ) {
			$args['id'] = $attachment_id;
		}

		if ( $force_protected ) {
			$args['pgm_force'] = 1;
		}

		$args['_wpnonce'] = wp_create_nonce( $this->nonce_action( $attachment_id, $relative_path ) );
		$download_token   = $this->create_private_download_token( $attachment_id, $relative_path, $force_protected );
		if ( '' !== $download_token ) {
			$args[ self::DOWNLOAD_TOKEN_QUERY_ARG ] = $download_token;
		}

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	private function build_login_start_url( $attachment_id, $relative_path, $force_protected = false ) {
		$args = array(
			'action' => self::PECODEX_ACTION_LOGIN,
			'file'   => $relative_path,
		);

		if ( $attachment_id ) {
			$args['id'] = $attachment_id;
		}

		if ( $force_protected ) {
			$args['pgm_force'] = 1;
		}

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	private function create_private_download_token( $attachment_id, $relative_path, $force_protected = false ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return '';
		}

		$token = wp_generate_password( 43, false, false );
		$data  = array(
			'user_id' => get_current_user_id(),
			'session' => hash( 'sha256', (string) wp_get_session_token() ),
			'id'      => absint( $attachment_id ),
			'file'    => $relative_path,
			'force'   => $force_protected ? 1 : 0,
			'created' => time(),
		);

		set_transient( $this->download_token_transient_key( $token ), $data, $this->private_download_token_ttl() );

		return $token;
	}

	private function private_download_token_ttl() {
		return max(
			60,
			(int) apply_filters( 'pgm_private_media_download_token_ttl', 5 * MINUTE_IN_SECONDS )
		);
	}

	private function download_token_transient_key( $token ) {
		return 'pgm_private_media_open_' . hash( 'sha256', (string) $token );
	}

	private function requested_private_download_token() {
		return isset( $_GET[ self::DOWNLOAD_TOKEN_QUERY_ARG ] )
			? sanitize_text_field( wp_unslash( $_GET[ self::DOWNLOAD_TOKEN_QUERY_ARG ] ) )
			: '';
	}

	private function current_page_url() {
		if ( is_singular() ) {
			$permalink = get_permalink();
			if ( $permalink ) {
				return $permalink;
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return home_url( $request_uri );
	}

	private function url_to_managed_upload_relative_path( $url ) {
		$private_path = $this->private_media_url_to_upload_relative_path( $url );

		return null === $private_path ? $this->url_to_upload_relative_path( $url ) : $private_path;
	}

	private function has_private_media_action_token( $value ) {
		return is_string( $value )
			&& ( false !== strpos( $value, self::ACTION ) || false !== strpos( $value, self::PECODEX_ACTION ) );
	}

	private function has_legacy_private_link_action_token( $value ) {
		return is_string( $value )
			&& ( false !== strpos( $value, 'pgm_private_link' ) || false !== strpos( $value, 'pecodex_private_link' ) );
	}

	private function private_media_action_names() {
		return array( self::ACTION, self::ACTION_LOGIN, self::PECODEX_ACTION, self::PECODEX_ACTION_LOGIN );
	}

	private function legacy_private_link_action_names() {
		return array( 'pgm_private_link', 'pgm_private_link_login', 'pecodex_private_link', 'pecodex_private_link_login' );
	}

	private function private_media_url_to_upload_relative_path( $url ) {
		if ( ! $this->has_private_media_action_token( $url ) ) {
			return null;
		}

		$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return null;
		}

		if ( ! $this->url_belongs_to_this_site( $parts ) ) {
			return null;
		}

		$query = array();
		wp_parse_str( $parts['query'], $query );

		$action = isset( $query['action'] ) ? sanitize_key( $query['action'] ) : '';
		if ( ! in_array( $action, $this->private_media_action_names(), true ) || empty( $query['file'] ) ) {
			return null;
		}

		return $this->normalize_relative_path( rawurldecode( (string) $query['file'] ) );
	}

	private function legacy_private_site_link_url_to_target( $url ) {
		if ( ! $this->has_legacy_private_link_action_token( $url ) ) {
			return null;
		}

		$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return null;
		}

		if ( ! $this->url_belongs_to_this_site( $parts ) ) {
			return null;
		}

		$query = array();
		wp_parse_str( $parts['query'], $query );

		$action = isset( $query['action'] ) ? sanitize_key( $query['action'] ) : '';
		if ( ! in_array( $action, $this->legacy_private_link_action_names(), true ) || empty( $query['url'] ) ) {
			return null;
		}

		$target_url = $this->normalize_legacy_private_site_url( (string) $query['url'] );

		return is_wp_error( $target_url ) ? null : $target_url;
	}

	/**
	 * Purkaa vanhan private-link endpointin kohteen turvallisesti takaisin URLiksi.
	 *
	 * Uutta sisäisten linkkien suojausta ei enää luoda; tämä on vain legacy-sisällön
	 * siivousta varten. Hyväksytään edelleen vain saman WordPress-sivuston http/https-linkit.
	 *
	 * @return string|WP_Error
	 */
	private function normalize_legacy_private_site_url( $url ) {
		if ( ! is_string( $url ) ) {
			return new WP_Error( 'pgm_bad_private_link', __( 'Virheellinen sisäinen linkki.', 'private-gutenberg-media' ) );
		}

		$url = trim( html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $url || '#' === $url || 0 === strpos( $url, '#' ) || preg_match( '#^(?:data|mailto|tel|sms|javascript):#i', $url ) ) {
			return new WP_Error( 'pgm_bad_private_link', __( 'Virheellinen sisäinen linkki.', 'private-gutenberg-media' ) );
		}

		// "vero.fi" ilman skeemaa näyttäää teknisesti suhteelliselta polulta.
		// Turvallisempi oletus on jättää paljaat domainit käsittelemättä.
		if ( $this->looks_like_bare_domain( $url ) ) {
			return new WP_Error( 'pgm_external_private_link', __( 'Ulkoista linkkiä ei suojata tällä lisäosalla.', 'private-gutenberg-media' ) );
		}

		$site_parts = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $site_parts ) || empty( $site_parts['host'] ) ) {
			return new WP_Error( 'pgm_bad_home_url', __( 'Sivuston osoitetta ei voitu tarkistaa.', 'private-gutenberg-media' ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return new WP_Error( 'pgm_bad_private_link', __( 'Virheellinen sisäinen linkki.', 'private-gutenberg-media' ) );
		}

		if ( empty( $parts['host'] ) ) {
			$path = isset( $parts['path'] ) ? $parts['path'] : '/';
			$path = '/' . ltrim( $path, '/' );
			$url  = home_url( $path );

			if ( ! empty( $parts['query'] ) ) {
				$url .= '?' . $parts['query'];
			}

			if ( ! empty( $parts['fragment'] ) ) {
				$url .= '#' . $parts['fragment'];
			}

			$parts = wp_parse_url( $url );
		} elseif ( empty( $parts['scheme'] ) ) {
			// Protokollaton //oma-domain/polku normalisoidaan sivuston omaan
			// skeemaan. Näin redirect-kohde on aina yksiselitteinen http/https-URL.
			$site_scheme = ! empty( $site_parts['scheme'] ) ? $site_parts['scheme'] : ( is_ssl() ? 'https' : 'http' );
			$url         = $site_scheme . ':' . $url;
			$parts       = wp_parse_url( $url );
		}

		if ( ! is_array( $parts ) || ! $this->url_belongs_to_this_site( $parts ) ) {
			return new WP_Error( 'pgm_external_private_link', __( 'Ulkoista linkkiä ei suojata tällä lisäosalla.', 'private-gutenberg-media' ) );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'pgm_bad_private_link', __( 'Virheellinen sisäinen linkki.', 'private-gutenberg-media' ) );
		}

		$path = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '/';
		if ( '' === $path ) {
			$path = '/';
		}

		if ( false !== strpos( $path, "\0" ) || preg_match( '#(^|/)\.\.(/|$)#', $path ) || $this->is_private_site_url_blocked_path( $path ) ) {
			return new WP_Error( 'pgm_bad_private_link', __( 'Tätä sisäistä linkkiä ei voi suojata.', 'private-gutenberg-media' ) );
		}

		$sanitized_url = esc_url_raw( $url );
		if ( '' === $sanitized_url ) {
			return new WP_Error( 'pgm_bad_private_link', __( 'Virheellinen sisäinen linkki.', 'private-gutenberg-media' ) );
		}

		return $sanitized_url;
	}

	private function looks_like_bare_domain( $url ) {
		return 1 === preg_match( '~^[a-z0-9.-]+\.[a-z]{2,}(?:[/:?#]|$)~i', trim( (string) $url ) );
	}

	private function is_private_site_url_blocked_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );

		return 1 === preg_match( '#^/(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php)(?:/|$)#i', $path );
	}

	private function upload_relative_path_to_url( $relative_path ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return '';
		}

		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return '';
		}

		$encoded_path = implode( '/', array_map( 'rawurlencode', explode( '/', $relative_path ) ) );

		return $this->normalize_public_upload_url_scheme( trailingslashit( $uploads['baseurl'] ) . $encoded_path );
	}

	private function normalize_public_upload_url_scheme( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( $this->current_request_prefers_https_urls() ) {
			return set_url_scheme( $url, 'https' );
		}

		return $url;
	}

	private function current_request_prefers_https_urls() {
		if ( is_ssl() ) {
			return true;
		}

		if ( function_exists( 'wp_is_using_https' ) && wp_is_using_https() ) {
			return true;
		}

		foreach ( array( 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_SCHEME', 'HTTP_X_URL_SCHEME' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$values = array_map( 'trim', explode( ',', strtolower( (string) wp_unslash( $_SERVER[ $key ] ) ) ) );
			if ( in_array( 'https', $values, true ) ) {
				return true;
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) ) {
			return true;
		}

		if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) wp_unslash( $_SERVER['HTTPS'] ) ) ) {
			return true;
		}

		return false;
	}

	private function url_belongs_to_this_site( $parts ) {
		if ( empty( $parts['host'] ) ) {
			return true;
		}

		$site_parts = wp_parse_url( home_url( '/' ) );
		if ( empty( $site_parts['host'] ) ) {
			return false;
		}

		return strtolower( $parts['host'] ) === strtolower( $site_parts['host'] );
	}

	/**
	 * Muuntaa uploads-URLin upload-hakemistoon suhteelliseksi poluksi.
	 *
	 * Palauttaa null, jos URL ei kuulu tämän WordPress-sivuston uploads-kansioon.
	 * Tämä estää ulkoisten linkkien ja javascript/mailto/data-osoitteiden käsittelyn.
	 */
	private function url_to_upload_relative_path( $url ) {
		if ( '' === $url || preg_match( '#^(?:data|mailto|tel|javascript):#i', $url ) ) {
			return null;
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return null;
		}

		$parts      = wp_parse_url( html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		$base_parts = wp_parse_url( $uploads['baseurl'] );

		if ( ! is_array( $parts ) || ! is_array( $base_parts ) ) {
			return null;
		}

		$path      = isset( $parts['path'] ) ? $parts['path'] : '';
		$base_path = isset( $base_parts['path'] ) ? untrailingslashit( $base_parts['path'] ) : '';

		if ( '' === $path || '' === $base_path ) {
			return null;
		}

		$decoded_path = rawurldecode( $path );
		$base_path    = rawurldecode( $base_path );

		if ( 0 !== strpos( $decoded_path, trailingslashit( $base_path ) ) ) {
			return null;
		}

		$relative_path = substr( $decoded_path, strlen( trailingslashit( $base_path ) ) );

		return $this->normalize_relative_path( $relative_path );
	}

	/**
	 * Normalisoi suhteellisen tiedostopolun ja torjuu path traversal -yritykset.
	 */
	private function normalize_relative_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = ltrim( $path, '/' );

		if ( '' === $path || false !== strpos( $path, "\0" ) || preg_match( '#(^|/)\.\.(/|$)#', $path ) ) {
			return null;
		}

		return $path;
	}

	/**
	 * Etsii medialiitteen upload-polun perusteella.
	 *
	 * Ensimmäinen haku kattaa alkuperäisen tiedoston. Toinen haku tarkistaa
	 * metadataan tallennetut kuvakoot, kuten image-300x200.jpg.
	 */
	private function find_attachment_for_relative_path( $relative_path ) {
		if ( isset( $this->attachment_lookup_cache[ $relative_path ] ) ) {
			return $this->attachment_lookup_cache[ $relative_path ];
		}

		$attachment_id = $this->find_attachment_by_attached_file( $relative_path );
		if ( ! $attachment_id ) {
			$attachment_id = $this->find_attachment_by_metadata_size( $relative_path );
		}
		if ( ! $attachment_id ) {
			$attachment_id = $this->find_attachment_by_private_storage_path( $relative_path );
		}

		$this->attachment_lookup_cache[ $relative_path ] = (int) $attachment_id;

		return (int) $attachment_id;
	}

	private function find_attachment_for_legacy_upload_alias( $relative_path ) {
		global $wpdb;

		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return null;
		}

		$directory = dirname( $relative_path );
		$directory = '.' === $directory ? '' : trim( $directory, '/' );
		$filename  = wp_basename( $relative_path );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$key       = $this->legacy_upload_alias_key( $filename );

		if ( '' === $extension || strlen( $key ) < 8 ) {
			return null;
		}

		$like = '' === $directory ? '%' : $directory . '/%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 250",
				$like
			),
			ARRAY_A
		);

		$matches = array();
		foreach ( (array) $rows as $row ) {
			$candidate_path = $this->normalize_relative_path( isset( $row['meta_value'] ) ? $row['meta_value'] : '' );
			if ( null === $candidate_path ) {
				continue;
			}

			$candidate_directory = dirname( $candidate_path );
			$candidate_directory = '.' === $candidate_directory ? '' : trim( $candidate_directory, '/' );
			$candidate_filename  = wp_basename( $candidate_path );

			if ( $candidate_directory !== $directory ) {
				continue;
			}

			if ( strtolower( pathinfo( $candidate_filename, PATHINFO_EXTENSION ) ) !== $extension ) {
				continue;
			}

			if ( $this->legacy_upload_alias_key( $candidate_filename ) !== $key ) {
				continue;
			}

			$attachment_id = absint( isset( $row['post_id'] ) ? $row['post_id'] : 0 );
			if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			$matches[] = array(
				'attachment_id' => $attachment_id,
				'relative_path' => $candidate_path,
			);
		}

		$protected_matches = array();
		foreach ( $matches as $match ) {
			if ( $this->is_protected_upload( $match['attachment_id'], $match['relative_path'], 'legacy_upload_alias' ) ) {
				$protected_matches[] = $match;
			}
		}

		if ( 1 === count( $protected_matches ) ) {
			$this->debug_log(
				'legacy_upload_alias_resolved',
				array(
					'requested_relative_path' => $relative_path,
					'resolved_relative_path' => $protected_matches[0]['relative_path'],
					'attachment_id' => $protected_matches[0]['attachment_id'],
				)
			);

			return $protected_matches[0];
		}

		if ( count( $protected_matches ) > 1 ) {
			$this->debug_log(
				'legacy_upload_alias_ambiguous',
				array(
					'requested_relative_path' => $relative_path,
					'matches' => $protected_matches,
				)
			);
		}

		return null;
	}

	private function legacy_upload_alias_key( $filename ) {
		$filename = remove_accents( strtolower( (string) $filename ) );

		return preg_replace( '/[^a-z0-9]+/', '', $filename );
	}

	private function find_attachment_by_attached_file( $relative_path ) {
		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$relative_path
			)
		);

		return $post_id ? (int) $post_id : 0;
	}

	private function find_attachment_by_private_storage_path( $relative_path ) {
		global $wpdb;

		$filename = wp_basename( $relative_path );
		if ( '' === $filename ) {
			return 0;
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s LIMIT 25",
				self::META_PRIVATE_STORAGE_PATHS,
				'%' . $wpdb->esc_like( $filename ) . '%'
			)
		);

		foreach ( (array) $ids as $id ) {
			if ( $this->attachment_owns_relative_path( (int) $id, $relative_path ) ) {
				return (int) $id;
			}
		}

		return 0;
	}

	private function find_attachment_by_metadata_size( $relative_path ) {
		global $wpdb;

		$filename = wp_basename( $relative_path );
		$ids      = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s LIMIT 25",
				'%' . $wpdb->esc_like( $filename ) . '%'
			)
		);

		foreach ( (array) $ids as $id ) {
			if ( $this->attachment_owns_relative_path( (int) $id, $relative_path ) ) {
				return (int) $id;
			}
		}

		return 0;
	}

	private function attachment_owns_relative_path( $attachment_id, $relative_path ) {
		if ( ! $attachment_id ) {
			return false;
		}

		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $relative_path === $attached_file ) {
			return true;
		}

		if ( in_array( $relative_path, $this->attachment_stored_private_paths( $attachment_id ), true ) ) {
			return true;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return false;
		}

		$base_dir = dirname( $metadata['file'] );
		$base_dir = '.' === $base_dir ? '' : trailingslashit( $base_dir );

		foreach ( $metadata['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) && $relative_path === $base_dir . $size['file'] ) {
				return true;
			}
		}

		return false;
	}

	private function remove_legacy_private_mark_if_unused( $attachment_id ) {
		if ( get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true ) || $this->attachment_has_folder_sources( $attachment_id ) ) {
			return;
		}

		$this->maybe_restore_attachment_if_no_private_sources( $attachment_id );
	}

	private function attachment_has_protected_content_links( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		if ( get_post_meta( $attachment_id, self::META_CONTENT_LINKS_PRIVATE, true ) ) {
			return true;
		}

		return '' !== trim( (string) get_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL, true ) );
	}

	private function attachment_folder_sources_for_js( $source_ids ) {
		$out = array();

		foreach ( (array) $source_ids as $source_id ) {
			$source_id = absint( $source_id );
			if ( ! $source_id ) {
				continue;
			}

			$term = get_term( $source_id, PGM_Media_Organizer::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$out[] = array(
				'id'     => $source_id,
				'name'   => wp_strip_all_tags( $term->name ),
				'access' => $this->folder_effective_access_for_term( $source_id ),
				'editUrl' => add_query_arg( PGM_Media_Organizer::FOLDER_QUERY_ARG, $source_id, admin_url( 'upload.php' ) ),
			);
		}

		return $out;
	}

	public function sync_attachment_folder_privacy( $attachment_id, $folder_id = 0, $previous_terms = array() ) {
		unset( $folder_id, $previous_terms );

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		$sources = $this->protected_folder_source_ids_for_attachment( $attachment_id );
		if ( empty( $sources ) ) {
			delete_post_meta( $attachment_id, self::META_FOLDER_SOURCES );
			$this->maybe_restore_attachment_if_no_private_sources( $attachment_id );
			$this->mark_origin_cache_clear_response();
			return;
		}

		update_post_meta( $attachment_id, self::META_FOLDER_SOURCES, $sources );
		update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
		$this->sync_attachment_storage_for_current_mode( $attachment_id );
		$this->mark_origin_cache_clear_response();
	}

	private function detach_attachment_from_media_folders( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'pgm_bad_attachment', __( 'Tuntematon medialiite.', 'private-gutenberg-media' ) );
		}

		$previous_terms = wp_get_object_terms( $attachment_id, PGM_Media_Organizer::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $previous_terms ) ) {
			return $previous_terms;
		}

		$previous_terms = array_values( array_unique( array_filter( array_map( 'absint', (array) $previous_terms ) ) ) );
		if ( empty( $previous_terms ) ) {
			delete_post_meta( $attachment_id, self::META_FOLDER_SOURCES );
			return array();
		}

		wp_delete_object_term_relationships( $attachment_id, PGM_Media_Organizer::TAXONOMY );
		clean_object_term_cache( $attachment_id, PGM_Media_Organizer::TAXONOMY );
		delete_post_meta( $attachment_id, self::META_FOLDER_SOURCES );
		do_action( 'pgm_mo_attachment_folder_changed', $attachment_id, 0, $previous_terms );

		return $previous_terms;
	}

	public function sync_folder_privacy_for_folder_tree( $term_id, $access = '', $old_access = '' ) {
		unset( $access, $old_access );

		$term_id = absint( $term_id );
		if ( ! $term_id ) {
			return;
		}

		$folder_ids = array( $term_id );
		$children   = get_term_children( $term_id, PGM_Media_Organizer::TAXONOMY );
		if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
			$folder_ids = array_merge( $folder_ids, array_map( 'absint', $children ) );
		}

		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => PGM_Media_Organizer::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $folder_ids,
					),
				),
			)
		);

		if ( ! empty( $attachment_ids ) ) {
			$this->queue_attachments_for_sync( $attachment_ids );
		}
	}

	public function queue_attachments_for_sync( $attachment_ids ) {
		$queue = get_option( 'pgm_mo_sync_queue', array() );
		$queue = array_values( array_unique( array_merge( (array) $queue, array_map( 'absint', $attachment_ids ) ) ) );
		update_option( 'pgm_mo_sync_queue', $queue, false );

		if ( ! wp_next_scheduled( 'pecodex_background_sync_cron' ) ) {
			wp_schedule_single_event( time(), 'pecodex_background_sync_cron' );
		}
	}

	public function process_background_sync_batch() {
		$queue = get_option( 'pgm_mo_sync_queue', array() );
		if ( empty( $queue ) ) {
			return 0;
		}

		$batch = array_slice( $queue, 0, 5 );
		$queue = array_slice( $queue, 5 );
		update_option( 'pgm_mo_sync_queue', $queue, false );

		foreach ( $batch as $attachment_id ) {
			$this->sync_attachment_folder_privacy( (int) $attachment_id );
		}

		if ( ! empty( $queue ) && ! wp_next_scheduled( 'pecodex_background_sync_cron' ) ) {
			wp_schedule_single_event( time(), 'pecodex_background_sync_cron' );
		}

		return count( $queue );
	}

	public function ajax_check_sync_status() {
		$queue = get_option( 'pgm_mo_sync_queue', array() );
		wp_send_json_success( array( 'remaining' => count( $queue ) ) );
	}

	public function ajax_trigger_sync_batch() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error();
		}
		$remaining = $this->process_background_sync_batch();
		wp_send_json_success( array( 'remaining' => $remaining ) );
	}

	public function provide_duplicate_attachment_source_contents( $contents, $attachment_id, $relative_path = '' ) {
		if ( null !== $contents ) {
			return $contents;
		}

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return $contents;
		}

		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		}
		if ( null === $relative_path ) {
			return $contents;
		}

		$public_path = $this->public_upload_file_path_if_exists( $relative_path );
		if ( $public_path ) {
			$data = file_get_contents( $public_path );
			return false === $data ? new WP_Error( 'pgm_mo_source_missing', __( 'Alkuperäistä tiedostoa ei voitu lukea.', 'private-gutenberg-media' ) ) : $data;
		}

		$private_path = $this->private_file_path_if_exists( $relative_path );
		if ( ! $private_path ) {
			return $contents;
		}

		return $this->read_private_storage_comparison_contents( $private_path );
	}

	public function sync_duplicated_attachment_privacy( $new_attachment_id, $source_attachment_id ) {
		$new_attachment_id    = absint( $new_attachment_id );
		$source_attachment_id = absint( $source_attachment_id );

		if (
			! $new_attachment_id
			|| ! $source_attachment_id
			|| 'attachment' !== get_post_type( $new_attachment_id )
			|| 'attachment' !== get_post_type( $source_attachment_id )
		) {
			return;
		}

		if ( get_post_meta( $source_attachment_id, self::META_PUBLIC_OVERRIDE, true ) ) {
			update_post_meta( $new_attachment_id, self::META_PUBLIC_OVERRIDE, '1' );
		} else {
			delete_post_meta( $new_attachment_id, self::META_PUBLIC_OVERRIDE );
		}

		if ( get_post_meta( $source_attachment_id, self::META_PRIVATE_MANUAL, true ) ) {
			update_post_meta( $new_attachment_id, self::META_PRIVATE_MANUAL, '1' );
			update_post_meta( $new_attachment_id, self::META_PRIVATE, '1' );
			delete_post_meta( $new_attachment_id, self::META_PUBLIC_OVERRIDE );
		} else {
			delete_post_meta( $new_attachment_id, self::META_PRIVATE_MANUAL );
		}

		$this->sync_attachment_storage_for_current_mode( $new_attachment_id );
	}

	private function protected_folder_source_ids_for_attachment( $attachment_id ) {
		$terms = wp_get_object_terms( $attachment_id, PGM_Media_Organizer::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$sources = array();
		foreach ( array_map( 'absint', $terms ) as $term_id ) {
			foreach ( $this->protected_folder_ids_for_term( $term_id ) as $source_id ) {
				$sources[] = $source_id;
			}
		}

		return array_values( array_unique( array_filter( $sources ) ) );
	}

	private function protected_folder_ids_for_term( $term_id ) {
		$term_id = absint( $term_id );
		$sources = array();

		while ( $term_id > 0 ) {
			$term = get_term( $term_id, PGM_Media_Organizer::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}

			if ( PGM_Media_Organizer::ACCESS_PUBLIC !== $this->folder_access_for_term( $term_id ) ) {
				$sources[] = $term_id;
			}

			$term_id = (int) $term->parent;
		}

		return $sources;
	}

	private function attachment_effective_folder_access( $attachment_id ) {
		$terms = wp_get_object_terms( $attachment_id, PGM_Media_Organizer::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return PGM_Media_Organizer::ACCESS_PUBLIC;
		}

		$access = PGM_Media_Organizer::ACCESS_PUBLIC;
		foreach ( array_map( 'absint', $terms ) as $term_id ) {
			$access = PGM_Media_Organizer::strongest_access( $access, $this->folder_effective_access_for_term( $term_id ) );
		}

		return $access;
	}

	private function folder_effective_access_for_term( $term_id ) {
		$term_id = absint( $term_id );
		$access  = PGM_Media_Organizer::ACCESS_PUBLIC;

		while ( $term_id > 0 ) {
			$term = get_term( $term_id, PGM_Media_Organizer::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}

			$access  = PGM_Media_Organizer::strongest_access( $access, $this->folder_access_for_term( $term_id ) );
			$term_id = (int) $term->parent;
		}

		return $access;
	}

	private function folder_access_for_term( $term_id ) {
		if ( ! $term_id ) {
			return PGM_Media_Organizer::ACCESS_PUBLIC;
		}

		return PGM_Media_Organizer::sanitize_access_mode( get_term_meta( (int) $term_id, PGM_Media_Organizer::ACCESS_META_KEY, true ) );
	}

	private function attachment_has_folder_sources( $attachment_id ) {
		return ! empty( $this->attachment_folder_source_ids( $attachment_id ) );
	}

	private function attachment_folder_source_ids( $attachment_id ) {
		$sources = get_post_meta( $attachment_id, self::META_FOLDER_SOURCES, true );
		$sources = is_array( $sources ) ? array_map( 'absint', $sources ) : array();

		return array_values( array_unique( array_filter( $sources ) ) );
	}

	private function attachment_has_public_override( $attachment_id ) {
		return $attachment_id && (bool) get_post_meta( $attachment_id, self::META_PUBLIC_OVERRIDE, true );
	}

	private function attachment_ids_for_exit_restore() {
		global $wpdb;

		$ids       = array();
		$meta_keys = $this->privacy_post_meta_keys();
		if ( ! empty( $meta_keys ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
			$sql          = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND pm.meta_key IN ($placeholders)";
			$ids          = $wpdb->get_col( $wpdb->prepare( $sql, $meta_keys ) );
		}

		if ( taxonomy_exists( PGM_Media_Organizer::TAXONOMY ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => PGM_Media_Organizer::TAXONOMY,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$foldered_ids = get_objects_in_term( array_map( 'absint', $terms ), PGM_Media_Organizer::TAXONOMY );
				if ( ! is_wp_error( $foldered_ids ) && ! empty( $foldered_ids ) ) {
					$ids = array_merge( $ids, $foldered_ids );
				}
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		sort( $ids );

		return $ids;
	}

	private function restore_attachment_to_plain_wordpress_media( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'pgm_bad_attachment', __( 'Tuntematon medialiite.', 'private-gutenberg-media' ) );
		}

		$had_private_copy = $this->attachment_has_private_storage_copy( $attachment_id );
		$result           = $this->restore_attachment_files_to_uploads( $attachment_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $had_private_copy && ! $this->attachment_has_primary_public_upload_copy( $attachment_id ) ) {
			return new WP_Error( 'pgm_public_restore_failed', __( 'Tiedostoa ei voitu palauttaa julkiseen uploads-kansioon.', 'private-gutenberg-media' ) );
		}

		$this->sync_attachment_content_links( $attachment_id, false );
		$this->delete_attachment_privacy_meta( $attachment_id );
		$this->purge_attachment_public_url_caches( $attachment_id );
		$this->queue_public_upload_protection_rules_refresh();

		return true;
	}

	private function delete_attachment_privacy_meta( $attachment_id ) {
		foreach ( $this->privacy_post_meta_keys() as $meta_key ) {
			delete_post_meta( $attachment_id, $meta_key );
		}
	}

	private function privacy_post_meta_keys() {
		return array(
			self::META_PRIVATE,
			self::META_PRIVATE_MANUAL,
			self::META_PUBLIC_OVERRIDE,
			self::META_BLOCK_SOURCES,
			self::META_FOLDER_SOURCES,
			self::META_ORIGINAL_PUBLIC_URL,
			self::META_ACTIVE_PROTECTED_URL,
			self::META_CONTENT_LINKS_PRIVATE,
			self::META_CONTENT_LINKS_SYNCED_AT,
			self::META_PRIVATE_STORAGE_PATHS,
			self::POST_META_BLOCK_PATHS,
		);
	}

	private function plugin_post_meta_keys() {
		return array_merge(
			$this->privacy_post_meta_keys(),
			array(
				PGM_Media_Organizer::ORDER_META_KEY,
				PGM_Media_Organizer::SIZE_META_KEY,
				PGM_Media_Organizer::CATFOLDERS_SOURCE_META_KEY,
				PGM_Media_Organizer::LOCAL_OVERRIDE_META_KEY,
			)
		);
	}

	private function delete_plugin_data_after_exit_restore() {
		foreach ( $this->plugin_post_meta_keys() as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		if ( taxonomy_exists( PGM_Media_Organizer::TAXONOMY ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => PGM_Media_Organizer::TAXONOMY,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$terms = array_map( 'absint', $terms );
				usort(
					$terms,
					function ( $a, $b ) {
						return count( get_ancestors( $b, PGM_Media_Organizer::TAXONOMY, 'taxonomy' ) ) <=> count( get_ancestors( $a, PGM_Media_Organizer::TAXONOMY, 'taxonomy' ) );
					}
				);

				foreach ( $terms as $term_id ) {
					wp_delete_term( $term_id, PGM_Media_Organizer::TAXONOMY );
				}
			}
		}

		foreach ( $this->plugin_option_keys() as $option_name ) {
			delete_option( $option_name );
		}

		$this->delete_plugin_transients();
	}

	private function plugin_option_keys() {
		return array(
			self::OPTION_NAME,
			self::VERSION_OPTION,
			self::STORAGE_KEY_OPTION,
			self::STORAGE_LEGACY_KEY_OPTION,
			'pgm_last_exit_restore_report',
			'pgm_046_policy_only_restore_done',
			'pgm_0711_legacy_default_extension_policy_migrated',
			'pgm_0820_gutenberg_sources_file_level_migrated',
			PGM_Media_Organizer::OPTION_NAME,
			PGM_Media_Organizer::CATFOLDERS_SYNC_OPTION,
			'pgm_mo_0832_startup_folder_default_migrated',
		);
	}

	private function delete_plugin_transients() {
		global $wpdb;

		$patterns = array(
			$wpdb->esc_like( '_transient_pgm_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_pgm_' ) . '%',
			$wpdb->esc_like( '_site_transient_pgm_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_pgm_' ) . '%',
			$wpdb->esc_like( '_transient_pecodex_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_pecodex_' ) . '%',
			$wpdb->esc_like( '_site_transient_pecodex_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_pecodex_' ) . '%',
		);

		foreach ( $patterns as $pattern ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
		}
	}

	private function make_attachment_public( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'pgm_bad_attachment', __( 'Tuntematon medialiite.', 'private-gutenberg-media' ) );
		}

		$relative_path       = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$is_policy_protected = null !== $relative_path && $this->attachment_matches_protected_policy( $attachment_id, $relative_path );
		$result              = $this->restore_attachment_files_to_uploads( $attachment_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $this->attachment_has_primary_public_upload_copy( $attachment_id ) ) {
			return new WP_Error( 'pgm_public_restore_failed', __( 'Tiedostoa ei voitu palauttaa julkiseen uploads-kansioon.', 'private-gutenberg-media' ) );
		}

		delete_post_meta( $attachment_id, self::META_PRIVATE_MANUAL );
		if ( $is_policy_protected ) {
			update_post_meta( $attachment_id, self::META_PUBLIC_OVERRIDE, '1' );
		} else {
			delete_post_meta( $attachment_id, self::META_PUBLIC_OVERRIDE );
		}
		delete_post_meta( $attachment_id, self::META_PRIVATE );
		delete_post_meta( $attachment_id, self::META_PRIVATE_STORAGE_PATHS );
		delete_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL );
		$this->sync_attachment_content_links( $attachment_id, false );
		$this->purge_attachment_public_url_caches( $attachment_id );
		$this->queue_public_upload_protection_rules_refresh();

		return true;
	}

	private function maybe_restore_attachment_if_no_private_sources( $attachment_id ) {
		if (
			get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true )
			|| $this->attachment_has_folder_sources( $attachment_id )
		) {
			return;
		}

		$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		if ( $this->attachment_has_public_override( $attachment_id ) ) {
			$this->make_attachment_public( $attachment_id );
			return;
		}

		if ( null !== $relative_path && $this->attachment_matches_protected_policy( $attachment_id, $relative_path ) ) {
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			$this->sync_attachment_storage_for_current_mode( $attachment_id );
			return;
		}

		$this->make_attachment_public( $attachment_id );
	}

	private function folder_access_required_capability( $access ) {
		$access = PGM_Media_Organizer::sanitize_access_mode( $access );
		if ( PGM_Media_Organizer::ACCESS_ADMIN_ONLY === $access ) {
			return 'manage_options';
		}
		if ( PGM_Media_Organizer::ACCESS_LOGGED_IN === $access ) {
			return 'read';
		}

		return '';
	}

	private function attachment_matches_protected_policy( $attachment_id, $relative_path, $options = null ) {
		if ( null === $options ) {
			$options = $this->get_options();
		}

		if ( 'all' === $options['protect_mode'] ) {
			return true;
		}

		$extension = strtolower( pathinfo( (string) $relative_path, PATHINFO_EXTENSION ) );
		if ( 'marked_or_extension' === $options['protect_mode'] && $extension && in_array( $extension, $this->protected_extensions(), true ) ) {
			return true;
		}

		return ! $attachment_id && ! empty( $options['protect_unknown_uploads'] );
	}

	private function attachment_file_type_key( $attachment_id, $relative_path = '' ) {
		$mime      = $attachment_id ? strtolower( (string) get_post_mime_type( $attachment_id ) ) : '';
		$extension = strtolower( pathinfo( (string) $relative_path, PATHINFO_EXTENSION ) );

		if ( 'pdf' === $extension || 'application/pdf' === $mime ) {
			return 'pdf';
		}

		if ( in_array( $extension, array( 'doc', 'docx', 'docm', 'rtf', 'odt' ), true ) || false !== strpos( $mime, 'msword' ) || false !== strpos( $mime, 'wordprocessingml' ) ) {
			return 'word';
		}

		if ( in_array( $extension, array( 'xls', 'xlsx', 'xlsm', 'csv', 'ods' ), true ) || false !== strpos( $mime, 'excel' ) || false !== strpos( $mime, 'spreadsheetml' ) ) {
			return 'excel';
		}

		if ( in_array( $extension, array( 'ppt', 'pptx', 'pptm', 'odp' ), true ) || false !== strpos( $mime, 'powerpoint' ) || false !== strpos( $mime, 'presentationml' ) ) {
			return 'powerpoint';
		}

		if ( in_array( $extension, array( 'zip', 'rar', '7z', 'tar', 'gz' ), true ) ) {
			return 'archive';
		}

		if ( 0 === strpos( $mime, 'audio/' ) ) {
			return 'audio';
		}

		if ( 0 === strpos( $mime, 'video/' ) ) {
			return 'video';
		}

		if ( 0 === strpos( $mime, 'image/' ) ) {
			return '';
		}

		return $extension || $mime ? 'document' : '';
	}

	private function is_protected_upload( $attachment_id, $relative_path, $context = 'global' ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$options   = $this->get_options();
		$protected = false;
		$folder_access = $attachment_id ? $this->attachment_effective_folder_access( $attachment_id ) : PGM_Media_Organizer::ACCESS_PUBLIC;
		$public_override = $attachment_id ? $this->attachment_has_public_override( $attachment_id ) : false;

		if ( $attachment_id && get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true ) ) {
			$protected = true;
		} elseif ( PGM_Media_Organizer::ACCESS_PUBLIC !== $folder_access ) {
			$protected = true;
		} elseif ( $attachment_id && $this->attachment_is_hidden_from_public_uploads( $attachment_id ) ) {
			// Jos tiedosto on jo fyysisesti pois uploads-kansiosta, mikään vanha
			// uploads-linkki ei saa jäädä 404:ksi tai yrittää ohittaa suojausta.
			$protected = true;
		} elseif ( $attachment_id && $this->should_move_files_to_private_storage() && $this->attachment_has_protected_content_links( $attachment_id ) ) {
			$protected = true;
		} elseif ( $public_override ) {
			$protected = false;
		} elseif ( $this->attachment_matches_protected_policy( $attachment_id, $relative_path, $options ) ) {
			$protected = true;
		} elseif ( $attachment_id && get_post_meta( $attachment_id, self::META_PRIVATE, true ) ) {
			$protected = true;
		}

		return (bool) apply_filters( 'pgm_is_protected_upload', $protected, $attachment_id, $relative_path, $options, $context );
	}

	/**
	 * Suojatun tiedoston latausendpoint.
	 *
	 * Kaikki yksityiset lataukset kulkevat tämän kautta, jotta kirjautuminen,
	 * capability ja nonce tarkistetaan ennen tiedoston lukemista levyltä.
	 */
	public function handle_file_request() {
		if ( ! $this->is_enabled() ) {
			$this->deny_request( __( 'Lisäosan suojaus on pois käytöstä asetuksista.', 'private-gutenberg-media' ), 503 );
		}

		$relative_path = $this->get_requested_relative_path();
		if ( is_wp_error( $relative_path ) ) {
			$this->deny_request( $relative_path->get_error_message(), 404 );
		}

		$attachment_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$found_id      = $this->find_attachment_for_relative_path( $relative_path );

		if ( $attachment_id && ! $this->attachment_owns_relative_path( $attachment_id, $relative_path ) ) {
			$this->debug_log(
				'handle_file_request_denied',
				array(
					'reason' => 'attachment_does_not_own_path',
					'requested_attachment_id' => $attachment_id,
					'found_attachment_id' => $found_id,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			$this->deny_request( __( 'Tiedosto ei kuulu pyydettyyn medialiitteeseen.', 'private-gutenberg-media' ), 404 );
		}

		if ( ! $attachment_id ) {
			$attachment_id = $found_id;
		}

		$force_protected = ! empty( $_GET['pgm_force'] );
		$promote_result  = $this->maybe_promote_forced_private_request( $attachment_id, $relative_path, $force_protected );
		if ( is_wp_error( $promote_result ) ) {
			$this->deny_request( $promote_result->get_error_message(), 500 );
		}

		$protected = $this->is_protected_upload( $attachment_id, $relative_path, 'endpoint' );
		$this->debug_log(
			'handle_file_request_state',
			array(
				'found_attachment_id' => $found_id,
				'force_protected' => $force_protected,
				'protected' => $protected,
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		if ( $force_protected && ! $protected ) {
			$this->deny_request( __( 'Tiedostoa ei ole merkitty yksityiseksi. Suojaa mediatiedosto ensin Media Libraryssa tai Pecodex Media Libraryn kansiosuojauksella.', 'private-gutenberg-media' ), 403 );
		}

		if ( $protected ) {
			$this->authorize_private_file_request( $attachment_id, $relative_path, $force_protected );
			$storage_result = $this->ensure_protected_attachment_storage_is_private( $attachment_id, $relative_path, 'file_endpoint' );
			if ( is_wp_error( $storage_result ) ) {
				$this->deny_request( $storage_result->get_error_message(), 500 );
			}
		}

		$file_path = $this->resolve_file_path_for_request( $relative_path, $protected );
		if ( is_wp_error( $file_path ) ) {
			$this->debug_log(
				'handle_file_request_missing_file',
				array(
					'error' => $file_path->get_error_message(),
					'protected' => $protected,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			$this->deny_request( $file_path->get_error_message(), 404 );
		}

		$this->debug_log(
			'handle_file_request_resolved',
			array(
				'protected' => $protected,
				'resolved_path' => $file_path,
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);

		$this->serve_file( $file_path, $protected, $relative_path );
	}

	/**
	 * Aloittaa kirjautumattoman käyttäjän jäsenkirjautumisen yksityistä tiedostoa varten.
	 *
	 * Tämä endpoint ei tarjoile tiedostoa. Se ainoastaan varmistaa, että pyydetty
	 * tiedosto kuuluu suojauksen piiriin, luo lyhytaikaisen paluutokenin ja ohjaa
	 * käyttäjän FloMembersiin tai asetuksissa valittuun kirjautumiseen.
	 */
	public function handle_login_start_request() {
		if ( ! $this->is_enabled() ) {
			$this->deny_request( __( 'Lisäosan suojaus on pois käytöstä asetuksista.', 'private-gutenberg-media' ), 503 );
		}

		$relative_path = $this->get_requested_relative_path();
		if ( is_wp_error( $relative_path ) ) {
			$this->deny_request( $relative_path->get_error_message(), 404 );
		}

		$attachment_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$found_id      = $this->find_attachment_for_relative_path( $relative_path );

		if ( $attachment_id && ! $this->attachment_owns_relative_path( $attachment_id, $relative_path ) ) {
			$this->debug_log(
				'handle_login_start_denied',
				array(
					'reason' => 'attachment_does_not_own_path',
					'requested_attachment_id' => $attachment_id,
					'found_attachment_id' => $found_id,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			$this->deny_request( __( 'Tiedosto ei kuulu pyydettyyn medialiitteeseen.', 'private-gutenberg-media' ), 404 );
		}

		if ( ! $attachment_id ) {
			$attachment_id = $found_id;
		}

		$force_protected = ! empty( $_GET['pgm_force'] );
		$promote_result  = $this->maybe_promote_forced_private_request( $attachment_id, $relative_path, $force_protected );
		if ( is_wp_error( $promote_result ) ) {
			$this->deny_request( $promote_result->get_error_message(), 500 );
		}

		$is_protected_upload = $this->is_protected_upload( $attachment_id, $relative_path, 'endpoint' );
		$this->debug_log(
			'handle_login_start_state',
			array(
				'found_attachment_id' => $found_id,
				'force_protected' => $force_protected,
				'is_protected_upload' => $is_protected_upload,
				'is_user_logged_in' => is_user_logged_in(),
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);
		if ( ! $is_protected_upload ) {
			$this->debug_log(
				'handle_login_start_denied',
				array(
					'reason' => $force_protected ? 'force_requested_but_not_private' : 'not_private',
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			$this->deny_request( __( 'Tiedosto ei ole yksityinen.', 'private-gutenberg-media' ), 403 );
		}

		$storage_result = $this->ensure_protected_attachment_storage_is_private( $attachment_id, $relative_path, 'login_start' );
		if ( is_wp_error( $storage_result ) ) {
			$this->deny_request( $storage_result->get_error_message(), 500 );
		}

		if ( is_user_logged_in() ) {
			$this->mark_private_response_uncacheable();
			$private_url = $this->build_private_file_url( $attachment_id, $relative_path, $force_protected );
			if ( isset( $_GET['share_token'] ) ) {
				$private_url = add_query_arg( 'share_token', rawurlencode( sanitize_text_field( wp_unslash( $_GET['share_token'] ) ) ), $private_url );
			}
			$this->debug_log(
				'handle_login_start_redirect',
				array(
					'decision' => 'logged_in_to_nonce_url',
					'redirect_url' => $private_url,
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
			wp_safe_redirect( $private_url );
			exit;
		}

		$token        = wp_generate_password( 32, false, false );
		$return_data  = array(
			'file'  => $relative_path,
			'id'    => $attachment_id,
			'force' => $force_protected ? 1 : 0,
		);
		if ( isset( $_GET['share_token'] ) ) {
			$return_data['share_token'] = sanitize_text_field( wp_unslash( $_GET['share_token'] ) );
		}
		$continue_url = $this->build_login_return_url( $token );

		set_transient( $this->return_transient_key( $token ), $return_data, 15 * MINUTE_IN_SECONDS );
		$this->set_return_cookie( $token, 15 * MINUTE_IN_SECONDS );

		$this->mark_private_response_uncacheable();
		$this->debug_log(
			'handle_login_start_redirect',
			array(
				'decision' => 'login_flow',
				'continue_url' => $continue_url,
				'login_url' => $this->frontend_login_url( $continue_url ),
			) + $this->debug_attachment_state( $attachment_id, $relative_path )
		);
		wp_redirect( esc_url_raw( $this->frontend_login_url( $continue_url ) ) );
		exit;
	}

	/**
	 * Estää valheellisen "force protected" -tilan.
	 *
	 * Force-linkki saa nostaa tiedoston oikeasti yksityiseksi vain silloin, kun
	 * nykyinen käyttäjä pystyy muutenkin muokkaamaan kyseistä medialiitettä.
	 * Julkinen tai tavallinen kirjautunut käyttäjä ei voi muuttaa suojaustilaa
	 * pelkällä URL-parametrilla.
	 *
	 * @return true|WP_Error
	 */
	private function ensure_protected_attachment_storage_is_private( $attachment_id, $relative_path, $context ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return true;
		}

		if ( ! ( $this->should_move_files_to_private_storage() || $this->attachment_requires_private_storage( $attachment_id ) ) ) {
			return true;
		}

		if ( ! $this->attachment_has_public_upload_copy( $attachment_id ) ) {
			return true;
		}

		$result = $this->sync_attachment_storage_for_current_mode( $attachment_id );
		if ( is_wp_error( $result ) ) {
			$this->debug_log(
				'protected_storage_sync_failed',
				array(
					'context' => $context,
					'error'   => $result->get_error_message(),
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);

			return $result;
		}

		if ( $this->attachment_has_public_upload_copy( $attachment_id ) ) {
			$error = new WP_Error(
				'pgm_public_copy_remaining',
				__( 'Tiedoston suojausta ei voitu varmistaa, koska julkinen uploads-kopio on edelleen olemassa.', 'private-gutenberg-media' )
			);

			$this->debug_log(
				'protected_storage_sync_incomplete',
				array(
					'context' => $context,
					'error'   => $error->get_error_message(),
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);

			return $error;
		}

		return true;
	}

	private function maybe_promote_forced_private_request( $attachment_id, $relative_path, $force_protected ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $force_protected || ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return true;
		}

		if ( ! $this->attachment_owns_relative_path( $attachment_id, $relative_path ) ) {
			return true;
		}

		if (
			get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true )
			&& ! $this->attachment_has_public_upload_copy( $attachment_id )
			&& $this->attachment_has_private_storage_copy( $attachment_id )
		) {
			return true;
		}

		delete_post_meta( $attachment_id, self::META_PUBLIC_OVERRIDE );
		update_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, '1' );
		update_post_meta( $attachment_id, self::META_PRIVATE, '1' );

		$result = $this->sync_attachment_storage_for_current_mode( $attachment_id );
		if ( is_wp_error( $result ) ) {
			$this->debug_log(
				'force_private_promotion_failed',
				array(
					'error' => $result->get_error_message(),
				) + $this->debug_attachment_state( $attachment_id, $relative_path )
			);
		} else {
			$this->debug_log(
				'force_private_promotion_done',
				$this->debug_attachment_state( $attachment_id, $relative_path )
			);
		}

		return $result;
	}

	/**
	 * Palauttaa käyttäjän jäsenkirjautumisen jälkeen yksityiseen tiedostoon.
	 *
	 * Täällä käyttäjä on yleensä juuri kirjautunut FloAuthin kautta sisään. Vasta nyt
	 * voidaan luoda oikean käyttäjän nonce ja ohjata varsinaiseen tiedostoendpointtiin.
	 */
	public function handle_login_return_request() {
		if ( ! $this->is_enabled() ) {
			$this->deny_request( __( 'Lisäosan suojaus on pois käytöstä asetuksista.', 'private-gutenberg-media' ), 503 );
		}

		$token = $this->get_return_token_from_request();
		if ( '' === $token ) {
			$this->deny_request( __( 'Kirjautumisen paluutunniste puuttuu.', 'private-gutenberg-media' ), 403 );
		}

		$return_data = get_transient( $this->return_transient_key( $token ) );
		if ( ! is_array( $return_data ) || empty( $return_data['file'] ) ) {
			$this->clear_return_cookie();
			$this->deny_request( __( 'Kirjautumisen paluutunniste on vanhentunut. Avaa yksityinen linkki uudelleen.', 'private-gutenberg-media' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			$this->mark_private_response_uncacheable();
			wp_redirect( esc_url_raw( $this->frontend_login_url( $this->build_login_return_url( $token ) ) ) );
			exit;
		}

		delete_transient( $this->return_transient_key( $token ) );
		$this->clear_return_cookie();

		$relative_path = $this->normalize_relative_path( $return_data['file'] );
		if ( null === $relative_path ) {
			$this->deny_request( __( 'Virheellinen tiedostopolku.', 'private-gutenberg-media' ), 404 );
		}

		$this->mark_private_response_uncacheable();
		$private_url = $this->build_private_file_url(
			absint( $return_data['id'] ),
			$relative_path,
			! empty( $return_data['force'] )
		);
		if ( ! empty( $return_data['share_token'] ) ) {
			$private_url = add_query_arg( 'share_token', rawurlencode( $return_data['share_token'] ), $private_url );
		}
		wp_safe_redirect( $private_url );
		exit;
	}

	/**
	 * FloAuth kutsuu tätä kirjautumisen paluuvaiheessa ennen lopullista ohjausta.
	 *
	 * Jos käyttäjä tuli yksityisen tiedostolinkin kautta, palautetaan FloAuthille
	 * lisäosan oma paluupolku. Näin käyttäjä ei päädy wp-adminiin eikä pelkästään
	 * jäsenalueen etusivulle, vaan takaisin siihen tiedostoon jota hän oli avaamassa.
	 */
	public function modify_floauth_redirect_path( $redirect_path, $parameters ) {
		unset( $parameters );

		if ( ! $this->is_enabled() ) {
			return $redirect_path;
		}

		if ( 'floauth' !== $this->resolved_login_mode() ) {
			return $redirect_path;
		}

		// KOSKA FloAuth ei itse tue dynaamista return_url:ää sivupyyntöihin,
		// tarkistetaan ensin sivutason cookie ja palautetaan käyttäjä oikealle sivulle.
		if ( isset( $_COOKIE['pgm_page_continue_url'] ) ) {
			$continue_url = wp_unslash( $_COOKIE['pgm_page_continue_url'] );
			setcookie( 'pgm_page_continue_url', '', time() - 3600, '/' ); // Tyhjennetään heti
			
			$path  = wp_parse_url( $continue_url, PHP_URL_PATH );
			$query = wp_parse_url( $continue_url, PHP_URL_QUERY );
			return ltrim( $path . ( $query ? '?' . $query : '' ), '/' );
		}

		$token = $this->get_return_token_from_cookie();
		if ( '' === $token || false === get_transient( $this->return_transient_key( $token ) ) ) {
			return $redirect_path;
		}

		$return_url = $this->build_login_return_url( $token );
		$path       = wp_parse_url( $return_url, PHP_URL_PATH );
		$query      = wp_parse_url( $return_url, PHP_URL_QUERY );

		return ltrim( $path . ( $query ? '?' . $query : '' ), '/' );
	}

	/**
	 * Kaappaa FloAuthin tylyn "potkitaan kirjautumattomat etusivulle" -ohjauksen,
	 * asettaa paluuosoitteen talteen ja käynnistää fiksun OAuth-kirjautumisprosessin.
	 */
	public function override_floauth_native_redirect( $url ) {
		if ( ! $this->is_enabled() || 'floauth' !== $this->resolved_login_mode() ) {
			return $url;
		}

		global $wp;
		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		setcookie( 'pgm_page_continue_url', $current_url, time() + 3600, '/' );

		// Palautetaan osoite joka laukaisee FloAuthin kirjautumisprosessin lennosta
		return $this->frontend_login_url( $current_url );
	}

	private function build_login_return_url( $token ) {
		return add_query_arg(
			array(
				'action'    => self::PECODEX_ACTION_RETURN,
				'pgm_token' => $token,
			),
			admin_url( 'admin-post.php' )
		);
	}

	private function frontend_login_url( $continue_url ) {
		$options    = $this->get_options();
		$login_mode = $this->resolved_login_mode( $options );

		if ( 'wordpress' === $login_mode ) {
			return wp_login_url( $continue_url );
		}

		if ( 'custom' === $login_mode && ! empty( $options['custom_login_url'] ) ) {
			return add_query_arg( 'redirect_to', $continue_url, $options['custom_login_url'] );
		}

		// FloAuth käynnistyy sivuston etusivun floauth=pages-parametrilla ja ohjaa siitä FloMembersiin.
		return add_query_arg(
			array(
				'floauth'           => 'pages',
				'pgm_private_media' => '1',
			),
			home_url( '/' )
		);
	}

	/**
	 * Ratkaisee todellisen kirjautumistavan asetusten ja sivuston lisäosien mukaan.
	 *
	 * auto tekee lisäosasta yleiskäyttöisen: FloAuthia käytetään vain, jos se on
	 * oikeasti aktiivinen ja sillä on välttämättömät asetukset. Muuten pudotaan
	 * WordPressin omaan kirjautumiseen, joka toimii kaikilla WordPress-sivustoilla.
	 */
	private function resolved_login_mode( $options = null ) {
		if ( null === $options ) {
			$options = $this->get_options();
		}

		$login_mode = isset( $options['login_mode'] ) ? $options['login_mode'] : 'auto';

		if ( 'custom' === $login_mode ) {
			return empty( $options['custom_login_url'] ) ? 'wordpress' : 'custom';
		}

		if ( 'floauth' === $login_mode ) {
			return $this->floauth_is_available() ? 'floauth' : 'wordpress';
		}

		if ( 'wordpress' === $login_mode ) {
			return 'wordpress';
		}

		return $this->floauth_is_available() ? 'floauth' : 'wordpress';
	}

	/**
	 * Tarkistaa onko FloAuth/FloMembers-kirjautuminen käytettävissä.
	 *
	 * Pelkkä lisäosan tiedoston olemassaolo ei riitä: FloAuth tarvitsee myös
	 * FloMembers URLin ja salaisen avaimen, muuten se päätyy tyhjään ohjaukseen.
	 */
	private function floauth_is_available() {
		return function_exists( 'floauth_init' )
			&& '' !== trim( (string) get_option( 'floauth_flomembers_url' ) )
			&& '' !== trim( (string) get_option( 'floauth_secret_key' ) );
	}

	private function return_transient_key( $token ) {
		return 'pgm_private_media_return_' . md5( $token );
	}

	private function set_return_cookie( $token, $ttl ) {
		setcookie(
			self::RETURN_COOKIE,
			$token,
			$this->return_cookie_options( time() + (int) $ttl )
		);

		$_COOKIE[ self::RETURN_COOKIE ] = $token;
	}

	private function clear_return_cookie() {
		setcookie(
			self::RETURN_COOKIE,
			'',
			$this->return_cookie_options( time() - HOUR_IN_SECONDS )
		);

		unset( $_COOKIE[ self::RETURN_COOKIE ] );
	}

	private function get_return_token_from_request() {
		$token = isset( $_GET['pgm_token'] ) ? sanitize_text_field( wp_unslash( $_GET['pgm_token'] ) ) : '';

		if ( '' !== $token ) {
			return $token;
		}

		return $this->get_return_token_from_cookie();
	}

	private function get_return_token_from_cookie() {
		return isset( $_COOKIE[ self::RETURN_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::RETURN_COOKIE ] ) ) : '';
	}

	private function return_cookie_options( $expires ) {
		$options = array(
			'expires'  => (int) $expires,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
			$options['domain'] = COOKIE_DOMAIN;
		}

		return $options;
	}

	private function get_requested_relative_path() {
		$file = isset( $_GET['file'] ) ? rawurldecode( (string) wp_unslash( $_GET['file'] ) ) : '';
		
		if ( '' === $file && ! empty( $_GET['id'] ) ) {
			$attachment_id = absint( $_GET['id'] );
			$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
			if ( $attached_file ) {
				$file = $attached_file;
			}
		}

		$file = $this->normalize_relative_path( $file );

		if ( null === $file ) {
			return new WP_Error( 'pgm_bad_file', __( 'Virheellinen tiedostopolku.', 'private-gutenberg-media' ) );
		}

		return $file;
	}

	private function resolve_file_path_for_request( $relative_path, $protected ) {
		if ( $protected ) {
			$private_file = $this->private_file_path_if_exists( $relative_path );
			if ( $private_file ) {
				$this->debug_log(
					'resolve_file_path_for_request',
					array(
						'decision'      => 'private_storage',
						'relative_path' => $relative_path,
						'protected'     => $protected,
						'resolved_path' => $private_file,
					)
				);
				return $private_file;
			}
		}

		$public_file = $this->public_upload_file_path_if_exists( $relative_path );
		if ( $public_file ) {
			$this->debug_log(
				'resolve_file_path_for_request',
				array(
					'decision'      => 'public_uploads',
					'relative_path' => $relative_path,
					'protected'     => $protected,
					'resolved_path' => $public_file,
				)
			);
			return $public_file;
		}

		$this->debug_log(
			'resolve_file_path_for_request',
			array(
				'decision'      => 'missing',
				'relative_path' => $relative_path,
				'protected'     => $protected,
			)
		);

		return new WP_Error( 'pgm_missing_file', __( 'Tiedostoa ei löytynyt.', 'private-gutenberg-media' ) );
	}

	/**
	 * Tarkistaa julkisen uploads-kansion tiedoston turvallisesti realpath()-polulla.
	 */
	private function public_upload_file_path_if_exists( $relative_path ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$base_dir = realpath( $uploads['basedir'] );
		$target   = realpath( trailingslashit( $uploads['basedir'] ) . $relative_path );

		if ( ! $base_dir || ! $target || ! is_file( $target ) ) {
			return false;
		}

		$base_dir_normalized = wp_normalize_path( trailingslashit( $base_dir ) );
		$target_normalized   = wp_normalize_path( $target );

		if ( 0 !== strpos( $target_normalized, $base_dir_normalized ) ) {
			return false;
		}

		return $target;
	}

	/**
	 * Varmistaa, että nykyisellä käyttäjällä on oikeus lukea yksityinen tiedosto.
	 */
	private function public_converted_upload_copy_exists( $relative_path ) {
		return ! empty( $this->public_converted_upload_file_paths( $relative_path ) );
	}

	private function delete_public_converted_upload_copies( $relative_path ) {
		foreach ( $this->public_converted_upload_file_paths( $relative_path ) as $path ) {
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	private function public_converted_upload_file_paths( $relative_path ) {
		$paths = array();

		foreach ( $this->public_converted_upload_file_candidates( $relative_path ) as $candidate ) {
			if ( empty( $candidate['path'] ) ) {
				continue;
			}

			$target = $this->public_converted_upload_file_path_if_exists( $candidate['path'] );
			if ( $target ) {
				$paths[] = $target;
			}
		}

		return array_values( array_unique( $paths ) );
	}

	private function public_converted_upload_file_urls( $relative_path ) {
		$urls = array();

		foreach ( $this->public_converted_upload_file_candidates( $relative_path ) as $candidate ) {
			if ( empty( $candidate['path'] ) || empty( $candidate['url'] ) ) {
				continue;
			}

			if ( $this->public_converted_upload_file_path_if_exists( $candidate['path'] ) ) {
				$urls[] = $candidate['url'];
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private function public_converted_upload_file_candidates( $relative_path ) {
		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return array();
		}

		$relative_candidates = $this->converted_upload_relative_path_candidates( $relative_path );
		$candidates          = array();
		$uploads             = wp_get_upload_dir();

		if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
			foreach ( $relative_candidates as $candidate_relative ) {
				$candidates[] = array(
					'path' => trailingslashit( $uploads['basedir'] ) . $candidate_relative,
					'url'  => $this->upload_relative_path_to_url( $candidate_relative ),
				);
			}
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$content_bases = array(
				'uploads-webpc/uploads',
				'webp-express/webp-images/doc-root/wp-content/uploads',
			);

			foreach ( $content_bases as $content_base ) {
				foreach ( $relative_candidates as $candidate_relative ) {
					$candidates[] = array(
						'path' => trailingslashit( WP_CONTENT_DIR ) . trailingslashit( $content_base ) . $candidate_relative,
						'url'  => trailingslashit( content_url( $content_base ) ) . implode( '/', array_map( 'rawurlencode', explode( '/', $candidate_relative ) ) ),
					);
				}
			}
		}

		/**
		 * Allows image optimization plugins to register extra generated public files
		 * that must be deleted/purged when the original attachment is protected.
		 *
		 * Each item should be an array with "path" and optional "url".
		 */
		$candidates = apply_filters( 'pecodex_media_control_public_converted_image_candidates', $candidates, $relative_path );
		$candidates = apply_filters( 'pgm_public_converted_upload_candidates', $candidates, $relative_path );

		$normalized = array();
		foreach ( (array) $candidates as $candidate ) {
			if ( ! is_array( $candidate ) || empty( $candidate['path'] ) ) {
				continue;
			}

			$path = wp_normalize_path( (string) $candidate['path'] );
			$url  = empty( $candidate['url'] ) ? '' : esc_url_raw( (string) $candidate['url'] );
			$key  = strtolower( $path );

			$normalized[ $key ] = array(
				'path' => $path,
				'url'  => $url,
			);
		}

		return array_values( $normalized );
	}

	private function converted_upload_relative_path_candidates( $relative_path ) {
		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return array();
		}

		$candidates = array();
		foreach ( array( '.webp', '.avif', '.optm.webp', '.optm.avif' ) as $suffix ) {
			$candidates[] = $relative_path . $suffix;
		}

		$extension = pathinfo( $relative_path, PATHINFO_EXTENSION );
		if ( '' !== $extension ) {
			$without_extension = substr( $relative_path, 0, -1 * ( strlen( $extension ) + 1 ) );
			foreach ( array( '.webp', '.avif' ) as $suffix ) {
				$candidates[] = $without_extension . $suffix;
			}
		}

		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	private function public_converted_upload_file_path_if_exists( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$target = realpath( $path );
		if ( ! $target || ! is_file( $target ) ) {
			return false;
		}

		$allowed_bases = array();
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$allowed_bases[] = WP_CONTENT_DIR;
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['basedir'] ) ) {
			$allowed_bases[] = $uploads['basedir'];
		}

		$target_normalized = wp_normalize_path( $target );
		foreach ( array_unique( $allowed_bases ) as $base ) {
			$base_real = realpath( $base );
			if ( ! $base_real ) {
				continue;
			}

			$base_normalized = wp_normalize_path( trailingslashit( $base_real ) );
			if ( 0 === strpos( $target_normalized, $base_normalized ) ) {
				return $target;
			}
		}

		return false;
	}

	private function authorize_private_file_request( $attachment_id, $relative_path, $force_protected = false ) {
		if ( ! is_user_logged_in() ) {
			$this->mark_private_response_uncacheable();
			$login_url = $this->build_login_start_url( $attachment_id, $relative_path, $force_protected );
			if ( isset( $_GET['share_token'] ) ) {
				$login_url = add_query_arg( 'share_token', rawurlencode( sanitize_text_field( wp_unslash( $_GET['share_token'] ) ) ), $login_url );
			}
			wp_safe_redirect( $login_url );
			exit;
		}

		// Share token ohittaa kansio-oikeustarkistukset, mutta vaatii kirjautumisen.
		if ( isset( $_GET['share_token'] ) && $attachment_id ) {
			$token = sanitize_text_field( wp_unslash( $_GET['share_token'] ) );
			if ( $this->verify_and_consume_share_token( $attachment_id, $token ) ) {
				return;
			}
			// Token annettu mutta ei kelpaa — ei ohjata kirjautumiseen vaan estetään.
			$this->deny_request( 'Jakolinkki on vanhentunut tai jo käytetty.', 403 );
		}

		$options    = $this->get_options();
		$folder_access = $attachment_id ? $this->attachment_effective_folder_access( $attachment_id ) : PGM_Media_Organizer::ACCESS_PUBLIC;
		
		$file_roles = $attachment_id ? get_post_meta( $attachment_id, '_pgm_visibility_roles', true ) : array();
		$file_roles = is_array( $file_roles ) ? $file_roles : array();

		// Jos yksittäisellä tiedostolla on omat roolit, se muuttuu automaattisesti roolipohjaiseksi
		if ( ! empty( $file_roles ) ) {
			$folder_access = PGM_Media_Organizer::ACCESS_ROLE_BASED;
		}

		$folder_capability = $this->folder_access_required_capability( $folder_access );
		$capability = $folder_capability ? $folder_capability : apply_filters( 'pgm_private_media_required_capability', $options['required_capability'], $attachment_id, $relative_path );
		$admin_can_manage = current_user_can( 'manage_options' );

		if ( PGM_Media_Organizer::ACCESS_ROLE_BASED === $folder_access && ! $admin_can_manage ) {
			$allowed_roles = PGM_Media_Organizer::instance()->attachment_folder_roles( $attachment_id );
			
			// Yhdistetään kansion sallitut roolit ja tiedoston omat roolit
			if ( ! empty( $file_roles ) ) {
				$allowed_roles = empty( $allowed_roles ) ? $file_roles : array_unique( array_merge( $allowed_roles, $file_roles ) );
			}

			$user = wp_get_current_user();
			$has_role = false;
			if ( $user->exists() ) {
				foreach ( $allowed_roles as $role ) {
					if ( in_array( $role, (array) $user->roles, true ) ) {
						$has_role = true;
						break;
					}
				}
			}
			if ( ! $has_role ) {
				$this->deny_request( __( 'Sinulla ei ole oikeutta avata tätä tiedostoa.', 'private-gutenberg-media' ), 403 );
			}
		} elseif ( $capability && ! $admin_can_manage && ! current_user_can( $capability ) ) {
			$this->deny_request( __( 'Sinulla ei ole oikeutta avata tätä tiedostoa.', 'private-gutenberg-media' ), 403 );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, $this->nonce_action( $attachment_id, $relative_path ) ) ) {
			$this->redirect_invalid_nonce_request();
		}

		$token_result = $this->verify_private_download_token( $attachment_id, $relative_path, $force_protected );
		if ( is_wp_error( $token_result ) ) {
			$this->mark_private_response_uncacheable();
			wp_safe_redirect( $this->build_login_start_url( $attachment_id, $relative_path, $force_protected ) );
			exit;
		}

		$allowed = apply_filters( 'pgm_user_can_read_private_upload', true, get_current_user_id(), $attachment_id, $relative_path );
		if ( ! $admin_can_manage && ! $allowed ) {
			$this->deny_request( __( 'Sinulla ei ole oikeutta avata tätä tiedostoa.', 'private-gutenberg-media' ), 403 );
		}
	}

	private function nonce_action( $attachment_id, $relative_path ) {
		return 'pgm_private_file_' . (int) $attachment_id . '_' . md5( $relative_path );
	}

	private function verify_private_download_token( $attachment_id, $relative_path, $force_protected = false ) {
		$token = $this->requested_private_download_token();
		if ( '' === $token ) {
			return new WP_Error( 'pgm_missing_download_token', __( 'Suojatun tiedoston avaustunniste puuttuu.', 'private-gutenberg-media' ) );
		}

		$data = get_transient( $this->download_token_transient_key( $token ) );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'pgm_expired_download_token', __( 'Suojatun tiedoston avaustunniste on vanhentunut.', 'private-gutenberg-media' ) );
		}

		$relative_path    = $this->normalize_relative_path( $relative_path );
		$current_session = hash( 'sha256', (string) wp_get_session_token() );

		$matches = isset( $data['user_id'], $data['session'], $data['id'], $data['file'], $data['force'] )
			&& (int) $data['user_id'] === get_current_user_id()
			&& hash_equals( (string) $data['session'], $current_session )
			&& absint( $data['id'] ) === absint( $attachment_id )
			&& null !== $relative_path
			&& hash_equals( (string) $data['file'], $relative_path )
			&& (int) $data['force'] === ( $force_protected ? 1 : 0 );

		if ( ! $matches ) {
			return new WP_Error( 'pgm_invalid_download_token', __( 'Suojatun tiedoston avaustunniste ei kelpaa tälle käyttäjälle tai tiedostolle.', 'private-gutenberg-media' ) );
		}

		return true;
	}

	/**
	 * Lähettää tiedoston selaimelle.
	 *
	 * Yksityisille tiedostoille asetetaan no-store/no-cache-headerit, jotta selain,
	 * välityspalvelin tai CDN ei tallenna tiedostoa julkiseen välimuistiin.
	 */
	private function serve_file( $absolute_path, $protected, $relative_path = '' ) {
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}

		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );

		$display_path = '' !== $relative_path ? $relative_path : $absolute_path;
		$extension    = strtolower( pathinfo( $display_path, PATHINFO_EXTENSION ) );
		$blocked   = array( 'php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'bash', 'exe', 'dll', 'so', 'asp', 'aspx', 'jsp', 'html', 'htm' );

		if ( in_array( $extension, $blocked, true ) ) {
			$this->deny_request( __( 'Tätä tiedostotyyppiä ei tarjoilla yksityisen median endpointin kautta.', 'private-gutenberg-media' ), 403 );
		}

		$filetype = wp_check_filetype( $display_path );
		$mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : 'application/octet-stream';
		$filename = wp_basename( $display_path );
		$contents = null;
		$file_size = is_file( $absolute_path ) ? filesize( $absolute_path ) : false;
		$encrypted_private_file = $protected && $this->is_encrypted_private_file( $absolute_path );
		$is_private_payload_path = '.pgm.php' === strtolower( substr( $absolute_path, -8 ) );

		$this->debug_log(
			'serve_file_prepare',
			array(
				'protected'     => (bool) $protected,
				'path'          => $absolute_path,
				'relative_path' => $relative_path,
				'mime'          => $mime,
				'encrypted'     => $encrypted_private_file,
				'file_size'     => false === $file_size ? null : (int) $file_size,
				'zlib_output_compression' => ini_get( 'zlib.output_compression' ),
			)
		);

		if ( $encrypted_private_file ) {
			$contents = $this->decrypt_private_file_contents( $absolute_path );
			if ( is_wp_error( $contents ) ) {
				$this->debug_log(
					'serve_file_decrypt_failed',
					array(
						'path'          => $absolute_path,
						'relative_path' => $relative_path,
						'error_code'    => $contents->get_error_code(),
						'error_message' => $contents->get_error_message(),
					)
				);
				$this->deny_request( $contents->get_error_message(), 500 );
			}
		} elseif ( $protected && $is_private_payload_path ) {
			$this->debug_log(
				'serve_file_decrypt_failed',
				array(
					'path'          => $absolute_path,
					'relative_path' => $relative_path,
					'error_code'    => 'pgm_private_payload_not_detected',
					'error_message' => 'Private storage payload was not detected as encrypted.',
				)
			);
			$this->deny_request( __( 'Yksityistä tiedostoa ei voitu purkaa.', 'private-gutenberg-media' ), 500 );
		}

		$bytes = null === $contents ? $file_size : strlen( $contents );
		if ( false === $bytes ) {
			$this->debug_log(
				'serve_file_missing',
				array(
					'path'          => $absolute_path,
					'relative_path' => $relative_path,
				)
			);
			$this->deny_request( __( 'Tiedostoa ei löytynyt.', 'private-gutenberg-media' ), 404 );
		}
		$bytes = (int) $bytes;

		if (
			'application/pdf' === $mime
			&& method_exists( $this, 'should_serve_pdf_viewer_page' )
			&& method_exists( $this, 'serve_pdf_viewer_page' )
			&& $this->should_serve_pdf_viewer_page()
		) {
			$this->serve_pdf_viewer_page( $filename, $protected );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'Content-Encoding' );
		}

		if ( $protected ) {
			$this->mark_private_response_uncacheable();
		}

		status_header( 200 );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . $bytes );
		header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Accept-Ranges: none' );
		header( 'X-Pecodex-Private-Media: ' . ( $protected ? 'protected' : 'public' ) );
		header( 'X-PGM-Private-Media: ' . ( $protected ? 'protected' : 'public' ) );
		header( 'X-Pecodex-Security: ' . ( $protected ? 'protected' : 'public' ) );

		if ( $protected ) {
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform' );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		} else {
			header( 'Cache-Control: public, max-age=300' );
		}

		$this->debug_log(
			'serve_file_response',
			array(
				'protected'     => (bool) $protected,
				'relative_path' => $relative_path,
				'mime'          => $mime,
				'filename'      => $filename,
				'bytes'         => $bytes,
				'encrypted'     => $encrypted_private_file,
			)
		);

		if ( null === $contents ) {
			$chunk_size = 1024 * 1024; // 1 MB
			$handle = @fopen( $absolute_path, 'rb' );
			if ( $handle ) {
				while ( ! feof( $handle ) ) {
					echo fread( $handle, $chunk_size );
					if ( ob_get_level() ) @ob_flush();
					@flush();
				}
				fclose( $handle );
			}
		} else {
			$chunk_size = 1024 * 1024; // 1 MB
			$length = strlen( $contents );
			for ( $i = 0; $i < $length; $i += $chunk_size ) {
				echo substr( $contents, $i, $chunk_size );
				if ( ob_get_level() ) @ob_flush();
				@flush();
			}
		}
		exit;
	}

	/**
	 * Palauttaa true, kun selain avaa PDF:n dokumenttinäkymänä eikä API/fetch-pyynnöllä.
	 */
	private function should_serve_pdf_viewer_page() {
		if ( ! empty( $_GET['pgm_raw'] ) ) {
			return false;
		}

		$fetch_dest = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) : '';

		return in_array( $fetch_dest, array( 'document', 'iframe' ), true );
	}

	/**
	 * Tarjoilee kevyen HTML + PDF.js -katselijan inline-PDF:n sijaan.
	 *
	 * Tämä välttää selaimen sisäänrakennetun PDF-laajennuksen, joka aiheuttaa
	 * wheel-passive -varoituksia konsolissa.
	 */
	private function serve_pdf_viewer_page( $filename, $protected ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( $protected ) {
			$this->mark_private_response_uncacheable();
		}

		$pdf_js_url = esc_url( plugins_url( 'assets/vendor/pdf.min.js', __FILE__ ) );
		$worker_url = esc_url_raw( plugins_url( 'assets/vendor/pdf.worker.min.js', __FILE__ ) );
		$raw_url    = esc_url_raw( add_query_arg( 'pgm_raw', '1' ) );
		$title      = esc_html( $filename );
		$error_text = esc_js( __( 'PDF:n avaaminen epäonnistui.', 'private-gutenberg-media' ) );
		$loading    = esc_html( __( 'Ladataan PDF-esikatselua...', 'private-gutenberg-media' ) );
		$download   = esc_html( __( 'Lataa PDF', 'private-gutenberg-media' ) );

		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );

		if ( $protected ) {
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		} else {
			header( 'Cache-Control: private, max-age=0, must-revalidate' );
		}

		echo '<!DOCTYPE html>
<html lang="fi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $title . '</title>
<style>
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;background:#525659;color:#f8fafc;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.pgm-pdf-viewer-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:#1f2937;border-bottom:1px solid #374151}
.pgm-pdf-viewer-header h1{margin:0;font-size:15px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pgm-pdf-viewer-header a{color:#93c5fd;text-decoration:none;font-size:13px;white-space:nowrap}
.pgm-pdf-viewer-stage{padding:20px;display:flex;flex-direction:column;align-items:center;gap:16px;min-height:calc(100vh - 53px);overflow:auto}
.pgm-pdf-viewer-stage canvas{display:block;max-width:100%;height:auto;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.35)}
.pgm-pdf-viewer-message{padding:24px;color:#cbd5e1;font-size:14px;text-align:center}
</style>
</head>
<body>
<header class="pgm-pdf-viewer-header">
<h1>' . $title . '</h1>
<a href="' . $raw_url . '" download="' . esc_attr( $filename ) . '">' . $download . '</a>
</header>
<div id="pgm-pdf-viewer" class="pgm-pdf-viewer-stage" role="main">
<div class="pgm-pdf-viewer-message">' . $loading . '</div>
</div>
<script src="' . $pdf_js_url . '"></script>
<script>
(function () {
	var viewer = document.getElementById("pgm-pdf-viewer");
	if (!window.pdfjsLib || !viewer) {
		viewer.textContent = ' . wp_json_encode( $error_text ) . ';
		return;
	}
	pdfjsLib.GlobalWorkerOptions.workerSrc = ' . wp_json_encode( $worker_url ) . ';
	function renderPage(pdf, pageNum) {
		return pdf.getPage(pageNum).then(function (page) {
			var baseViewport = page.getViewport({ scale: 1 });
			var scale = Math.min(1.5, Math.max(0.75, (window.innerWidth - 48) / baseViewport.width));
			var viewport = page.getViewport({ scale: scale });
			var canvas = document.createElement("canvas");
			var context = canvas.getContext("2d", { alpha: false });
			canvas.width = viewport.width;
			canvas.height = viewport.height;
			return page.render({ canvasContext: context, viewport: viewport }).promise.then(function () {
				viewer.appendChild(canvas);
				if (pageNum < pdf.numPages) {
					return renderPage(pdf, pageNum + 1);
				}
			});
		});
	}
	pdfjsLib.getDocument({
		url: ' . wp_json_encode( $raw_url ) . ',
		standardFontDataUrl: "https://unpkg.com/pdfjs-dist@3.11.174/standard_fonts/",
		cMapUrl: "https://unpkg.com/pdfjs-dist@3.11.174/cmaps/",
		cMapPacked: true
	}).promise.then(function (pdf) {
		viewer.textContent = "";
		return renderPage(pdf, 1);
	}).catch(function () {
		viewer.innerHTML = "<div class=\\"pgm-pdf-viewer-message\\">' . $error_text . '</div>";
	});
})();
</script>
</body>
</html>';
		exit;
	}

	/**
	 * Merkitsee nykyisen vastauksen välimuistille sopimattomaksi ja pyytää
	 * selainta tyhjentämään saman sivuston HTTP-cachen, kun selain tukee sitä.
	 *
	 * Varsinainen pääsynhallinta tehdään tiedoston siirrolla pois uploadsista,
	 * kirjautumisella, capabilityllä ja nonce-tarkistuksella. Tämä kerros hoitaa
	 * selaimet, proxy-cachet ja WordPressin cache-lisäosat parhaansa mukaan.
	 */
	private function mark_origin_cache_clear_response() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}

		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}

		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'X-Pecodex-Security: cache-clear' );
			header( 'Surrogate-Control: no-store' );
			header( 'Pragma: no-cache' );
			header( 'Vary: Cookie, Authorization' );
			if ( $this->can_send_clear_site_data_header() ) {
				header( 'Clear-Site-Data: "cache"' );
			}
		}

		/**
		 * LiteSpeed Cache kuuntelee tätä actionia, mutta jos lisäosaa ei ole
		 * asennettu, kutsu on WordPressissä harmiton no-op.
		 */
		do_action( 'litespeed_control_set_nocache', 'pecodex-media-library' );
	}

	private function mark_private_response_uncacheable() {
		$this->mark_origin_cache_clear_response();

		if ( ! headers_sent() ) {
			header( 'X-Pecodex-Private-Media-Flow: 1' );
			header( 'X-PGM-Private-Media-Flow: 1' );
			header( 'X-Pecodex-Security: protected' );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex' );
		}
	}

	private function can_send_clear_site_data_header() {
		return is_ssl();
	}

	private function deny_request( $message, $status ) {
		$url = home_url( '/' );
		$url = add_query_arg( array(
			'pgm_error'   => 'media_access_denied',
			'pgm_message' => urlencode( base64_encode( $message ) )
		), $url );
		
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Väärä tai vanhentunut nonce tarkoittaa käytännössä kopioitua linkkiä,
	 * eri käyttäjää, eri sessiota tai liian vanhaa selaimen välilehteä.
	 *
	 * Tällöin ei näytetä teknistä virhesivua, koska suojaus toimii oikein:
	 * linkkiä ei saa avata tällä käyttäjä/sessio-yhdistelmällä. Ohjataan sen
	 * sijaan neutraalisti etusivulle. Kehittäjä voi vaihtaa kohteen filtterillä.
	 */
	private function redirect_invalid_nonce_request() {
		$this->mark_private_response_uncacheable();
		nocache_headers();

		$redirect_url = apply_filters( 'pgm_invalid_nonce_redirect_url', home_url( '/' ) );
		$redirect_url = wp_validate_redirect( $redirect_url, home_url( '/' ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Sovittaa tiedoston fyysisen sijainnin nykyiseen yhteensopivuusasetukseen.
	 *
	 * Linkkisuojaustilassa pidetään tai palautetaan tiedosto uploads-kansioon.
	 * Tiukassa tilassa tiedosto siirretään yksityiseen varastoon. Tätä keskitettyö
	 * metodia käyttämällä vältetään vahingossa tapahtuvat siirrot eri koukuista.
	 */
	private function sync_attachment_storage_for_current_mode( $attachment_id, $metadata = null ) {
		$this->clear_attachment_storage_cache( $attachment_id );

		if (
			$this->attachment_has_public_override( $attachment_id )
			&& ! get_post_meta( $attachment_id, self::META_PRIVATE_MANUAL, true )
			&& ! $this->attachment_has_folder_sources( $attachment_id )
			&& PGM_Media_Organizer::ACCESS_PUBLIC === $this->attachment_effective_folder_access( $attachment_id )
		) {
			delete_post_meta( $attachment_id, self::META_PRIVATE );
			$result = $this->restore_attachment_files_to_uploads( $attachment_id );
			if ( ! is_wp_error( $result ) ) {
				$this->sync_attachment_content_links( $attachment_id, false );
				$this->purge_attachment_public_url_caches( $attachment_id );
				$this->queue_public_upload_protection_rules_refresh();
			}

			return $result;
		}

		if ( $this->attachment_requires_private_storage( $attachment_id ) ) {
			update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			$result = $this->move_attachment_files_to_private_storage( $attachment_id, $metadata );
			if ( ! is_wp_error( $result ) ) {
				$this->sync_attachment_content_links( $attachment_id, true );
				$this->purge_attachment_public_url_caches( $attachment_id );
				$this->queue_public_upload_protection_rules_refresh();
			}

			return $result;
		}

		if ( $this->should_move_files_to_private_storage() ) {
			$result = $this->move_attachment_files_to_private_storage( $attachment_id, $metadata );
			if ( ! is_wp_error( $result ) ) {
				$this->sync_attachment_content_links( $attachment_id, true );
				$this->purge_attachment_public_url_caches( $attachment_id );
				$this->queue_public_upload_protection_rules_refresh();
			}

			return $result;
		}

		$result = $this->restore_attachment_files_to_uploads( $attachment_id );
		if ( ! is_wp_error( $result ) ) {
			$this->sync_attachment_content_links( $attachment_id, false );
			$this->purge_attachment_public_url_caches( $attachment_id );
			$this->queue_public_upload_protection_rules_refresh();
		}

		return $result;
	}

	private function purge_attachment_public_url_caches( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return array();
		}

		$urls = $this->attachment_public_url_cache_candidates( $attachment_id );
		if ( empty( $urls ) ) {
			return array();
		}

		clean_attachment_cache( $attachment_id );
		clean_post_cache( $attachment_id );
		wp_cache_delete( $attachment_id, 'posts' );
		wp_cache_delete( $attachment_id, 'post_meta' );

		foreach ( $urls as $url ) {
			$this->purge_litespeed_url_silently( $url );

			if ( function_exists( 'w3tc_flush_url' ) ) {
				w3tc_flush_url( $url );
			}
		}

		do_action( 'litespeed_purge_post', $attachment_id );
		do_action( 'pecodex_media_control_purge_attachment_cache', $attachment_id, $urls );

		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );
		}

		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $attachment_id );
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$this->debug_log(
			'attachment_public_url_caches_purged',
			array(
				'attachment_id' => $attachment_id,
				'urls'          => $urls,
			)
		);

		return $urls;
	}

	private function attachment_public_url_cache_candidates( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return array();
		}

		$urls = array();
		foreach ( $this->attachment_relative_paths( $attachment_id ) as $relative_path ) {
			$url = $this->upload_relative_path_to_url( $relative_path );
			if ( '' !== $url ) {
				$urls[] = $url;
			}

			foreach ( $this->public_converted_upload_file_urls( $relative_path ) as $converted_url ) {
				$urls[] = $converted_url;
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Keeps protected direct uploads URLs blocked at the web-server layer when
	 * Apache/LiteSpeed honors uploads .htaccess rules.
	 */
	private function queue_public_upload_protection_rules_refresh() {
		$this->needs_public_upload_protection_rules_refresh = true;
	}

	public function flush_queued_public_upload_protection_rules_refresh() {
		if ( ! $this->needs_public_upload_protection_rules_refresh ) {
			return;
		}

		$this->needs_public_upload_protection_rules_refresh = false;
		$this->refresh_public_upload_protection_rules();
	}

	private function refresh_public_upload_protection_rules() {
		if ( ! $this->is_enabled() ) {
			return $this->remove_public_upload_protection_rules();
		}

		$entries = $this->public_upload_protection_rule_entries();
		if ( empty( $entries ) ) {
			return $this->remove_public_upload_protection_rules();
		}

		$htaccess_path = $this->public_upload_htaccess_path_for_write();
		$filesystem    = $this->wp_filesystem_for_local_files();
		if ( ! $htaccess_path ) {
			$this->debug_log(
				'public_upload_protection_rules_refresh_failed',
				array(
					'reason' => 'uploads_htaccess_not_writable',
					'count'  => count( $entries ),
				)
			);
			return false;
		}
		$file_existed = file_exists( $htaccess_path );
		$existing     = '';
		if ( $file_existed ) {
			$existing = $filesystem ? $filesystem->get_contents( $htaccess_path ) : file_get_contents( $htaccess_path );
		}
		$existing = is_string( $existing ) ? $existing : '';
		$updated  = $this->replace_public_upload_protection_htaccess_block(
			$existing,
			$this->render_public_upload_protection_htaccess_block( $entries )
		);

		if ( hash_equals( hash( 'sha256', $existing ), hash( 'sha256', $updated ) ) ) {
			$this->set_public_upload_htaccess_status(
				'unchanged',
				array(
					'path'   => $htaccess_path,
					'count'  => count( $entries ),
					'server' => $this->server_software_name(),
				)
			);
			return true;
		}

		$this->backup_public_upload_htaccess_before_write( $htaccess_path, $existing );
		$written = $this->write_public_upload_htaccess_file( $htaccess_path, $updated );
		if ( $written && ! $file_existed ) {
			update_option( self::PUBLIC_UPLOAD_HTACCESS_CREATED_OPTION, 1, false );
		}

		$this->set_public_upload_htaccess_status(
			$written ? 'written' : 'write_failed',
			array(
				'path'   => $htaccess_path,
				'count'  => count( $entries ),
				'server' => $this->server_software_name(),
			)
		);
		$this->debug_log(
			$written ? 'public_upload_protection_rules_refreshed' : 'public_upload_protection_rules_write_failed',
			array(
				'path'  => $htaccess_path,
				'count' => count( $entries ),
				'server_uses_htaccess' => $this->server_likely_uses_htaccess(),
			)
		);

		return $written;
	}

	private function remove_public_upload_protection_rules() {
		$this->needs_public_upload_protection_rules_refresh = false;

		$htaccess_path = $this->public_upload_htaccess_path_for_write();
		if ( ! $htaccess_path || ! file_exists( $htaccess_path ) ) {
			delete_option( self::PUBLIC_UPLOAD_HTACCESS_CREATED_OPTION );
			return true;
		}

		$filesystem = $this->wp_filesystem_for_local_files();
		$existing   = $filesystem ? $filesystem->get_contents( $htaccess_path ) : file_get_contents( $htaccess_path );
		if ( ! is_string( $existing )
			|| (
				false === strpos( $existing, '# BEGIN Pecodex Media Control Protected Uploads' )
				&& false === strpos( $existing, '# BEGIN Private Gutenberg Media Protected Uploads' )
			)
		) {
			return true;
		}

		$updated = $this->strip_public_upload_protection_htaccess_block( $existing );
		$this->backup_public_upload_htaccess_before_write( $htaccess_path, $existing );
		if ( '' === trim( $updated ) ) {
			$created_by_plugin = (bool) get_option( self::PUBLIC_UPLOAD_HTACCESS_CREATED_OPTION, false );
			delete_option( self::PUBLIC_UPLOAD_HTACCESS_CREATED_OPTION );
			if ( $created_by_plugin ) {
				wp_delete_file( $htaccess_path );
				return true;
			}

			return $this->write_public_upload_htaccess_file( $htaccess_path, '' );
		}

		delete_option( self::PUBLIC_UPLOAD_HTACCESS_CREATED_OPTION );

		return $this->write_public_upload_htaccess_file( $htaccess_path, trim( $updated ) . PHP_EOL );
	}

	private function public_upload_htaccess_path_for_write() {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) || ! is_dir( $uploads['basedir'] ) ) {
			return false;
		}

		$base_dir = realpath( $uploads['basedir'] );
		if ( ! $base_dir ) {
			return false;
		}

		$path = trailingslashit( $base_dir ) . '.htaccess';
		if ( file_exists( $path ) ) {
			return is_writable( $path ) ? $path : false;
		}

		if ( ! is_writable( $base_dir ) ) {
			return false;
		}

		return $path;
	}

	private function wp_filesystem_for_local_files() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			return null;
		}

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			WP_Filesystem();
		}

		return $wp_filesystem instanceof WP_Filesystem_Base ? $wp_filesystem : null;
	}

	private function backup_public_upload_htaccess_before_write( $path, $existing ) {
		if ( ! is_string( $existing ) ) {
			return false;
		}

		return update_option(
			self::PUBLIC_UPLOAD_HTACCESS_BACKUP_OPTION,
			array(
				'path'       => wp_normalize_path( (string) $path ),
				'created_at' => time(),
				'sha256'     => hash( 'sha256', $existing ),
				'content'    => $existing,
			),
			false
		);
	}

	private function set_public_upload_htaccess_status( $status, $context = array() ) {
		update_option(
			self::PUBLIC_UPLOAD_HTACCESS_STATUS_OPTION,
			array(
				'status'     => sanitize_key( (string) $status ),
				'updated_at' => time(),
				'context'    => is_array( $context ) ? $this->debug_clean_context( $context ) : array(),
			),
			false
		);
	}

	private function write_public_upload_htaccess_file( $path, $content ) {
		$path    = (string) $path;
		$content = str_replace( array( "\r\n", "\r" ), "\n", (string) $content );

		$dir = dirname( $path );
		if ( ! is_dir( $dir ) || ( ! is_writable( $dir ) && ! file_exists( $path ) ) ) {
			return false;
		}

		if ( file_exists( $path ) && ! is_writable( $path ) ) {
			return false;
		}

		$result = file_put_contents( $path, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $result ) {
			return false;
		}

		@chmod( $path, 0644 );

		return true;
	}

	private function public_upload_protection_rule_entries() {
		$entries = array();

		foreach ( $this->protected_attachment_ids_for_upload_rules() as $attachment_id ) {
			foreach ( $this->attachment_relative_paths( $attachment_id ) as $relative_path ) {
				if ( ! $this->is_protected_upload( $attachment_id, $relative_path, 'uploads_htaccess' ) ) {
					continue;
				}

				$this->add_public_upload_protection_rule_entry( $entries, $attachment_id, $relative_path, $relative_path );

				foreach ( $this->converted_upload_relative_path_candidates( $relative_path ) as $converted_relative_path ) {
					$this->add_public_upload_protection_rule_entry( $entries, $attachment_id, $converted_relative_path, $relative_path );
				}
			}
		}

		ksort( $entries, SORT_NATURAL );

		return array_values( $entries );
	}

	private function add_public_upload_protection_rule_entry( &$entries, $attachment_id, $rule_relative_path, $target_relative_path ) {
		$rule_relative_path   = $this->normalize_relative_path( $rule_relative_path );
		$target_relative_path = $this->normalize_relative_path( $target_relative_path );

		if ( null === $rule_relative_path || null === $target_relative_path ) {
			return;
		}

		$entries[ $rule_relative_path ] = array(
			'path' => $rule_relative_path,
		);
	}

	private function protected_attachment_ids_for_upload_rules() {
		global $wpdb;

		$options = $this->get_options();
		$ids     = array();

		if ( in_array( $options['protect_mode'], array( 'all', 'marked_or_extension' ), true ) || ! empty( $options['protect_unknown_uploads'] ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
				)
			);
		} else {
			$meta_keys = array(
				self::META_PRIVATE,
				self::META_PRIVATE_MANUAL,
				self::META_FOLDER_SOURCES,
				self::META_CONTENT_LINKS_PRIVATE,
				self::META_ACTIVE_PROTECTED_URL,
			);
			$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
			$sql          = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND pm.meta_key IN ($placeholders)";
			$ids          = $wpdb->get_col( $wpdb->prepare( $sql, $meta_keys ) );
		}

		if ( taxonomy_exists( PGM_Media_Organizer::TAXONOMY ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => PGM_Media_Organizer::TAXONOMY,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$foldered_ids = get_objects_in_term( array_map( 'absint', $terms ), PGM_Media_Organizer::TAXONOMY );
				if ( ! is_wp_error( $foldered_ids ) && ! empty( $foldered_ids ) ) {
					$ids = array_merge( $ids, $foldered_ids );
				}
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		sort( $ids );

		return $ids;
	}

	private function render_public_upload_protection_htaccess_block( $entries ) {
		$lines = array(
			'# BEGIN Pecodex Media Control Protected Uploads',
			'<IfModule mod_headers.c>',
			'<FilesMatch "\.(?:pdf|doc|docx|xls|xlsx|ppt|pptx|odt|ods|odp|zip|jpg|jpeg|png|gif|webp|avif|heic|heif|mp4|webm|mov|mp3|wav|ogg)$">',
			'Header set Cache-Control "private, no-cache, must-revalidate, max-age=0, no-transform"',
			'Header set Pragma "no-cache"',
			'Header set Expires "0"',
			'Header set Vary "Cookie, Authorization"',
			'</FilesMatch>',
			'</IfModule>',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
		);

		foreach ( $entries as $entry ) {
			if ( empty( $entry['path'] ) ) {
				continue;
			}

			$pattern = $this->htaccess_rewrite_pattern_for_relative_path( $entry['path'] );
			if ( '' === $pattern ) {
				continue;
			}

			// We rewrite the file to a non-existent path to force Apache to fall back to the
			// WordPress root index.php handler for missing files. This avoids breaking $_SERVER 
			// variables on complex hosting environments (which causes DB connection errors).
			$lines[] = 'RewriteRule ' . $pattern . ' $0.pecodex_protected [L,E=PECODEX_PROTECTED_UPLOAD:1]';
		}

		$lines[] = '</IfModule>';
		$lines[] = '<IfModule mod_headers.c>';
		$lines[] = 'Header always set Cache-Control "private, no-store, no-cache, must-revalidate, max-age=0, no-transform" env=PECODEX_PROTECTED_UPLOAD';
		$lines[] = 'Header always set Pragma "no-cache" env=PECODEX_PROTECTED_UPLOAD';
		$lines[] = 'Header always set Expires "0" env=PECODEX_PROTECTED_UPLOAD';
		$lines[] = 'Header always unset ETag env=PECODEX_PROTECTED_UPLOAD';
		$lines[] = '</IfModule>';
		$lines[] = '# END Pecodex Media Control Protected Uploads';

		return implode( PHP_EOL, $lines ) . PHP_EOL;
	}

	private function htaccess_rewrite_pattern_for_relative_path( $relative_path ) {
		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return '';
		}

		$pattern = preg_quote( $relative_path, '#' );
		$pattern = str_replace( ' ', '\\ ', $pattern );

		return '^' . $pattern . '$';
	}

	private function replace_public_upload_protection_htaccess_block( $existing, $block ) {
		$clean = $this->strip_public_upload_protection_htaccess_block( $existing );
		$clean = trim( $clean );

		if ( '' === $clean ) {
			return $block;
		}

		return rtrim( $block ) . PHP_EOL . PHP_EOL . $clean . PHP_EOL;
	}

	private function strip_public_upload_protection_htaccess_block( $existing ) {
		$existing = (string) $existing;
		$blocks   = array(
			array( '# BEGIN Pecodex Media Control Protected Uploads', '# END Pecodex Media Control Protected Uploads' ),
			array( '# BEGIN Private Gutenberg Media Protected Uploads', '# END Private Gutenberg Media Protected Uploads' ),
		);

		foreach ( $blocks as $block ) {
			$pattern = '/(?:\R)?' . preg_quote( $block[0], '/' ) . '.*?' . preg_quote( $block[1], '/' ) . '(?:\R)?/s';
			$updated = preg_replace( $pattern, PHP_EOL, $existing );
			if ( is_string( $updated ) ) {
				$existing = $updated;
			}
		}

		return $existing;
	}

	/**
	 * Updates stored content URLs to match file-level visibility while keeping
	 * WordPress' canonical _wp_attached_file path restorable.
	 */
	private function sync_attachment_content_links( $attachment_id, $private ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$paths = $this->attachment_relative_paths( $attachment_id );
		if ( empty( $paths ) ) {
			return;
		}

		$this->remember_attachment_url_sync_state( $attachment_id, (bool) $private, $paths );

		$likes = array();
		foreach ( $paths as $relative_path ) {
			$filename = wp_basename( $relative_path );
			if ( '' !== $filename ) {
				$likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $filename ) . '%' );
			}
		}

		if ( ! $private ) {
			$likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( self::ACTION ) . '%' );
			$likes[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( self::PECODEX_ACTION ) . '%' );
		}

		$likes = array_values( array_unique( array_filter( $likes ) ) );
		if ( empty( $likes ) ) {
			return;
		}

		$post_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ('auto-draft', 'trash') AND (" . implode( ' OR ', $likes ) . ')'
		);

		$path_lookup = array_fill_keys( $paths, true );
		if ( ! empty( $post_ids ) ) {
			foreach ( array_map( 'absint', $post_ids ) as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post || ! is_string( $post->post_content ) || '' === $post->post_content ) {
					continue;
				}

				$updated_content = $this->sync_attachment_content_markup( $post->post_content, $attachment_id, $path_lookup, (bool) $private );
				$updated_content = $this->sync_attachment_reference_string( $updated_content, $attachment_id, $path_lookup, (bool) $private );
				if ( $updated_content === $post->post_content ) {
					continue;
				}

				$this->cleaning_post_content = true;
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $updated_content,
					)
				);
				$this->cleaning_post_content = false;
			}

			$this->purge_content_post_caches_for_attachment( $attachment_id, array_map( 'absint', $post_ids ) );
		}

		$this->sync_attachment_postmeta_links( $attachment_id, $path_lookup, (bool) $private );
		$this->sync_attachment_option_links( $attachment_id, $path_lookup, (bool) $private );
	}

	private function purge_content_post_caches_for_attachment( $attachment_id, $post_ids ) {
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );

			do_action( 'litespeed_purge_post', $post_id );

			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				$this->purge_litespeed_url_silently( $permalink );

				if ( function_exists( 'w3tc_flush_url' ) ) {
					w3tc_flush_url( $permalink );
				}
			}

			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id );
			}
		}

		do_action( 'pecodex_media_control_purge_content_post_caches', absint( $attachment_id ), $post_ids );

		$this->debug_log(
			'attachment_content_post_caches_purged',
			array(
				'attachment_id' => absint( $attachment_id ),
				'post_ids'      => $post_ids,
			)
		);
	}

	private function purge_litespeed_url_silently( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return;
		}

		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'cls' ) ) {
			try {
				$purge = \LiteSpeed\Purge::cls( 'Purge' );
				if ( is_object( $purge ) && method_exists( $purge, 'purge_url' ) ) {
					$purge->purge_url( $url, false, true );
					return;
				}
			} catch ( \Throwable $error ) {
				$this->debug_log(
					'litespeed_silent_url_purge_failed',
					array(
						'url'   => $url,
						'error' => $error->getMessage(),
					)
				);
			}
		}

		if ( ! defined( 'LITESPEED_PURGE_SILENT' ) ) {
			define( 'LITESPEED_PURGE_SILENT', true );
		}

		do_action( 'litespeed_purge_url', $url );
	}

	private function remember_attachment_url_sync_state( $attachment_id, $private, $paths = array() ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$relative_path = $this->normalize_relative_path( get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		if ( null === $relative_path ) {
			foreach ( (array) $paths as $candidate ) {
				$relative_path = $this->normalize_relative_path( $candidate );
				if ( null !== $relative_path ) {
					break;
				}
			}
		}

		if ( null === $relative_path ) {
			return;
		}

		$public_url = $this->upload_relative_path_to_url( $relative_path );
		if ( '' !== $public_url ) {
			update_post_meta( $attachment_id, self::META_ORIGINAL_PUBLIC_URL, esc_url_raw( $public_url ) );
		}

		if ( $private ) {
			if ( $this->should_move_files_to_private_storage() ) {
				update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			}
			update_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL, esc_url_raw( $this->build_login_start_url( $attachment_id, $relative_path, true ) ) );
			update_post_meta( $attachment_id, self::META_CONTENT_LINKS_PRIVATE, '1' );
		} else {
			delete_post_meta( $attachment_id, self::META_ACTIVE_PROTECTED_URL );
			delete_post_meta( $attachment_id, self::META_CONTENT_LINKS_PRIVATE );
		}

		update_post_meta( $attachment_id, self::META_CONTENT_LINKS_SYNCED_AT, time() );
	}

	private function sync_attachment_content_markup( $content, $attachment_id, $path_lookup, $private ) {
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );

			while ( $processor->next_tag() ) {
				foreach ( array( 'href', 'src', 'poster' ) as $attribute ) {
					$value = $processor->get_attribute( $attribute );
					if ( is_string( $value ) ) {
						$updated = $this->sync_attachment_content_url( $value, $attachment_id, $path_lookup, $private );
						if ( $updated !== $value ) {
							$processor->set_attribute( $attribute, $updated );
						}
					}
				}

				$srcset = $processor->get_attribute( 'srcset' );
				if ( is_string( $srcset ) ) {
					$updated_srcset = $this->sync_attachment_content_srcset( $srcset, $attachment_id, $path_lookup, $private );
					if ( $updated_srcset !== $srcset ) {
						$processor->set_attribute( 'srcset', $updated_srcset );
					}
				}
			}

			return $processor->get_updated_html();
		}

		$content = preg_replace_callback(
			'/\b(href|src|poster)=([\'"])(.*?)\2/i',
			function ( $matches ) use ( $attachment_id, $path_lookup, $private ) {
				$updated = $this->sync_attachment_content_url( $matches[3], $attachment_id, $path_lookup, $private );

				return $matches[1] . '=' . $matches[2] . esc_attr( $updated ) . $matches[2];
			},
			$content
		);

		return preg_replace_callback(
			'/\bsrcset=([\'"])(.*?)\1/i',
			function ( $matches ) use ( $attachment_id, $path_lookup, $private ) {
				$updated = $this->sync_attachment_content_srcset( $matches[2], $attachment_id, $path_lookup, $private );

				return 'srcset=' . $matches[1] . esc_attr( $updated ) . $matches[1];
			},
			$content
		);
	}

	private function sync_attachment_content_srcset( $srcset, $attachment_id, $path_lookup, $private ) {
		$out = array();

		foreach ( explode( ',', $srcset ) as $item ) {
			if ( ! preg_match( '/^(\s*)(\S+)(.*)$/', $item, $matches ) ) {
				$out[] = $item;
				continue;
			}

			$out[] = $matches[1] . $this->sync_attachment_content_url( $matches[2], $attachment_id, $path_lookup, $private ) . $matches[3];
		}

		return implode( ', ', $out );
	}

	private function sync_attachment_content_url( $url, $attachment_id, $path_lookup, $private ) {
		$relative_path = $private ? $this->url_to_managed_upload_relative_path( $url ) : $this->private_media_url_to_upload_relative_path( $url );
		if ( null === $relative_path && ! $private ) {
			$relative_path = $this->url_to_upload_relative_path( $url );
		}

		if ( null === $relative_path || empty( $path_lookup[ $relative_path ] ) ) {
			return $url;
		}

		return $private
			? $this->build_login_start_url( $attachment_id, $relative_path, true )
			: $this->upload_relative_path_to_url( $relative_path );
	}

	private function sync_attachment_postmeta_links( $attachment_id, $path_lookup, $private ) {
		global $wpdb;

		$likes = array();
		foreach ( array_keys( $path_lookup ) as $relative_path ) {
			$filename = wp_basename( $relative_path );
			if ( '' !== $filename ) {
				$likes[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( $filename ) . '%' );
			}
		}

		if ( ! $private ) {
			foreach ( $this->private_media_action_names() as $action ) {
				$likes[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( 'action=' . $action ) . '%' );
			}
		}

		$likes = array_values( array_unique( array_filter( $likes ) ) );
		if ( empty( $likes ) ) {
			return;
		}

		$skip_keys = array(
			'_wp_attached_file',
			'_wp_attachment_metadata',
			'_wp_attachment_backup_sizes',
			self::META_PRIVATE,
			self::META_PRIVATE_MANUAL,
			self::META_PUBLIC_OVERRIDE,
			self::META_BLOCK_SOURCES,
			self::META_FOLDER_SOURCES,
			self::META_ORIGINAL_PUBLIC_URL,
			self::META_ACTIVE_PROTECTED_URL,
			self::META_CONTENT_LINKS_PRIVATE,
			self::META_CONTENT_LINKS_SYNCED_AT,
			self::META_PRIVATE_STORAGE_PATHS,
			self::POST_META_BLOCK_PATHS,
		);

		$last_meta_id = 0;
		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d AND (" . implode( ' OR ', $likes ) . ') ORDER BY meta_id ASC LIMIT 500',
					$last_meta_id
				)
			);

			foreach ( (array) $rows as $row ) {
				$last_meta_id = max( $last_meta_id, (int) $row->meta_id );
				$meta_key     = isset( $row->meta_key ) ? (string) $row->meta_key : '';
				if ( in_array( $meta_key, $skip_keys, true ) ) {
					continue;
				}

				$raw_value = isset( $row->meta_value ) ? $row->meta_value : '';
				$value     = maybe_unserialize( $raw_value );
				$updated   = $this->sync_attachment_reference_value( $value, $attachment_id, $path_lookup, $private );

				if ( maybe_serialize( $updated ) === maybe_serialize( $value ) ) {
					continue;
				}

				update_metadata_by_mid( 'post', (int) $row->meta_id, $updated );
			}
		} while ( 500 === count( (array) $rows ) );
	}

	private function sync_attachment_option_links( $attachment_id, $path_lookup, $private ) {
		global $wpdb;

		$likes = array();
		foreach ( array_keys( $path_lookup ) as $relative_path ) {
			$filename = wp_basename( $relative_path );
			if ( '' !== $filename ) {
				$likes[] = $wpdb->prepare( 'option_value LIKE %s', '%' . $wpdb->esc_like( $filename ) . '%' );
			}
		}

		if ( ! $private ) {
			foreach ( $this->private_media_action_names() as $action ) {
				$likes[] = $wpdb->prepare( 'option_value LIKE %s', '%' . $wpdb->esc_like( 'action=' . $action ) . '%' );
			}
		}

		$likes = array_values( array_unique( array_filter( $likes ) ) );
		if ( empty( $likes ) ) {
			return;
		}

		$last_option_id = 0;
		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_id > %d AND (" . implode( ' OR ', $likes ) . ') ORDER BY option_id ASC LIMIT 200',
					$last_option_id
				)
			);

			foreach ( (array) $rows as $row ) {
				$last_option_id = max( $last_option_id, (int) $row->option_id );
				$option_name    = isset( $row->option_name ) ? (string) $row->option_name : '';
				if ( $this->should_skip_option_reference_sync( $option_name ) ) {
					continue;
				}

				$raw_value = isset( $row->option_value ) ? $row->option_value : '';
				$value     = maybe_unserialize( $raw_value );
				$updated   = $this->sync_attachment_reference_value( $value, $attachment_id, $path_lookup, $private );

				if ( maybe_serialize( $updated ) === maybe_serialize( $value ) ) {
					continue;
				}

				update_option( $option_name, $updated );
			}
		} while ( 200 === count( (array) $rows ) );
	}

	private function should_skip_option_reference_sync( $option_name ) {
		if ( '' === $option_name ) {
			return true;
		}

		if ( 0 === strpos( $option_name, '_transient_' ) || 0 === strpos( $option_name, '_site_transient_' ) ) {
			return true;
		}

		if ( 0 === strpos( $option_name, 'pgm_' ) || 0 === strpos( $option_name, 'pecodex_' ) ) {
			return true;
		}

		return in_array(
			$option_name,
			array(
				'active_plugins',
				'cron',
				'home',
				'rewrite_rules',
				'siteurl',
				'upload_path',
				'upload_url_path',
			),
			true
		);
	}

	private function sync_attachment_reference_value( $value, $attachment_id, $path_lookup, $private ) {
		if ( is_string( $value ) ) {
			return $this->sync_attachment_reference_string( $value, $attachment_id, $path_lookup, $private );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->sync_attachment_reference_value( $item, $attachment_id, $path_lookup, $private );
			}
		}

		return $value;
	}

	private function sync_attachment_reference_string( $value, $attachment_id, $path_lookup, $private ) {
		if ( '' === $value ) {
			return $value;
		}

		$updated = preg_replace_callback(
			'#https?://[^\s"\'<>]+#i',
			function ( $matches ) use ( $attachment_id, $path_lookup, $private ) {
				$url      = html_entity_decode( $matches[0], ENT_QUOTES, get_bloginfo( 'charset' ) );
				$rewrote  = $this->sync_attachment_content_url( $url, $attachment_id, $path_lookup, $private );

				return $rewrote === $url ? $matches[0] : esc_url_raw( $rewrote );
			},
			$value
		);

		$updated = preg_replace_callback(
			'#https?:\\\\/\\\\/[^"\'<>\s]+#i',
			function ( $matches ) use ( $attachment_id, $path_lookup, $private ) {
				$had_unicode_amp = false !== strpos( $matches[0], '\\u0026' );
				$url             = str_replace( array( '\\/', '\\u0026' ), array( '/', '&' ), $matches[0] );
				$rewrote         = $this->sync_attachment_content_url( $url, $attachment_id, $path_lookup, $private );

				if ( $rewrote === $url ) {
					return $matches[0];
				}

				$rewrote = str_replace( '/', '\\/', esc_url_raw( $rewrote ) );

				return $had_unicode_amp ? str_replace( '&', '\\u0026', $rewrote ) : $rewrote;
			},
			$updated
		);

		return is_string( $updated ) ? $updated : $value;
	}

	/**
	 * Synkronoi kaikki yksityiseksi merkityt mediat, kun ylläpitäjä vaihtaa
	 * tiedostovaraston asetusta.
	 */
	private function sync_all_private_attachments_for_current_mode() {
		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => self::META_PRIVATE,
						'value' => '1',
					),
					array(
						'key'   => self::META_CONTENT_LINKS_PRIVATE,
						'value' => '1',
					),
					array(
						'key'     => self::META_ACTIVE_PROTECTED_URL,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$report = array(
			'total'   => count( $attachment_ids ),
			'synced'  => 0,
			'pending' => 0,
			'errors'  => array(),
		);

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( $this->should_move_files_to_private_storage() && $this->attachment_has_protected_content_links( $attachment_id ) ) {
				update_post_meta( $attachment_id, self::META_PRIVATE, '1' );
			}

			$result        = $this->sync_attachment_storage_for_current_mode( $attachment_id );
			$expects_hidden = $this->should_move_files_to_private_storage() || $this->attachment_requires_private_storage( $attachment_id );

			if ( is_wp_error( $result ) ) {
				$report['errors'][] = sprintf(
					/* translators: 1: attachment ID, 2: error message. */
					__( 'Media #%1$d: %2$s', 'private-gutenberg-media' ),
					$attachment_id,
					$result->get_error_message()
				);
			}

			if ( $expects_hidden ) {
				if ( $this->attachment_is_hidden_from_public_uploads( $attachment_id ) ) {
					$report['synced']++;
				} else {
					$report['pending']++;
				}
			} elseif ( $this->attachment_has_public_upload_copy( $attachment_id ) ) {
				$report['synced']++;
			} else {
				$report['pending']++;
			}
		}

		return $report;
	}

	/**
	 * Kopioi medialiitteen kaikki tunnetut tiedostot yksityiseen varastoon ja
	 * poistaa julkisen kopion vasta, kun kopion koko täsmää alkuperäiseen.
	 */
	private function move_attachment_files_to_private_storage( $attachment_id, $metadata = null ) {
		$storage = $this->ensure_private_storage();
		if ( is_wp_error( $storage ) ) {
			return $storage;
		}

		$remaining_public = array();

		$relative_paths = $this->attachment_relative_paths( $attachment_id, $metadata );

		foreach ( $relative_paths as $relative_path ) {
			$public_source = $this->public_upload_file_path_if_exists( $relative_path );
			$source        = $public_source;
			$target        = $this->private_file_path_for_write( $relative_path );
			$has_converted_public_copy = $this->public_converted_upload_copy_exists( $relative_path );

			if ( ! $target ) {
				if ( $public_source || $has_converted_public_copy ) {
					$remaining_public[] = $relative_path;
				}
				continue;
			}

			if ( ! $source ) {
				$source = $this->private_file_path_if_exists( $relative_path );
			}

			if ( ! $source ) {
				if ( $has_converted_public_copy ) {
					$this->delete_public_converted_upload_copies( $relative_path );
				}
				continue;
			}

			if ( ! file_exists( $target ) && ! $this->copy_file_to_private_storage( $source, $target ) ) {
				if ( $this->public_upload_file_path_if_exists( $relative_path ) ) {
					$remaining_public[] = $relative_path;
				}
				continue;
			}

			$source_real = realpath( $source );
			$target_real = realpath( $target );

			if ( $source_real && $target_real && $source_real === $target_real ) {
				$this->delete_public_converted_upload_copies( $relative_path );
				continue;
			}

			if ( file_exists( $target ) && $this->private_storage_file_matches_public_file( $target, $source ) ) {
				if ( $public_source && is_file( $public_source ) ) {
					wp_delete_file( $public_source );
				}
				$this->delete_public_converted_upload_copies( $relative_path );
			}

			if ( $this->public_upload_file_path_if_exists( $relative_path ) || $this->public_converted_upload_copy_exists( $relative_path ) ) {
				$remaining_public[] = $relative_path;
			}
		}

		if ( ! empty( $remaining_public ) ) {
			return new WP_Error(
				'pgm_private_storage_public_copy_remaining',
				sprintf(
					/* translators: %s is a comma separated list of relative upload paths. */
					__( 'Julkista uploads-kopiota ei voitu poistaa: %s', 'private-gutenberg-media' ),
					implode( ', ', array_map( 'sanitize_text_field', array_unique( $remaining_public ) ) )
				)
			);
		}

		update_post_meta( $attachment_id, self::META_PRIVATE_STORAGE_PATHS, array_values( array_unique( $relative_paths ) ) );

		return true;
	}

	/**
	 * Palauttaa yksityiset tiedostot takaisin uploads-kansioon, kun yksityisyysrasti poistetaan.
	 */
	private function restore_attachment_files_to_uploads( $attachment_id ) {
		$failed_paths   = array();
		$relative_paths = $this->attachment_relative_paths( $attachment_id );

		foreach ( $relative_paths as $relative_path ) {
			$source = $this->private_file_path_if_exists( $relative_path );
			$target = $this->public_upload_file_path_for_write( $relative_path );

			if ( ! $source ) {
				continue;
			}

			if ( ! $target ) {
				$failed_paths[] = $relative_path;
				continue;
			}

			$public_exists = (bool) $this->public_upload_file_path_if_exists( $relative_path );
			$needs_copy    = ! $public_exists || ! $this->private_storage_file_matches_public_file( $source, $target );

			if ( $needs_copy && ! $this->copy_private_file_to_public_upload( $source, $target ) ) {
				$failed_paths[] = $relative_path;
				continue;
			}

			clearstatcache( true, $target );

			if ( ! file_exists( $target ) || ! $this->private_storage_file_matches_public_file( $source, $target ) ) {
				$failed_paths[] = $relative_path;
				continue;
			}

			wp_delete_file( $source );
		}

		$primary_path = $this->attachment_primary_relative_path( $attachment_id );
		if ( null !== $primary_path && ! $this->public_upload_file_path_if_exists( $primary_path ) ) {
			$stored_private_paths = $this->attachment_stored_private_paths( $attachment_id );
			if (
				$this->private_file_path_if_exists( $primary_path )
				|| get_post_meta( $attachment_id, self::META_PRIVATE, true )
				|| in_array( $primary_path, $stored_private_paths, true )
			) {
				$failed_paths[] = $primary_path;
			}
		}

		$failed_paths = array_values( array_unique( array_filter( $failed_paths ) ) );
		if ( ! empty( $failed_paths ) ) {
			return new WP_Error(
				'pgm_public_restore_failed',
				sprintf(
					/* translators: %s: relative media paths that could not be restored. */
					__( 'Tiedostoa ei voitu palauttaa julkiseen uploads-kansioon: %s', 'private-gutenberg-media' ),
					implode( ', ', array_map( 'sanitize_text_field', $failed_paths ) )
				)
			);
		}

		return true;
	}

	/**
	 * Palauttaa alkuperäisen tiedoston, PDF-esikatselut ja kaikki kuvakoot.
	 */
	private function attachment_relative_paths( $attachment_id, $metadata = null ) {
		$paths = array();
		$file  = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$file  = $this->normalize_relative_path( $file );

		if ( null !== $file ) {
			$paths[] = $file;
		}

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}
		if ( is_array( $metadata ) && ! empty( $metadata['file'] ) ) {
			$metadata_file = $this->normalize_relative_path( $metadata['file'] );
			if ( null !== $metadata_file ) {
				$paths[] = $metadata_file;
			}

			$base_dir = dirname( $metadata['file'] );
			$base_dir = '.' === $base_dir ? '' : trailingslashit( $base_dir );

			if ( ! empty( $metadata['original_image'] ) ) {
				$original = $this->normalize_relative_path( $base_dir . $metadata['original_image'] );
				if ( null !== $original ) {
					$paths[] = $original;
				}
			}

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					if ( empty( $size['file'] ) ) {
						continue;
					}

					$size_path = $this->normalize_relative_path( $base_dir . $size['file'] );
					if ( null !== $size_path ) {
						$paths[] = $size_path;
					}
				}
			}
		}

		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		if ( is_array( $backup_sizes ) && null !== $file ) {
			$base_dir = dirname( $file );
			$base_dir = '.' === $base_dir ? '' : trailingslashit( $base_dir );

			foreach ( $backup_sizes as $backup_size ) {
				if ( empty( $backup_size['file'] ) ) {
					continue;
				}

				$backup_path = $this->normalize_relative_path( $base_dir . $backup_size['file'] );
				if ( null !== $backup_path ) {
					$paths[] = $backup_path;
				}
			}
		}

		foreach ( $this->attachment_stored_private_paths( $attachment_id ) as $stored_path ) {
			$paths[] = $stored_path;
		}

		foreach ( array_values( array_unique( $paths ) ) as $known_path ) {
			foreach ( $this->discover_public_image_variant_relative_paths( $known_path ) as $variant_path ) {
				$paths[] = $variant_path;
			}
		}

		return array_values( array_unique( $paths ) );
	}

	private function attachment_stored_private_paths( $attachment_id ) {
		$stored = get_post_meta( $attachment_id, self::META_PRIVATE_STORAGE_PATHS, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$paths = array();
		foreach ( $stored as $path ) {
			$path = $this->normalize_relative_path( $path );
			if ( null !== $path ) {
				$paths[] = $path;
			}
		}

		return array_values( array_unique( $paths ) );
	}

	private function discover_public_image_variant_relative_paths( $relative_path ) {
		$relative_path = $this->normalize_relative_path( $relative_path );
		if ( null === $relative_path ) {
			return array();
		}

		$extension = strtolower( pathinfo( $relative_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif' ), true ) ) {
			return array();
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return array();
		}

		$base_dir = realpath( $uploads['basedir'] );
		$dir      = dirname( $relative_path );
		$dir      = '.' === $dir ? '' : trim( $dir, '/' );
		$scan_dir = realpath( trailingslashit( $uploads['basedir'] ) . $dir );
		if ( ! $base_dir || ! $scan_dir || ! is_dir( $scan_dir ) ) {
			return array();
		}

		$base_dir_normalized = wp_normalize_path( trailingslashit( $base_dir ) );
		$scan_dir_normalized = wp_normalize_path( trailingslashit( $scan_dir ) );
		if ( 0 !== strpos( $scan_dir_normalized, $base_dir_normalized ) ) {
			return array();
		}

		$filename = wp_basename( $relative_path );
		$stem     = pathinfo( $filename, PATHINFO_FILENAME );
		$quoted   = preg_quote( $stem, '/' );
		$pattern  = '/^' . $quoted . '-(?:\d+x\d+|scaled|rotated|e\d{10,})(?:-\d+)?\.' . preg_quote( $extension, '/' ) . '$/i';
		$paths    = array();

		foreach ( scandir( $scan_dir ) as $candidate ) {
			if ( '.' === $candidate || '..' === $candidate || ! preg_match( $pattern, $candidate ) ) {
				continue;
			}

			$target = realpath( trailingslashit( $scan_dir ) . $candidate );
			if ( ! $target || ! is_file( $target ) ) {
				continue;
			}

			$target_normalized = wp_normalize_path( $target );
			if ( 0 !== strpos( $target_normalized, $scan_dir_normalized ) ) {
				continue;
			}

			$paths[] = $this->normalize_relative_path( ( '' === $dir ? '' : trailingslashit( $dir ) ) . $candidate );
		}

		return array_values( array_unique( array_filter( $paths ) ) );
	}

	/**
	 * Turvallinen kopiointi: luo kohdekansion, kopioi tiedoston ja varmistaa koon.
	 */
	private function copy_file_safely( $source, $target ) {
		if ( ! is_file( $source ) ) {
			return false;
		}

		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return false;
		}

		if ( ! copy( $source, $target ) ) {
			return false;
		}

		clearstatcache( true, $source );
		clearstatcache( true, $target );

		if ( filesize( $source ) !== filesize( $target ) ) {
			wp_delete_file( $target );
			return false;
		}

		return true;
	}

	/**
	 * Tallentaa alkuperäisen tiedoston yksityiseen varastoon salattuna.
	 *
	 * Ensisijainen varasto on webrootin ulkopuolella. Jos hosting pakottaa fallbackin
	 * uploadsiin, alkuperäistä PDF:ää, kuvaa tai dokumenttia ei silti jätetä sinne
	 * sellaisenaan: suora URL mahdolliseen .pgm-tiedostoon antaa vain salattua dataa.
	 * Oikea tiedosto puretaan vasta serve_file()-metodissa kirjautumisen,
	 * käyttöoikeuden ja noncen jälkeen.
	 */
	private function copy_file_to_private_storage( $source, $target ) {
		if ( ! is_file( $source ) ) {
			return false;
		}

		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return false;
		}

		if ( $this->is_encrypted_private_file( $source ) ) {
			$contents = $this->decrypt_private_file_contents( $source );
			if ( is_wp_error( $contents ) ) {
				return false;
			}
		} else {
			$contents = file_get_contents( $source );
			if ( false === $contents ) {
				return false;
			}
		}

		$encrypted = $this->encrypt_private_file_contents( $contents );
		if ( is_wp_error( $encrypted ) ) {
			return false;
		}

		$temp = $target . '.tmp-' . wp_generate_password( 12, false, false );
		if ( false === file_put_contents( $temp, $encrypted, LOCK_EX ) ) {
			return false;
		}

		if ( ! $this->private_storage_file_matches_public_file( $temp, $source ) ) {
			wp_delete_file( $temp );
			return false;
		}

		if ( file_exists( $target ) ) {
			wp_delete_file( $target );
		}

		return rename( $temp, $target );
	}

	/**
	 * Palauttaa salatun tai vanhan selväkielisen varastotiedoston takaisin uploadsiin.
	 */
	private function copy_private_file_to_public_upload( $source, $target ) {
		if ( ! is_file( $source ) ) {
			return false;
		}

		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return false;
		}

		if ( $this->is_encrypted_private_file( $source ) ) {
			$contents = $this->decrypt_private_file_contents( $source );
			if ( is_wp_error( $contents ) ) {
				return false;
			}

			return false !== file_put_contents( $target, $contents, LOCK_EX );
		}

		return $this->copy_file_safely( $source, $target );
	}

	/**
	 * Varmistaa, että varastotiedosto purkautuu täsmälleen samaksi kuin julkinen tiedosto.
	 */
	private function private_storage_file_matches_public_file( $private_file, $public_file ) {
		if ( ! is_file( $private_file ) || ! is_file( $public_file ) ) {
			return false;
		}

		$private_contents = $this->read_private_storage_comparison_contents( $private_file );
		$public_contents  = $this->read_private_storage_comparison_contents( $public_file );

		if ( is_wp_error( $private_contents ) || is_wp_error( $public_contents ) ) {
			return false;
		}

		return hash_equals( hash( 'sha256', $public_contents ), hash( 'sha256', $private_contents ) );
	}

	private function read_private_storage_comparison_contents( $path ) {
		if ( $this->is_encrypted_private_file( $path ) ) {
			return $this->decrypt_private_file_contents( $path );
		}

		$contents = file_get_contents( $path );

		return false === $contents ? new WP_Error( 'pgm_read_failed', __( 'Tiedostoa ei voitu lukea tarkistusta varten.', 'private-gutenberg-media' ) ) : $contents;
	}

	private function encrypt_private_file_contents( $contents ) {
		if ( ! $this->private_storage_crypto_available() ) {
			return new WP_Error( 'pgm_openssl_missing', __( 'PHP OpenSSL -laajennus puuttuu, joten tiedostoa ei voida tallentaa salattuun yksityisvarastoon.', 'private-gutenberg-media' ) );
		}

		$iv  = random_bytes( 12 );
		$tag = '';

		$ciphertext = openssl_encrypt(
			$contents,
			'aes-256-gcm',
			$this->private_storage_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $ciphertext || '' === $tag ) {
			return new WP_Error( 'pgm_encrypt_failed', __( 'Tiedoston salaaminen epäonnistui.', 'private-gutenberg-media' ) );
		}

		return self::STORAGE_PHP_GUARD . self::STORAGE_HEADER . base64_encode( $iv ) . "\n" . base64_encode( $tag ) . "\n" . $ciphertext;
	}

	private function decrypt_private_file_contents( $path ) {
		if ( ! $this->private_storage_crypto_available() ) {
			return new WP_Error( 'pgm_openssl_missing', __( 'PHP OpenSSL -laajennus puuttuu, joten yksityistä tiedostoa ei voida purkaa.', 'private-gutenberg-media' ) );
		}

		$data = file_get_contents( $path );
		if ( false === $data ) {
			return new WP_Error( 'pgm_bad_private_file', __( 'Yksityinen varastotiedosto ei ole kelvollinen.', 'private-gutenberg-media' ) );
		}

		return $this->decrypt_private_storage_payload( $data );
	}

	private function decrypt_private_storage_payload( $data, $depth = 0 ) {
		if ( $depth > 2 ) {
			return new WP_Error( 'pgm_nested_private_file', __( 'Yksityinen varastotiedosto sisältäää liian monta salauskerrosta.', 'private-gutenberg-media' ) );
		}

		$data = $this->normalize_private_storage_payload( $data );
		if ( false === $data || 0 !== strpos( $data, self::STORAGE_HEADER ) ) {
			return new WP_Error( 'pgm_bad_private_file', __( 'Yksityinen varastotiedosto ei ole kelvollinen.', 'private-gutenberg-media' ) );
		}

		$offset = strlen( self::STORAGE_HEADER );
		$iv_end = strpos( $data, "\n", $offset );
		if ( false === $iv_end ) {
			return new WP_Error( 'pgm_bad_private_file', __( 'Yksityinen varastotiedosto ei ole kelvollinen.', 'private-gutenberg-media' ) );
		}

		$tag_start = $iv_end + 1;
		$tag_end   = strpos( $data, "\n", $tag_start );
		if ( false === $tag_end ) {
			return new WP_Error( 'pgm_bad_private_file', __( 'Yksityinen varastotiedosto ei ole kelvollinen.', 'private-gutenberg-media' ) );
		}

		$iv         = base64_decode( substr( $data, $offset, $iv_end - $offset ), true );
		$tag        = base64_decode( substr( $data, $tag_start, $tag_end - $tag_start ), true );
		$ciphertext = substr( $data, $tag_end + 1 );

		if ( false === $iv || false === $tag || '' === $ciphertext ) {
			return new WP_Error( 'pgm_bad_private_file', __( 'Yksityinen varastotiedosto ei ole kelvollinen.', 'private-gutenberg-media' ) );
		}

		$plain = false;
		foreach ( $this->private_storage_decryption_keys() as $key ) {
			$plain = openssl_decrypt(
				$ciphertext,
				'aes-256-gcm',
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false !== $plain ) {
				break;
			}
		}

		if ( false === $plain ) {
			return new WP_Error( 'pgm_decrypt_failed', __( 'Yksityistä tiedostoa ei voitu purkaa.', 'private-gutenberg-media' ) );
		}

		if ( $this->looks_like_private_storage_payload( $plain ) ) {
			return $this->decrypt_private_storage_payload( $plain, $depth + 1 );
		}

		return $plain;
	}

	private function is_encrypted_private_file( $path ) {
		if ( ! is_file( $path ) ) {
			return false;
		}

		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}

		$header = fread( $handle, $this->private_storage_guard_read_length() + strlen( self::STORAGE_HEADER ) );
		fclose( $handle );

		$header = $this->normalize_private_storage_payload( (string) $header );

		return 0 === strpos( $header, self::STORAGE_HEADER );
	}

	private function normalize_private_storage_payload( $data ) {
		foreach ( $this->private_storage_php_guards() as $guard ) {
			if ( 0 === strpos( $data, $guard ) ) {
				return substr( $data, strlen( $guard ) );
			}
		}

		return $data;
	}

	private function private_storage_php_guards() {
		return array(
			self::STORAGE_PHP_GUARD,
			self::STORAGE_LEGACY_PHP_GUARD,
		);
	}

	private function private_storage_guard_read_length() {
		$length = 0;
		foreach ( $this->private_storage_php_guards() as $guard ) {
			$length = max( $length, strlen( $guard ) );
		}

		return $length;
	}

	private function looks_like_private_storage_payload( $data ) {
		$data = $this->normalize_private_storage_payload( (string) $data );

		return 0 === strpos( $data, self::STORAGE_HEADER );
	}

	private function private_storage_key() {
		$key = $this->decode_private_storage_key_option( get_option( self::STORAGE_KEY_OPTION, '' ) );
		if ( false !== $key ) {
			$this->store_legacy_private_storage_key();
			return $key;
		}

		$key = $this->generate_private_storage_key();
		update_option( self::STORAGE_KEY_OPTION, base64_encode( $key ), false );
		$this->store_legacy_private_storage_key();

		return $key;
	}

	private function private_storage_decryption_keys() {
		$keys = array( $this->private_storage_key() );

		$stored_legacy_key = $this->decode_private_storage_key_option( get_option( self::STORAGE_LEGACY_KEY_OPTION, '' ) );
		if ( false !== $stored_legacy_key ) {
			$keys[] = $stored_legacy_key;
		}

		$keys[] = $this->legacy_private_storage_key();

		$unique = array();
		foreach ( $keys as $key ) {
			$unique[ base64_encode( $key ) ] = $key;
		}

		return array_values( $unique );
	}

	private function store_legacy_private_storage_key() {
		if ( false !== $this->decode_private_storage_key_option( get_option( self::STORAGE_LEGACY_KEY_OPTION, '' ) ) ) {
			return;
		}

		update_option( self::STORAGE_LEGACY_KEY_OPTION, base64_encode( $this->legacy_private_storage_key() ), false );
	}

	private function legacy_private_storage_key() {
		return hash( 'sha256', 'private-gutenberg-media|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
	}

	private function decode_private_storage_key_option( $encoded_key ) {
		if ( ! is_string( $encoded_key ) || '' === $encoded_key ) {
			return false;
		}

		$key = base64_decode( $encoded_key, true );

		return is_string( $key ) && 32 === strlen( $key ) ? $key : false;
	}

	private function generate_private_storage_key() {
		try {
			return random_bytes( 32 );
		} catch ( Exception $e ) {
			return hash( 'sha256', wp_generate_password( 64, true, true ) . '|' . microtime( true ) . '|' . wp_rand(), true );
		}
	}

	private function private_storage_crypto_available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * Muodostaa kirjoitettavan kohdepolun julkiseen uploads-kansioon ilman
	 * mahdollisuutta karata uploads-hakemiston ulkopuolelle.
	 */
	private function public_upload_file_path_for_write( $relative_path ) {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$base_dir = realpath( $uploads['basedir'] );
		if ( ! $base_dir ) {
			return false;
		}

		$target = trailingslashit( $uploads['basedir'] ) . $relative_path;
		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return false;
		}

		$parent = realpath( dirname( $target ) );
		if ( ! $parent ) {
			return false;
		}

		$base_dir_normalized = wp_normalize_path( trailingslashit( $base_dir ) );
		$parent_normalized   = wp_normalize_path( trailingslashit( $parent ) );

		return 0 === strpos( $parent_normalized, $base_dir_normalized ) ? $target : false;
	}

	/**
	 * Palauttaa yksityisen tiedoston polun vain, jos se todella on yksityisen
	 * varaston sisällä.
	 */
	private function private_file_path_if_exists( $relative_path ) {
		foreach ( $this->private_storage_read_dirs() as $storage_path ) {
			$storage = realpath( $storage_path );
			if ( ! $storage ) {
				continue;
			}

			foreach ( $this->private_storage_candidate_relative_paths( $relative_path ) as $stored_relative_path ) {
				$target = realpath( trailingslashit( $storage ) . $stored_relative_path );
				if ( $target && is_file( $target ) ) {
					$storage_normalized = wp_normalize_path( trailingslashit( $storage ) );
					$target_normalized  = wp_normalize_path( $target );

					if ( 0 === strpos( $target_normalized, $storage_normalized ) ) {
						return $target;
					}
				}
			}
		}

		return $this->legacy_private_file_path_if_exists( $relative_path );
	}

	private function legacy_private_file_path_if_exists( $relative_path ) {
		// Takautuva yhteensopivuus: aiemmat kehitysversiot saattoivat siirtää
		// tiedoston WordPress-juuren ulkopuolelle alkuperäisellä nimellä.
		$legacy_storage = realpath( $this->legacy_private_storage_dir() );
		if ( ! $legacy_storage ) {
			return false;
		}

		$legacy_target = realpath( trailingslashit( $legacy_storage ) . $relative_path );
		if ( ! $legacy_target || ! is_file( $legacy_target ) ) {
			return false;
		}

		$legacy_storage_normalized = wp_normalize_path( trailingslashit( $legacy_storage ) );
		$legacy_target_normalized  = wp_normalize_path( $legacy_target );

		return 0 === strpos( $legacy_target_normalized, $legacy_storage_normalized ) ? $legacy_target : false;
	}

	/**
	 * Muodostaa kirjoitettavan kohdepolun yksityiseen tiedostovarastoon.
	 */
	private function private_file_path_for_write( $relative_path ) {
		$storage = $this->ensure_private_storage();
		if ( is_wp_error( $storage ) ) {
			return false;
		}

		$target = trailingslashit( $storage ) . $this->private_storage_relative_path( $relative_path );
		if ( ! wp_mkdir_p( dirname( $target ) ) ) {
			return false;
		}

		$storage_real = realpath( $storage );
		$parent_real  = realpath( dirname( $target ) );

		if ( ! $storage_real || ! $parent_real ) {
			return false;
		}

		$storage_normalized = wp_normalize_path( trailingslashit( $storage_real ) );
		$parent_normalized  = wp_normalize_path( trailingslashit( $parent_real ) );

		return 0 === strpos( $parent_normalized, $storage_normalized ) ? $target : false;
	}

	/**
	 * Luo ja tarkistaa yksityisen varaston.
	 *
	 * Varasto sijaitsee oletuksena julkisen webrootin ulkopuolella. Uploads-kansion
	 * salattu varasto on fallback niille hosting-ympäristöille, joissa PHP ei saa
	 * kirjoittaa public-kansion ulkopuolelle.
	 */
	private function ensure_private_storage() {
		$last_error = null;
		$debug_log = array(
			'open_basedir' => ini_get('open_basedir'),
			'tested_dirs'  => array()
		);

		foreach ( $this->private_storage_write_dirs() as $storage ) {
			$dir_debug = array(
				'path' => $storage,
				'is_dir_before' => @is_dir( $storage )
			);

			if ( ! @is_dir( $storage ) ) {
				$dir_debug['wp_mkdir_p'] = @wp_mkdir_p( $storage );
			}

			$dir_debug['is_dir_after'] = @is_dir( $storage );

			if ( ! @is_dir( $storage ) ) {
				$last_error = new WP_Error( 'pgm_storage_not_created', __( 'Yksityistä tiedostovarastoa ei voitu luoda.', 'private-gutenberg-media' ) );
				$dir_debug['error'] = 'Could not create directory';
				$debug_log['tested_dirs'][] = $dir_debug;
				continue;
			}

			$dir_debug['is_writable'] = @is_writable( $storage );

			if ( ! @is_writable( $storage ) ) {
				$last_error = new WP_Error( 'pgm_storage_not_writable', __( 'Yksityiseen tiedostovarastoon ei voi kirjoittaa.', 'private-gutenberg-media' ) );
				$dir_debug['error'] = 'Directory not writable';
				$debug_log['tested_dirs'][] = $dir_debug;
				continue;
			}

			$index = trailingslashit( $storage ) . 'index.php';
			if ( ! @file_exists( $index ) ) {
				@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}

			$this->write_private_storage_htaccess( $storage );
			$this->write_private_storage_web_config( $storage );

			$dir_debug['success'] = true;
			$debug_log['tested_dirs'][] = $dir_debug;

			update_option( 'pecodex_storage_debug', $debug_log, false );
			return untrailingslashit( wp_normalize_path( $storage ) );
		}

		update_option( 'pecodex_storage_debug', $debug_log, false );
		return $last_error ? $last_error : new WP_Error( 'pgm_storage_not_created', __( 'Yksityistä tiedostovarastoa ei voitu luoda.', 'private-gutenberg-media' ) );
	}

	/**
	 * Kirjoittaa Apache/LiteSpeed-lisäsuojan salattuun varastokansioon.
	 *
	 * Tämä ei ole pääasiallinen suojausmekanismi: tiedostot ovat jo salattuja .pgm-
	 * blobbeja. .htaccess on puolustuskerros niille palvelimille, jotka lukevat sitä.
	 * Jos palvelin ei tue .htaccessia tai kirjoitus epäonnistuu, latauslogiikka pysyy
	 * silti toiminnassa eikä plugin riko sivustoa.
	 */
	private function write_private_storage_htaccess( $storage ) {
		$path   = trailingslashit( $storage ) . '.htaccess';
		$marker = '# BEGIN Pecodex Media Control';
		$legacy_marker = '# BEGIN Private Gutenberg Media';

		$content = $marker . "\n"
			. "# Salattu varasto toimii myös ilman tätä tiedostoa, mutta Apache/LiteSpeed estää tällä suorat pyynnöt.\n"
			. "<IfModule mod_authz_core.c>\n"
			. "Require all denied\n"
			. "</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n"
			. "Order deny,allow\n"
			. "Deny from all\n"
			. "</IfModule>\n"
			. "# END Pecodex Media Control\n";

		if ( file_exists( $path ) ) {
			$existing = file_get_contents( $path );
			if ( is_string( $existing ) && false === strpos( $existing, $marker ) && false === strpos( $existing, $legacy_marker ) ) {
				return false;
			}
		}

		return false !== file_put_contents( $path, $content, LOCK_EX );
	}

	/**
	 * Kirjoittaa IIS-palvelimille best-effort lisäsuojan. Nginx ei lue
	 * kansiokohtaisia config-tiedostoja, joten universaali suoja on edelleen se,
	 * että varastotiedostot ovat salattuja ja nimet arvattamattomia.
	 */
	private function write_private_storage_web_config( $storage ) {
		$path    = trailingslashit( $storage ) . 'web.config';
		$marker  = '<!-- Pecodex Media Control -->';
		$legacy_marker = '<!-- Private Gutenberg Media -->';
		$content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
			. "<configuration>\n"
			. "  {$marker}\n"
			. "  <system.webServer>\n"
			. "    <security>\n"
			. "      <authorization>\n"
			. "        <remove users=\"*\" roles=\"\" verbs=\"\" />\n"
			. "        <add accessType=\"Deny\" users=\"*\" />\n"
			. "      </authorization>\n"
			. "    </security>\n"
			. "  </system.webServer>\n"
			. "</configuration>\n";

		if ( file_exists( $path ) ) {
			$existing = file_get_contents( $path );
			if ( is_string( $existing ) && false === strpos( $existing, $marker ) && false === strpos( $existing, $legacy_marker ) ) {
				return false;
			}
		}

		return false !== file_put_contents( $path, $content, LOCK_EX );
	}

	private function private_storage_hardening_status_text() {
		$storage = $this->private_storage_dir();
		$server  = $this->server_software_name();
		$path    = trailingslashit( $storage ) . '.htaccess';
		$web_config = trailingslashit( $storage ) . 'web.config';
		$iis_suffix = is_file( $web_config ) ? __( ' IIS-web.config on myös kirjoitettu.', 'private-gutenberg-media' ) : '';

		if ( $this->private_storage_is_outside_public_webroot( $storage ) ) {
			return __( 'Pääsuojaus: yksityinen varasto on WordPressin julkisen webrootin ulkopuolella. Tämä toimii palvelimesta riippumatta; tiedostot puretaan vain Pecodexin PHP-endpointin kautta.', 'private-gutenberg-media' ) . $iis_suffix;
		}

		if ( is_file( $path ) ) {
			if ( $this->server_likely_uses_htaccess() ) {
				return sprintf(
					/* translators: 1: server software name. */
					__( 'Lisäsuojaus: .htaccess on kirjoitettu ja palvelin näyttäää käyttävän sitä (%1$s). Varsinainen suojaus on silti salattu .pgm-varasto ja PHP-endpoint.', 'private-gutenberg-media' ),
					$server
				) . $iis_suffix;
			}

			return sprintf(
				/* translators: 1: server software name. */
				__( 'Lisäsuojaus: .htaccess on kirjoitettu varastokansioon, mutta nykyinen palvelin ei välttämättä käytä sitä (%1$s). Varsinainen suojaus on salattu .pgm-varasto ja PHP-endpoint.', 'private-gutenberg-media' ),
				$server
			) . $iis_suffix;
		}

		return __( 'Lisäsuojaus: .htaccess-lisäsuojaa ei voitu kirjoittaa varastokansioon. Varsinainen suojaus on silti salattu .pgm-varasto ja PHP-endpoint.', 'private-gutenberg-media' );
	}

	private function server_likely_uses_htaccess() {
		global $is_apache, $is_nginx, $is_IIS, $is_iis7;

		if ( $is_apache ) {
			return true;
		}

		if ( $is_nginx || $is_IIS || $is_iis7 ) {
			return false;
		}

		$server = strtolower( $this->server_software_name() );

		return false !== strpos( $server, 'apache' ) || false !== strpos( $server, 'litespeed' );
	}

	private function server_software_name() {
		$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

		return '' === $server ? __( 'tuntematon palvelin', 'private-gutenberg-media' ) : $server;
	}

	/**
	 * Oletussijainti on WordPressin julkisen webrootin ulkopuolella, jos hosting
	 * sallii sinne kirjoittamisen. Uploads-kansion salattu varasto on fallback.
	 *
	 * Kehittäjä voi vaihtaa sijainnin pgm_private_storage_dir-filtterillä.
	 */
	private function private_storage_dir() {
		$default = $this->preferred_private_storage_dir();
		$storage = apply_filters( 'pgm_private_storage_dir', $default );

		return untrailingslashit( wp_normalize_path( $storage ) );
	}

	private function preferred_private_storage_dir() {
		$outside = $this->outside_private_storage_dir();

		return $this->private_storage_dir_looks_usable( $outside ) ? $outside : $this->uploads_private_storage_dir();
	}

	private function private_storage_write_dirs() {
		return $this->unique_storage_dirs(
			array(
				$this->private_storage_dir(),
				$this->outside_private_storage_dir(),
				$this->uploads_private_storage_dir(),
			)
		);
	}

	private function private_storage_read_dirs() {
		return $this->unique_storage_dirs(
			array(
				$this->private_storage_dir(),
				$this->outside_private_storage_dir(),
				$this->uploads_private_storage_dir(),
				$this->legacy_private_storage_dir(),
			)
		);
	}

	private function unique_storage_dirs( $dirs ) {
		$unique = array();

		foreach ( $dirs as $dir ) {
			if ( ! is_string( $dir ) || '' === $dir ) {
				continue;
			}

			$normalized = untrailingslashit( wp_normalize_path( $dir ) );
			$unique[ strtolower( $normalized ) ] = $normalized;
		}

		return array_values( $unique );
	}

	private function outside_private_storage_dir() {
		$base = defined( 'ABSPATH' ) ? dirname( untrailingslashit( ABSPATH ) ) : '';

		return untrailingslashit( wp_normalize_path( trailingslashit( $base ) . 'pecodex-private-media' ) );
	}

	private function uploads_private_storage_dir() {
		$uploads = wp_get_upload_dir();

		return untrailingslashit( wp_normalize_path( trailingslashit( $uploads['basedir'] ) . self::STORAGE_DIR_NAME ) );
	}

	private function legacy_private_storage_dir() {
		return untrailingslashit( wp_normalize_path( trailingslashit( dirname( ABSPATH ) ) . self::STORAGE_DIR_NAME ) );
	}

	private function private_storage_dir_looks_usable( $path ) {
		$path = untrailingslashit( wp_normalize_path( $path ) );
		if ( '' === $path ) {
			return false;
		}

		if ( @is_dir( $path ) ) {
			return @is_writable( $path );
		}

		$parent = dirname( $path );
		while ( $parent && ! @is_dir( $parent ) && dirname( $parent ) !== $parent ) {
			$parent = dirname( $parent );
		}

		return @is_dir( $parent ) && @is_writable( $parent );
	}

	private function private_storage_is_outside_public_webroot( $path ) {
		$path = $this->real_or_normalized_path( $path );
		if ( '' === $path ) {
			return false;
		}

		$uploads = wp_get_upload_dir();
		$public_roots = array(
			defined( 'ABSPATH' ) ? ABSPATH : '',
			isset( $uploads['basedir'] ) ? $uploads['basedir'] : '',
		);

		foreach ( $public_roots as $root ) {
			$root = $this->real_or_normalized_path( $root );
			if ( '' === $root ) {
				continue;
			}

			$root = trailingslashit( $root );
			if ( $path === untrailingslashit( $root ) || 0 === strpos( trailingslashit( $path ), $root ) ) {
				return false;
			}
		}

		return true;
	}

	private function real_or_normalized_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$real = realpath( $path );

		return untrailingslashit( wp_normalize_path( $real ? $real : $path ) );
	}

	/**
	 * Muuntaa alkuperäisen uploads-polun salaisen varastopolun nimeksi.
	 *
	 * Alkuperäistä tiedostonimeä ei käytetä varastossa, jotta tiedoston nimen
	 * tietovä käyttäjä ei voi päätellä uutta staattista URLia.
	 */
	private function private_storage_relative_path( $relative_path ) {
		$hash = hash_hmac( 'sha256', $relative_path, $this->private_storage_key() );

		return substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 ) . '/' . $hash . '.pgm.php';
	}

	private function private_storage_legacy_relative_path( $relative_path ) {
		$keys = $this->private_storage_decryption_keys();
		$key  = isset( $keys[1] ) ? $keys[1] : $this->legacy_private_storage_key();
		$hash = hash_hmac( 'sha256', $relative_path, $key );

		return substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 ) . '/' . $hash . '.pgm';
	}

	private function private_storage_candidate_relative_paths( $relative_path ) {
		$paths = array();

		foreach ( $this->private_storage_decryption_keys() as $key ) {
			$hash = hash_hmac( 'sha256', $relative_path, $key );
			$base = substr( $hash, 0, 2 ) . '/' . substr( $hash, 2, 2 ) . '/' . $hash;

			$paths[] = $base . '.pgm.php';
			$paths[] = $base . '.pgm';
		}

		$paths[] = $this->private_storage_relative_path( $relative_path );
		$paths[] = $this->private_storage_legacy_relative_path( $relative_path );

		return array_values( array_unique( $paths ) );
	}

	// -------------------------------------------------------------------------
	// Share link -metodit
	// -------------------------------------------------------------------------

	private function verify_and_consume_share_token( $attachment_id, $token ) {
		if ( empty( $token ) ) { return false; }
		$links = get_post_meta( $attachment_id, '_pgm_share_links', true );
		if ( ! is_array( $links ) ) { return false; }
		$valid = false; $updated = false;
		foreach ( $links as $i => $link ) {
			if ( ! isset( $link['token'] ) || ! hash_equals( $link['token'], $token ) ) { continue; }
			if ( ! empty( $link['expires'] ) && current_time( 'timestamp' ) > (int) $link['expires'] ) { return false; }
			if ( ! empty( $link['single_use'] ) ) {
				if ( ! empty( $link['used'] ) ) { return false; }
				$links[ $i ]['used'] = current_time( 'timestamp' );
				$updated = true;
			}
			$links[ $i ]['uses']      = ( isset( $link['uses'] ) ? (int) $link['uses'] : 0 ) + 1;
			$links[ $i ]['last_used'] = current_time( 'timestamp' );
			$updated = true; $valid = true; break;
		}
		if ( $updated ) { update_post_meta( $attachment_id, '_pgm_share_links', $links ); }
		return $valid;
	}

	public function handle_create_share_link_ajax() {
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		check_ajax_referer( 'pgm_toggle_attachment_privacy', 'nonce' );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => 'Ei oikeuksia.' ) );
		}
		$expires_hours = isset( $_POST['expires_hours'] ) ? intval( $_POST['expires_hours'] ) : 0;
		$single_use    = isset( $_POST['single_use'] ) && '1' === $_POST['single_use'];
		$email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$token         = wp_generate_password( 32, false );
		$expires       = $expires_hours > 0 ? current_time( 'timestamp' ) + ( $expires_hours * HOUR_IN_SECONDS ) : 0;
		$links         = get_post_meta( $attachment_id, '_pgm_share_links', true );
		if ( ! is_array( $links ) ) { $links = array(); }
		$links[] = array( 'token' => $token, 'created' => current_time( 'timestamp' ), 'expires' => $expires, 'single_use' => $single_use, 'email' => $email, 'creator' => get_current_user_id() );
		update_post_meta( $attachment_id, '_pgm_share_links', $links );
		$share_url = add_query_arg( array( 'action' => 'pecodex_private_media', 'id' => $attachment_id, 'share_token' => $token ), admin_url( 'admin-post.php' ) );
		if ( is_email( $email ) ) {
			wp_mail( $email, 'Sinulle on jaettu tiedosto', sprintf( "Hei!\n\nSinut on kutsuttu katsomaan tiedostoa: %s\n\nLinkki:\n%s", get_the_title( $attachment_id ), $share_url ) );
		}
		wp_send_json_success( array( 'links' => $links, 'share_url' => $share_url ) );
	}

	public function handle_delete_share_link_ajax() {
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		check_ajax_referer( 'pgm_toggle_attachment_privacy', 'nonce' );
		if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) { wp_send_json_error(); }
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$links = get_post_meta( $attachment_id, '_pgm_share_links', true );
		if ( is_array( $links ) ) {
			foreach ( $links as $i => $link ) { if ( isset( $link['token'] ) && hash_equals( $link['token'], $token ) ) { unset( $links[ $i ] ); } }
			update_post_meta( $attachment_id, '_pgm_share_links', array_values( $links ) );
		}
		wp_send_json_success();
	}

	public function handle_revoke_share_link_ajax() {
		check_ajax_referer( 'pgm_admin_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$token         = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( $attachment_id && $token ) {
			$links = get_post_meta( $attachment_id, '_pgm_share_links', true );
			if ( is_array( $links ) ) {
				foreach ( $links as $i => $link ) { if ( isset( $link['token'] ) && hash_equals( $link['token'], $token ) ) { unset( $links[ $i ] ); } }
				update_post_meta( $attachment_id, '_pgm_share_links', array_values( $links ) );
			}
		}
		wp_send_json_success();
	}

	public function handle_revoke_all_share_links_ajax() {
		check_ajax_referer( 'pgm_admin_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id ) {
			delete_post_meta( $attachment_id, '_pgm_share_links' );
		}
		wp_send_json_success();
	}

	public function handle_search_users_ajax() {
		check_ajax_referer( 'pgm_toggle_attachment_privacy', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error(); }
		$q     = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$users = get_users( array( 'search' => '*' . $q . '*', 'search_columns' => array( 'user_login', 'user_email', 'display_name' ), 'number' => 8, 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
		$data  = array();
		foreach ( $users as $u ) { $data[] = array( 'id' => $u->ID, 'display_name' => $u->display_name, 'email' => $u->user_email ); }
		wp_send_json_success( $data );
	}

	public function get_shared_files_data( $offset = 0, $limit = 30, $search = '' ) {
		global $wpdb;
		$offset = absint( $offset );
		$limit  = absint( $limit );
		
		$search_join = "";
		$search_where = "";
		$args = array( '_pgm_share_links' );

		if ( ! empty( $search ) ) {
			// Search in title, author display_name, or meta_value (emails)
			$search_join = " LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID ";
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$search_where = " AND ( p.post_title LIKE %s OR u.display_name LIKE %s OR pm.meta_value LIKE %s ) ";
			array_push( $args, $like, $like, $like );
		}

		// Count total
		$count_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id {$search_join} WHERE pm.meta_key = %s {$search_where} AND p.post_type = 'attachment'";
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) );

		// Get items
		array_push( $args, $limit, $offset );
		$sql = "SELECT DISTINCT p.ID, p.post_title, p.post_author, p.post_modified, p.post_mime_type FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id {$search_join} WHERE pm.meta_key = %s {$search_where} AND p.post_type = 'attachment' ORDER BY p.post_modified DESC LIMIT %d OFFSET %d";
		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

		$files = array();
		$now = current_time( 'timestamp' );

		foreach ( $results as $row ) {
			$id = $row->ID;
			$links = get_post_meta( $id, '_pgm_share_links', true );
			if ( ! is_array( $links ) || empty( $links ) ) { continue; }

			$author_id = (int) $row->post_author;
			$author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : 'Tuntematon';
			$avatar_url = get_avatar_url( $author_id, array( 'size' => 100 ) );

			$title = $row->post_title ?: wp_basename( get_attached_file( $id ) );
			$mime_type = $row->post_mime_type;

			$icon_bg = 'bg-gray-50'; $icon_text = 'text-gray-600';
			$icon_svg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
			if ( strpos( $mime_type, 'pdf' ) !== false ) {
				$icon_bg = 'bg-red-50'; $icon_text = 'text-red-600';
			} elseif ( strpos( $mime_type, 'spreadsheet' ) !== false || strpos( $mime_type, 'excel' ) !== false || strpos( $mime_type, 'csv' ) !== false ) {
				$icon_bg = 'bg-green-50'; $icon_text = 'text-green-600';
				$icon_svg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>';
			} elseif ( strpos( $mime_type, 'word' ) !== false || strpos( $mime_type, 'document' ) !== false ) {
				$icon_bg = 'bg-blue-50'; $icon_text = 'text-blue-600';
			} elseif ( strpos( $mime_type, 'image/' ) === 0 ) {
				$icon_bg = 'bg-purple-50'; $icon_text = 'text-purple-600';
				$icon_svg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
			}

			$is_active = false;
			foreach ( $links as $l ) {
				if ( ! isset( $l['expires'] ) || ! $l['expires'] || $l['expires'] > $now ) {
					$is_active = true; break;
				}
			}

			$files[] = array(
				'id'          => $id,
				'title'       => $title,
				'date'        => wp_date( 'd.m.Y', strtotime( $row->post_modified ) ),
				'author'      => $author_name,
				'avatar'      => $avatar_url,
				'links'       => $links,
				'is_active'   => $is_active,
				'icon_bg'     => $icon_bg,
				'icon_text'   => $icon_text,
				'icon_svg'    => $icon_svg,
			);
		}

		return array( 'files' => $files, 'total' => $total );
	}

	public function handle_load_shared_files_ajax() {
		check_ajax_referer( 'pgm_admin_settings', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error(); }

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 30;
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$data = $this->get_shared_files_data( $offset, $limit, $search );
		$files = $data['files'];
		$total = $data['total'];

		ob_start();
		$now = current_time( 'timestamp' );
		require __DIR__ . '/admin-shared-files-rows.php';
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'  => $html,
			'total' => $total,
			'count' => count( $files ),
		) );
	}
	// -------------------------------------------------------------------------

	const SHARED_FILES_PAGE_SLUG = 'pecodex-shared-files';

	public function add_shared_files_page() {
		add_media_page( 'Jaetut tiedostot', 'Jaetut tiedostot', 'upload_files', self::SHARED_FILES_PAGE_SLUG, array( $this, 'render_shared_files_page' ) );
	}

	public function enqueue_shared_files_admin_assets( $hook_suffix ) {
		if ( 'media_page_' . self::SHARED_FILES_PAGE_SLUG !== $hook_suffix ) { return; }
		$nonce = wp_create_nonce( 'pgm_admin_settings' );
		$ajax  = esc_js( admin_url( 'admin-ajax.php' ) );
		wp_add_inline_script( 'jquery', 'jQuery(function($){ $(document).on("click",".pgm-revoke-link",function(){ if(!confirm("Poista linkki?")){return;} var btn=$(this),row=btn.closest("tr"); $.post("' . $ajax . '",{action:"pgm_revoke_share_link",nonce:"' . $nonce . '",attachment_id:btn.data("attachment"),token:btn.data("token")}).done(function(r){if(r&&r.success){row.fadeOut(300,function(){$(this).remove()});}}); }); $(document).on("click",".pgm-copy-link",function(){ var url=$(this).data("url"); navigator.clipboard?navigator.clipboard.writeText(url).then(function(){alert("Kopioitu!")}):alert(url); }); });' );
	}

	public function render_shared_files_page() {
		if ( ! current_user_can( 'upload_files' ) ) { wp_die( 'Ei oikeuksia.' ); }
		$iframe_url = add_query_arg( array( 'action' => 'pgm_shared_files_ui' ), admin_url( 'admin-post.php' ) );
		echo '<div class="wrap" style="margin: 0 0 0 -20px; padding: 0; height: calc(100vh - 32px); max-width: 100%; overflow: hidden;">';
		echo '<iframe src="' . esc_url( $iframe_url ) . '" style="width: 100%; height: 100%; border: none;"></iframe>';
		echo '</div>';
	}

	public function render_shared_files_ui_iframe() {
		if ( ! current_user_can( 'upload_files' ) ) { wp_die( 'Ei oikeuksia.' ); }
		require __DIR__ . '/admin-shared-files.php';
		exit;
	}

	/**
	 * Näyttää ilmoituksen, jos WAF ei ole "optimoitu" (auto_prepend_file puuttuu).
	 */
	public function show_waf_optimization_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_optimized = get_option( 'pecodex_waf_optimized', false );
		if ( $is_optimized ) {
			return;
		}

		$optimize_url = wp_nonce_url( admin_url( 'admin-post.php?action=pecodex_optimize_waf' ), 'pecodex_optimize_waf' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p><strong>Pecodex Security:</strong> Palomuuria (WAF) ei ole optimoitu. Jotta palomuuri voi torjua hyökkäykset ennen WordPressin latautumista, se vaatii asennuksen (.user.ini tai .htaccess).</p>
			<p><a href="<?php echo esc_url( $optimize_url ); ?>" class="button button-primary">Optimoi Pecodex WAF nyt</a></p>
		</div>
		<?php
	}

	/**
	 * Käsittelee WAFin optimointipyynnön (yrittää lisätä auto_prepend_file).
	 */
	public function handle_optimize_waf_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Ei oikeuksia.' );
		}

		check_admin_referer( 'pecodex_optimize_waf' );

		$bootstrap_path = plugin_dir_path( __FILE__ ) . 'pecodex-waf-bootstrap.php';
		$home_path      = get_home_path();
		
		$success = false;

		// 1. Yritetään .user.ini
		$user_ini_file = $home_path . '.user.ini';
		$prepend_directive = "\n; Pecodex WAF\nauto_prepend_file = '" . $bootstrap_path . "'\n";
		
		if ( is_writable( $home_path ) || is_writable( $user_ini_file ) ) {
			$current_content = file_exists( $user_ini_file ) ? file_get_contents( $user_ini_file ) : '';
			if ( strpos( $current_content, 'Pecodex WAF' ) === false ) {
				file_put_contents( $user_ini_file, $current_content . $prepend_directive );
			}
			$success = true;
		}

		if ( $success ) {
			update_option( 'pecodex_waf_optimized', true );
			add_settings_error( 'pecodex_security', 'waf_optimized', 'Pecodex WAF optimoitu onnistuneesti!', 'updated' );
		} else {
			add_settings_error( 'pecodex_security', 'waf_failed', 'WAFin optimointi epäonnistui. Tarkista tiedostojen kirjoitusoikeudet hakemistossa ' . $home_path, 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE_SLUG ) );
		exit;
	}
}

if ( ! class_exists( 'Pecodex_Media_Library', false ) ) {
	class_alias( 'PGM_Private_Gutenberg_Media', 'Pecodex_Media_Library' );
}

if ( ! class_exists( 'Pecodex_Media_Control', false ) ) {
	class_alias( 'PGM_Private_Gutenberg_Media', 'Pecodex_Media_Control' );
}

register_activation_hook( __FILE__, array( 'Pecodex_Media_Control', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Pecodex_Media_Control', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Pecodex_Media_Control', 'uninstall' ) );

Pecodex_Media_Control::instance();
