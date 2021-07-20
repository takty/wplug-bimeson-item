<?php
/**
 * Bimeson (Importer)
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_item;

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) return;

require_once ABSPATH . 'wp-admin/includes/import.php';
if ( ! class_exists( '\WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) require $class_wp_importer;
}
if ( ! class_exists( '\WP_Importer' ) ) return;


class Bimeson_Importer extends \WP_Importer {

	static public function register( $url_to ) {
		$GLOBALS['bimeson_import'] = new Bimeson_Importer( $url_to );
		register_importer(
			'bimeson', 'Bimeson',
			__( 'Import <strong>publications</strong> from a Excel file.', 'bimeson_item' ),
			[ $GLOBALS['bimeson_import'], 'dispatch' ]
		);
	}

	private $_url_to = null;

	private $_id;
	private $_file_name;
	private $_add_taxonomies = false;
	private $_add_terms      = false;
	private $_items          = [];

	public function __construct( $url_to = false ) {
		$this->_url_to = $url_to;
	}

	public function dispatch() {
		wp_enqueue_script( 'bimeson_item_importer', $this->_url_to . '/assets/js/importer.min.js' );
		wp_enqueue_script( 'xlsx',                  $this->_url_to . '/assets/js/xlsx.full.min.js' );

		$this->_header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->_greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				if ( $this->_handle_upload() ) $this->_parse_upload();
				break;
			case 2:
				check_admin_referer( 'import-bimeson' );
				$this->_id             = (int) $_POST['import_id'];
				$this->_file_name      = pathinfo( get_attached_file( $this->_id ), PATHINFO_FILENAME );
				$this->_add_taxonomies = ( ! empty( $_POST['add_terms'] ) && $_POST['add_terms'] === 'taxonomy' );
				$this->_add_terms      = ( ! empty( $_POST['add_terms'] ) && $_POST['add_terms'] === 'term' );
				set_time_limit(0);
				$this->_import( stripslashes( $_POST['bimeson_items'] ) );
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


	private function _handle_upload() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'bimeson_item' ) . '</strong><br>';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} else if ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'bimeson_item' ) . '</strong><br>';
			printf( esc_html__( 'The file could not be found at <code>%s</code>.', 'bimeson_item' ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}
		$this->_id = (int) $file['id'];
		return true;
	}

	private function _parse_upload() {
		$url = wp_get_attachment_url( $this->_id );
?>
<form action="<?php echo admin_url( 'admin.php?import=bimeson&amp;step=2' ); ?>" method="post" name="form">
	<?php wp_nonce_field( 'import-bimeson' ); ?>
	<input type="hidden" name="import_id" value="<?php echo $this->_id; ?>">
	<input type="hidden" name="bimeson_items" id="bimeson-items" value="">
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			BIMESON.loadFiles(['<?php echo $url; ?>'], '#bimeson-items', function (successAll) {
				if (successAll) document.form.submit.disabled = false;
				else document.getElementById('error').style.display = 'block';
			});
		});
	</script>

	<h3><?php esc_html_e( 'Add Terms', 'bimeson_item' ); ?></h3>
	<p>
		<input type="radio" value="taxonomy" name="do_nothing" id="do-nothing" checked>
		<label for="do-nothing"><?php esc_html_e( 'Do nothing', 'bimeson_item' ); ?></label>
	</p>
	<p>
		<input type="radio" value="taxonomy" name="add_terms" id="add-taxonomies-terms">
		<label for="add-taxonomies-terms"><?php esc_html_e( 'Add category groups themselves', 'bimeson_item' ); ?></label>
	</p>
	<p>
		<input type="radio" value="term" name="add_terms" id="add-terms">
		<label for="add-terms"><?php esc_html_e( 'Add categories to the category group', 'bimeson_item' ); ?></label>
	</p>

	<p class="submit"><input type="submit" name="submit" disabled class="button" value="<?php esc_attr_e( 'Start Import', 'bimeson_item' ); ?>"></p>
</form>
<?php
		echo '<p id="error" style="display: none;"><strong>' . esc_html__( 'Sorry, failed to read the file.', 'bimeson_item' ) . '</strong><br>';
	}


	// Step 2 ------------------------------------------------------------------


	private function _import( $json ) {
		add_filter( 'http_request_timeout', function ( $val ) { return 60; } );

		$this->_import_start( $json );
		wp_suspend_cache_invalidation( true );
		echo '<div style="margin-top:1em;max-height:50vh;overflow:auto;">';
		process_terms( $this->_items, $this->_add_taxonomies, $this->_add_terms );
		process_items( $this->_items, $this->_file_name );
		echo '</div>';
		wp_suspend_cache_invalidation( false );
		$this->_import_end();
	}

	private function _import_start( $json ) {
		$data = json_decode( $json, true );
		if ( $data === null ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'bimeson_item' ) . '</strong><br>';
			esc_html_e( 'The content of the file is wrong, please try again.', 'bimeson_item' ) . '</p>';
			$this->_footer();
			die();
		}
		$this->_items = $data;
		do_action( 'import_start' );
	}

	private function _import_end() {
		wp_import_cleanup( $this->_id );
		wp_cache_flush();
		echo '<p>' . esc_html__( 'All done.', 'bimeson_item' ) . '</p>';
		do_action( 'import_end' );
	}

}
