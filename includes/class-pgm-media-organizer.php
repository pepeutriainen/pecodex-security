<?php
/**
 * Media Library folder organizer for Pecodex Media Control.
 *
 * @package Pecodex_Media_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PGM_Media_Organizer {
	const TAXONOMY                   = 'pgm_media_folder';
	const OPTION_NAME                = 'pgm_media_organizer';
	const NONCE_ACTION               = 'pgm_media_organizer';
	const EXPORT_ACTION              = 'pgm_mo_export';
	const ZIP_ACTION                 = 'pgm_mo_import_zip';
	const PECODEX_EXPORT_ACTION      = 'pecodex_mo_export';
	const PECODEX_ZIP_ACTION         = 'pecodex_mo_import_zip';
	const ORDER_META_KEY             = '_pgm_mo_order';
	const SIZE_META_KEY              = '_pgm_mo_file_size';
	const CATFOLDERS_SYNC_OPTION     = 'pgm_mo_catfolders_source_fingerprint';
	const CATFOLDERS_SOURCE_META_KEY = '_pgm_mo_catfolders_source_id';
	const CATFOLDERS_SYNC_VERSION    = '3';
	const LOCAL_OVERRIDE_META_KEY    = '_pecodex_mo_local_override';
	const ACCESS_META_KEY            = '_pgm_mo_access';
	const ROLES_META_KEY             = '_pgm_mo_roles';
	const ACCESS_PUBLIC              = 'public';
	const ACCESS_LOGGED_IN           = 'logged_in';
	const ACCESS_ADMIN_ONLY          = 'admin_only';
	const ACCESS_ROLE_BASED          = 'role_based';
	const ADMIN_PAGE_SLUG            = 'pecodex-media-library';
	const LEGACY_ADMIN_PAGE_SLUG     = 'pgm-media-organizer';
	const FOLDER_QUERY_ARG           = 'pecodex_media_folder';
	const LEGACY_FOLDER_QUERY_ARG    = 'pgm_media_folder';
	const ORDER_QUERY_ARG            = 'pecodex_mo_order';
	const LEGACY_ORDER_QUERY_ARG     = 'pgm_mo_order';
	const DEEP_SEARCH_QUERY_ARG      = 'pecodex_mo_deep_search';
	const LEGACY_DEEP_SEARCH_QUERY_ARG = 'pgm_mo_deep_search';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * @var string
	 */
	private $plugin_url;

	public static function instance( $plugin_file = '' ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	private function __construct( $plugin_file ) {
		if ( $plugin_file ) {
			$this->plugin_file = $plugin_file;
		} else {
			$canonical = dirname( __DIR__ ) . '/pecodex-security.php';
			$legacy    = dirname( __DIR__ ) . '/private-gutenberg-media.php';

			$this->plugin_file = file_exists( $canonical ) ? $canonical : $legacy;
		}
		$this->plugin_dir  = trailingslashit( plugin_dir_path( $this->plugin_file ) );
		$this->plugin_url  = trailingslashit( plugin_dir_url( $this->plugin_file ) );

		add_action( 'init', array( $this, 'register_taxonomy' ), 5 );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_admin_page' ) );
		add_action( 'admin_init', array( $this, 'migrate_legacy_startup_folder_default' ), 5 );
		add_action( 'admin_init', array( $this, 'maybe_auto_import_catfolders' ), 30 );
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 30 );
		add_action( 'admin_footer', array( $this, 'print_browser_debug_script' ) );

		add_action( 'add_attachment', array( $this, 'apply_upload_defaults' ), 20 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'track_attachment_size_after_metadata' ), 20, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_folder_data_to_attachment_js' ), 20, 3 );
		// add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_folder_field' ), 20, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_folder_field' ), 20, 2 );
		add_filter( 'manage_media_columns', array( $this, 'add_media_folder_column' ), 20 );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_folder_column' ), 20, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_media_list_query' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_ajax_attachment_query' ) );
		add_filter( 'posts_search', array( $this, 'filter_deep_media_search' ), 20, 2 );

		foreach ( $this->ajax_action_map() as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}

		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export_request' ) );
		add_action( 'admin_post_' . self::PECODEX_EXPORT_ACTION, array( $this, 'handle_export_request' ) );
		add_action( 'admin_post_' . self::ZIP_ACTION, array( $this, 'handle_zip_upload_request' ) );
		add_action( 'admin_post_' . self::PECODEX_ZIP_ACTION, array( $this, 'handle_zip_upload_request' ) );
	}

	private function ajax_action_map() {
		$actions = array(
			'get_state'            => 'ajax_get_state',
			'create_folder'        => 'ajax_create_folder',
			'update_folder'        => 'ajax_update_folder',
			'reorder_folder'       => 'ajax_reorder_folder',
			'delete_folder'        => 'ajax_delete_folder',
			'assign_attachments'   => 'ajax_assign_attachments',
			'unassign_attachments' => 'ajax_unassign_attachments',
			'duplicate_attachment' => 'ajax_duplicate_attachment',
			'import_catfolders'    => 'ajax_import_catfolders',
			'save_preferences'     => 'ajax_save_preferences',
			'save_private_settings' => 'ajax_save_private_settings',
		);
		$mapped = array();

		foreach ( $actions as $suffix => $method ) {
			$mapped[ 'pgm_mo_' . $suffix ]     = $method;
			$mapped[ 'pecodex_mo_' . $suffix ] = $method;
		}

		return $mapped;
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::default_options(), '', false );
		}

		$instance = self::instance();
		$instance->register_taxonomy();
	}

	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			'attachment',
			array(
				'labels'             => array(
					'name'              => __( 'Mediakansiot', 'private-gutenberg-media' ),
					'singular_name'     => __( 'Mediakansio', 'private-gutenberg-media' ),
					'search_items'      => __( 'Etsi mediakansioita', 'private-gutenberg-media' ),
					'all_items'         => __( 'Kaikki mediakansiot', 'private-gutenberg-media' ),
					'parent_item'       => __( 'Yläkansio', 'private-gutenberg-media' ),
					'parent_item_colon' => __( 'Yläkansio:', 'private-gutenberg-media' ),
					'edit_item'         => __( 'Muokkaa mediakansiota', 'private-gutenberg-media' ),
					'update_item'       => __( 'Päivitä mediakansio', 'private-gutenberg-media' ),
					'add_new_item'      => __( 'Lisää uusi mediakansio', 'private-gutenberg-media' ),
					'new_item_name'     => __( 'Uuden mediakansion nimi', 'private-gutenberg-media' ),
					'menu_name'         => __( 'Mediakansiot', 'private-gutenberg-media' ),
				),
				'public'             => false,
				'hierarchical'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'show_in_quick_edit' => true,
				'query_var'             => true,
				'rewrite'               => false,
				'update_count_callback' => '_update_generic_term_count',
				'capabilities'          => array(
					'manage_terms' => 'upload_files',
					'edit_terms'   => 'upload_files',
					'delete_terms' => 'upload_files',
					'assign_terms' => 'upload_files',
				),
			)
		);
	}

	public function register_shortcodes() {
		add_shortcode( 'pecodex_media_gallery', array( $this, 'render_gallery_shortcode' ) );
		add_shortcode( 'pgm_media_gallery', array( $this, 'render_gallery_shortcode' ) );
	}

	private static function default_attribute_rules() {
		$empty = array(
			'title'       => '',
			'alt'         => '',
			'caption'     => '',
			'description' => '',
		);

		return array(
			'image'    => $empty,
			'document' => $empty,
			'video'    => $empty,
			'audio'    => $empty,
			'archive'  => $empty,
			'other'    => $empty,
		);
	}

	private static function default_options() {
		return array(
			'enabled'             => 1,
			'default_folder'      => 0,
			'startup_folder'      => 0,
			'show_folder_tree'    => 1,
			'show_counts'         => 1,
			'show_empty_folders'  => 1,
			'include_children'    => 1,
			'grid_size'           => 'medium',
			'infinite_scroll_grid'=> 1,
			'folder_tree_width'   => 280,
			'default_sort'        => 'date_desc',
			'deep_search_default' => 0,
			'overwrite_attributes'=> 0,
			'list_columns'        => self::default_list_columns(),
			'default_attributes'  => self::default_attribute_rules(),
			'display_defaults'    => array(
				'link'  => 'none',
				'size'  => 'medium',
				'align' => 'none',
			),
		);
	}

	private static function default_list_columns() {
		return array( 'icon', 'title', 'author', 'parent', 'comments', 'pgm_media_folder', 'pgm_public_visibility', 'date' );
	}

	private function get_options() {
		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$defaults = self::default_options();
		$options  = wp_parse_args( $options, $defaults );

		if ( ! is_array( $options['default_attributes'] ) ) {
			$options['default_attributes'] = array();
		}
		$options['default_attributes'] = array_replace_recursive( $defaults['default_attributes'], $options['default_attributes'] );

		if ( ! is_array( $options['display_defaults'] ) ) {
			$options['display_defaults'] = array();
		}
		$options['display_defaults'] = wp_parse_args( $options['display_defaults'], $defaults['display_defaults'] );

		if ( ! is_array( $options['list_columns'] ) ) {
			$options['list_columns'] = $defaults['list_columns'];
		}
		$options['list_columns'] = array_values( array_intersect( array_map( 'sanitize_key', $options['list_columns'] ), array_keys( $this->list_column_choices() ) ) );
		if ( empty( $options['list_columns'] ) ) {
			$options['list_columns'] = $defaults['list_columns'];
		}

		return $options;
	}

	public function migrate_legacy_startup_folder_default() {
		$migration_option = 'pgm_mo_0832_startup_folder_default_migrated';

		if ( get_option( $migration_option ) ) {
			return;
		}

		$options = get_option( self::OPTION_NAME, array() );
		$options = is_array( $options ) ? $options : array();

		if ( isset( $options['startup_folder'] ) && -1 === (int) $options['startup_folder'] ) {
			$options['startup_folder'] = 0;
			update_option( self::OPTION_NAME, wp_parse_args( $options, self::default_options() ), false );
		}

		update_option( $migration_option, time(), false );
	}

	private function is_enabled() {
		$options = $this->get_options();

		return ! empty( $options['enabled'] );
	}

	public function register_settings() {
		register_setting(
			'pgm_media_organizer_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::default_options(),
			)
		);
	}

	public function sanitize_options( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::default_options();

		$grid_size = isset( $input['grid_size'] ) ? sanitize_key( $input['grid_size'] ) : $defaults['grid_size'];
		if ( ! in_array( $grid_size, array( 'small', 'medium', 'large' ), true ) ) {
			$grid_size = $defaults['grid_size'];
		}

		$default_sort = isset( $input['default_sort'] ) ? sanitize_key( $input['default_sort'] ) : $defaults['default_sort'];
		if ( ! array_key_exists( $default_sort, $this->sort_options() ) ) {
			$default_sort = $defaults['default_sort'];
		}

		$link = isset( $input['display_defaults']['link'] ) ? sanitize_key( $input['display_defaults']['link'] ) : $defaults['display_defaults']['link'];
		if ( ! in_array( $link, array( 'none', 'file', 'post' ), true ) ) {
			$link = $defaults['display_defaults']['link'];
		}

		$size = isset( $input['display_defaults']['size'] ) ? sanitize_key( $input['display_defaults']['size'] ) : $defaults['display_defaults']['size'];
		$size = '' === $size ? $defaults['display_defaults']['size'] : $size;

		$align = isset( $input['display_defaults']['align'] ) ? sanitize_key( $input['display_defaults']['align'] ) : $defaults['display_defaults']['align'];
		if ( ! in_array( $align, array( 'none', 'left', 'center', 'right' ), true ) ) {
			$align = $defaults['display_defaults']['align'];
		}

		return array(
			'enabled'              => empty( $input['enabled'] ) ? 0 : 1,
			'default_folder'       => isset( $input['default_folder'] ) ? absint( $input['default_folder'] ) : 0,
			'startup_folder'       => isset( $input['startup_folder'] ) ? (int) $input['startup_folder'] : $defaults['startup_folder'],
			'show_folder_tree'     => empty( $input['show_folder_tree'] ) ? 0 : 1,
			'show_counts'          => empty( $input['show_counts'] ) ? 0 : 1,
			'show_empty_folders'   => empty( $input['show_empty_folders'] ) ? 0 : 1,
			'include_children'     => empty( $input['include_children'] ) ? 0 : 1,
			'grid_size'            => $grid_size,
			'infinite_scroll_grid' => empty( $input['infinite_scroll_grid'] ) ? 0 : 1,
				'folder_tree_width'    => isset( $input['folder_tree_width'] ) ? min( 520, max( 220, absint( $input['folder_tree_width'] ) ) ) : $defaults['folder_tree_width'],
			'default_sort'         => $default_sort,
			'deep_search_default'  => empty( $input['deep_search_default'] ) ? 0 : 1,
			'overwrite_attributes' => empty( $input['overwrite_attributes'] ) ? 0 : 1,
			'list_columns'         => $this->sanitize_list_columns( isset( $input['list_columns'] ) ? $input['list_columns'] : array() ),
			'default_attributes'   => $this->sanitize_attribute_rules( isset( $input['default_attributes'] ) ? $input['default_attributes'] : array() ),
			'display_defaults'     => array(
				'link'  => $link,
				'size'  => $size,
				'align' => $align,
			),
		);
	}

	private function sanitize_list_columns( $columns ) {
		$allowed = array_keys( $this->list_column_choices() );
		$columns = is_array( $columns ) ? $columns : array();
		$columns = array_values( array_intersect( array_map( 'sanitize_key', $columns ), $allowed ) );

		return empty( $columns ) ? self::default_list_columns() : $columns;
	}

	private function list_column_choices() {
		return array(
			'icon'                  => __( 'Thumbnail', 'private-gutenberg-media' ),
			'title'                 => __( 'File', 'private-gutenberg-media' ),
			'author'                => __( 'Author', 'private-gutenberg-media' ),
			'parent'                => __( 'Uploaded to', 'private-gutenberg-media' ),
			'comments'              => __( 'Comments', 'private-gutenberg-media' ),
			'pgm_media_folder'      => __( 'Folder', 'private-gutenberg-media' ),
			'pgm_public_visibility' => __( 'Visibility', 'private-gutenberg-media' ),
			'date'                  => __( 'Date', 'private-gutenberg-media' ),
		);
	}

	private function sanitize_attribute_rules( $rules ) {
		$rules  = is_array( $rules ) ? $rules : array();
		$output = self::default_attribute_rules();

		foreach ( $output as $group => $fields ) {
			foreach ( $fields as $field => $value ) {
				unset( $value );
				if ( isset( $rules[ $group ][ $field ] ) ) {
					$output[ $group ][ $field ] = sanitize_textarea_field( wp_unslash( $rules[ $group ][ $field ] ) );
				}
			}
		}

		return $output;
	}

	public function add_admin_pages() {
		add_media_page(
			__( 'Pecodex Media Control', 'private-gutenberg-media' ),
			__( 'Pecodex Media Control', 'private-gutenberg-media' ),
			'upload_files',
			self::ADMIN_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function redirect_legacy_admin_page() {
		global $pagenow;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'upload.php' !== $pagenow || self::LEGACY_ADMIN_PAGE_SLUG !== $page ) {
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'upload.php?page=' . self::ADMIN_PAGE_SLUG ) );
		exit;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$options = $this->get_options();
		?>
		<div class="wrap pgm-mo-settings-page">
			<h1><?php esc_html_e( 'Pecodex Media Control', 'private-gutenberg-media' ); ?></h1>
			<p><?php esc_html_e( 'Lisää Pecodex Media Controliin kansiot, alikansiot, mediakirjaston suodatuksen, oletusmetatiedot ja ZIP-työkalut.', 'private-gutenberg-media' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'pgm_media_organizer_settings' ); ?>
				<h2><?php esc_html_e( 'Kansiot ja mediakirjasto', 'private-gutenberg-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Tila', 'private-gutenberg-media' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $options['enabled'], 1 ); ?> />
								<?php esc_html_e( 'Ota Pecodex Media Control käyttöön', 'private-gutenberg-media' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgm-mo-default-folder"><?php esc_html_e( 'Oletuskansio uusille latauksille', 'private-gutenberg-media' ); ?></label></th>
						<td>
							<?php $this->render_folder_select( self::OPTION_NAME . '[default_folder]', (int) $options['default_folder'], 'pgm-mo-default-folder', true ); ?>
							<p class="description"><?php esc_html_e( 'Uudet Media Library -lataukset siirretään tähän kansioon automaattisesti.', 'private-gutenberg-media' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kansiopuu', 'private-gutenberg-media' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[show_folder_tree]" value="1" <?php checked( $options['show_folder_tree'], 1 ); ?> /> <?php esc_html_e( 'Näytä kansiopuu mediakirjastossa ja mediavalitsimissa', 'private-gutenberg-media' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[show_counts]" value="1" <?php checked( $options['show_counts'], 1 ); ?> /> <?php esc_html_e( 'Näytä tiedostomäärät kansioissa', 'private-gutenberg-media' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[include_children]" value="1" <?php checked( $options['include_children'], 1 ); ?> /> <?php esc_html_e( 'Sisällytä alikansiot, kun kansiota suodatetaan tai viedään ZIPiksi', 'private-gutenberg-media' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgm-mo-grid-size"><?php esc_html_e( 'Ruudukkokoko', 'private-gutenberg-media' ); ?></label></th>
						<td>
							<select id="pgm-mo-grid-size" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[grid_size]">
								<option value="small" <?php selected( $options['grid_size'], 'small' ); ?>><?php esc_html_e( 'Pieni', 'private-gutenberg-media' ); ?></option>
								<option value="medium" <?php selected( $options['grid_size'], 'medium' ); ?>><?php esc_html_e( 'Keskikokoinen', 'private-gutenberg-media' ); ?></option>
								<option value="large" <?php selected( $options['grid_size'], 'large' ); ?>><?php esc_html_e( 'Suuri', 'private-gutenberg-media' ); ?></option>
							</select>
							<label class="pgm-mo-inline-field" for="pgm-mo-tree-width"><?php esc_html_e( 'Kansiopuun leveys', 'private-gutenberg-media' ); ?></label>
							<input id="pgm-mo-tree-width" type="number" min="220" max="520" step="10" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[folder_tree_width]" value="<?php echo esc_attr( (int) $options['folder_tree_width'] ); ?>" /> px
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgm-mo-default-sort"><?php esc_html_e( 'Oletusjärjestys', 'private-gutenberg-media' ); ?></label></th>
						<td>
							<select id="pgm-mo-default-sort" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_sort]">
								<?php foreach ( $this->sort_options() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['default_sort'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<label class="pgm-mo-inline-field"><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deep_search_default]" value="1" <?php checked( $options['deep_search_default'], 1 ); ?> /> <?php esc_html_e( 'Etsi oletuksena myös alt-tekstistä, tiedostonimestä, kuvatekstistä ja kuvauksesta', 'private-gutenberg-media' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Oletusmetatiedot tiedostotyypeittäin', 'private-gutenberg-media' ); ?></h2>
				<p><?php esc_html_e( 'Dynaamiset tagit: {filename}, {title}, {date}, {site_name}, {mime}, {extension}, {folder}. Tyhjä kenttä ei muuta mediaa.', 'private-gutenberg-media' ); ?></p>
				<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[overwrite_attributes]" value="1" <?php checked( $options['overwrite_attributes'], 1 ); ?> /> <?php esc_html_e( 'Korvaa myös olemassa olevat tyhjästä poikkeavat kentät latauksessa', 'private-gutenberg-media' ); ?></label>
				<table class="widefat striped pgm-mo-attribute-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tyyppi', 'private-gutenberg-media' ); ?></th>
							<th><?php esc_html_e( 'Otsikko', 'private-gutenberg-media' ); ?></th>
							<th><?php esc_html_e( 'Alt-teksti', 'private-gutenberg-media' ); ?></th>
							<th><?php esc_html_e( 'Kuvateksti', 'private-gutenberg-media' ); ?></th>
							<th><?php esc_html_e( 'Kuvaus', 'private-gutenberg-media' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->attribute_group_labels() as $group => $label ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<?php foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) : ?>
									<td>
										<textarea rows="2" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_attributes][<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $field ); ?>]"><?php echo esc_textarea( $options['default_attributes'][ $group ][ $field ] ); ?></textarea>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Lisäyksen näyttöoletukset', 'private-gutenberg-media' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Kun media lisätään sisältöön', 'private-gutenberg-media' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Linkki', 'private-gutenberg-media' ); ?>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[display_defaults][link]">
									<option value="none" <?php selected( $options['display_defaults']['link'], 'none' ); ?>><?php esc_html_e( 'Ei linkkiä', 'private-gutenberg-media' ); ?></option>
									<option value="file" <?php selected( $options['display_defaults']['link'], 'file' ); ?>><?php esc_html_e( 'Mediatiedosto', 'private-gutenberg-media' ); ?></option>
									<option value="post" <?php selected( $options['display_defaults']['link'], 'post' ); ?>><?php esc_html_e( 'Liitesivu', 'private-gutenberg-media' ); ?></option>
								</select>
							</label>
							<label class="pgm-mo-inline-field"><?php esc_html_e( 'Koko', 'private-gutenberg-media' ); ?>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[display_defaults][size]">
									<?php foreach ( $this->image_size_choices() as $size => $label ) : ?>
										<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $options['display_defaults']['size'], $size ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="pgm-mo-inline-field"><?php esc_html_e( 'Tasaus', 'private-gutenberg-media' ); ?>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[display_defaults][align]">
									<option value="none" <?php selected( $options['display_defaults']['align'], 'none' ); ?>><?php esc_html_e( 'Ei mitään', 'private-gutenberg-media' ); ?></option>
									<option value="left" <?php selected( $options['display_defaults']['align'], 'left' ); ?>><?php esc_html_e( 'Vasen', 'private-gutenberg-media' ); ?></option>
									<option value="center" <?php selected( $options['display_defaults']['align'], 'center' ); ?>><?php esc_html_e( 'Keskellä', 'private-gutenberg-media' ); ?></option>
									<option value="right" <?php selected( $options['display_defaults']['align'], 'right' ); ?>><?php esc_html_e( 'Oikea', 'private-gutenberg-media' ); ?></option>
								</select>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Tuonti, vienti ja ZIP-työkalut', 'private-gutenberg-media' ); ?></h2>
			<p>
				<button type="button" class="button" id="pgm-mo-import-catfolders"><?php esc_html_e( 'Tuo CatFoldersista', 'private-gutenberg-media' ); ?></button>
				<a class="button" href="<?php echo esc_url( $this->export_url( 0, array(), true ) ); ?>"><?php esc_html_e( 'Vie koko mediakirjasto ZIPiksi', 'private-gutenberg-media' ); ?></a>
			</p>
			<p id="pgm-mo-import-result" class="description" role="status"></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="pgm-mo-zip-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PECODEX_ZIP_ACTION ); ?>" />
				<?php wp_nonce_field( self::PECODEX_ZIP_ACTION ); ?>
				<label for="pgm-mo-zip-file"><strong><?php esc_html_e( 'Lataa ZIP ja luo kansiorakenne', 'private-gutenberg-media' ); ?></strong></label><br />
				<input id="pgm-mo-zip-file" type="file" name="pgm_mo_zip" accept=".zip,application/zip" />
				<?php submit_button( __( 'Tuo ZIP', 'private-gutenberg-media' ), 'secondary', 'submit', false ); ?>
				<p class="description"><?php esc_html_e( 'ZIPin kansiorakenne luodaan mediakansioiksi ja tiedostot lisätään Media Libraryyn.', 'private-gutenberg-media' ); ?></p>
			</form>
		</div>
		<?php
	}

	private function attribute_group_labels() {
		return array(
			'image'    => __( 'Kuvat', 'private-gutenberg-media' ),
			'document' => __( 'Dokumentit', 'private-gutenberg-media' ),
			'video'    => __( 'Videot', 'private-gutenberg-media' ),
			'audio'    => __( 'Äänet', 'private-gutenberg-media' ),
			'archive'  => __( 'Arkistot', 'private-gutenberg-media' ),
			'other'    => __( 'Muut', 'private-gutenberg-media' ),
		);
	}

	private function attribute_field_labels() {
		return array(
			'title'       => __( 'Otsikko', 'private-gutenberg-media' ),
			'alt'         => __( 'Alt-teksti', 'private-gutenberg-media' ),
			'caption'     => __( 'Kuvateksti', 'private-gutenberg-media' ),
			'description' => __( 'Kuvaus', 'private-gutenberg-media' ),
		);
	}

	private function image_size_choices() {
		$sizes = array(
			'thumbnail' => __( 'Pikkukuva', 'private-gutenberg-media' ),
			'medium'    => __( 'Keskikokoinen', 'private-gutenberg-media' ),
			'large'     => __( 'Suuri', 'private-gutenberg-media' ),
			'full'      => __( 'Täysikokoinen', 'private-gutenberg-media' ),
		);

		foreach ( wp_get_registered_image_subsizes() as $name => $data ) {
			if ( ! isset( $sizes[ $name ] ) ) {
				$sizes[ $name ] = sprintf(
					/* translators: 1: image size name, 2: width, 3: height. */
					__( '%1$s (%2$d x %3$d)', 'private-gutenberg-media' ),
					$name,
					isset( $data['width'] ) ? (int) $data['width'] : 0,
					isset( $data['height'] ) ? (int) $data['height'] : 0
				);
			}
		}

		return $sizes;
	}

	private function display_link_choices() {
		return array(
			'none' => __( 'Ei linkkiä', 'private-gutenberg-media' ),
			'file' => __( 'Mediatiedosto', 'private-gutenberg-media' ),
			'post' => __( 'Liitesivu', 'private-gutenberg-media' ),
		);
	}

	private function display_align_choices() {
		return array(
			'none'   => __( 'Ei mitään', 'private-gutenberg-media' ),
			'left'   => __( 'Vasen', 'private-gutenberg-media' ),
			'center' => __( 'Keskellä', 'private-gutenberg-media' ),
			'right'  => __( 'Oikea', 'private-gutenberg-media' ),
		);
	}

	private function sort_options() {
		return array(
			'date_desc'     => __( 'Uusin ensin', 'private-gutenberg-media' ),
			'date_asc'      => __( 'Vanhin ensin', 'private-gutenberg-media' ),
			'modified_desc' => __( 'Viimeksi muokattu ensin', 'private-gutenberg-media' ),
			'modified_asc'  => __( 'Vanhin muokkaus ensin', 'private-gutenberg-media' ),
			'id_desc'       => __( 'ID laskevasti', 'private-gutenberg-media' ),
			'id_asc'        => __( 'ID nousevasti', 'private-gutenberg-media' ),
			'title_asc'     => __( 'Otsikko A-Z', 'private-gutenberg-media' ),
			'title_desc'    => __( 'Otsikko Z-A', 'private-gutenberg-media' ),
			'author_asc'    => __( 'Tekijä A-Z', 'private-gutenberg-media' ),
			'author_desc'   => __( 'Tekijä Z-A', 'private-gutenberg-media' ),
			'size_desc'     => __( 'Suurin tiedosto ensin', 'private-gutenberg-media' ),
			'size_asc'      => __( 'Pienin tiedosto ensin', 'private-gutenberg-media' ),
		);
	}

	public static function access_modes() {
		return array(
			self::ACCESS_PUBLIC     => array(
				'label'       => __( 'Julkinen', 'private-gutenberg-media' ),
				'shortLabel'  => __( 'Julkinen', 'private-gutenberg-media' ),
				'description' => __( 'Kansion tiedostot toimivat tavallisina julkisina mediatiedostoina, ellei muu suojaus lukitse niitä.', 'private-gutenberg-media' ),
			),
			self::ACCESS_LOGGED_IN  => array(
				'label'       => __( 'Normaali Jäsensivu', 'private-gutenberg-media' ),
				'shortLabel'  => __( 'Jäsensivu', 'private-gutenberg-media' ),
				'description' => __( 'Kansion tiedostot avataan vain kirjautuneille käyttäjille suojatun endpointin kautta.', 'private-gutenberg-media' ),
			),
			self::ACCESS_ADMIN_ONLY => array(
				'label'       => __( 'Vain admin', 'private-gutenberg-media' ),
				'shortLabel'  => __( 'Ylläpito', 'private-gutenberg-media' ),
				'description' => __( 'Kansion tiedostot avataan vain ylläpitäjille suojatun endpointin kautta.', 'private-gutenberg-media' ),
			),
			self::ACCESS_ROLE_BASED => array(
				'label'       => __( 'Roolipohjainen', 'private-gutenberg-media' ),
				'shortLabel'  => __( 'Roolit', 'private-gutenberg-media' ),
				'description' => __( 'Kansion tiedostot avataan vain valituille käyttäjärooleille.', 'private-gutenberg-media' ),
			),
		);
	}

	public static function sanitize_access_mode( $access ) {
		$access = sanitize_key( (string) $access );

		return array_key_exists( $access, self::access_modes() ) ? $access : self::ACCESS_PUBLIC;
	}

	public static function access_rank( $access ) {
		$access = self::sanitize_access_mode( $access );
		if ( self::ACCESS_ADMIN_ONLY === $access ) {
			return 3;
		}
		if ( self::ACCESS_ROLE_BASED === $access ) {
			return 2;
		}
		if ( self::ACCESS_LOGGED_IN === $access ) {
			return 1;
		}

		return 0;
	}

	public static function strongest_access( $a, $b ) {
		return self::access_rank( $a ) >= self::access_rank( $b ) ? self::sanitize_access_mode( $a ) : self::sanitize_access_mode( $b );
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! is_admin() || ! current_user_can( 'upload_files' ) || ! $this->is_enabled() ) {
			return;
		}

		if ( ! in_array( $hook_suffix, array( 'upload.php', 'media_page_' . self::ADMIN_PAGE_SLUG, 'media_page_' . self::LEGACY_ADMIN_PAGE_SLUG, 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$css_path = $this->plugin_dir . 'assets/media-organizer.css';
		$js_path  = $this->plugin_dir . 'assets/media-organizer.js';
		$version  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : '0.1.0';
		$script_dependencies = array( 'jquery', 'media-views' );
		if ( wp_script_is( 'pecodex-media-library-admin-media', 'enqueued' ) || wp_script_is( 'pecodex-media-library-admin-media', 'registered' ) ) {
			$script_dependencies[] = 'pecodex-media-library-admin-media';
		}
		$vendor_scripts = array(
			'pgm-mo-jszip'   => 'assets/vendor/jszip.min.js',
			'pgm-mo-mammoth' => 'assets/vendor/mammoth.browser.min.js',
			'pgm-mo-xlsx'    => 'assets/vendor/xlsx.full.min.js',
			'pgm-mo-pdfjs'   => 'assets/vendor/pdf.min.js',
		);
		if ( 'upload.php' === $hook_suffix && wp_script_is( 'media-grid', 'registered' ) ) {
			$script_dependencies[] = 'media-grid';
		}

		wp_enqueue_style(
			'pecodex-media-library-organizer',
			$this->plugin_url . 'assets/media-organizer.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : $version
		);

		foreach ( $vendor_scripts as $handle => $relative_path ) {
			$vendor_path = $this->plugin_dir . $relative_path;
			if ( 'pgm-mo-pdfjs' === $handle && ( wp_script_is( 'pgm-pdf-js', 'enqueued' ) || wp_script_is( 'pgm-pdf-js', 'registered' ) ) ) {
				$script_dependencies[] = 'pgm-pdf-js';
				continue;
			}
			if ( file_exists( $vendor_path ) ) {
				wp_enqueue_script(
					$handle,
					$this->plugin_url . $relative_path,
					array(),
					(string) filemtime( $vendor_path ),
					true
				);
				$script_dependencies[] = $handle;
			}
		}

		wp_enqueue_script(
			'pecodex-media-library-organizer',
			$this->plugin_url . 'assets/media-organizer.js',
			$script_dependencies,
			$version,
			true
		);

		wp_add_inline_script(
			'pecodex-media-library-organizer',
			'window.pecodexMediaLibrary = window.pgmMediaOrganizer = ' . wp_json_encode( $this->script_config() ) . ';',
			'before'
		);
	}

	private function script_config() {
		$options = $this->get_options();

		return array(
			'brandName'       => __( 'Pecodex Media Control', 'private-gutenberg-media' ),
			'pluginVersion'   => class_exists( 'PGM_Private_Gutenberg_Media' ) ? PGM_Private_Gutenberg_Media::VERSION : '',
			'authorName'      => 'Pepe Utriainen',
			'companyName'     => 'Pecodex',
			'companyUrl'      => 'https://pecodex.fi/',
			'adminPageSlug'   => self::ADMIN_PAGE_SLUG,
			'legacyAdminPageSlug' => self::LEGACY_ADMIN_PAGE_SLUG,
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'adminPostUrl'    => admin_url( 'admin-post.php' ),
			'uploadUrl'       => admin_url( 'upload.php' ),
			'iconBaseUrl'     => $this->plugin_url . 'assets/icons/',
			'pdfWorkerUrl'    => $this->plugin_url . 'assets/vendor/pdf.worker.min.js',
			'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
			'privacyNonce'    => wp_create_nonce( 'pgm_toggle_attachment_privacy' ),
			'deleteNonce'     => wp_create_nonce( 'pgm_delete_attachments' ),
			'exportAction'    => self::PECODEX_EXPORT_ACTION,
			'libraryExportUrl'=> $this->export_url( 0, array(), true ),
			'zipImportAction' => self::PECODEX_ZIP_ACTION,
			'zipImportNonce'  => wp_create_nonce( self::PECODEX_ZIP_ACTION ),
			'canManagePrivateSettings' => current_user_can( 'manage_options' ),
			'privateMediaSettings' => $this->private_media_settings_payload(),
			'privateMediaDiagnostics' => $this->private_media_diagnostics_payload(),
			'privateMediaChoices'  => array(
				'loginModes'   => array(
					'auto'      => __( 'Automaattinen: FloAuth jos käytössä, muuten WordPress-login', 'private-gutenberg-media' ),
					'floauth'   => __( 'FloMembers / FloAuth', 'private-gutenberg-media' ),
					'custom'    => __( 'Oma kirjautumisosoite', 'private-gutenberg-media' ),
					'wordpress' => __( 'WordPress login', 'private-gutenberg-media' ),
				),
				'protectModes' => array(
					'marked'              => __( 'Vain yksityiseksi merkitty media', 'private-gutenberg-media' ),
					'marked_or_extension' => __( 'Yksityiseksi merkitty media tai tiedostopäätteet', 'private-gutenberg-media' ),
					'all'                 => __( 'Kaikki uploads-tiedostot', 'private-gutenberg-media' ),
				),
			),
			'ajaxActions'     => array(
				'pgm_mo_get_state'              => 'pecodex_mo_get_state',
				'pgm_mo_create_folder'          => 'pecodex_mo_create_folder',
				'pgm_mo_update_folder'          => 'pecodex_mo_update_folder',
				'pgm_mo_reorder_folder'         => 'pecodex_mo_reorder_folder',
				'pgm_mo_delete_folder'          => 'pecodex_mo_delete_folder',
				'pgm_mo_assign_attachments'     => 'pecodex_mo_assign_attachments',
				'pgm_mo_unassign_attachments'   => 'pecodex_mo_unassign_attachments',
				'pgm_mo_duplicate_attachment'   => 'pecodex_mo_duplicate_attachment',
				'pgm_mo_import_catfolders'      => 'pecodex_mo_import_catfolders',
				'pgm_mo_save_preferences'       => 'pecodex_mo_save_preferences',
				'pgm_mo_save_private_settings'  => 'pecodex_mo_save_private_settings',
				'pgm_bulk_toggle_attachment_privacy' => 'pecodex_bulk_toggle_attachment_privacy',
				'pgm_bulk_delete_attachments'   => 'pecodex_bulk_delete_attachments',
			),
			'queryArgs'       => array(
				'folder'           => self::FOLDER_QUERY_ARG,
				'legacyFolder'     => self::LEGACY_FOLDER_QUERY_ARG,
				'order'            => self::ORDER_QUERY_ARG,
				'legacyOrder'      => self::LEGACY_ORDER_QUERY_ARG,
				'deepSearch'       => self::DEEP_SEARCH_QUERY_ARG,
				'legacyDeepSearch' => self::LEGACY_DEEP_SEARCH_QUERY_ARG,
				'refresh'          => 'pecodex_mo_refresh',
			),
			'currentFolder'   => $this->initial_folder(),
			'options'         => array(
				'showFolderTree'    => (bool) $options['show_folder_tree'],
				'showCounts'        => (bool) $options['show_counts'],
				'showEmptyFolders'  => (bool) $options['show_empty_folders'],
				'includeChildren'   => (bool) $options['include_children'],
				'gridSize'          => $options['grid_size'],
				'infiniteScrollGrid'=> (bool) $options['infinite_scroll_grid'],
				'folderTreeWidth'   => (int) $options['folder_tree_width'],
				'defaultSort'       => $options['default_sort'],
				'deepSearchDefault'=> (bool) $options['deep_search_default'],
				'startupFolder'     => (int) $options['startup_folder'],
				'listColumns'       => array_values( $options['list_columns'] ),
				'overwriteAttributes' => (bool) $options['overwrite_attributes'],
				'defaultAttributes' => $options['default_attributes'],
				'displayDefaults'   => $options['display_defaults'],
			),
			'columnChoices'       => $this->list_column_choices(),
			'sortOptions'         => $this->sort_options(),
			'attributeGroups'     => $this->attribute_group_labels(),
			'attributeFields'     => $this->attribute_field_labels(),
			'imageSizeChoices'    => $this->image_size_choices(),
			'displayLinkChoices'  => $this->display_link_choices(),
			'displayAlignChoices' => $this->display_align_choices(),
			'accessChoices'       => self::access_modes(),
			'availableRoles'      => wp_roles()->get_names(),
			'i18n'            => array(
				'allFiles'          => __( 'Kaikki tiedostot', 'private-gutenberg-media' ),
				'uncategorized'    => __( 'Ilman kansiota', 'private-gutenberg-media' ),
				'newFolder'        => __( 'Uusi kansio', 'private-gutenberg-media' ),
				'rename'           => __( 'Nimeä uudelleen', 'private-gutenberg-media' ),
				'delete'           => __( 'Poista', 'private-gutenberg-media' ),
				'exportFolder'     => __( 'Vie ZIP', 'private-gutenberg-media' ),
				'exportSelected'   => __( 'Vie valitut ZIPiksi', 'private-gutenberg-media' ),
				'download'         => __( 'Lataa ZIP', 'private-gutenberg-media' ),
				'moveSelected'     => __( 'Siirrä valitut', 'private-gutenberg-media' ),
				'moveToFolder'     => __( 'Siirrä kansioon', 'private-gutenberg-media' ),
				'moveToFolderTitle' => __( 'Siirrä mediatiedostot kansioon', 'private-gutenberg-media' ),
				'chooseTargetFolder' => __( 'Kohdekansio', 'private-gutenberg-media' ),
				'moveToUncategorized' => __( 'Ilman kansiota', 'private-gutenberg-media' ),
				'moveFiles'        => __( 'Siirrä', 'private-gutenberg-media' ),
				'movingFiles'      => __( 'Siirretään tiedostoja...', 'private-gutenberg-media' ),
				'moveFolderHelp'   => __( 'Tämä vaihtaa vain mediakirjaston kansioluokituksen. Varsinaista tiedostoa ei poisteta.', 'private-gutenberg-media' ),
				'protectSelected'  => __( 'Suojaa', 'private-gutenberg-media' ),
				'unprotectSelected' => __( 'Poista suojaus', 'private-gutenberg-media' ),
				'bulkProtect'      => __( 'Suojaa valitut', 'private-gutenberg-media' ),
				'bulkUnprotect'    => __( 'Poista suojaus', 'private-gutenberg-media' ),
				'confirmProtectMediaTitle' => __( 'Suojaa valitut mediatiedostot?', 'private-gutenberg-media' ),
				'confirmUnprotectMediaTitle' => __( 'Poista suojaus valituilta?', 'private-gutenberg-media' ),
				'confirmProtectMediaBody' => __( 'Valitut tiedostot merkitään suojatuiksi ja public uploads -kopio poistetaan, kun private-varasto on valmis.', 'private-gutenberg-media' ),
				'confirmUnprotectMediaBody' => __( 'Valitut tiedostot palautetaan julkisiksi vain, jos kansio tai asetussääntö ei pidä niitä edelleen suojattuina.', 'private-gutenberg-media' ),
				'privacyActionProtect' => __( 'Suojaa tiedostot', 'private-gutenberg-media' ),
				'privacyActionUnprotect' => __( 'Poista suojaus', 'private-gutenberg-media' ),
				'privacySelectedFiles' => __( 'Valitut mediatiedostot', 'private-gutenberg-media' ),
				'privacyProtectedResult' => __( 'Vanha uploads-linkki ohjataan kirjautumiseen, kun tiedosto on suojattu.', 'private-gutenberg-media' ),
				'privacyUnprotectedResult' => __( 'Julkiseksi palautettu tiedosto toimii taas normaalina WordPress-mediatiedostona.', 'private-gutenberg-media' ),
				'removeFromFolder' => __( 'Poista kansiosta', 'private-gutenberg-media' ),
				'removeFromFolderShort' => __( 'Irrota', 'private-gutenberg-media' ),
				'removeFromFolderTitle' => __( 'Irrota valitut tiedostot tästä kansiosta poistamatta mediatiedostoja.', 'private-gutenberg-media' ),
				'confirmRemoveFromFolderTitle' => __( 'Poista valitut tästä kansiosta?', 'private-gutenberg-media' ),
				'confirmRemoveFromFolderBody' => __( 'Tiedostoja ei poisteta Media Librarysta tai palvelimelta. Ne irrotetaan vain tästä kansiosta ja näkyvät jatkossa Ilman kansiota -näkymässä, ellei niillä ole muuta kansiota.', 'private-gutenberg-media' ),
				'removeFromFolderAction' => __( 'Poista kansiosta', 'private-gutenberg-media' ),
				'removeFromFolderResult' => __( 'Varsinainen tiedosto, suojaukset ja mediatiedot säilyvät ennallaan.', 'private-gutenberg-media' ),
				'exportZipShort'   => __( 'Vie ZIP', 'private-gutenberg-media' ),
				'duplicate'        => __( 'Monista', 'private-gutenberg-media' ),
				'duplicateSelected' => __( 'Monista valitut', 'private-gutenberg-media' ),
				'duplicatingFiles' => __( 'Monistetaan tiedostoja...', 'private-gutenberg-media' ),
				'duplicatedFiles'  => __( 'tiedostoa monistettiin.', 'private-gutenberg-media' ),
				'duplicateMediaTitle' => __( 'Monista mediatiedostot?', 'private-gutenberg-media' ),
				'duplicateMediaBody' => __( 'Valituista mediatiedostoista luodaan kopiot Media Libraryyn. Alkuperäiset tiedostot säilyvät ennallaan.', 'private-gutenberg-media' ),
				'duplicateFiles'   => __( 'Monista tiedostot', 'private-gutenberg-media' ),
				'exportZipTitle'   => __( 'Vie valitut ZIPiksi?', 'private-gutenberg-media' ),
				'exportZipBody'    => __( 'Valitut tiedostot pakataan ZIP-lataukseksi. Mediatiedostoihin tai kansioihin ei tehdä muutoksia.', 'private-gutenberg-media' ),
				'exportZipAction'  => __( 'Lataa ZIP', 'private-gutenberg-media' ),
				'exportFolderZipTitle' => __( 'Lataa kansio ZIPiksi?', 'private-gutenberg-media' ),
				'exportFolderZipBody' => __( 'Kansion tiedostot pakataan ZIP-lataukseksi. Mediatiedostoihin, kansioihin tai suojauksiin ei tehdä muutoksia.', 'private-gutenberg-media' ),
				'exportFolderZipAction' => __( 'Lataa kansion ZIP', 'private-gutenberg-media' ),
				'exportFolderIncludesChildren' => __( 'Asetus sisällyttää myös alikansioiden tiedostot.', 'private-gutenberg-media' ),
				'exportFolderDirectOnly' => __( 'Asetus vie vain tämän kansion omat tiedostot.', 'private-gutenberg-media' ),
				'exportFolderEmpty' => __( 'Kansiossa ei ole näkyviä mediatiedostoja. ZIP-lataus voi olla tyhjä.', 'private-gutenberg-media' ),
				'importCatFolders' => __( 'Tuo CatFolders', 'private-gutenberg-media' ),
				'importExportTools' => __( 'Tuonti, vienti ja ZIP', 'private-gutenberg-media' ),
				'manageImportExportTools' => __( 'Avaa tuonti- ja vientityökalut', 'private-gutenberg-media' ),
				'importExportToolsSummary' => __( 'CatFolders, koko kirjaston ZIP ja ZIP-tuonti', 'private-gutenberg-media' ),
				'importExportToolsHelp' => __( 'Tuo olemassa olevia kansioita, vie mediakirjasto ZIPiksi tai rakenna kansiopuu ZIP-tiedostosta.', 'private-gutenberg-media' ),
				'importCatFoldersTitle' => __( 'Tuo CatFoldersista', 'private-gutenberg-media' ),
				'importCatFoldersHelp' => __( 'Kopioi CatFolders-kansiot ja medialiitokset Pecodex Media Libraryyn. Nykyiset Pecodex-kansiot säilyvät.', 'private-gutenberg-media' ),
				'exportLibraryZip' => __( 'Vie koko mediakirjasto ZIPiksi', 'private-gutenberg-media' ),
				'exportLibraryZipHelp' => __( 'Lataa kaikki mediat nykyisellä kansiorakenteella. Alikansiot sisällytetään vientiin.', 'private-gutenberg-media' ),
				'zipImportTitle' => __( 'Tuo ZIP ja luo kansiorakenne', 'private-gutenberg-media' ),
				'zipImportHelp' => __( 'ZIPin kansiorakenne luodaan mediakansioiksi ja tiedostot lisätään Media Libraryyn.', 'private-gutenberg-media' ),
				'chooseZipFile' => __( 'Valitse ZIP-tiedosto', 'private-gutenberg-media' ),
				'importZip' => __( 'Tuo ZIP', 'private-gutenberg-media' ),
				'zipFileRequired' => __( 'Valitse ensin ZIP-tiedosto.', 'private-gutenberg-media' ),
				'zipImportSubmitting' => __( 'Lähetetään ZIP-tuonti...', 'private-gutenberg-media' ),
				'privateMediaSettings' => __( 'Suojaus ja kirjautuminen', 'private-gutenberg-media' ),
				'managePrivateMediaSettings' => __( 'Hallitse suojausasetuksia', 'private-gutenberg-media' ),
				'privateMediaSettingsSummary' => __( 'Login, tiedostopäätteet ja private-varasto', 'private-gutenberg-media' ),
				'privateMediaSettingsHelp' => __( 'Nämä asetukset ohjaavat miten suojatut mediatiedostot avataan, kenelle ne näkyvät ja poistetaanko public uploads -kopio.', 'private-gutenberg-media' ),
				'privateMediaEnabled' => __( 'Ota suojaus käyttöön', 'private-gutenberg-media' ),
				'privateMediaLoginMode' => __( 'Kirjautumaton käyttäjä ohjataan', 'private-gutenberg-media' ),
				'privateMediaCustomLoginUrl' => __( 'Oma kirjautumisosoite', 'private-gutenberg-media' ),
				'privateMediaProtectMode' => __( 'Suojaustapa', 'private-gutenberg-media' ),
				'privateMediaProtectedExtensions' => __( 'Suojattavat tiedostopäätteet', 'private-gutenberg-media' ),
				'privateMediaRequiredCapability' => __( 'Vaadittu käyttöoikeus', 'private-gutenberg-media' ),
				'privateMediaProtectUnknownUploads' => __( 'Suojaa myös tuntemattomat upload-tiedostot endpointin kautta', 'private-gutenberg-media' ),
				'privateMediaMoveFiles' => __( 'Piilota yksityiset mediatiedostot julkisilta salattuun uploads-varastoon', 'private-gutenberg-media' ),
				'privateMediaMoveFilesHelp' => __( 'Vahvin tila: public uploads -kopio poistetaan ja tiedosto puretaan vain Pecodexin suojatun endpointin kautta.', 'private-gutenberg-media' ),
				'privateMediaSyncStorage' => __( 'Siirrä odottavat tiedostot nyt', 'private-gutenberg-media' ),
				'privateMediaSyncStorageHelp' => __( 'Ajaa läpi yksityiseksi merkityt mediat ja siirtää public-kopiot private-varastoon.', 'private-gutenberg-media' ),
				'privateMediaDiagnostics' => __( 'Diagnostiikka ja korjaus', 'private-gutenberg-media' ),
				'managePrivateMediaDiagnostics' => __( 'Tarkista suojaustilanne', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsSummary' => __( 'Private-varasto, public-kopiot ja linkkisynkka', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsHelp' => __( 'Yhteenveto kertoo ovatko suojatut tiedostot fyysisesti pois public uploads -kansiosta ja onko linkkisynkka ajan tasalla.', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsOpenFull' => __( 'Avaa täysi diagnostiikka', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsSync' => __( 'Synkronoi suojatut tiedostot', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsHealthy' => __( 'Suojaus näyttäää hyvältä.', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsAttention' => __( 'Tarkistettavia kohteita löytyi.', 'private-gutenberg-media' ),
				'privateMediaDiagnosticsDisabled' => __( 'Suojaus ei ole käytössä.', 'private-gutenberg-media' ),
				'privateMediaDiagnosticProtectedFiles' => __( 'Suojatut liitteet', 'private-gutenberg-media' ),
				'privateMediaDiagnosticHiddenFiles' => __( 'Piilotettu publicista', 'private-gutenberg-media' ),
				'privateMediaDiagnosticLinkOnly' => __( 'Public-kopio tarkistettava', 'private-gutenberg-media' ),
				'privateMediaDiagnosticPublicCopies' => __( 'Public-kopioita', 'private-gutenberg-media' ),
				'privateMediaDiagnosticPrivateCopies' => __( 'Private-kopioita', 'private-gutenberg-media' ),
				'privateMediaDiagnosticDirectBlocked' => __( 'Suorat uploads-URLit estetty', 'private-gutenberg-media' ),
				'privateMediaDiagnosticDirectOpen' => __( 'Suorat uploads-URLit avoinna', 'private-gutenberg-media' ),
				'privateMediaDiagnosticAttentionCount' => __( 'Huomiot', 'private-gutenberg-media' ),
				'privateMediaDiagnosticOriginalUrlMemory' => __( 'Alkuperäisiä URL-muisteja', 'private-gutenberg-media' ),
				'privateMediaDiagnosticActiveProtected' => __( 'Aktiivisia suojalinkkejä', 'private-gutenberg-media' ),
				'privateMediaDiagnosticContentPrivate' => __( 'Private-linkkisynkkia', 'private-gutenberg-media' ),
				'privateMediaDiagnosticOpenSsl' => __( 'OpenSSL', 'private-gutenberg-media' ),
				'privateMediaDiagnosticStorage' => __( 'Private-varasto', 'private-gutenberg-media' ),
				'aboutPecodex' => __( 'Tietoja ja tuki', 'private-gutenberg-media' ),
				'manageAboutPecodex' => __( 'Pecodex Media Control', 'private-gutenberg-media' ),
				'aboutPecodexSummary' => __( 'Versio, tekijä ja tukilinkit', 'private-gutenberg-media' ),
				'aboutPecodexHelp' => __( 'Tämä lisäosa on Pecodexin mediatiedostojen hallinta- ja suojaustyökalu WordPressiin.', 'private-gutenberg-media' ),
				'aboutPluginVersion' => __( 'Versio', 'private-gutenberg-media' ),
				'aboutPluginAuthor' => __( 'Tekijä', 'private-gutenberg-media' ),
				'aboutPluginCompany' => __( 'Yritys', 'private-gutenberg-media' ),
				'aboutPluginWebsite' => __( 'Verkkosivusto', 'private-gutenberg-media' ),
				'aboutOpenWebsite' => __( 'Avaa pecodex.fi', 'private-gutenberg-media' ),
				'aboutBrandNote' => __( 'Legacy PGM-nimet säilyvät vain yhteensopivuuden vuoksi. Uudet käyttöliittymät, endpointit ja paketit käyttävät Pecodex-nimiä.', 'private-gutenberg-media' ),
				'folderName'       => __( 'Kansion nimi', 'private-gutenberg-media' ),
				'createFolderTitle'=> __( 'Uusi kansio', 'private-gutenberg-media' ),
				'createFolder'     => __( 'Luo kansio', 'private-gutenberg-media' ),
				'folderLocation'   => __( 'Sijainti', 'private-gutenberg-media' ),
				'folderLocationHelp' => __( 'Valitse juuritaso tai kansio, jonka alle uusi kansio luodaan.', 'private-gutenberg-media' ),
				'folderNameRequired' => __( 'Anna kansion nimi.', 'private-gutenberg-media' ),
				'rootFolder'       => __( 'Juuritaso (Kaikki tiedostot)', 'private-gutenberg-media' ),
				'cancel'           => __( 'Peruuta', 'private-gutenberg-media' ),
				'confirmDeleteFolderTitle' => __( 'Poista kansio?', 'private-gutenberg-media' ),
				'deleteFolder'     => __( 'Poista kansio', 'private-gutenberg-media' ),
				'confirmDeleteFolderBody' => __( 'Tiedostoja ei poisteta palvelimelta. Valitse alta poistetaanko vain tämä kansio vai koko alipuuhun kuuluva kansiorakenne.', 'private-gutenberg-media' ),
				'deleteFolderScope' => __( 'Poiston laajuus', 'private-gutenberg-media' ),
				'deleteFolderSingleTitle' => __( 'Poista vain tämä kansio', 'private-gutenberg-media' ),
				'deleteFolderSingleHelp' => __( 'Tämän kansion omat tiedostot siirtyvät Ilman kansiota -näkymään. Alikansiot säilyvät ja nousevat ylemmälle tasolle.', 'private-gutenberg-media' ),
				'deleteFolderSingleNoChildrenHelp' => __( 'Tämän kansion tiedostot siirtyvät Ilman kansiota -näkymään. Mediatiedostoja ei poisteta.', 'private-gutenberg-media' ),
				'deleteFolderTreeTitle' => __( 'Poista myös alikansiot kansiopuusta', 'private-gutenberg-media' ),
				'deleteFolderTreeHelp' => __( 'Kaikki tämän kansion ja alikansioiden tiedostot irrotetaan kansioista, mutta mediatiedostoja ei poisteta.', 'private-gutenberg-media' ),
				'noChildFolders'   => __( 'Ei alikansioita.', 'private-gutenberg-media' ),
				'confirmDelete'    => __( 'Poistetaanko kansio? Tiedostot jäävät Media Libraryyn.', 'private-gutenberg-media' ),
				'saving'           => __( 'Tallennetaan...', 'private-gutenberg-media' ),
				'loading'          => __( 'Ladataan...', 'private-gutenberg-media' ),
				'done'             => __( 'Valmis.', 'private-gutenberg-media' ),
				'error'            => __( 'Toimintoa ei voitu suorittaa.', 'private-gutenberg-media' ),
				'noSelection'      => __( 'Valitse ensin yksi tai useampi mediatiedosto.', 'private-gutenberg-media' ),
				'dropHere'         => __( 'Pudota tähän kansioon', 'private-gutenberg-media' ),
				'sort'             => __( 'Järjestys', 'private-gutenberg-media' ),
				'deepSearch'       => __( 'Hae metatiedoista', 'private-gutenberg-media' ),
				'properties'       => __( 'Ominaisuudet', 'private-gutenberg-media' ),
				'folderVisibility' => __( 'Kansion näkyvyys', 'private-gutenberg-media' ),
				'effectiveAccess'  => __( 'Voimassa oleva näkyvyys', 'private-gutenberg-media' ),
				'saveProperties'   => __( 'Tallenna ominaisuudet', 'private-gutenberg-media' ),
				'protectFolderLoggedIn' => __( 'Suojaa kansio kirjautuneille', 'private-gutenberg-media' ),
				'protectFolderAdmin' => __( 'Suojaa kansio ylläpitäjille', 'private-gutenberg-media' ),
				'clearFolderProtection' => __( 'Poista kansion oma suojaus', 'private-gutenberg-media' ),
				'folderProtectionTitle' => __( 'Kansion suojaus', 'private-gutenberg-media' ),
				'folderProtectionBody' => __( 'Valitse miten tämän kansion nykyiset ja myöhemmin tähän siirrettävät tiedostot suojataan.', 'private-gutenberg-media' ),
				'folderProtectionScope' => __( 'Suojaustaso', 'private-gutenberg-media' ),
				'folderProtectionPublicTitle' => __( 'Julkinen kansio', 'private-gutenberg-media' ),
				'folderProtectionLoggedInTitle' => __( 'Vain kirjautuneet', 'private-gutenberg-media' ),
				'folderProtectionAdminTitle' => __( 'Vain ylläpitäjät', 'private-gutenberg-media' ),
				'folderProtectionPublicModalHelp' => __( 'Tämän kansion oma suojaus poistetaan. Tiedostot voivat silti olla suojattuja yksittäisen lukon tai yläkansion kautta.', 'private-gutenberg-media' ),
				'folderProtectionLoggedInHelp' => __( 'Nykyiset tiedostot, alikansioiden tiedostot ja myöhemmin tähän siirretyt tiedostot avataan vain kirjautuneille käyttäjille.', 'private-gutenberg-media' ),
				'folderProtectionAdminHelp' => __( 'Nykyiset tiedostot, alikansioiden tiedostot ja myöhemmin tähän siirretyt tiedostot avataan vain ylläpitäjille.', 'private-gutenberg-media' ),
				'folderProtectionInheritedHelp' => __( 'Yläkansion suojaus pysyy voimassa, vaikka tämän kansion oma suojaus olisi julkinen.', 'private-gutenberg-media' ),
				'currentFolderProtection' => __( 'Kansion oma suojaus', 'private-gutenberg-media' ),
				'inheritedFolderProtection' => __( 'Yläkansion suojaus', 'private-gutenberg-media' ),
				'effectiveFolderProtection' => __( 'Voimassa oleva suojaus', 'private-gutenberg-media' ),
				'noInheritedProtection' => __( 'Ei perittyö suojausta', 'private-gutenberg-media' ),
				'saveFolderProtection' => __( 'Tallenna suojaus', 'private-gutenberg-media' ),
				'folderProtectionSaved' => __( 'Kansion suojaus tallennettiin.', 'private-gutenberg-media' ),
				'propertyId'       => __( 'ID', 'private-gutenberg-media' ),
				'propertyParent'   => __( 'Yläkansio', 'private-gutenberg-media' ),
				'propertyFiles'    => __( 'Tiedostot', 'private-gutenberg-media' ),
				'settings'         => __( 'Asetukset', 'private-gutenberg-media' ),
				'folderSearchPlaceholder' => __( 'Kirjoita kansion nimi...', 'private-gutenberg-media' ),
				'collapseAll'      => __( 'Sulje kaikki kansiot', 'private-gutenberg-media' ),
				'expandAll'        => __( 'Avaa kaikki kansiot', 'private-gutenberg-media' ),
				'resizeSidebar'    => __( 'Muuta sivupalkin leveyttä', 'private-gutenberg-media' ),
				'libraryLayoutSettings' => __( 'Mediakirjaston näkymä', 'private-gutenberg-media' ),
				'manageLibraryLayoutSettings' => __( 'Hallitse näkymää ja kansiopuuta', 'private-gutenberg-media' ),
				'libraryLayoutSettingsSummary' => __( 'Ruudukko: %s Â· järjestys: %s', 'private-gutenberg-media' ),
				'libraryLayoutSettingsHelp' => __( 'Säädä ruudukon, järjestyksen, haun ja kansiopuun oletukset yhdessä paikassa.', 'private-gutenberg-media' ),
				'defaultStartupFolder' => __( 'Oletuskansio avattaessa', 'private-gutenberg-media' ),
				'infiniteScrollGrid' => __( 'Jatkuva vieritys ruudukkonäkymässä', 'private-gutenberg-media' ),
				'gridSize'         => __( 'Ruudukkokoko', 'private-gutenberg-media' ),
				'gridSmall'        => __( 'Pieni', 'private-gutenberg-media' ),
				'gridMedium'       => __( 'Keskikokoinen', 'private-gutenberg-media' ),
				'gridLarge'        => __( 'Suuri', 'private-gutenberg-media' ),
				'listColumns'      => __( 'Listan sarakkeet', 'private-gutenberg-media' ),
				'manageColumns'    => __( 'Hallitse sarakkeita', 'private-gutenberg-media' ),
				'columnsEnabled'   => __( '%d saraketta käytössä', 'private-gutenberg-media' ),
				'selectAllColumns' => __( 'Valitse kaikki', 'private-gutenberg-media' ),
				'clearColumns'     => __( 'Tyhjennä', 'private-gutenberg-media' ),
				'resetColumns'     => __( 'Palauta oletukset', 'private-gutenberg-media' ),
				'columnsHelp'      => __( 'Valitse sarakkeet, jotka näkyvät listanäkymässä.', 'private-gutenberg-media' ),
				'noColumnsSelected' => __( 'Valitse vähintään yksi sarake.', 'private-gutenberg-media' ),
				'galleryShortcode' => __( 'Galleria-shortcode', 'private-gutenberg-media' ),
				'customizeShortcode' => __( 'Muokkaa', 'private-gutenberg-media' ),
				'galleryShortcodeTitle' => __( 'Galleria-shortcoden asetukset', 'private-gutenberg-media' ),
				'galleryShortcodeHelp' => __( 'Säädä shortcodea ennen kopiointia. Tämä ei muuta kansion tiedostoja tai asetuksia.', 'private-gutenberg-media' ),
				'galleryImageSize' => __( 'Kuvakoko', 'private-gutenberg-media' ),
				'galleryImageSizeThumbnail' => __( 'Pienoiskuva', 'private-gutenberg-media' ),
				'galleryImageSizeMedium' => __( 'Keskikokoinen', 'private-gutenberg-media' ),
				'galleryImageSizeLarge' => __( 'Suuri', 'private-gutenberg-media' ),
				'galleryImageSizeFull' => __( 'Täysikokoinen', 'private-gutenberg-media' ),
				'galleryColumns'   => __( 'Sarakkeet', 'private-gutenberg-media' ),
				'galleryLimit'     => __( 'Määrä', 'private-gutenberg-media' ),
				'galleryLink'      => __( 'Linkki', 'private-gutenberg-media' ),
				'galleryLinkNone'  => __( 'Ei linkkiä', 'private-gutenberg-media' ),
				'galleryLinkFile'  => __( 'Mediatiedosto', 'private-gutenberg-media' ),
				'galleryLinkPost'  => __( 'Liitesivu', 'private-gutenberg-media' ),
				'galleryShortcodePreview' => __( 'Shortcode', 'private-gutenberg-media' ),
				'useShortcode'     => __( 'Käytä shortcodea', 'private-gutenberg-media' ),
				'copyShortcode'    => __( 'Kopioi', 'private-gutenberg-media' ),
				'shortcodeCopied'  => __( 'Shortcode kopioitu.', 'private-gutenberg-media' ),
				'shortcodeCopyFailed' => __( 'Shortcodea ei voitu kopioida.', 'private-gutenberg-media' ),
				'defaultSort'      => __( 'Oletusjärjestys', 'private-gutenberg-media' ),
				'deepSearchDefault'=> __( 'Hae metatiedoista oletuksena', 'private-gutenberg-media' ),
				'displayDefaults'  => __( 'Lisäyksen näyttöoletukset', 'private-gutenberg-media' ),
				'manageDisplayDefaults' => __( 'Hallitse näyttöoletuksia', 'private-gutenberg-media' ),
				'displayDefaultsSummary' => __( 'Linkki: %s Â· Koko: %s Â· Tasaus: %s', 'private-gutenberg-media' ),
				'displayDefaultsHelp' => __( 'Nämä asetukset esitäyttävät WordPressin mediavalitsimen, kun media lisätään sisältöön.', 'private-gutenberg-media' ),
				'displayLink'      => __( 'Linkki', 'private-gutenberg-media' ),
				'displaySize'      => __( 'Koko', 'private-gutenberg-media' ),
				'displayAlign'     => __( 'Tasaus', 'private-gutenberg-media' ),
				'displayLinkNone'  => __( 'Ei linkkiä', 'private-gutenberg-media' ),
				'displayLinkFile'  => __( 'Mediatiedosto', 'private-gutenberg-media' ),
				'displayLinkPost'  => __( 'Liitesivu', 'private-gutenberg-media' ),
				'displayAlignNone' => __( 'Ei mitään', 'private-gutenberg-media' ),
				'displayAlignLeft' => __( 'Vasen', 'private-gutenberg-media' ),
				'displayAlignCenter' => __( 'Keskellä', 'private-gutenberg-media' ),
				'displayAlignRight'=> __( 'Oikea', 'private-gutenberg-media' ),
				'metadataDefaults' => __( 'Oletusmetatiedot', 'private-gutenberg-media' ),
				'manageMetadataDefaults' => __( 'Hallitse oletusmetatietoja', 'private-gutenberg-media' ),
				'metadataDefaultsSummary' => __( '%d kenttää käytössä', 'private-gutenberg-media' ),
				'metadataDefaultsSummaryOverwrite' => __( '%d kenttää käytössä Â· korvaa olemassa olevat', 'private-gutenberg-media' ),
				'metadataDefaultsHelp' => __( 'Dynaamiset tagit: {filename}, {title}, {date}, {site_name}, {mime}, {extension}, {folder}. Tyhjä kenttä ei muuta mediaa.', 'private-gutenberg-media' ),
				'overwriteAttributes' => __( 'Korvaa olemassa olevat kentät latauksessa', 'private-gutenberg-media' ),
				'metadataPlaceholder' => __( 'Tyhjä kenttä ei muuta mediaa.', 'private-gutenberg-media' ),
				'metadataTitle'   => __( 'Otsikko', 'private-gutenberg-media' ),
				'metadataAlt'     => __( 'Alt-teksti', 'private-gutenberg-media' ),
				'metadataCaption' => __( 'Kuvateksti', 'private-gutenberg-media' ),
				'metadataDescription' => __( 'Kuvaus', 'private-gutenberg-media' ),
				'metadataGroupImage' => __( 'Kuvat', 'private-gutenberg-media' ),
				'metadataGroupDocument' => __( 'Dokumentit', 'private-gutenberg-media' ),
				'metadataGroupVideo' => __( 'Videot', 'private-gutenberg-media' ),
				'metadataGroupAudio' => __( 'Äänet', 'private-gutenberg-media' ),
				'metadataGroupArchive' => __( 'Arkistot', 'private-gutenberg-media' ),
				'metadataGroupOther' => __( 'Muut', 'private-gutenberg-media' ),
				'showFileCounts'   => __( 'Näytä tiedostomäärät', 'private-gutenberg-media' ),
				'includeChildren'  => __( 'Sisällytä alikansiot', 'private-gutenberg-media' ),
				'showEmptyFolders' => __( 'Näytä tyhjät kansiot', 'private-gutenberg-media' ),
				'saveSettings'     => __( 'Tallenna asetukset', 'private-gutenberg-media' ),
				'back'             => __( 'Takaisin', 'private-gutenberg-media' ),
				'close'            => __( 'Sulje', 'private-gutenberg-media' ),
			),
		);
	}

	private function private_media_settings_defaults() {
		return array(
			'enabled'                 => 1,
			'login_mode'              => 'auto',
			'custom_login_url'        => '',
			'protect_mode'            => 'marked',
			'protected_extensions'    => 'pdf,doc,docx,xls,xlsx,ppt,pptx,zip',
			'protect_unknown_uploads' => 0,
			'move_files_to_private_storage' => 0,
			'required_capability'     => 'read',
		);
	}

	private function private_media_options() {
		if ( ! class_exists( 'PGM_Private_Gutenberg_Media' ) ) {
			return $this->private_media_settings_defaults();
		}

		$options = get_option( PGM_Private_Gutenberg_Media::OPTION_NAME, array() );
		$options = is_array( $options ) ? $options : array();

		return wp_parse_args( $options, $this->private_media_settings_defaults() );
	}

	private function private_media_settings_payload( $options = null ) {
		$options = null === $options
			? $this->private_media_options()
			: wp_parse_args( (array) $options, $this->private_media_settings_defaults() );

		$sync_url = class_exists( 'PGM_Private_Gutenberg_Media' )
			? wp_nonce_url(
				add_query_arg(
					'action',
					PGM_Private_Gutenberg_Media::PECODEX_ACTION_SYNC_STORAGE,
					admin_url( 'admin-post.php' )
				),
				PGM_Private_Gutenberg_Media::PECODEX_ACTION_SYNC_STORAGE
			)
			: '';

		return array(
			'enabled'                 => ! empty( $options['enabled'] ),
			'loginMode'               => isset( $options['login_mode'] ) ? (string) $options['login_mode'] : 'auto',
			'customLoginUrl'          => isset( $options['custom_login_url'] ) ? (string) $options['custom_login_url'] : '',
			'protectMode'             => isset( $options['protect_mode'] ) ? (string) $options['protect_mode'] : 'marked',
			'protectedExtensions'     => isset( $options['protected_extensions'] ) ? (string) $options['protected_extensions'] : '',
			'protectUnknownUploads'   => ! empty( $options['protect_unknown_uploads'] ),
			'moveFilesToPrivateStorage' => ! empty( $options['move_files_to_private_storage'] ),
			'requiredCapability'      => isset( $options['required_capability'] ) ? (string) $options['required_capability'] : 'read',
			'syncStorageUrl'          => $sync_url,
		);
	}

	private function private_media_diagnostics_payload() {
		if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'PGM_Private_Gutenberg_Media' ) ) {
			return array();
		}

		$plugin = PGM_Private_Gutenberg_Media::instance();
		if ( ! method_exists( $plugin, 'admin_diagnostic_summary_payload' ) ) {
			return array();
		}

		return $plugin->admin_diagnostic_summary_payload();
	}

	public function apply_upload_defaults( $attachment_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$options = $this->get_options();
		if ( ! empty( $options['default_folder'] ) && term_exists( (int) $options['default_folder'], self::TAXONOMY ) ) {
			$current_terms = wp_get_object_terms( $attachment_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
			if ( empty( $current_terms ) || is_wp_error( $current_terms ) ) {
				wp_set_object_terms( $attachment_id, array( (int) $options['default_folder'] ), self::TAXONOMY, false );
				do_action( 'pgm_mo_attachment_folder_changed', (int) $attachment_id, (int) $options['default_folder'], array() );
			}
		}

		$this->apply_default_attributes( $attachment_id );
		$this->track_attachment_size( $attachment_id );
	}

	public function track_attachment_size_after_metadata( $metadata, $attachment_id ) {
		$this->track_attachment_size( $attachment_id );

		return $metadata;
	}

	private function track_attachment_size( $attachment_id ) {
		$file = get_attached_file( $attachment_id, true );
		if ( $file && file_exists( $file ) ) {
			update_post_meta( $attachment_id, self::SIZE_META_KEY, (int) filesize( $file ) );
		}
	}

	private function apply_default_attributes( $attachment_id ) {
		$options = $this->get_options();
		$group   = $this->attachment_group( $attachment_id );
		$rules   = isset( $options['default_attributes'][ $group ] ) ? $options['default_attributes'][ $group ] : array();
		$rules   = wp_parse_args( $rules, array( 'title' => '', 'alt' => '', 'caption' => '', 'description' => '' ) );
		$post    = get_post( $attachment_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$overwrite = ! empty( $options['overwrite_attributes'] );
		$update    = array( 'ID' => $attachment_id );

		if ( '' !== trim( $rules['title'] ) && ( $overwrite || '' === trim( $post->post_title ) ) ) {
			$update['post_title'] = $this->render_attribute_template( $rules['title'], $attachment_id );
		}

		if ( '' !== trim( $rules['caption'] ) && ( $overwrite || '' === trim( $post->post_excerpt ) ) ) {
			$update['post_excerpt'] = $this->render_attribute_template( $rules['caption'], $attachment_id );
		}

		if ( '' !== trim( $rules['description'] ) && ( $overwrite || '' === trim( $post->post_content ) ) ) {
			$update['post_content'] = $this->render_attribute_template( $rules['description'], $attachment_id );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}

		if ( '' !== trim( $rules['alt'] ) && 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( $overwrite || '' === trim( (string) $current_alt ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $this->render_attribute_template( $rules['alt'], $attachment_id ) );
			}
		}
	}

	private function attachment_group( $attachment_id ) {
		$mime = (string) get_post_mime_type( $attachment_id );
		$file = (string) get_attached_file( $attachment_id, true );
		$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

		if ( 0 === strpos( $mime, 'image/' ) ) {
			return 'image';
		}
		if ( 0 === strpos( $mime, 'video/' ) ) {
			return 'video';
		}
		if ( 0 === strpos( $mime, 'audio/' ) ) {
			return 'audio';
		}
		if ( in_array( $ext, array( 'zip', 'rar', '7z', 'tar', 'gz' ), true ) ) {
			return 'archive';
		}
		if ( preg_match( '/(?:pdf|msword|officedocument|opendocument|text|csv|rtf)/i', $mime ) || in_array( $ext, array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv', 'rtf' ), true ) ) {
			return 'document';
		}

		return 'other';
	}

	private function render_attribute_template( $template, $attachment_id ) {
		$file        = (string) get_attached_file( $attachment_id, true );
		$filename    = $file ? pathinfo( wp_basename( $file ), PATHINFO_FILENAME ) : '';
		$extension   = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
		$folder_name = $this->attachment_folder_label( $attachment_id );
		$replaces    = array(
			'{filename}'  => $filename,
			'{title}'     => get_the_title( $attachment_id ),
			'{date}'      => wp_date( get_option( 'date_format' ) ),
			'{site_name}' => get_bloginfo( 'name' ),
			'{mime}'      => (string) get_post_mime_type( $attachment_id ),
			'{extension}' => $extension,
			'{folder}'    => $folder_name,
		);

		return trim( strtr( (string) $template, $replaces ) );
	}

	public function add_folder_data_to_attachment_js( $response, $attachment, $meta ) {
		unset( $meta );

		if ( ! $attachment instanceof WP_Post ) {
			return $response;
		}

		$terms = wp_get_object_terms( $attachment->ID, self::TAXONOMY );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$response['pgmMediaOrganizer'] = array(
			'folderIds'    => wp_list_pluck( $terms, 'term_id' ),
			'folderNames'  => wp_list_pluck( $terms, 'name' ),
			'folderLabel'  => $this->attachment_folder_label( $attachment->ID ),
			'canOrganize'  => current_user_can( 'edit_post', $attachment->ID ),
			'exportUrl'    => $this->export_url( 0, array( $attachment->ID ), false ),
			'duplicateUrl' => '',
		);

		return $response;
	}

	public function add_attachment_folder_field( $form_fields, $post ) {
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $form_fields;
		}

		$terms     = wp_get_object_terms( $post->ID, self::TAXONOMY, array( 'fields' => 'ids' ) );
		$folder_id = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;

		ob_start();
		$this->render_folder_select( 'attachments[' . $post->ID . '][pgm_media_folder]', $folder_id, 'attachments-' . $post->ID . '-pgm-media-folder', true );
		$html = ob_get_clean();

		$form_fields['pgm_media_folder'] = array(
			'label' => __( 'Mediakansio', 'private-gutenberg-media' ),
			'input' => 'html',
			'html'  => $html,
			'helps' => __( 'Valitse kansio tai käytä mediakirjaston kansiopuuta.', 'private-gutenberg-media' ),
		);

		return $form_fields;
	}

	public function save_attachment_folder_field( $post, $attachment ) {
		if ( isset( $post['ID'], $attachment['pgm_media_folder'] ) && current_user_can( 'edit_post', (int) $post['ID'] ) ) {
			$this->assign_attachment_to_folder( (int) $post['ID'], (int) $attachment['pgm_media_folder'] );
		}

		return $post;
	}

	public function add_media_folder_column( $columns ) {
		$columns['pgm_media_folder'] = __( 'Kansio', 'private-gutenberg-media' );

		return $this->apply_list_column_preferences( $columns );
	}

	private function apply_list_column_preferences( $columns ) {
		$options = $this->get_options();
		$enabled = array_fill_keys( array_merge( array( 'cb' ), (array) $options['list_columns'] ), true );

		foreach ( array_keys( $columns ) as $key ) {
			if ( empty( $enabled[ $key ] ) ) {
				unset( $columns[ $key ] );
			}
		}

		return $columns;
	}

	public function render_media_folder_column( $column_name, $post_id ) {
		if ( 'pgm_media_folder' !== $column_name ) {
			return;
		}

		echo esc_html( $this->attachment_folder_label( $post_id ) );
	}

	public function render_list_filters() {
		global $typenow;

		if ( 'attachment' !== $typenow || ! $this->is_enabled() ) {
			return;
		}

		$folder_id = $this->folder_from_request();
		$sort      = $this->request_sort_value( $this->get_options()['default_sort'] );
		$deep      = $this->request_bool_arg( self::DEEP_SEARCH_QUERY_ARG, self::LEGACY_DEEP_SEARCH_QUERY_ARG, $_GET );

		echo '<label class="screen-reader-text" for="pgm-media-folder-filter">' . esc_html__( 'Suodata mediakansion mukaan', 'private-gutenberg-media' ) . '</label>';
		$this->render_folder_select( self::FOLDER_QUERY_ARG, $folder_id, 'pgm-media-folder-filter', true, true );

		echo '<label class="screen-reader-text" for="pgm-mo-order-filter">' . esc_html__( 'Järjestä media', 'private-gutenberg-media' ) . '</label>';
		echo '<select id="pgm-mo-order-filter" name="' . esc_attr( self::ORDER_QUERY_ARG ) . '">';
		foreach ( $this->sort_options() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $sort, $key, false ), esc_html( $label ) );
		}
		echo '</select>';

		printf(
			'<label class="pgm-mo-list-deep-search"><input type="checkbox" name="' . esc_attr( self::DEEP_SEARCH_QUERY_ARG ) . '" value="1"%s /> %s</label>',
			checked( $deep, true, false ),
			esc_html__( 'Hae metatiedoista', 'private-gutenberg-media' )
		);
	}

	public function filter_media_list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) || ! $this->is_enabled() ) {
			return;
		}

		$this->apply_folder_to_query_args( $query, $this->folder_from_request() );
		$query->set( self::TAXONOMY, '' );

		$sort = $this->request_sort_value( $this->get_options()['default_sort'] );
		$this->apply_sort_to_query( $query, $sort );

		if ( $this->request_bool_arg( self::DEEP_SEARCH_QUERY_ARG, self::LEGACY_DEEP_SEARCH_QUERY_ARG, $_GET ) ) {
			$query->set( 'pgm_mo_deep_search', 1 );
		}
	}

	public function filter_ajax_attachment_query( $args ) {
		if ( ! $this->is_enabled() ) {
			return $args;
		}

		$raw_query = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ? wp_unslash( $_REQUEST['query'] ) : array();
		$folder_id = $this->array_int_arg(
			$raw_query,
			self::FOLDER_QUERY_ARG,
			self::LEGACY_FOLDER_QUERY_ARG,
			$this->array_int_arg( $args, self::FOLDER_QUERY_ARG, self::LEGACY_FOLDER_QUERY_ARG )
		);
		unset( $args[ self::FOLDER_QUERY_ARG ], $args[ self::LEGACY_FOLDER_QUERY_ARG ], $args[ self::TAXONOMY ] );
		$args      = $this->apply_folder_to_args( $args, $folder_id );

		$sort = $this->array_string_arg( $raw_query, self::ORDER_QUERY_ARG, self::LEGACY_ORDER_QUERY_ARG, $this->get_options()['default_sort'] );
		$args = $this->apply_sort_to_args( $args, $sort );

		if ( $this->array_bool_arg( $raw_query, self::DEEP_SEARCH_QUERY_ARG, self::LEGACY_DEEP_SEARCH_QUERY_ARG ) ) {
			$args['pgm_mo_deep_search'] = 1;
		}

		return $args;
	}

	public function filter_deep_media_search( $search, $query ) {
		if ( ! $query->get( 'pgm_mo_deep_search' ) || 'attachment' !== $query->get( 'post_type' ) ) {
			return $search;
		}

		$term = $query->get( 's' );
		if ( '' === trim( (string) $term ) ) {
			return $search;
		}

		global $wpdb;

		$like = '%' . $wpdb->esc_like( (string) $term ) . '%';

		return $wpdb->prepare(
			" AND (
				{$wpdb->posts}.post_title LIKE %s
				OR {$wpdb->posts}.post_excerpt LIKE %s
				OR {$wpdb->posts}.post_content LIKE %s
				OR {$wpdb->posts}.guid LIKE %s
				OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pgm_mo_pm
					WHERE pgm_mo_pm.post_id = {$wpdb->posts}.ID
					AND pgm_mo_pm.meta_key IN ('_wp_attachment_image_alt', '_wp_attached_file')
					AND pgm_mo_pm.meta_value LIKE %s
				)
			)",
			$like,
			$like,
			$like,
			$like,
			$like
		);
	}

	private function apply_folder_to_query_args( WP_Query $query, $folder_id, $include_children = null ) {
		$args = array();
		$args = $this->apply_folder_to_args( $args, $folder_id, $include_children );
		if ( isset( $args['tax_query'] ) ) {
			$query->set( 'tax_query', $args['tax_query'] );
		}
	}

	private function apply_folder_to_args( $args, $folder_id, $include_children = null ) {
		$folder_id = (int) $folder_id;
		if ( 0 === $folder_id ) {
			return $args;
		}

		$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();

		if ( -1 === $folder_id ) {
			$tax_query[] = array(
				'taxonomy' => self::TAXONOMY,
				'operator' => 'NOT EXISTS',
			);
		} elseif ( term_exists( $folder_id, self::TAXONOMY ) ) {
			$terms = $this->folder_term_scope( $folder_id, $include_children );

			$tax_query[] = array(
				'taxonomy'         => self::TAXONOMY,
				'field'            => 'term_id',
				'terms'            => array_values( array_unique( $terms ) ),
				'include_children' => false,
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 && empty( $tax_query['relation'] ) ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	private function folder_term_scope( $folder_id, $include_children = null ) {
		$folder_id = absint( $folder_id );
		if ( ! $folder_id || ! term_exists( $folder_id, self::TAXONOMY ) ) {
			return array();
		}

		if ( null === $include_children ) {
			$include_children = ! empty( $this->get_options()['include_children'] );
		}

		$terms = array( $folder_id );
		if ( $include_children ) {
			$children = get_term_children( $folder_id, self::TAXONOMY );
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$terms = array_merge( $terms, array_map( 'absint', $children ) );
			}
		}

		return array_values( array_unique( array_filter( $terms ) ) );
	}

	private function apply_sort_to_query( WP_Query $query, $sort ) {
		$args = $this->apply_sort_to_args( array(), $sort );
		foreach ( $args as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	private function apply_sort_to_args( $args, $sort ) {
		switch ( $sort ) {
			case 'date_asc':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;
			case 'modified_desc':
				$args['orderby'] = 'modified';
				$args['order']   = 'DESC';
				break;
			case 'modified_asc':
				$args['orderby'] = 'modified';
				$args['order']   = 'ASC';
				break;
			case 'id_desc':
				$args['orderby'] = 'ID';
				$args['order']   = 'DESC';
				break;
			case 'id_asc':
				$args['orderby'] = 'ID';
				$args['order']   = 'ASC';
				break;
			case 'title_asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'title_desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;
			case 'author_asc':
				$args['orderby'] = 'author';
				$args['order']   = 'ASC';
				break;
			case 'author_desc':
				$args['orderby'] = 'author';
				$args['order']   = 'DESC';
				break;
			case 'size_desc':
				$args['meta_query'] = array(
					'relation'            => 'OR',
					'size_clause'         => array(
						'key'     => self::SIZE_META_KEY,
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
					'missing_size_clause' => array(
						'key'     => self::SIZE_META_KEY,
						'compare' => 'NOT EXISTS',
					),
				);
				$args['orderby'] = array(
					'size_clause' => 'DESC',
					'date'        => 'DESC',
				);
				break;
			case 'size_asc':
				$args['meta_query'] = array(
					'relation'            => 'OR',
					'size_clause'         => array(
						'key'     => self::SIZE_META_KEY,
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
					'missing_size_clause' => array(
						'key'     => self::SIZE_META_KEY,
						'compare' => 'NOT EXISTS',
					),
				);
				$args['orderby'] = array(
					'size_clause' => 'ASC',
					'date'        => 'DESC',
				);
				break;
			case 'date_desc':
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		return $args;
	}

	public function ajax_get_state() {
		$this->check_ajax_permissions();

		wp_send_json_success( $this->state_payload( null, $this->media_query_context_from_request() ) );
	}

	public function ajax_create_folder() {
		$this->check_ajax_permissions();

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$access = isset( $_POST['access'] ) ? self::sanitize_access_mode( wp_unslash( $_POST['access'] ) ) : self::ACCESS_PUBLIC;
		$roles  = isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['roles'] ) ) : array();

		if ( '' === trim( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansion nimi puuttuu.', 'private-gutenberg-media' ) ), 400 );
		}

		if ( $parent && ! term_exists( $parent, self::TAXONOMY ) ) {
			$parent = 0;
		}

		if ( $parent && self::ACCESS_PUBLIC !== $access ) {
			if ( self::ACCESS_PUBLIC === $this->folder_effective_access( $parent ) ) {
				wp_send_json_error( array( 'message' => __( 'Suojattua kansiota ei voi luoda suojaamattoman kansion alikansioksi.', 'private-gutenberg-media' ) ), 400 );
			}
		}

		$result = wp_insert_term(
			$name,
			self::TAXONOMY,
			array(
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		update_term_meta( (int) $result['term_id'], self::ORDER_META_KEY, $this->next_folder_order( $parent ) );
		$this->mark_folder_locally_managed( (int) $result['term_id'] );
		if ( self::ACCESS_PUBLIC !== $access ) {
			update_term_meta( (int) $result['term_id'], self::ACCESS_META_KEY, $access );
			if ( self::ACCESS_ROLE_BASED === $access ) {
				if ( ! in_array( 'administrator', $roles, true ) ) {
					$roles[] = 'administrator';
				}
				update_term_meta( (int) $result['term_id'], self::ROLES_META_KEY, $roles );
			} else {
				delete_term_meta( (int) $result['term_id'], self::ROLES_META_KEY );
			}
			do_action( 'pgm_mo_folder_access_changed', (int) $result['term_id'], $access, self::ACCESS_PUBLIC );
		}

		wp_send_json_success( $this->state_payload( (int) $result['term_id'] ) );
	}

	public function ajax_update_folder() {
		$this->check_ajax_permissions();

		$term_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent  = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$access  = isset( $_POST['access'] ) ? self::sanitize_access_mode( wp_unslash( $_POST['access'] ) ) : null;
		$roles   = isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['roles'] ) ) : null;

		$term = get_term( $term_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei löytynyt.', 'private-gutenberg-media' ) ), 404 );
		}

		if ( '' === trim( $name ) ) {
			$name = $term->name;
		}

		if ( $parent && ( $parent === $term_id || $this->term_is_descendant_of( $parent, $term_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei voi siirtää oman alikansionsa sisään.', 'private-gutenberg-media' ) ), 400 );
		}

		if ( $parent && ! term_exists( $parent, self::TAXONOMY ) ) {
			$parent = 0;
		}

		$old_parent = (int) $term->parent;
		$old_access = $this->folder_access( $term_id );

		$effective_new_access = ( null !== $access ) ? $access : $old_access;
		if ( $parent && self::ACCESS_PUBLIC !== $effective_new_access ) {
			if ( self::ACCESS_PUBLIC === $this->folder_effective_access( $parent ) ) {
				wp_send_json_error( array( 'message' => __( 'Suojattua kansiota ei voi siirtää suojaamattoman kansion alikansioksi.', 'private-gutenberg-media' ) ), 400 );
			}
		}

		$result = $this->update_folder_parent(
			$term_id,
			$parent,
			array(
				'name' => $name,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		if ( null !== $access ) {
			if ( self::ACCESS_PUBLIC === $access ) {
				delete_term_meta( $term_id, self::ACCESS_META_KEY );
			} else {
				update_term_meta( $term_id, self::ACCESS_META_KEY, $access );
			}
			if ( self::ACCESS_ROLE_BASED === $access && null !== $roles ) {
				if ( ! in_array( 'administrator', $roles, true ) ) {
					$roles[] = 'administrator';
				}
				update_term_meta( $term_id, self::ROLES_META_KEY, $roles );
			} else {
				delete_term_meta( $term_id, self::ROLES_META_KEY );
			}
		} else {
			$access = $old_access;
		}

		if ( $access !== $old_access || $parent !== $old_parent ) {
			do_action( 'pgm_mo_folder_access_changed', $term_id, $access, $old_access );
		}

		wp_send_json_success( $this->state_payload( $term_id ) );
	}

	public function ajax_reorder_folder() {
		$this->check_ajax_permissions();

		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$position  = isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'after';

		if ( ! in_array( $position, array( 'before', 'after', 'inside' ), true ) ) {
			$position = 'after';
		}

		if ( ! $folder_id || ! $target_id || $folder_id === $target_id ) {
			wp_send_json_error( array( 'message' => __( 'Kansiojärjestystä ei voitu tallentaa.', 'private-gutenberg-media' ) ), 400 );
		}

		$folder = get_term( $folder_id, self::TAXONOMY );
		$target = get_term( $target_id, self::TAXONOMY );
		if ( ! $folder || is_wp_error( $folder ) || ! $target || is_wp_error( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei löytynyt.', 'private-gutenberg-media' ) ), 404 );
		}

		if ( $this->term_is_descendant_of( $target_id, $folder_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei voi siirtää oman alikansionsa sisään.', 'private-gutenberg-media' ) ), 400 );
		}

		$old_parent = (int) $folder->parent;
		$new_parent = 'inside' === $position ? $target_id : (int) $target->parent;

		if ( $new_parent === $folder_id || ( $new_parent && $this->term_is_descendant_of( $new_parent, $folder_id ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei voi siirtää omaan alapuuhunsa.', 'private-gutenberg-media' ) ), 400 );
		}

		if ( $new_parent && self::ACCESS_PUBLIC !== $this->folder_access( $folder_id ) ) {
			if ( self::ACCESS_PUBLIC === $this->folder_effective_access( $new_parent ) ) {
				wp_send_json_error( array( 'message' => __( 'Suojattua kansiota ei voi siirtää suojaamattoman kansion alikansioksi.', 'private-gutenberg-media' ) ), 400 );
			}
		}

		if ( $new_parent !== $old_parent ) {
			$result = $this->update_folder_parent( $folder_id, $new_parent );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}
		}

		$siblings = array_values(
			array_filter(
				$this->flat_folders(),
				static function ( $item ) use ( $new_parent ) {
					return (int) $item['parent'] === (int) $new_parent;
				}
			)
		);

		$ordered_ids = array();
		foreach ( $siblings as $sibling ) {
			$sibling_id = (int) $sibling['id'];
			if ( $sibling_id && $sibling_id !== $folder_id ) {
				$ordered_ids[] = $sibling_id;
			}
		}

		if ( 'inside' === $position ) {
			$ordered_ids[] = $folder_id;
		} else {
			$target_index = array_search( $target_id, $ordered_ids, true );
			if ( false === $target_index ) {
				wp_send_json_error( array( 'message' => __( 'Kohdekansiota ei löytynyt.', 'private-gutenberg-media' ) ), 404 );
			}

			array_splice( $ordered_ids, 'before' === $position ? $target_index : $target_index + 1, 0, array( $folder_id ) );
		}

		foreach ( $ordered_ids as $index => $term_id ) {
			update_term_meta( (int) $term_id, self::ORDER_META_KEY, ( $index + 1 ) * 10 );
			$this->mark_folder_locally_managed( (int) $term_id );
		}

		if ( $new_parent !== $old_parent ) {
			$access = $this->folder_access( $folder_id );
			do_action( 'pgm_mo_folder_access_changed', $folder_id, $access, $access );
		}

		wp_send_json_success(
			array_merge(
				$this->state_payload( $folder_id ),
				array( 'message' => 'inside' === $position ? __( 'Kansio siirrettiin.', 'private-gutenberg-media' ) : __( 'Järjestys tallennettiin.', 'private-gutenberg-media' ) )
			)
		);
	}

	public function ajax_delete_folder() {
		$this->check_ajax_permissions();

		$term_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$scope   = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'single';
		$term    = get_term( $term_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei löytynyt.', 'private-gutenberg-media' ) ), 404 );
		}

		$delete_tree  = 'tree' === $scope;
		$parent       = (int) $term->parent;
		$folder_scope = $this->folder_scope_ids( $term_id, $delete_tree );

		if ( $delete_tree ) {
			$objects = get_objects_in_term( $folder_scope, self::TAXONOMY );
			if ( ! is_wp_error( $objects ) ) {
				foreach ( array_unique( array_map( 'intval', (array) $objects ) ) as $object_id ) {
					$this->remove_attachment_from_folder_scope( $object_id, $folder_scope );
				}
			}

			foreach ( $this->sort_folder_ids_deepest_first( $folder_scope ) as $delete_id ) {
				$result = wp_delete_term( $delete_id, self::TAXONOMY );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
				}
			}

			wp_send_json_success(
				array_merge(
					$this->state_payload( -1 ),
					array( 'message' => __( 'Kansio ja alikansiot poistettiin kansiopuusta. Mediatiedostoja ei poistettu.', 'private-gutenberg-media' ) )
				)
			);
		}

		$children = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => $term_id,
				'fields'     => 'ids',
			)
		);

		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child_id ) {
				$child_id = (int) $child_id;
				$result   = $this->update_folder_parent( $child_id, $parent );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
				}
				$child_access = $this->folder_access( $child_id );
				do_action( 'pgm_mo_folder_access_changed', $child_id, $child_access, $child_access );
			}
		}

		$objects = get_objects_in_term( $term_id, self::TAXONOMY );
		if ( ! is_wp_error( $objects ) ) {
			foreach ( $objects as $object_id ) {
				$this->remove_attachment_from_folder_scope( (int) $object_id, array( $term_id ) );
			}
		}

		$result = wp_delete_term( $term_id, self::TAXONOMY );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array_merge(
				$this->state_payload( -1 ),
				array( 'message' => __( 'Kansio poistettiin. Tiedostot palautettiin Ilman kansiota -näkymään.', 'private-gutenberg-media' ) )
			)
		);
	}

	public function ajax_assign_attachments() {
		$this->check_ajax_permissions();

		$folder_id = isset( $_POST['folder_id'] ) ? (int) $_POST['folder_id'] : 0;
		$ids       = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
		$ids       = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Valitse ensin mediatiedostot.', 'private-gutenberg-media' ) ), 400 );
		}

		if ( $folder_id > 0 && ! term_exists( $folder_id, self::TAXONOMY ) ) {
			wp_send_json_error( array( 'message' => __( 'Kansiota ei löytynyt.', 'private-gutenberg-media' ) ), 404 );
		}

		$moved = 0;
		foreach ( $ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			$this->assign_attachment_to_folder( $attachment_id, $folder_id );
			$moved++;
		}

		wp_send_json_success(
			array_merge(
				$this->state_payload( $folder_id ),
				array(
					'message' => sprintf(
						/* translators: %d: moved attachment count. */
						_n( '%d tiedosto siirrettiin.', '%d tiedostoa siirrettiin.', $moved, 'private-gutenberg-media' ),
						$moved
					),
				)
			)
		);
	}

	public function ajax_unassign_attachments() {
		$this->check_ajax_permissions();

		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$ids       = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
		$ids       = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Valitse ensin mediatiedostot.', 'private-gutenberg-media' ) ), 400 );
		}

		if ( $folder_id <= 0 || ! term_exists( $folder_id, self::TAXONOMY ) ) {
			wp_send_json_error( array( 'message' => __( 'Valitse tavallinen kansio, josta tiedostot irrotetaan.', 'private-gutenberg-media' ) ), 400 );
		}

		$folder_scope = array( $folder_id );
		$children     = get_term_children( $folder_id, self::TAXONOMY );
		if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
			$folder_scope = array_merge( $folder_scope, array_map( 'absint', $children ) );
		}
		$folder_scope = array_values( array_unique( array_filter( $folder_scope ) ) );

		$removed = 0;
		foreach ( $ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			if ( $this->remove_attachment_from_folder_scope( $attachment_id, $folder_scope ) ) {
				$removed++;
			}
		}

		wp_send_json_success(
			array_merge(
				$this->state_payload( $folder_id ),
				array(
					'message' => $removed
						? sprintf(
							/* translators: %d: removed attachment count. */
							_n( '%d tiedosto poistettiin kansiosta.', '%d tiedostoa poistettiin kansiosta.', $removed, 'private-gutenberg-media' ),
							$removed
						)
						: __( 'Valituissa tiedostoissa ei ollut poistettavaa kansiosuhdetta.', 'private-gutenberg-media' ),
				)
			)
		);
	}

	public function ajax_duplicate_attachment() {
		$this->check_ajax_permissions();

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Tuntematon mediatiedosto.', 'private-gutenberg-media' ) ), 404 );
		}

		$new_id = $this->duplicate_attachment( $attachment_id );
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array_merge(
				$this->state_payload(),
				array(
					'attachmentId' => $new_id,
					'message'      => __( 'Media monistettiin.', 'private-gutenberg-media' ),
				)
			)
		);
	}

	public function ajax_import_catfolders() {
		$this->check_ajax_permissions();

		$result = $this->import_catfolders();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$this->remember_catfolders_source_fingerprint();

		wp_send_json_success(
			array_merge(
				$this->state_payload(),
				array(
					'message' => sprintf(
						/* translators: 1: folder count, 2: attachment count. */
						__( 'Tuotu %1$d kansiota ja %2$d medialiitosta CatFoldersista.', 'private-gutenberg-media' ),
						(int) $result['folders'],
						(int) $result['attachments']
					),
				)
			)
		);
	}

	public function ajax_save_preferences() {
		$this->check_ajax_permissions();

		$current = $this->get_options();
		$input   = isset( $_POST['preferences'] ) && is_array( $_POST['preferences'] ) ? wp_unslash( $_POST['preferences'] ) : array();

		$next = array_merge(
			$current,
			array(
				'startup_folder'       => isset( $input['startup_folder'] ) ? (int) $input['startup_folder'] : (int) $current['startup_folder'],
				'infinite_scroll_grid' => empty( $input['infinite_scroll_grid'] ) ? 0 : 1,
				'grid_size'            => isset( $input['grid_size'] ) ? sanitize_key( $input['grid_size'] ) : $current['grid_size'],
				'default_sort'         => isset( $input['default_sort'] ) ? sanitize_key( $input['default_sort'] ) : $current['default_sort'],
				'deep_search_default'  => isset( $input['deep_search_default'] ) ? ( empty( $input['deep_search_default'] ) ? 0 : 1 ) : (int) $current['deep_search_default'],
				'display_defaults'     => isset( $input['display_defaults'] ) && is_array( $input['display_defaults'] ) ? (array) $input['display_defaults'] : $current['display_defaults'],
				'folder_tree_width'    => isset( $input['folder_tree_width'] ) ? absint( $input['folder_tree_width'] ) : (int) $current['folder_tree_width'],
				'show_counts'          => empty( $input['show_counts'] ) ? 0 : 1,
				'show_empty_folders'   => empty( $input['show_empty_folders'] ) ? 0 : 1,
				'include_children'     => empty( $input['include_children'] ) ? 0 : 1,
				'list_columns'         => $this->sanitize_list_columns( isset( $input['list_columns'] ) ? (array) $input['list_columns'] : array() ),
				'overwrite_attributes' => isset( $input['overwrite_attributes'] ) ? ( empty( $input['overwrite_attributes'] ) ? 0 : 1 ) : (int) $current['overwrite_attributes'],
				'default_attributes'   => isset( $input['default_attributes'] ) && is_array( $input['default_attributes'] ) ? (array) $input['default_attributes'] : $current['default_attributes'],
			)
		);

		$next = $this->sanitize_options( $next );
		update_option( self::OPTION_NAME, $next, false );

		wp_send_json_success(
			array(
				'options' => array(
					'showFolderTree'    => (bool) $next['show_folder_tree'],
					'showCounts'        => (bool) $next['show_counts'],
					'showEmptyFolders'  => (bool) $next['show_empty_folders'],
					'includeChildren'   => (bool) $next['include_children'],
					'gridSize'          => $next['grid_size'],
					'infiniteScrollGrid'=> (bool) $next['infinite_scroll_grid'],
					'folderTreeWidth'   => (int) $next['folder_tree_width'],
					'defaultSort'       => $next['default_sort'],
					'deepSearchDefault'=> (bool) $next['deep_search_default'],
					'startupFolder'     => (int) $next['startup_folder'],
					'listColumns'       => array_values( $next['list_columns'] ),
					'overwriteAttributes' => (bool) $next['overwrite_attributes'],
					'defaultAttributes' => $next['default_attributes'],
					'displayDefaults'   => $next['display_defaults'],
				),
				'message' => __( 'Asetukset tallennettiin.', 'private-gutenberg-media' ),
			)
		);
	}

	public function ajax_save_private_settings() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sinulla ei ole oikeutta hallita suojausasetuksia.', 'private-gutenberg-media' ) ), 403 );
		}

		if ( ! class_exists( 'PGM_Private_Gutenberg_Media' ) ) {
			wp_send_json_error( array( 'message' => __( 'Pecodex Media Controlin suojausmoottoria ei löytynyt.', 'private-gutenberg-media' ) ), 500 );
		}

		$current = $this->private_media_options();
		$input   = isset( $_POST['private_settings'] ) && is_array( $_POST['private_settings'] ) ? wp_unslash( $_POST['private_settings'] ) : array();

		$next = array_merge(
			$current,
			array(
				'enabled'                 => empty( $input['enabled'] ) ? 0 : 1,
				'login_mode'              => isset( $input['login_mode'] ) ? sanitize_key( $input['login_mode'] ) : $current['login_mode'],
				'custom_login_url'        => isset( $input['custom_login_url'] ) ? esc_url_raw( $input['custom_login_url'] ) : $current['custom_login_url'],
				'protect_mode'            => isset( $input['protect_mode'] ) ? sanitize_key( $input['protect_mode'] ) : $current['protect_mode'],
				'protected_extensions'    => isset( $input['protected_extensions'] ) ? sanitize_text_field( $input['protected_extensions'] ) : $current['protected_extensions'],
				'protect_unknown_uploads' => empty( $input['protect_unknown_uploads'] ) ? 0 : 1,
				'move_files_to_private_storage' => empty( $input['move_files_to_private_storage'] ) ? 0 : 1,
				'required_capability'     => isset( $input['required_capability'] ) ? sanitize_key( $input['required_capability'] ) : $current['required_capability'],
			)
		);

		$private_media = PGM_Private_Gutenberg_Media::instance();
		$next          = $private_media->sanitize_options( $next );
		update_option( PGM_Private_Gutenberg_Media::OPTION_NAME, $next, false );

		wp_send_json_success(
			array(
				'privateMediaSettings' => $this->private_media_settings_payload( $next ),
				'message'              => __( 'Suojausasetukset tallennettiin.', 'private-gutenberg-media' ),
			)
		);
	}

	private function check_ajax_permissions() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sinulla ei ole oikeutta hallita mediaa.', 'private-gutenberg-media' ) ), 403 );
		}
	}

	private function assign_attachment_to_folder( $attachment_id, $folder_id ) {
		$previous_terms = wp_get_object_terms( $attachment_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
		$previous_terms = is_wp_error( $previous_terms ) ? array() : array_map( 'intval', (array) $previous_terms );

		if ( $folder_id > 0 ) {
			wp_set_object_terms( $attachment_id, array( (int) $folder_id ), self::TAXONOMY, false );
		} else {
			wp_delete_object_term_relationships( $attachment_id, self::TAXONOMY );
		}

		do_action( 'pgm_mo_attachment_folder_changed', (int) $attachment_id, (int) $folder_id, $previous_terms );
	}

	private function remove_attachment_from_folder_scope( $attachment_id, $folder_ids ) {
		$folder_ids     = array_values( array_unique( array_filter( array_map( 'intval', (array) $folder_ids ) ) ) );
		$previous_terms = wp_get_object_terms( $attachment_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
		$previous_terms = is_wp_error( $previous_terms ) ? array() : array_values( array_unique( array_map( 'intval', (array) $previous_terms ) ) );

		if ( empty( $folder_ids ) || empty( $previous_terms ) ) {
			return false;
		}

		$remaining_terms = array_values( array_diff( $previous_terms, $folder_ids ) );
		if ( count( $remaining_terms ) === count( $previous_terms ) ) {
			return false;
		}

		if ( ! empty( $remaining_terms ) ) {
			wp_set_object_terms( $attachment_id, $remaining_terms, self::TAXONOMY, false );
		} else {
			wp_delete_object_term_relationships( $attachment_id, self::TAXONOMY );
		}

		do_action( 'pgm_mo_attachment_folder_changed', (int) $attachment_id, 0, $previous_terms );

		return true;
	}

	private function folder_access( $term_id ) {
		if ( $term_id <= 0 ) {
			return self::ACCESS_PUBLIC;
		}

		return self::sanitize_access_mode( get_term_meta( (int) $term_id, self::ACCESS_META_KEY, true ) );
	}
	private function folder_effective_access( $term_id ) {
		$term_id = (int) $term_id;
		$access  = self::ACCESS_PUBLIC;

		while ( $term_id > 0 ) {
			$term = get_term( $term_id, self::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}
			$access = self::strongest_access( $access, $this->folder_access( $term_id ) );
			$term_id = (int) $term->parent;
		}

		return $access;
	}

	public function attachment_folder_roles( $attachment_id ) {
		$terms = wp_get_object_terms( $attachment_id, PGM_Media_Organizer::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$roles = array();
		foreach ( array_map( 'absint', $terms ) as $term_id ) {
			$term_roles = (array) get_term_meta( $term_id, self::ROLES_META_KEY, true );
			foreach ( $term_roles as $role ) {
				if ( ! in_array( $role, $roles, true ) ) {
					$roles[] = $role;
				}
			}
		}

		return $roles;
	}

	private function folder_effective_access_for_term( $term_id ) {
		$term_id = (int) $term_id;
		$access  = self::ACCESS_PUBLIC;

		while ( $term_id > 0 ) {
			$term = get_term( $term_id, self::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}

			$access  = self::strongest_access( $access, $this->folder_access( $term_id ) );
			$term_id = (int) $term->parent;
		}

		return $access;
	}

	public function state_payload( $selected_folder = null, $query_context = array() ) {
		$this->maybe_auto_import_catfolders();
		$query_context = $this->sanitize_media_query_context( $query_context );

		$debug_info = get_option( 'pecodex_storage_debug', array() );
		// Optional: clear debug info after reading so it doesn't stay in db forever
		if ( ! empty( $debug_info ) ) {
			delete_option( 'pecodex_storage_debug' );
		}

		return array(
			'folders'        => $this->folder_tree( $query_context ),
			'flatFolders'    => $this->flat_folders( $query_context ),
			'selectedFolder' => null === $selected_folder ? $this->folder_from_request() : (int) $selected_folder,
			'counts'         => $this->folder_counts( $query_context ),
			'mediaQuery'     => $query_context,
			'exportBaseUrl'  => $this->export_url( 0, array(), false ),
			'serverDebug'    => $debug_info,
		);
	}

	private function folder_tree( $query_context = array() ) {
		$terms = $this->flat_folders( $query_context );
		$nodes = array();
		$tree  = array();

		foreach ( $terms as $term ) {
			$nodes[ $term['id'] ] = $term;
			$nodes[ $term['id'] ]['children'] = array();
		}

		foreach ( $nodes as $id => &$node ) {
			$parent = (int) $node['parent'];
			if ( $parent > 0 && isset( $nodes[ $parent ] ) ) {
				$nodes[ $parent ]['children'][] =& $node;
			} else {
				$tree[] =& $node;
			}
		}
		unset( $node );

		return array_merge(
			array(
				array(
					'id'       => 0,
					'name'     => __( 'Kaikki tiedostot', 'private-gutenberg-media' ),
					'parent'   => 0,
					'count'    => $this->attachment_count_all( $query_context ),
					'special'  => 'all',
					'access'   => self::ACCESS_PUBLIC,
					'effectiveAccess' => self::ACCESS_PUBLIC,
					'children' => array(),
				),
				array(
					'id'       => -1,
					'name'     => __( 'Ilman kansiota', 'private-gutenberg-media' ),
					'parent'   => 0,
					'count'    => $this->attachment_count_uncategorized( $query_context ),
					'special'  => 'uncategorized',
					'access'   => self::ACCESS_PUBLIC,
					'effectiveAccess' => self::ACCESS_PUBLIC,
					'children' => array(),
				),
			),
			$tree
		);
	}

	private function flat_folders( $query_context = array() ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$object_map       = $this->folder_relationship_object_map( $query_context );
		$direct_counts    = $this->folder_direct_counts_from_object_map( $terms, $object_map );
		$nested_counts    = $this->folder_nested_counts_from_object_map( $terms, $object_map );
		$include_children = ! empty( $this->get_options()['include_children'] );
		$output = array();
		foreach ( $terms as $term ) {
			$access = $this->folder_access( $term->term_id );
			$effective_access = $this->folder_effective_access( $term->term_id );
			$term_id = (int) $term->term_id;
			$direct_count = isset( $direct_counts[ $term_id ] ) ? (int) $direct_counts[ $term_id ] : 0;
			$nested_count = isset( $nested_counts[ $term_id ] ) ? (int) $nested_counts[ $term_id ] : 0;
			$output[] = array(
				'id'              => $term_id,
				'name'            => $term->name,
				'parent'          => (int) $term->parent,
				'count'           => $include_children ? $nested_count : $direct_count,
				'directCount'     => $direct_count,
				'nestedCount'     => $nested_count,
				'slug'            => $term->slug,
				'order'           => (int) get_term_meta( $term->term_id, self::ORDER_META_KEY, true ),
				'source'          => (int) get_term_meta( $term->term_id, self::CATFOLDERS_SOURCE_META_KEY, true ),
				'access'          => $access,
				'roles'           => (array) get_term_meta( $term->term_id, self::ROLES_META_KEY, true ),
				'effectiveAccess' => $effective_access,
			);
		}

		usort(
			$output,
			static function ( $a, $b ) {
				if ( (int) $a['parent'] !== (int) $b['parent'] ) {
					return (int) $a['parent'] <=> (int) $b['parent'];
				}

				if ( (int) $a['order'] !== (int) $b['order'] ) {
					return (int) $a['order'] <=> (int) $b['order'];
				}

				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $output;
	}

	private function folder_relationship_object_map( $query_context = array() ) {
		global $wpdb;

		$attachment_ids = $this->matching_attachment_ids_for_context( $query_context );
		if ( null !== $attachment_ids && empty( $attachment_ids ) ) {
			return array();
		}

		$id_clause = '';
		if ( null !== $attachment_ids ) {
			$id_clause = ' AND tr.object_id IN (' . implode( ',', array_map( 'absint', $attachment_ids ) ) . ')';
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT tt.term_id, tr.object_id
				FROM {$wpdb->term_taxonomy} tt
				INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				WHERE tt.taxonomy = %s
					AND p.post_type = 'attachment'
					AND p.post_status = 'inherit'{$id_clause}",
				self::TAXONOMY
			),
			ARRAY_A
		);

		$object_map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$term_id   = isset( $row['term_id'] ) ? (int) $row['term_id'] : 0;
				$object_id = isset( $row['object_id'] ) ? (int) $row['object_id'] : 0;
				if ( $term_id <= 0 || $object_id <= 0 ) {
					continue;
				}

				if ( ! isset( $object_map[ $term_id ] ) ) {
					$object_map[ $term_id ] = array();
				}

				$object_map[ $term_id ][ $object_id ] = true;
			}
		}

		return $object_map;
	}

	private function folder_direct_counts_from_object_map( $terms, $object_map ) {
		$counts = array();

		foreach ( (array) $terms as $term ) {
			$term_id = is_object( $term ) && isset( $term->term_id ) ? (int) $term->term_id : 0;
			if ( $term_id <= 0 ) {
				continue;
			}

			$counts[ $term_id ] = isset( $object_map[ $term_id ] ) ? count( $object_map[ $term_id ] ) : 0;
		}

		return $counts;
	}

	private function folder_nested_counts_from_object_map( $terms, $object_map ) {
		$parents = array();
		$term_ids = array();

		foreach ( (array) $terms as $term ) {
			if ( ! is_object( $term ) || empty( $term->term_id ) ) {
				continue;
			}

			$term_id = (int) $term->term_id;
			$term_ids[ $term_id ] = true;
			$parents[ $term_id ] = (int) $term->parent;
			if ( ! isset( $object_map[ $term_id ] ) ) {
				$object_map[ $term_id ] = array();
			}
		}

		foreach ( $object_map as $term_id => $objects ) {
			$term_id = (int) $term_id;
			if ( $term_id <= 0 || empty( $objects ) || empty( $term_ids[ $term_id ] ) ) {
				continue;
			}

			$parent = isset( $parents[ $term_id ] ) ? (int) $parents[ $term_id ] : 0;
			$seen   = array( $term_id => true );

			while ( $parent > 0 && ! empty( $term_ids[ $parent ] ) && empty( $seen[ $parent ] ) ) {
				foreach ( $objects as $object_id => $present ) {
					if ( $present ) {
						$object_map[ $parent ][ (int) $object_id ] = true;
					}
				}

				$seen[ $parent ] = true;
				$parent = isset( $parents[ $parent ] ) ? (int) $parents[ $parent ] : 0;
			}
		}

		$counts = array();
		foreach ( array_keys( $term_ids ) as $term_id ) {
			$counts[ $term_id ] = isset( $object_map[ $term_id ] ) ? count( $object_map[ $term_id ] ) : 0;
		}

		return $counts;
	}

	private function media_query_context_from_request() {
		$context = array();

		if ( isset( $_POST['media_query'] ) ) {
			$raw = wp_unslash( $_POST['media_query'] );
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$context = $decoded;
				}
			} elseif ( is_array( $raw ) ) {
				$context = $raw;
			}
		} elseif ( isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ) {
			$context = wp_unslash( $_REQUEST['query'] );
		}

		return $this->sanitize_media_query_context( $context );
	}

	private function sanitize_media_query_context( $context ) {
		$context = is_array( $context ) ? $context : array();
		$output  = array();

		foreach ( array( 'type', 'post_mime_type', 'mime_type' ) as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$values = $this->sanitize_media_query_mime_values( $context[ $key ] );
				if ( ! empty( $values ) ) {
					$output[ $key ] = count( $values ) > 1 ? $values : reset( $values );
				}
			}
		}

		foreach ( array( 'uploadedTo', 'uploaded_to', 'author', 'year', 'monthnum', 'm' ) as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$value = absint( wp_unslash( $context[ $key ] ) );
				if ( $value > 0 ) {
					$output[ $key ] = $value;
				}
			}
		}

		foreach ( array( 's', 'search' ) as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $context[ $key ] ) );
				if ( '' !== $value ) {
					$output[ $key ] = $value;
				}
			}
		}

		return $output;
	}

	private function sanitize_media_query_mime_values( $value ) {
		$values = is_array( $value ) ? $value : explode( ',', (string) $value );
		$output = array();

		foreach ( $values as $item ) {
			$item = strtolower( trim( (string) wp_unslash( $item ) ) );
			if ( '' === $item || 'all' === $item || 'uploaded' === $item ) {
				continue;
			}

			$item = preg_replace( '/[^a-z0-9_+.\-\/]/', '', $item );
			if ( '' !== $item ) {
				$output[] = $item;
			}
		}

		return array_values( array_unique( $output ) );
	}

	private function media_query_args_from_context( $query_context = array() ) {
		$query_context = $this->sanitize_media_query_context( $query_context );
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		$mime_values = array();
		foreach ( array( 'post_mime_type', 'mime_type', 'type' ) as $key ) {
			if ( empty( $query_context[ $key ] ) ) {
				continue;
			}

			$value = $query_context[ $key ];
			$mime_values = array_merge( $mime_values, is_array( $value ) ? $value : array( $value ) );
		}

		$mime_values = array_values( array_unique( array_filter( $mime_values ) ) );
		if ( ! empty( $mime_values ) ) {
			$args['post_mime_type'] = count( $mime_values ) > 1 ? $mime_values : reset( $mime_values );
		}

		$uploaded_to = 0;
		if ( ! empty( $query_context['uploadedTo'] ) ) {
			$uploaded_to = absint( $query_context['uploadedTo'] );
		} elseif ( ! empty( $query_context['uploaded_to'] ) ) {
			$uploaded_to = absint( $query_context['uploaded_to'] );
		}
		if ( $uploaded_to > 0 ) {
			$args['post_parent'] = $uploaded_to;
		}

		foreach ( array( 'author', 'year', 'monthnum', 'm' ) as $key ) {
			if ( ! empty( $query_context[ $key ] ) ) {
				$args[ $key ] = absint( $query_context[ $key ] );
			}
		}

		$search = '';
		if ( ! empty( $query_context['s'] ) ) {
			$search = (string) $query_context['s'];
		} elseif ( ! empty( $query_context['search'] ) ) {
			$search = (string) $query_context['search'];
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return $args;
	}

	private function matching_attachment_ids_for_context( $query_context = array() ) {
		$query_context = $this->sanitize_media_query_context( $query_context );
		if ( empty( $query_context ) ) {
			return null;
		}

		static $cache = array();
		$cache_key = md5( wp_json_encode( $query_context ) );
		if ( ! isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ] = array_values( array_unique( array_map( 'absint', get_posts( $this->media_query_args_from_context( $query_context ) ) ) ) );
		}

		return $cache[ $cache_key ];
	}

	private function folder_counts( $query_context = array() ) {
		$counts = array(
			0  => $this->attachment_count_all( $query_context ),
			-1 => $this->attachment_count_uncategorized( $query_context ),
		);

		foreach ( $this->flat_folders( $query_context ) as $folder ) {
			$counts[ $folder['id'] ] = (int) $folder['count'];
		}

		return $counts;
	}

	private function attachment_count_all( $query_context = array() ) {
		$attachment_ids = $this->matching_attachment_ids_for_context( $query_context );
		if ( null !== $attachment_ids ) {
			return count( $attachment_ids );
		}

		$counts = wp_count_posts( 'attachment' );

		return isset( $counts->inherit ) ? (int) $counts->inherit : 0;
	}

	private function attachment_count_uncategorized( $query_context = array() ) {
		$args = $this->media_query_args_from_context( $query_context );
		$args['fields'] = 'ids';
		$args['posts_per_page'] = -1;
		$args['no_found_rows'] = true;
		$args['tax_query'] = array(
			array(
				'taxonomy' => self::TAXONOMY,
				'operator' => 'NOT EXISTS',
			),
		);

		return count( get_posts( $args ) );
	}

	private function next_folder_order( $parent ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => (int) $parent,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 1;
		}

		$max = 0;
		foreach ( $terms as $term_id ) {
			$max = max( $max, (int) get_term_meta( (int) $term_id, self::ORDER_META_KEY, true ) );
		}

		return $max + 1;
	}

	private function folder_scope_ids( $term_id, $include_descendants = false ) {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return array();
		}

		$ids = array( $term_id );
		if ( $include_descendants ) {
			$children = get_term_children( $term_id, self::TAXONOMY );
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				$ids = array_merge( $ids, array_map( 'absint', $children ) );
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private function sort_folder_ids_deepest_first( $folder_ids ) {
		$folder_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $folder_ids ) ) ) );

		usort(
			$folder_ids,
			static function ( $a, $b ) {
				$depth_a = count( get_ancestors( (int) $a, self::TAXONOMY, 'taxonomy' ) );
				$depth_b = count( get_ancestors( (int) $b, self::TAXONOMY, 'taxonomy' ) );

				if ( $depth_a === $depth_b ) {
					return (int) $b <=> (int) $a;
				}

				return $depth_b <=> $depth_a;
			}
		);

		return $folder_ids;
	}

	private function term_is_descendant_of( $term_id, $ancestor_id ) {
		$children = get_term_children( $ancestor_id, self::TAXONOMY );
		if ( is_wp_error( $children ) ) {
			return false;
		}

		return in_array( (int) $term_id, array_map( 'intval', $children ), true );
	}

	private function array_string_arg( $source, $key, $legacy_key, $default = '' ) {
		if ( isset( $source[ $key ] ) ) {
			return sanitize_key( wp_unslash( $source[ $key ] ) );
		}

		if ( isset( $source[ $legacy_key ] ) ) {
			return sanitize_key( wp_unslash( $source[ $legacy_key ] ) );
		}

		return $default;
	}

	private function array_int_arg( $source, $key, $legacy_key, $default = 0 ) {
		if ( isset( $source[ $key ] ) ) {
			return (int) wp_unslash( $source[ $key ] );
		}

		if ( isset( $source[ $legacy_key ] ) ) {
			return (int) wp_unslash( $source[ $legacy_key ] );
		}

		return (int) $default;
	}

	private function array_bool_arg( $source, $key, $legacy_key ) {
		return ! empty( $source[ $key ] ) || ! empty( $source[ $legacy_key ] );
	}

	private function request_sort_value( $default ) {
		return $this->array_string_arg( $_GET, self::ORDER_QUERY_ARG, self::LEGACY_ORDER_QUERY_ARG, $default );
	}

	private function request_bool_arg( $key, $legacy_key, $source = null ) {
		return $this->array_bool_arg( null === $source ? $_REQUEST : $source, $key, $legacy_key );
	}

	private function folder_from_request() {
		return $this->array_int_arg( $_REQUEST, self::FOLDER_QUERY_ARG, self::LEGACY_FOLDER_QUERY_ARG );
	}

	private function initial_folder() {
		if ( isset( $_REQUEST[ self::FOLDER_QUERY_ARG ] ) || isset( $_REQUEST[ self::LEGACY_FOLDER_QUERY_ARG ] ) ) {
			return $this->folder_from_request();
		}

		$options = $this->get_options();

		return isset( $options['startup_folder'] ) ? (int) $options['startup_folder'] : 0;
	}

	private function render_folder_select( $name, $selected, $id = '', $include_none = true, $include_special = false ) {
		$id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';
		echo '<select name="' . esc_attr( $name ) . '"' . $id_attr . '>';
		if ( $include_special ) {
			echo '<option value="0"' . selected( $selected, 0, false ) . '>' . esc_html__( 'Kaikki kansiot', 'private-gutenberg-media' ) . '</option>';
			echo '<option value="-1"' . selected( $selected, -1, false ) . '>' . esc_html__( 'Ilman kansiota', 'private-gutenberg-media' ) . '</option>';
		} elseif ( $include_none ) {
			echo '<option value="0"' . selected( $selected, 0, false ) . '>' . esc_html__( 'Ei kansiota', 'private-gutenberg-media' ) . '</option>';
		}

		foreach ( $this->flat_folder_choices() as $term_id => $label ) {
			echo '<option value="' . esc_attr( $term_id ) . '"' . selected( $selected, $term_id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	private function flat_folder_choices() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$output = array();
		$walk = function ( $parent, $depth ) use ( &$walk, &$output, &$by_parent ) {
			if ( empty( $by_parent[ $parent ] ) ) {
				return;
			}

			usort(
				$by_parent[ $parent ],
				static function ( $a, $b ) {
					$a_order = (int) get_term_meta( $a->term_id, PGM_Media_Organizer::ORDER_META_KEY, true );
					$b_order = (int) get_term_meta( $b->term_id, PGM_Media_Organizer::ORDER_META_KEY, true );
					if ( $a_order === $b_order ) {
						return strcasecmp( $a->name, $b->name );
					}

					return $a_order <=> $b_order;
				}
			);

			foreach ( $by_parent[ $parent ] as $term ) {
				$output[ (int) $term->term_id ] = str_repeat( '- ', $depth ) . $term->name;
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		return $output;
	}

	private function attachment_folder_label( $attachment_id ) {
		$terms = wp_get_object_terms( $attachment_id, self::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return __( 'Ilman kansiota', 'private-gutenberg-media' );
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	private function export_url( $folder_id = 0, $attachment_ids = array(), $include_children = null ) {
		$args = array(
			'action'           => self::PECODEX_EXPORT_ACTION,
			self::FOLDER_QUERY_ARG => (int) $folder_id,
			'_wpnonce'         => wp_create_nonce( self::NONCE_ACTION ),
		);

		if ( ! empty( $attachment_ids ) ) {
			$args['attachment_ids'] = implode( ',', array_map( 'absint', $attachment_ids ) );
		}

		if ( null !== $include_children ) {
			$args['include_children'] = $include_children ? 1 : 0;
		}

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	private function current_admin_action_is( $action ) {
		$current_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return $action === $current_action;
	}

	public function handle_export_request() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Sinulla ei ole oikeutta viedä mediaa.', 'private-gutenberg-media' ), 403 );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Varmenne ei täsmää.', 'private-gutenberg-media' ), 403 );
		}

		$folder_id        = $this->array_int_arg( $_GET, self::FOLDER_QUERY_ARG, self::LEGACY_FOLDER_QUERY_ARG );
		$include_children = isset( $_GET['include_children'] ) ? (bool) absint( $_GET['include_children'] ) : (bool) $this->get_options()['include_children'];
		$ids              = array();

		if ( ! empty( $_GET['attachment_ids'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['attachment_ids'] ) ) ) ) );
		}

		$attachment_ids = ! empty( $ids ) ? $ids : $this->attachment_ids_for_folder( $folder_id, $include_children );
		$zip            = $this->create_export_zip( $attachment_ids, $folder_id );

		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ), 500 );
		}

		$download_name = $this->export_filename( $folder_id );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		header( 'Content-Length: ' . filesize( $zip ) );
		readfile( $zip );
		@unlink( $zip );
		exit;
	}

	private function attachment_ids_for_folder( $folder_id, $include_children ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$args = $this->apply_folder_to_args( $args, (int) $folder_id, (bool) $include_children );

		return get_posts( $args );
	}

	private function create_export_zip( $attachment_ids, $folder_id ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'pgm_mo_no_ziparchive', __( 'PHP ZipArchive -laajennus puuttuu.', 'private-gutenberg-media' ) );
		}

		$attachment_ids = array_values( array_filter( array_map( 'absint', (array) $attachment_ids ) ) );
		if ( empty( $attachment_ids ) ) {
			return new WP_Error( 'pgm_mo_empty_export', __( 'Vietäviä tiedostoja ei löytynyt.', 'private-gutenberg-media' ) );
		}

		$tmp = wp_tempnam( 'pgm-media-export.zip' );
		if ( ! $tmp ) {
			return new WP_Error( 'pgm_mo_tmp_failed', __( 'Väliaikaista ZIP-tiedostoa ei voitu luoda.', 'private-gutenberg-media' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'pgm_mo_zip_failed', __( 'ZIP-tiedostoa ei voitu avata kirjoitusta varten.', 'private-gutenberg-media' ) );
		}

		$manifest = array(
			'created'       => current_time( 'mysql' ),
			'site'          => home_url( '/' ),
			'folder_id'     => (int) $folder_id,
			'folders'       => array(),
			'attachments'   => array(),
			'skipped_files' => array(),
		);
		$used_paths = array();

		foreach ( $attachment_ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			$file = get_attached_file( $attachment_id, true );
			$relative_path = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			$relative_path = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
			$file_readable = $file && file_exists( $file ) && is_readable( $file );
			$file_contents = null;

			if ( ! $file_readable ) {
				$file_contents = apply_filters( 'pgm_mo_export_attachment_source_contents', null, $attachment_id, $relative_path );
				if ( is_wp_error( $file_contents ) ) {
					$manifest['skipped_files'][] = array(
						'id'     => $attachment_id,
						'title'  => get_the_title( $attachment_id ),
						'reason' => $file_contents->get_error_message(),
					);
					continue;
				}
			}

			if ( ! $file_readable && ! is_string( $file_contents ) ) {
				$manifest['skipped_files'][] = array(
					'id'     => $attachment_id,
					'title'  => get_the_title( $attachment_id ),
					'reason' => 'File missing from public uploads path and private source fallback did not return contents.',
				);
				continue;
			}

			$zip_path = $this->unique_zip_path( $this->attachment_zip_path( $attachment_id, $file ? $file : $relative_path ), $used_paths );
			$added = $file_readable ? $zip->addFile( $file, $zip_path ) : $zip->addFromString( $zip_path, $file_contents );
			if ( ! $added ) {
				$manifest['skipped_files'][] = array(
					'id'     => $attachment_id,
					'title'  => get_the_title( $attachment_id ),
					'reason' => 'File could not be added to ZIP archive.',
				);
				continue;
			}

			$manifest['attachments'][] = array(
				'id'       => $attachment_id,
				'title'    => get_the_title( $attachment_id ),
				'file'     => $zip_path,
				'mime'     => get_post_mime_type( $attachment_id ),
				'folder'   => $this->attachment_folder_label( $attachment_id ),
				'source'   => wp_get_attachment_url( $attachment_id ),
			);

			$terms = wp_get_object_terms( $attachment_id, self::TAXONOMY );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$this->add_folder_manifest_entries( $terms[0], $manifest['folders'] );
			}
		}

		$manifest['folders'] = array_values( $manifest['folders'] );
		$zip->addFromString( 'pgm-media-export-manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->close();

		return $tmp;
	}

	private function unique_zip_path( $path, &$used_paths ) {
		$path = trim( str_replace( '\\', '/', $path ), '/' );
		if ( '' === $path ) {
			$path = 'media-file';
		}

		$base = pathinfo( $path, PATHINFO_FILENAME );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );
		$dir  = pathinfo( $path, PATHINFO_DIRNAME );
		$dir  = '.' === $dir ? '' : trailingslashit( $dir );
		$candidate = $path;
		$index = 2;

		while ( isset( $used_paths[ strtolower( $candidate ) ] ) ) {
			$candidate = $dir . $base . '-' . $index . ( $ext ? '.' . $ext : '' );
			$index++;
		}

		$used_paths[ strtolower( $candidate ) ] = true;

		return $candidate;
	}

	private function attachment_zip_path( $attachment_id, $file ) {
		$terms = wp_get_object_terms( $attachment_id, self::TAXONOMY );
		$parts = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$parts = $this->term_path_parts( $terms[0] );
		} else {
			$parts = array( __( 'Ilman kansiota', 'private-gutenberg-media' ) );
		}

		$parts = array_map( 'sanitize_file_name', $parts );
		$parts[] = sanitize_file_name( wp_basename( $file ) );

		return implode( '/', array_filter( $parts ) );
	}

	private function term_path_parts( $term ) {
		if ( is_numeric( $term ) ) {
			$term = get_term( (int) $term, self::TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$parts = array( $term->name );
		while ( ! empty( $term->parent ) ) {
			$term = get_term( (int) $term->parent, self::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}
			array_unshift( $parts, $term->name );
		}

		return $parts;
	}

	private function add_folder_manifest_entries( $term, &$folders ) {
		if ( is_numeric( $term ) ) {
			$term = get_term( (int) $term, self::TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$chain = array( $term );
		while ( ! empty( $term->parent ) ) {
			$term = get_term( (int) $term->parent, self::TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}
			array_unshift( $chain, $term );
		}

		$original_path = array();
		$zip_path      = array();
		foreach ( $chain as $folder_term ) {
			$original_path[] = $folder_term->name;
			$zip_path[]      = sanitize_file_name( $folder_term->name );
			$key             = $this->folder_path_key( $zip_path );

			if ( isset( $folders[ $key ] ) ) {
				continue;
			}

			$folders[ $key ] = array(
				'id'              => (int) $folder_term->term_id,
				'name'            => $folder_term->name,
				'path'            => $zip_path,
				'originalPath'    => $original_path,
				'order'           => (int) get_term_meta( (int) $folder_term->term_id, self::ORDER_META_KEY, true ),
				'access'          => $this->folder_access( (int) $folder_term->term_id ),
				'effectiveAccess' => $this->folder_effective_access( (int) $folder_term->term_id ),
			);
		}
	}

	private function folder_path_key( $parts ) {
		$parts = array_values(
			array_filter(
				array_map(
					static function ( $part ) {
						return trim( str_replace( '\\', '/', (string) $part ), '/' );
					},
					(array) $parts
				),
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);

		return implode( '/', $parts );
	}

	private function export_filename( $folder_id ) {
		if ( $folder_id > 0 ) {
			$term = get_term( $folder_id, self::TAXONOMY );
			$name = ( $term && ! is_wp_error( $term ) ) ? $term->name : 'folder';
		} elseif ( -1 === (int) $folder_id ) {
			$name = 'uncategorized';
		} else {
			$name = 'media-library';
		}

		return sanitize_file_name( 'pecodex-media-library-' . $name . '-' . wp_date( 'Y-m-d-His' ) . '.zip' );
	}

	public function handle_zip_upload_request() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Sinulla ei ole oikeutta tuoda mediaa.', 'private-gutenberg-media' ), 403 );
		}

		check_admin_referer( $this->current_admin_action_is( self::PECODEX_ZIP_ACTION ) ? self::PECODEX_ZIP_ACTION : self::ZIP_ACTION );

		if ( empty( $_FILES['pgm_mo_zip']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'pgm_mo_zip', 'missing', admin_url( 'upload.php?page=' . self::ADMIN_PAGE_SLUG ) ) );
			exit;
		}

		$result = $this->import_zip_file( $_FILES['pgm_mo_zip']['tmp_name'] );
		$status = is_wp_error( $result ) ? 'error' : 'done';

		wp_safe_redirect(
			add_query_arg(
				array(
					'pgm_mo_zip' => $status,
					'count'      => is_wp_error( $result ) ? 0 : (int) $result['attachments'],
				),
				admin_url( 'upload.php?page=' . self::ADMIN_PAGE_SLUG )
			)
		);
		exit;
	}

	private function import_zip_file( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'pgm_mo_no_ziparchive', __( 'PHP ZipArchive -laajennus puuttuu.', 'private-gutenberg-media' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'pgm_mo_zip_open_failed', __( 'ZIP-tiedostoa ei voitu avata.', 'private-gutenberg-media' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			$zip->close();
			return new WP_Error( 'pgm_mo_upload_dir_failed', $uploads['error'] );
		}

		$created = 0;
		$manifest = $this->read_export_manifest_from_zip( $zip );
		$manifest_folder_map = $this->import_manifest_folders( $manifest );

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			$normalized_name = ltrim( str_replace( '\\', '/', (string) $name ), '/' );
			if ( ! $name || 'pgm-media-export-manifest.json' === $normalized_name || '/' === substr( $name, -1 ) || false !== strpos( $name, '..' ) || preg_match( '#(^|/)\.#', $name ) ) {
				continue;
			}

			$path_parts = array_values( array_filter( explode( '/', str_replace( '\\', '/', $name ) ) ) );
			$filename   = array_pop( $path_parts );
			$filetype   = wp_check_filetype( $filename );
			if ( empty( $filetype['type'] ) ) {
				continue;
			}

			$folder_key = $this->folder_path_key( $path_parts );
			$folder_id = isset( $manifest_folder_map[ $folder_key ] ) ? (int) $manifest_folder_map[ $folder_key ] : $this->get_or_create_folder_path( $path_parts );
			$target_name = wp_unique_filename( $uploads['path'], sanitize_file_name( $filename ) );
			$target_path = trailingslashit( $uploads['path'] ) . $target_name;
			$contents = $zip->getFromIndex( $i );
			if ( false === $contents ) {
				continue;
			}

			if ( false === file_put_contents( $target_path, $contents ) ) {
				continue;
			}

			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $filetype['type'],
					'post_title'     => sanitize_text_field( pathinfo( $target_name, PATHINFO_FILENAME ) ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				),
				$target_path
			);

			if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
				@unlink( $target_path );
				continue;
			}

			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $target_path ) );
			if ( $folder_id > 0 ) {
				$this->assign_attachment_to_folder( $attachment_id, $folder_id );
			}
			$this->apply_default_attributes( $attachment_id );
			$this->track_attachment_size( $attachment_id );
			$created++;
		}

		$zip->close();

		return array( 'attachments' => $created );
	}

	private function read_export_manifest_from_zip( $zip ) {
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		$manifest = $zip->getFromName( 'pgm-media-export-manifest.json' );
		if ( false === $manifest || '' === trim( $manifest ) ) {
			return array();
		}

		$data = json_decode( $manifest, true );

		return is_array( $data ) ? $data : array();
	}

	private function import_manifest_folders( $manifest ) {
		if ( empty( $manifest['folders'] ) || ! is_array( $manifest['folders'] ) ) {
			return array();
		}

		$folders = $manifest['folders'];
		usort(
			$folders,
			static function ( $a, $b ) {
				$a_path = isset( $a['path'] ) && is_array( $a['path'] ) ? $a['path'] : array();
				$b_path = isset( $b['path'] ) && is_array( $b['path'] ) ? $b['path'] : array();

				return count( $a_path ) <=> count( $b_path );
			}
		);

		$map = array();
		foreach ( $folders as $folder ) {
			if ( ! is_array( $folder ) ) {
				continue;
			}

			$zip_path = isset( $folder['path'] ) && is_array( $folder['path'] ) ? $folder['path'] : array();
			$original_path = isset( $folder['originalPath'] ) && is_array( $folder['originalPath'] ) ? $folder['originalPath'] : $zip_path;
			$zip_path = $this->sanitize_import_folder_path_parts( $zip_path );
			$original_path = $this->sanitize_import_folder_path_parts( $original_path );
			if ( empty( $zip_path ) || empty( $original_path ) ) {
				continue;
			}

			$term_id = $this->get_or_create_folder_path( $original_path );
			if ( ! $term_id ) {
				continue;
			}

			$this->mark_folder_locally_managed( $term_id );
			if ( ! empty( $folder['order'] ) ) {
				update_term_meta( $term_id, self::ORDER_META_KEY, absint( $folder['order'] ) );
			}

			$key = $this->folder_path_key( $zip_path );
			$map[ $key ] = $term_id;

			$access = isset( $folder['access'] ) ? self::sanitize_access_mode( $folder['access'] ) : self::ACCESS_PUBLIC;
			if ( self::ACCESS_PUBLIC !== $access ) {
				$old_access = $this->folder_access( $term_id );
				update_term_meta( $term_id, self::ACCESS_META_KEY, $access );
				if ( $old_access !== $access ) {
					do_action( 'pgm_mo_folder_access_changed', $term_id, $access, $old_access );
				}
			}
		}

		return $map;
	}

	private function sanitize_import_folder_path_parts( $parts ) {
		$clean = array();
		foreach ( (array) $parts as $part ) {
			$part = sanitize_text_field( wp_unslash( (string) $part ) );
			$part = trim( str_replace( '\\', '/', $part ), '/' );
			if ( '' === $part || '.' === $part || '..' === $part || false !== strpos( $part, '/' ) ) {
				continue;
			}
			$clean[] = $part;
		}

		return $clean;
	}

	private function get_or_create_folder_path( $parts ) {
		$parent = 0;
		foreach ( (array) $parts as $part ) {
			$name = trim( sanitize_text_field( $part ) );
			if ( '' === $name ) {
				continue;
			}

			$existing = $this->find_child_term_by_name( $name, $parent );
			if ( $existing ) {
				$parent = $existing;
				continue;
			}

			$result = wp_insert_term( $name, self::TAXONOMY, array( 'parent' => $parent ) );
			if ( is_wp_error( $result ) ) {
				break;
			}

			$new_parent = $parent;
			$parent     = (int) $result['term_id'];
			update_term_meta( $parent, self::ORDER_META_KEY, $this->next_folder_order( $new_parent ) );
			$this->mark_folder_locally_managed( $parent );
		}

		return $parent;
	}

	private function find_child_term_by_name( $name, $parent ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'parent'     => (int) $parent,
				'name'       => $name,
				'fields'     => 'ids',
				'number'     => 1,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		return (int) $terms[0];
	}

	private function duplicate_attachment( $attachment_id ) {
		$source_file = get_attached_file( $attachment_id, true );
		$relative_path = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$relative_path = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
		$source_readable = $source_file && file_exists( $source_file ) && is_readable( $source_file );
		$source_contents = null;

		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( ! $source_readable ) {
			$source_contents = apply_filters( 'pgm_mo_duplicate_attachment_source_contents', null, $attachment_id, $relative_path );
			if ( is_wp_error( $source_contents ) ) {
				return $source_contents;
			}
			if ( ! is_string( $source_contents ) ) {
				return new WP_Error( 'pgm_mo_source_missing', __( 'Alkuperäistä tiedostoa ei voitu lukea.', 'private-gutenberg-media' ) );
			}
		}

		$uploads = wp_get_upload_dir();
		$base_dir = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( ! $base_dir ) {
			return new WP_Error( 'pgm_mo_uploads_missing', __( 'Uploads-kansiota ei löytynyt.', 'private-gutenberg-media' ) );
		}

		if ( '' === $relative_path || false !== strpos( $relative_path, '../' ) || preg_match( '/^[a-z]+:/i', $relative_path ) ) {
			$relative_path = $source_file ? wp_basename( $source_file ) : 'media-' . $attachment_id;
		}

		$relative_dir = dirname( $relative_path );
		$dir = '.' === $relative_dir ? $uploads['basedir'] : trailingslashit( $uploads['basedir'] ) . $relative_dir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'pgm_mo_copy_failed', __( 'Tiedostokopiota ei voitu luoda.', 'private-gutenberg-media' ) );
		}

		$dir_real = realpath( $dir );
		if ( ! $dir_real || 0 !== strpos( wp_normalize_path( trailingslashit( $dir_real ) ), wp_normalize_path( trailingslashit( $base_dir ) ) ) ) {
			return new WP_Error( 'pgm_mo_copy_failed', __( 'Tiedostokopiota ei voitu luoda.', 'private-gutenberg-media' ) );
		}

		$extension = pathinfo( $relative_path, PATHINFO_EXTENSION );
		$copy_name = pathinfo( wp_basename( $relative_path ), PATHINFO_FILENAME ) . '-copy' . ( $extension ? '.' . $extension : '' );
		$filename = wp_unique_filename( $dir, $copy_name );
		$new_file = trailingslashit( $dir ) . $filename;

		$copied = $source_readable ? copy( $source_file, $new_file ) : false !== file_put_contents( $new_file, $source_contents, LOCK_EX );
		if ( ! $copied ) {
			return new WP_Error( 'pgm_mo_copy_failed', __( 'Tiedostokopiota ei voitu luoda.', 'private-gutenberg-media' ) );
		}

		$filetype = wp_check_filetype( $filename );
		$post     = get_post( $attachment_id );
		$new_id   = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => sprintf(
					/* translators: %s: attachment title. */
					__( '%s kopio', 'private-gutenberg-media' ),
					$post ? $post->post_title : pathinfo( $filename, PATHINFO_FILENAME )
				),
				'post_excerpt'   => $post ? $post->post_excerpt : '',
				'post_content'   => $post ? $post->post_content : '',
				'post_status'    => 'inherit',
				'post_parent'    => $post ? (int) $post->post_parent : 0,
			),
			$new_file
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			@unlink( $new_file );
			return new WP_Error( 'pgm_mo_insert_failed', __( 'Media Library -liitettä ei voitu luoda.', 'private-gutenberg-media' ) );
		}

		wp_update_attachment_metadata( $new_id, wp_generate_attachment_metadata( $new_id, $new_file ) );
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' !== (string) $alt ) {
			update_post_meta( $new_id, '_wp_attachment_image_alt', $alt );
		}

		$terms = wp_get_object_terms( $attachment_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			wp_set_object_terms( $new_id, array_map( 'intval', $terms ), self::TAXONOMY, false );
			do_action( 'pgm_mo_attachment_folder_changed', (int) $new_id, (int) reset( $terms ), array() );
		}

		$this->track_attachment_size( $new_id );
		do_action( 'pgm_mo_attachment_duplicated', (int) $new_id, (int) $attachment_id );

		return $new_id;
	}

	public function maybe_auto_import_catfolders() {
		if ( ! is_admin() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$this->preserve_existing_local_folder_changes();

		$fingerprint = $this->catfolders_source_fingerprint();
		if ( '' === $fingerprint || get_option( self::CATFOLDERS_SYNC_OPTION, '' ) === $fingerprint ) {
			return;
		}

		$result = $this->import_catfolders();
		if ( ! is_wp_error( $result ) ) {
			update_option( self::CATFOLDERS_SYNC_OPTION, $fingerprint, false );
		}
	}

	private function catfolders_tables() {
		global $wpdb;

		return array(
			'folders' => $wpdb->prefix . 'catfolders',
			'posts'   => $wpdb->prefix . 'catfolders_posts',
		);
	}

	private function catfolders_tables_exist( $tables = null ) {
		global $wpdb;

		$tables = is_array( $tables ) ? $tables : $this->catfolders_tables();

		$folders_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables['folders'] ) );
		$posts_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables['posts'] ) );

		return $folders_exists === $tables['folders'] && $posts_exists === $tables['posts'];
	}

	private function catfolders_source_fingerprint() {
		global $wpdb;

		$tables = $this->catfolders_tables();
		if ( ! $this->catfolders_tables_exist( $tables ) ) {
			return '';
		}

		$folders = $wpdb->get_results( "SELECT id, title, parent, type, ord FROM {$tables['folders']} WHERE type = 'attachment' ORDER BY id ASC", ARRAY_A );
		if ( empty( $folders ) ) {
			return '';
		}

		$relationships = $wpdb->get_results( "SELECT folder_id, post_id FROM {$tables['posts']} ORDER BY folder_id ASC, post_id ASC", ARRAY_A );

		$source = wp_json_encode(
			array(
				'folders'       => $folders,
				'relationships' => is_array( $relationships ) ? $relationships : array(),
			)
		);

		return $source ? self::CATFOLDERS_SYNC_VERSION . ':' . md5( $source ) : '';
	}

	private function remember_catfolders_source_fingerprint() {
		$fingerprint = $this->catfolders_source_fingerprint();
		if ( '' !== $fingerprint ) {
			update_option( self::CATFOLDERS_SYNC_OPTION, $fingerprint, false );
		}
	}

	private function mark_folder_locally_managed( $term_id ) {
		$term_id = absint( $term_id );
		if ( $term_id > 0 ) {
			update_term_meta( $term_id, self::LOCAL_OVERRIDE_META_KEY, 1 );
		}
	}

	private function preserve_existing_local_folder_changes() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$source_folders = $this->catfolders_source_folder_map();
		$term_by_source = array();

		foreach ( $terms as $term ) {
			$source_id = (int) get_term_meta( $term->term_id, self::CATFOLDERS_SOURCE_META_KEY, true );
			if ( $source_id > 0 ) {
				$term_by_source[ $source_id ] = (int) $term->term_id;
			}
		}

		foreach ( $terms as $term ) {
			$term_id = (int) $term->term_id;
			if ( $this->folder_is_locally_managed( $term_id ) ) {
				continue;
			}

			$source_id = (int) get_term_meta( $term_id, self::CATFOLDERS_SOURCE_META_KEY, true );
			if ( $source_id <= 0 ) {
				$this->mark_folder_locally_managed( $term_id );
				continue;
			}

			if ( empty( $source_folders[ $source_id ] ) ) {
				$this->mark_folder_locally_managed( $term_id );
				continue;
			}

			$source          = $source_folders[ $source_id ];
			$source_parent   = (int) $source['parent'];
			$expected_parent = $source_parent > 0 && isset( $term_by_source[ $source_parent ] ) ? (int) $term_by_source[ $source_parent ] : 0;
			$current_order   = (int) get_term_meta( $term_id, self::ORDER_META_KEY, true );

			if (
				(int) $term->parent !== $expected_parent ||
				(string) $term->name !== (string) $source['title'] ||
				$current_order !== (int) $source['ord']
			) {
				$this->mark_folder_locally_managed( $term_id );
			}
		}
	}

	private function catfolders_source_folder_map() {
		global $wpdb;

		$tables = $this->catfolders_tables();
		if ( ! $this->catfolders_tables_exist( $tables ) ) {
			return array();
		}

		$folders = $wpdb->get_results( "SELECT id, title, parent, ord FROM {$tables['folders']} WHERE type = 'attachment'", ARRAY_A );
		if ( empty( $folders ) || ! is_array( $folders ) ) {
			return array();
		}

		$map = array();
		foreach ( $folders as $folder ) {
			$source_id = isset( $folder['id'] ) ? (int) $folder['id'] : 0;
			if ( $source_id <= 0 ) {
				continue;
			}

			$map[ $source_id ] = array(
				'title'  => isset( $folder['title'] ) ? sanitize_text_field( $folder['title'] ) : '',
				'parent' => isset( $folder['parent'] ) ? (int) $folder['parent'] : 0,
				'ord'    => isset( $folder['ord'] ) ? (int) $folder['ord'] : 0,
			);
		}

		return $map;
	}

	private function update_folder_parent( $term_id, $parent, $args = array() ) {
		global $wpdb;

		$term_id = absint( $term_id );
		$parent  = absint( $parent );
		$args    = is_array( $args ) ? $args : array();
		$args['parent'] = $parent;

		$result = wp_update_term( $term_id, self::TAXONOMY, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		clean_term_cache( $term_id, self::TAXONOMY );
		$updated = get_term( $term_id, self::TAXONOMY );
		if ( ! $updated || is_wp_error( $updated ) ) {
			return new WP_Error( 'pgm_mo_folder_missing_after_update', __( 'Kansiota ei löytynyt tallennuksen jälkeen.', 'private-gutenberg-media' ) );
		}

		if ( (int) $updated->parent !== $parent ) {
			$taxonomy_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s LIMIT 1",
					$term_id,
					self::TAXONOMY
				)
			);

			if ( $taxonomy_id > 0 ) {
				$wpdb->update(
					$wpdb->term_taxonomy,
					array( 'parent' => $parent ),
					array( 'term_taxonomy_id' => $taxonomy_id ),
					array( '%d' ),
					array( '%d' )
				);
				clean_term_cache( $term_id, self::TAXONOMY );
				wp_cache_delete( 'all_ids', self::TAXONOMY );
				wp_cache_delete( 'get', self::TAXONOMY );
				$updated = get_term( $term_id, self::TAXONOMY );
			}

			if ( ! $updated || is_wp_error( $updated ) || (int) $updated->parent !== $parent ) {
				return new WP_Error( 'pgm_mo_folder_parent_not_saved', __( 'Kansion sijaintia ei voitu tallentaa pysyvästi.', 'private-gutenberg-media' ) );
			}
		}

		$this->mark_folder_locally_managed( $term_id );

		return $result;
	}

	private function folder_is_locally_managed( $term_id ) {
		return (bool) get_term_meta( (int) $term_id, self::LOCAL_OVERRIDE_META_KEY, true );
	}

	private function find_term_by_catfolders_source_id( $source_id ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => self::CATFOLDERS_SOURCE_META_KEY,
				'meta_value' => (string) absint( $source_id ),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		return (int) $terms[0];
	}

	private function import_catfolders() {
		global $wpdb;

		$tables        = $this->catfolders_tables();
		$folders_table = $tables['folders'];
		$posts_table   = $tables['posts'];

		if ( ! $this->catfolders_tables_exist( $tables ) ) {
			return new WP_Error( 'pgm_mo_catfolders_missing', __( 'CatFolders-tauluja ei löytynyt.', 'private-gutenberg-media' ) );
		}

		$folders = $wpdb->get_results( "SELECT id, title, parent, ord FROM {$folders_table} WHERE type = 'attachment' ORDER BY parent ASC, ord ASC, id ASC", ARRAY_A );
		if ( empty( $folders ) ) {
			return new WP_Error( 'pgm_mo_catfolders_empty', __( 'CatFolders-kansioita ei löytynyt tuotavaksi.', 'private-gutenberg-media' ) );
		}

		$pending = $folders;
		$map     = array( 0 => 0 );
		$created = 0;

		while ( ! empty( $pending ) ) {
			$progress = false;
			foreach ( $pending as $index => $folder ) {
				$old_parent = (int) $folder['parent'];
				if ( ! array_key_exists( $old_parent, $map ) ) {
					continue;
				}

				$name      = sanitize_text_field( $folder['title'] );
				$parent    = (int) $map[ $old_parent ];
				$source_id = (int) $folder['id'];
				$term_id   = $this->find_term_by_catfolders_source_id( $source_id );
				if ( $term_id ) {
					if ( ! $this->folder_is_locally_managed( $term_id ) ) {
						wp_update_term( $term_id, self::TAXONOMY, array( 'name' => $name, 'parent' => $parent ) );
					}
				}
				if ( ! $term_id ) {
					$term_id = $this->find_child_term_by_name( $name, $parent );
				}
				if ( ! $term_id ) {
					$result = wp_insert_term( $name, self::TAXONOMY, array( 'parent' => $parent ) );
					if ( is_wp_error( $result ) ) {
						unset( $pending[ $index ] );
						continue;
					}
					$term_id = (int) $result['term_id'];
					$created++;
				}

				if ( ! $this->folder_is_locally_managed( $term_id ) ) {
					update_term_meta( $term_id, self::ORDER_META_KEY, (int) $folder['ord'] );
				}
				update_term_meta( $term_id, self::CATFOLDERS_SOURCE_META_KEY, $source_id );
				$map[ $source_id ] = $term_id;
				unset( $pending[ $index ] );
				$progress = true;
			}

			if ( ! $progress ) {
				break;
			}
		}

		$relationships = $wpdb->get_results( "SELECT folder_id, post_id FROM {$posts_table}", ARRAY_A );
		$assigned = 0;
		foreach ( $relationships as $relationship ) {
			$old_folder = (int) $relationship['folder_id'];
			$post_id    = (int) $relationship['post_id'];
			if ( empty( $map[ $old_folder ] ) || 'attachment' !== get_post_type( $post_id ) ) {
				continue;
			}

			$this->assign_attachment_to_folder( $post_id, (int) $map[ $old_folder ] );
			$assigned++;
		}

		$term_ids = array_values( array_filter( array_map( 'absint', $map ) ) );
		if ( ! empty( $term_ids ) ) {
			wp_update_term_count_now( $term_ids, self::TAXONOMY );
		}

		return array(
			'folders'     => $created,
			'attachments' => $assigned,
		);
	}

	public function render_gallery_shortcode( $atts, $content = '', $shortcode_tag = 'pecodex_media_gallery' ) {
		unset( $content );

		$atts = shortcode_atts(
			array(
				'folder'  => 0,
				'size'    => 'medium',
				'columns' => 3,
				'link'    => 'file',
				'limit'   => 100,
			),
			$atts,
			$shortcode_tag
		);

		$folder_id = $this->resolve_folder_shortcode_value( $atts['folder'] );
		if ( ! $folder_id ) {
			return '';
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => min( 500, max( 1, absint( $atts['limit'] ) ) ),
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		);
		$args = $this->apply_folder_to_args( $args, $folder_id );

		$attachments = get_posts( $args );
		if ( empty( $attachments ) ) {
			return '';
		}

		$columns = min( 8, max( 1, absint( $atts['columns'] ) ) );
		$html = '<div class="pecodex-media-gallery pgm-media-gallery pgm-media-gallery-columns-' . esc_attr( $columns ) . '">';
		foreach ( $attachments as $attachment ) {
			$image = wp_get_attachment_image( $attachment->ID, sanitize_key( $atts['size'] ) );
			if ( ! $image ) {
				continue;
			}

			$html .= '<figure class="pgm-media-gallery-item">';
			if ( 'file' === $atts['link'] ) {
				$html .= '<a href="' . esc_url( wp_get_attachment_url( $attachment->ID ) ) . '">' . $image . '</a>';
			} elseif ( 'post' === $atts['link'] ) {
				$html .= '<a href="' . esc_url( get_attachment_link( $attachment->ID ) ) . '">' . $image . '</a>';
			} else {
				$html .= $image;
			}
			if ( '' !== trim( $attachment->post_excerpt ) ) {
				$html .= '<figcaption>' . esc_html( $attachment->post_excerpt ) . '</figcaption>';
			}
			$html .= '</figure>';
		}
		$html .= '</div>';

		return $html;
	}

	private function resolve_folder_shortcode_value( $value ) {
		if ( ! $value ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			$term = get_term( (int) $value, self::TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		$term = get_term_by( 'slug', sanitize_title( $value ), self::TAXONOMY );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$term = get_term_by( 'name', sanitize_text_field( $value ), self::TAXONOMY );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		return 0;
	}

	public function print_browser_debug_script() {
		?>
		<script type="text/javascript">
		jQuery(document).ajaxComplete(function(event, xhr, settings) {
			if (settings.data && typeof settings.data === 'string' && (settings.data.indexOf('action=pgm') !== -1 || settings.data.indexOf('action=create_folder') !== -1 || settings.data.indexOf('action=get_state') !== -1)) {
				try {
					var res = JSON.parse(xhr.responseText);
					if (res && res.data && res.data.serverDebug) {
						console.log('%c--- Pecodex Media Control: Live Server Directory Debug Logs ---', 'color: #0073aa; font-weight: bold;');
						console.log(res.data.serverDebug);
						console.log('%c---------------------------------------------------------------', 'color: #0073aa; font-weight: bold;');
					}
				} catch(e) {}
			}
		});
		</script>
		<?php
	}
}

if ( ! class_exists( 'Pecodex_Media_Library_Organizer', false ) ) {
	class_alias( 'PGM_Media_Organizer', 'Pecodex_Media_Library_Organizer' );
}

if ( ! class_exists( 'Pecodex_Media_Control_Organizer', false ) ) {
	class_alias( 'PGM_Media_Organizer', 'Pecodex_Media_Control_Organizer' );
}

