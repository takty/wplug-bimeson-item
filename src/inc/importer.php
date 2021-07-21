<?php
/**
 * Bimeson (Importer)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-07-21
 */

namespace wplug\bimeson_item;

require_once ABSPATH . 'wp-admin/includes/import.php';
if ( ! class_exists( '\WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) require $class_wp_importer;
}
if ( ! class_exists( '\WP_Importer' ) ) return;

require_once __DIR__ . '/../assets/ajax.php';

class Bimeson_Importer extends \WP_Importer {

	static public function register( $url_to ) {
		new Bimeson_Importer( $url_to );
	}

	private $_url_to;
	private $_ajax_request_url;

	private $_file_id;
	private $_file_name;
	private $_add_taxonomies = false;
	private $_add_terms      = false;
	private $_items          = [];

	public function __construct( $url_to ) {
		$this->_url_to = $url_to;
		$this->initialize();
		$this->_ajax_request_url = $this->initialize_ajax();
	}

	private function initialize() {
		$GLOBALS['bimeson_import'] = $this;
		register_importer(
			'bimeson',
			'Bimeson',
			__( 'Import <strong>publications</strong> from a Excel file.', 'bimeson_item' ),
			[ $GLOBALS['bimeson_import'], 'dispatch' ]
		);
	}

	private function initialize_ajax() {
		$ajax = new Ajax( 'bimeson_import', function ( $data ) {
			$status = $data['status'] ?? '';
			if ( 'start' === $status ) {
				add_filter( 'http_request_timeout', function () { return 60; } );
				do_action( 'import_start' );
				wp_suspend_cache_invalidation( true );
				Ajax::send_success( [ 'index' => 0 ] );
			} else if ( 'end' === $status ) {
				wp_suspend_cache_invalidation( false );
				wp_import_cleanup( (int) $data['file_id'] );
				wp_cache_flush();
				do_action( 'import_end' );
			} else {
				set_time_limit(0);
				$msgs_t = process_terms( $data['items'], $data['add_taxonomy'], $data['add_term'] );
				$msgs_i = process_items( $data['items'], $data['file_name'] );
				Ajax::send_success( [ 'msgs' => array_merge( $msgs_t, $msgs_i ), 'index' => $data['next_index'] ] );
			}
		}, false );
		return $ajax->get_url();
	}


	// -------------------------------------------------------------------------


	public function dispatch() {
		wp_enqueue_script( 'bimeson_item_importer', $this->_url_to . '/assets/js/importer.min.js' );
		wp_enqueue_script( 'xlsx',                  $this->_url_to . '/assets/js/xlsx.full.min.js' );

		$this->_header();

		$step = (int) ( $_GET['step'] ?? 0 );
		switch ( $step ) {
			case 0:
				$this->_greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				$fid = $this->_handle_upload();
				if ( ! is_null( $fid ) ) $this->_parse_upload( $fid );
				break;
		}

		$this->_footer();
	}

	private function _header() {
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Import Publication List', 'bimeson_item' ) . '</h2>';
	}

	private function _footer() {
		echo '</div>';
	}


	// Step 0 ------------------------------------------------------------------


	private function _greet() {
		echo '<div class="narrow">';
		echo '<p>' . esc_html__( 'Upload your Bimeson-formatted Excel (xlsx) file and we&#8217;ll import the publications into this site.', 'bimeson_item' ) . '</p>';
		echo '<p>' . esc_html__( 'Choose an Excel (.xlsx) file to upload, then click Upload file and import.', 'bimeson_item' ) . '</p>';
		wp_import_upload_form( 'admin.php?import=bimeson&amp;step=1' );
		echo '</div>';
	}


	// Step 1 ------------------------------------------------------------------


	private function _handle_upload(): ?int {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'bimeson_item' ) . '</strong><br>';
			echo esc_html( $file['error'] ) . '</p>';
			return null;
		} else if ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'bimeson_item' ) . '</strong><br>';
			printf( esc_html__( 'The file could not be found at <code>%s</code>.', 'bimeson_item' ), esc_html( $file['file'] ) );
			echo '</p>';
			return null;
		}
		$this->_file_id = (int) $file['id'];
		return $this->_file_id;
	}

	private function _parse_upload( int $file_id ) {
		$_url   = esc_attr( wp_get_attachment_url( $file_id ) );
		$_fname = esc_attr( pathinfo( get_attached_file( $file_id ), PATHINFO_FILENAME ) );
		$_ajax  = esc_attr( $this->_ajax_request_url );
		?>
		<input type="hidden" id="import-url" value="<?php echo $_url; ?>">
		<input type="hidden" id="import-file-id" value="<?php echo $file_id; ?>">
		<input type="hidden" id="import-file-name" value="<?php echo $_fname; ?>">
		<input type="hidden" id="import-ajax" value="<?php echo $_ajax; ?>">
		<div id="section-option">
			<h3><?php esc_html_e( 'Add Terms', 'bimeson_item' ); ?></h3>
			<p>
				<label>
					<input type="radio" id="do-nothing" checked>
					<?php esc_html_e( 'Do nothing', 'bimeson_item' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" id="add-tax">
					<?php esc_html_e( 'Add category groups themselves', 'bimeson_item' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" id="add-term">
					<?php esc_html_e( 'Add categories to the category group', 'bimeson_item' ); ?>
				</label>
			</p>
		</div>
		<p class="submit">
			<button type="button" id="btn-start-import" class="button"><?php esc_html_e( 'Start Import' ); ?></button>
		</p>
		<div id="msg-response" style="margin-top:1rem;max-height:50vh;overflow:auto;"></div>
		<p id="msg-success" hidden><strong><?php esc_html_e( 'All done.', 'bimeson_item' ); ?></strong></p>
		<p id="msg-failure" hidden><strong><?php esc_html_e( 'Sorry, failed to read the file.', 'bimeson_item' ); ?></strong></p>
		<?php
	}

}
