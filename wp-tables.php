<?php
/**
 * Plugin Name: WP Tables
 * Plugin URI: https://github.com/ianthompson/wp-tables
 * Description: Create frontend tables from CSV files stored in the WordPress media library.
 * Version: 0.4.0
 * Author: Ian Thompson
 * Update URI: https://github.com/ianthompson/wp-tables
 * Text Domain: wp-tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WP_Tables_Plugin {
	const POST_TYPE = 'wpt_table';
	const META_CSV = '_wpt_csv_attachment_id';
	const META_HEADERS = '_wpt_first_row_headers';
	const META_RESPONSIVE = '_wpt_responsive';
	const META_FONT_SIZE = '_wpt_font_size';
	const META_BORDER_STYLE = '_wpt_border_style';
	const META_BORDER_COLOR = '_wpt_border_color';
	const META_COLUMN_WIDTHS = '_wpt_column_widths';
	const NONCE_ACTION = 'wpt_save_table';
	const PREVIEW_ACTION = 'wpt_preview_csv';
	const UPDATE_URI = 'https://github.com/ianthompson/wp-tables';
	const RELEASE_API = 'https://api.github.com/repos/ianthompson/wp-tables/releases/latest';
	const RELEASE_ASSET = 'wp-tables.zip';
	const RELEASE_CACHE = 'wpt_latest_github_release';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_table' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, array( __CLASS__, 'ajax_preview_csv' ) );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_csv_uploads' ) );
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_github_update' ), 10, 4 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Tables', 'wp-tables' ),
			'singular_name'      => __( 'Table', 'wp-tables' ),
			'add_new_item'       => __( 'Add New Table', 'wp-tables' ),
			'edit_item'          => __( 'Edit Table', 'wp-tables' ),
			'new_item'           => __( 'New Table', 'wp-tables' ),
			'view_item'          => __( 'View Table', 'wp-tables' ),
			'search_items'       => __( 'Search Tables', 'wp-tables' ),
			'not_found'          => __( 'No tables found.', 'wp-tables' ),
			'not_found_in_trash' => __( 'No tables found in Trash.', 'wp-tables' ),
			'menu_name'          => __( 'Tables', 'wp-tables' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-editor-table',
				'supports'     => array( 'title' ),
			)
		);
	}

	public static function register_shortcode() {
		add_shortcode( 'wptables', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function allow_csv_uploads( $mimes ) {
		$mimes['csv'] = 'text/csv';

		return $mimes;
	}

	public static function check_github_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( self::UPDATE_URI !== $plugin_data['UpdateURI'] ) {
			return $update;
		}

		$release = self::get_latest_release();

		if ( ! $release || empty( $release['tag_name'] ) || empty( $release['html_url'] ) ) {
			return false;
		}

		$version = ltrim( $release['tag_name'], 'vV' );
		$package = self::get_release_package_url( $release );

		if ( ! $package || ! version_compare( $version, $plugin_data['Version'], '>' ) ) {
			return false;
		}

		return array(
			'id'      => self::UPDATE_URI,
			'slug'    => dirname( $plugin_file ),
			'version' => $version,
			'url'     => esc_url_raw( $release['html_url'] ),
			'package' => esc_url_raw( $package ),
		);
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'wpt_table_source',
			__( 'Table CSV', 'wp-tables' ),
			array( __CLASS__, 'render_source_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpt_table_shortcode',
			__( 'Shortcode', 'wp-tables' ),
			array( __CLASS__, 'render_shortcode_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_source_meta_box( $post ) {
		$attachment_id = absint( get_post_meta( $post->ID, self::META_CSV, true ) );
		$has_headers   = self::get_boolean_meta( $post->ID, self::META_HEADERS, true );
		$responsive    = self::get_boolean_meta( $post->ID, self::META_RESPONSIVE, true );
		$font_size     = self::get_font_size( $post->ID );
		$border_style  = self::get_border_style( $post->ID );
		$border_color  = self::get_border_color( $post->ID );
		$column_widths = self::get_column_widths( $post->ID );
		$attachment    = $attachment_id ? get_post( $attachment_id ) : null;
		$file_label    = $attachment ? get_the_title( $attachment ) : __( 'No CSV selected', 'wp-tables' );

		wp_nonce_field( self::NONCE_ACTION, 'wpt_table_nonce' );
		?>
		<div class="wpt-source">
			<input id="wpt-csv-attachment-id" name="wpt_csv_attachment_id" type="hidden" value="<?php echo esc_attr( $attachment_id ); ?>">
			<p class="wpt-file-row">
				<button id="wpt-select-csv" type="button" class="button button-secondary"><?php esc_html_e( 'Select CSV file', 'wp-tables' ); ?></button>
				<button id="wpt-remove-csv" type="button" class="button button-link-delete<?php echo $attachment_id ? '' : ' is-hidden'; ?>"><?php esc_html_e( 'Remove', 'wp-tables' ); ?></button>
				<strong id="wpt-csv-file-label"><?php echo esc_html( $file_label ); ?></strong>
			</p>
			<fieldset class="wpt-options">
				<label>
					<input id="wpt-first-row-headers" name="wpt_first_row_headers" type="checkbox" value="1" <?php checked( $has_headers ); ?>>
					<?php esc_html_e( 'First line is table column heading', 'wp-tables' ); ?>
				</label>
				<label>
					<input name="wpt_responsive" type="checkbox" value="1" <?php checked( $responsive ); ?>>
					<?php esc_html_e( 'Make table responsive', 'wp-tables' ); ?>
				</label>
				<label class="wpt-font-size-option">
					<span><?php esc_html_e( 'Table font scale', 'wp-tables' ); ?></span>
					<select id="wpt-font-size" name="wpt_font_size">
						<?php foreach ( self::get_font_size_options() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $font_size, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="wpt-border-option">
					<span><?php esc_html_e( 'Table borders', 'wp-tables' ); ?></span>
					<select id="wpt-border-style" name="wpt_border_style">
						<?php foreach ( self::get_border_style_options() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $border_style, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="wpt-border-option">
					<span><?php esc_html_e( 'Border colour', 'wp-tables' ); ?></span>
					<input id="wpt-border-color" name="wpt_border_color" type="color" value="<?php echo esc_attr( $border_color ); ?>">
				</label>
			</fieldset>
			<div class="wpt-preview-shell">
				<h3><?php esc_html_e( 'Preview', 'wp-tables' ); ?></h3>
				<div id="wpt-csv-preview" class="wpt-preview" aria-live="polite">
					<?php self::render_admin_preview( $attachment_id, $has_headers, $font_size, $border_style, $border_color, $column_widths ); ?>
				</div>
			</div>
			<div id="wpt-column-widths" class="wpt-column-widths" aria-live="polite">
				<?php self::render_column_width_controls( self::get_column_count_from_attachment( $attachment_id ), $column_widths ); ?>
			</div>
		</div>
		<?php
	}

	public static function render_shortcode_meta_box( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save this table to generate its shortcode.', 'wp-tables' ) . '</p>';
			return;
		}

		printf(
			'<p><input class="widefat code" type="text" readonly value="%s" onfocus="this.select();"></p>',
			esc_attr( sprintf( '[wptables id="%d"]', $post->ID ) )
		);
	}

	public static function save_table( $post_id ) {
		if ( ! isset( $_POST['wpt_table_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpt_table_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$attachment_id = isset( $_POST['wpt_csv_attachment_id'] ) ? absint( $_POST['wpt_csv_attachment_id'] ) : 0;

		if ( $attachment_id && self::is_csv_attachment( $attachment_id ) ) {
			update_post_meta( $post_id, self::META_CSV, $attachment_id );
		} else {
			delete_post_meta( $post_id, self::META_CSV );
		}

		self::save_boolean_meta( $post_id, self::META_HEADERS, isset( $_POST['wpt_first_row_headers'] ) );
		self::save_boolean_meta( $post_id, self::META_RESPONSIVE, isset( $_POST['wpt_responsive'] ) );

		$font_size = isset( $_POST['wpt_font_size'] ) ? sanitize_key( wp_unslash( $_POST['wpt_font_size'] ) ) : 'default';
		update_post_meta( $post_id, self::META_FONT_SIZE, self::sanitize_font_size( $font_size ) );

		$border_style = isset( $_POST['wpt_border_style'] ) ? sanitize_key( wp_unslash( $_POST['wpt_border_style'] ) ) : 'all';
		update_post_meta( $post_id, self::META_BORDER_STYLE, self::sanitize_border_style( $border_style ) );

		$border_color = isset( $_POST['wpt_border_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['wpt_border_color'] ) ) : '';
		update_post_meta( $post_id, self::META_BORDER_COLOR, self::sanitize_border_color( $border_color ) );

		$column_widths = isset( $_POST['wpt_column_widths'] ) ? self::sanitize_column_widths( wp_unslash( $_POST['wpt_column_widths'] ) ) : array();
		update_post_meta( $post_id, self::META_COLUMN_WIDTHS, $column_widths );
	}

	public static function enqueue_admin_assets( $hook ) {
		global $post_type;

		if ( self::POST_TYPE !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wpt-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), '0.4.0' );
		wp_enqueue_script( 'wpt-admin', plugins_url( 'assets/admin.js', __FILE__ ), array( 'jquery' ), '0.4.0', true );
		wp_localize_script(
			'wpt-admin',
			'wptAdmin',
			array(
				'action'          => self::PREVIEW_ACTION,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( self::PREVIEW_ACTION ),
				'chooseTitle'     => __( 'Choose a CSV file', 'wp-tables' ),
				'chooseButton'    => __( 'Use this CSV', 'wp-tables' ),
				'invalidFile'     => __( 'Please choose a CSV file.', 'wp-tables' ),
				'noFile'          => __( 'No CSV selected', 'wp-tables' ),
				'emptyPreview'    => __( 'Select a CSV file to preview the table.', 'wp-tables' ),
				'previewError'    => __( 'The CSV preview could not be loaded.', 'wp-tables' ),
				'loadingPreview'  => __( 'Loading preview...', 'wp-tables' ),
			)
		);
	}

	public static function ajax_preview_csv() {
		check_ajax_referer( self::PREVIEW_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot preview tables.', 'wp-tables' ) ), 403 );
		}

		$attachment_id = isset( $_POST['attachmentId'] ) ? absint( $_POST['attachmentId'] ) : 0;
		$has_headers   = ! empty( $_POST['hasHeaders'] );
		$font_size     = isset( $_POST['fontSize'] ) ? self::sanitize_font_size( sanitize_key( wp_unslash( $_POST['fontSize'] ) ) ) : 'default';
		$border_style  = isset( $_POST['borderStyle'] ) ? self::sanitize_border_style( sanitize_key( wp_unslash( $_POST['borderStyle'] ) ) ) : 'all';
		$border_color  = isset( $_POST['borderColor'] ) ? self::sanitize_border_color( sanitize_hex_color( wp_unslash( $_POST['borderColor'] ) ) ) : self::get_default_border_color();
		$column_widths = isset( $_POST['columnWidths'] ) ? self::sanitize_column_widths( wp_unslash( $_POST['columnWidths'] ) ) : array();

		if ( ! self::is_csv_attachment( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected media item is not a CSV file.', 'wp-tables' ) ), 400 );
		}

		$rows = self::read_csv_rows( $attachment_id, 21 );

		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( array( 'message' => $rows->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'html'           => self::build_table_markup( $rows, $has_headers, false, 'wpt-preview-table', $font_size, $border_style, $border_color, $column_widths ),
				'widthControls'  => self::build_column_width_controls( self::get_column_count( $rows ), $column_widths ),
			)
		);
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'wptables' );
		$id   = absint( $atts['id'] );

		if ( ! $id || self::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}

		$attachment_id = absint( get_post_meta( $id, self::META_CSV, true ) );
		$rows          = self::read_csv_rows( $attachment_id );

		if ( is_wp_error( $rows ) || empty( $rows ) ) {
			return '';
		}

		wp_enqueue_style( 'wpt-frontend', plugins_url( 'assets/frontend.css', __FILE__ ), array(), '0.4.0' );

		return self::build_table_markup(
			$rows,
			self::get_boolean_meta( $id, self::META_HEADERS, true ),
			self::get_boolean_meta( $id, self::META_RESPONSIVE, true ),
			'wpt-table',
			self::get_font_size( $id ),
			self::get_border_style( $id ),
			self::get_border_color( $id ),
			self::get_column_widths( $id )
		);
	}

	private static function render_admin_preview( $attachment_id, $has_headers, $font_size, $border_style, $border_color, $column_widths ) {
		if ( ! $attachment_id ) {
			echo '<p>' . esc_html__( 'Select a CSV file to preview the table.', 'wp-tables' ) . '</p>';
			return;
		}

		$rows = self::read_csv_rows( $attachment_id, 21 );

		if ( is_wp_error( $rows ) ) {
			echo '<p>' . esc_html( $rows->get_error_message() ) . '</p>';
			return;
		}

		echo self::build_table_markup( $rows, $has_headers, false, 'wpt-preview-table', $font_size, $border_style, $border_color, $column_widths ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function read_csv_rows( $attachment_id, $limit = 0 ) {
		if ( ! self::is_csv_attachment( $attachment_id ) ) {
			return new WP_Error( 'wpt_invalid_csv', __( 'Select a CSV attachment before rendering this table.', 'wp-tables' ) );
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error( 'wpt_missing_csv', __( 'The selected CSV file could not be read.', 'wp-tables' ) );
		}

		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return new WP_Error( 'wpt_open_csv', __( 'The selected CSV file could not be opened.', 'wp-tables' ) );
		}

		$rows = array();

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( empty( $rows ) && isset( $row[0] ) ) {
				$row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $row[0] );
			}

			$rows[] = $row;

			if ( $limit && count( $rows ) >= $limit ) {
				break;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $rows;
	}

	private static function build_table_markup( $rows, $has_headers, $responsive, $class_name, $font_size = 'default', $border_style = 'all', $border_color = '', $column_widths = array() ) {
		if ( empty( $rows ) ) {
			return '<p>' . esc_html__( 'This CSV file has no table rows.', 'wp-tables' ) . '</p>';
		}

		$border_color = self::sanitize_border_color( $border_color );
		$markup = sprintf(
			'<table class="%s" style="%s">',
			esc_attr( $class_name . ' wpt-font-size-' . self::sanitize_font_size( $font_size ) . ' wpt-borders-' . self::sanitize_border_style( $border_style ) ),
			esc_attr( '--wpt-border-color: ' . $border_color . ';' )
		);
		$markup .= self::build_column_group( $column_widths, self::get_column_count( $rows ) );

		if ( $has_headers ) {
			$heading_row = array_shift( $rows );
			$markup     .= '<thead><tr>';

			foreach ( $heading_row as $cell ) {
				$markup .= '<th scope="col">' . esc_html( $cell ) . '</th>';
			}

			$markup .= '</tr></thead>';
		}

		$markup .= '<tbody>';

		foreach ( $rows as $row ) {
			$markup .= '<tr>';

			foreach ( $row as $cell ) {
				$markup .= '<td>' . esc_html( $cell ) . '</td>';
			}

			$markup .= '</tr>';
		}

		$markup .= '</tbody></table>';

		if ( $responsive ) {
			$markup = '<div class="wpt-responsive-table">' . $markup . '</div>';
		}

		return $markup;
	}

	private static function is_csv_attachment( $attachment_id ) {
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		$path      = get_attached_file( $attachment_id );
		$extension = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

		return 'csv' === $extension;
	}

	private static function get_column_count_from_attachment( $attachment_id ) {
		$rows = self::read_csv_rows( $attachment_id, 21 );

		return is_wp_error( $rows ) ? 0 : self::get_column_count( $rows );
	}

	private static function get_column_count( $rows ) {
		$column_count = 0;

		foreach ( $rows as $row ) {
			$column_count = max( $column_count, count( $row ) );
		}

		return $column_count;
	}

	private static function render_column_width_controls( $column_count, $column_widths ) {
		echo self::build_column_width_controls( $column_count, $column_widths ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function build_column_width_controls( $column_count, $column_widths ) {
		if ( ! $column_count ) {
			return '<p>' . esc_html__( 'Select a CSV file to set column widths.', 'wp-tables' ) . '</p>';
		}

		$column_widths = self::sanitize_column_widths( $column_widths );
		$markup        = '<h3>' . esc_html__( 'Column widths', 'wp-tables' ) . '</h3>';
		$markup       .= '<div class="wpt-column-width-grid">';

		for ( $index = 0; $index < $column_count; $index++ ) {
			$width = isset( $column_widths[ $index ] ) ? $column_widths[ $index ] : array( 'value' => '', 'unit' => 'auto' );
			$markup .= '<label class="wpt-column-width">';
			$markup .= '<span>' . esc_html( sprintf( __( 'Column %d', 'wp-tables' ), $index + 1 ) ) . '</span>';
			$markup .= sprintf(
				'<input class="small-text" name="wpt_column_widths[%1$d][value]" type="number" min="0" step="0.1" value="%2$s" %3$s>',
				esc_attr( $index ),
				esc_attr( $width['value'] ),
				'auto' === $width['unit'] ? 'disabled' : ''
			);
			$markup .= sprintf( '<select name="wpt_column_widths[%d][unit]">', esc_attr( $index ) );

			foreach ( self::get_column_width_units() as $value => $label ) {
				$markup .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $width['unit'], $value, false ), esc_html( $label ) );
			}

			$markup .= '</select></label>';
		}

		return $markup . '</div>';
	}

	private static function build_column_group( $column_widths, $column_count ) {
		$column_widths = self::sanitize_column_widths( $column_widths );

		if ( ! $column_widths || ! $column_count ) {
			return '';
		}

		$markup = '<colgroup>';

		for ( $index = 0; $index < $column_count; $index++ ) {
			if ( ! isset( $column_widths[ $index ] ) || 'auto' === $column_widths[ $index ]['unit'] ) {
				$markup .= '<col>';
				continue;
			}

			$markup .= sprintf(
				'<col style="%s">',
				esc_attr( 'width: ' . $column_widths[ $index ]['value'] . $column_widths[ $index ]['unit'] . ';' )
			);
		}

		return $markup . '</colgroup>';
	}

	private static function get_column_width_units() {
		return array(
			'auto' => __( 'Auto', 'wp-tables' ),
			'%'    => '%',
			'px'   => 'px',
		);
	}

	private static function get_column_widths( $post_id ) {
		return self::sanitize_column_widths( get_post_meta( $post_id, self::META_COLUMN_WIDTHS, true ) );
	}

	private static function sanitize_column_widths( $column_widths ) {
		if ( ! is_array( $column_widths ) ) {
			return array();
		}

		$units  = self::get_column_width_units();
		$widths = array();

		foreach ( $column_widths as $index => $width ) {
			if ( ! is_array( $width ) || ! is_numeric( $index ) ) {
				continue;
			}

			$unit  = isset( $width['unit'] ) && isset( $units[ $width['unit'] ] ) ? $width['unit'] : 'auto';
			$value = isset( $width['value'] ) ? (float) $width['value'] : 0;

			if ( 'auto' === $unit || $value <= 0 ) {
				$widths[ absint( $index ) ] = array( 'value' => '', 'unit' => 'auto' );
				continue;
			}

			$widths[ absint( $index ) ] = array(
				'value' => (string) min( $value, '%' === $unit ? 100 : 5000 ),
				'unit'  => $unit,
			);
		}

		ksort( $widths );

		return $widths;
	}

	private static function get_font_size_options() {
		return array(
			'default' => __( 'Theme default', 'wp-tables' ),
			'small'   => __( '80% of theme size', 'wp-tables' ),
			'medium'  => __( '90% of theme size', 'wp-tables' ),
			'large'   => __( '110% of theme size', 'wp-tables' ),
			'x-large' => __( '125% of theme size', 'wp-tables' ),
		);
	}

	private static function get_font_size( $post_id ) {
		return self::sanitize_font_size( get_post_meta( $post_id, self::META_FONT_SIZE, true ) );
	}

	private static function sanitize_font_size( $font_size ) {
		$options = self::get_font_size_options();

		return isset( $options[ $font_size ] ) ? $font_size : 'default';
	}

	private static function get_border_style_options() {
		return array(
			'all'        => __( 'All borders', 'wp-tables' ),
			'vertical'   => __( 'Vertical only', 'wp-tables' ),
			'horizontal' => __( 'Horizontal only', 'wp-tables' ),
			'header'     => __( 'Table header only', 'wp-tables' ),
		);
	}

	private static function get_border_style( $post_id ) {
		return self::sanitize_border_style( get_post_meta( $post_id, self::META_BORDER_STYLE, true ) );
	}

	private static function sanitize_border_style( $border_style ) {
		$options = self::get_border_style_options();

		return isset( $options[ $border_style ] ) ? $border_style : 'all';
	}

	private static function get_border_color( $post_id ) {
		return self::sanitize_border_color( get_post_meta( $post_id, self::META_BORDER_COLOR, true ) );
	}

	private static function sanitize_border_color( $border_color ) {
		return $border_color ? $border_color : self::get_default_border_color();
	}

	private static function get_default_border_color() {
		return '#cccccc';
	}

	private static function get_latest_release() {
		$cached_release = get_site_transient( self::RELEASE_CACHE );

		if ( is_array( $cached_release ) ) {
			return $cached_release;
		}

		$response = wp_remote_get(
			self::RELEASE_API,
			array(
				'headers' => array(
					'Accept' => 'application/vnd.github+json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $release ) ) {
			return false;
		}

		set_site_transient( self::RELEASE_CACHE, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	private static function get_release_package_url( $release ) {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			if ( ! empty( $asset['name'] ) && self::RELEASE_ASSET === $asset['name'] && ! empty( $asset['browser_download_url'] ) ) {
				return $asset['browser_download_url'];
			}
		}

		return '';
	}

	private static function get_boolean_meta( $post_id, $key, $default ) {
		$value = get_post_meta( $post_id, $key, true );

		if ( '' === $value ) {
			return $default;
		}

		return '1' === $value;
	}

	private static function save_boolean_meta( $post_id, $key, $value ) {
		update_post_meta( $post_id, $key, $value ? '1' : '0' );
	}
}

WP_Tables_Plugin::init();
