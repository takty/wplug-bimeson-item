<?php
/**
 * Bimeson (Importer)
 *
 * @author Takuto Yanagida
 * @version 2021-07-15
 */

namespace wplug\bimeson_post;

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
			__('Import <strong>publications</strong> from a Excel file.', 'bimeson-post'),
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
		wp_enqueue_script( 'bimeson-post-loader', $this->_url_to . '/assets/js/loader.min.js' );
		wp_enqueue_script( 'xlsx',                $this->_url_to . '/assets/js/xlsx.full.min.js' );

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
		echo '<h2>' . __( 'Import Bimeson', 'bimeson-post' ) . '</h2>';
	}

	private function _footer() {
		echo '</div>';
	}


	// Step 0 ------------------------------------------------------------------


	private function _greet() {
		echo '<div class="narrow">';
		echo '<p>'.__( 'Howdy! Upload your Bimeson Excel (xlsx) file and we&#8217;ll import the publications into this site.', 'bimeson-post' ).'</p>';
		echo '<p>'.__( 'Choose a Excel (.xlsx) file to upload, then click Upload file and import.', 'bimeson-post' ).'</p>';
		wp_import_upload_form( 'admin.php?import=bimeson&amp;step=1' );
		echo '</div>';
	}


	// Step 1 ------------------------------------------------------------------


	private function _handle_upload() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'bimeson-post' ) . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} else if ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'bimeson-post' ) . '</strong><br />';
			printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'bimeson-post' ), esc_html( $file['file'] ) );
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
	<input type="hidden" name="import_id" value="<?php echo $this->_id; ?>" />
	<input type="hidden" name="bimeson_items" id="bimeson-items" value="" />
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			BIMESON.loadFiles(['<?php echo $url ?>'], '#bimeson-items', function (successAll) {
				if (successAll) document.form.submit.disabled = false;
				else document.getElementById('error').style.display = 'block';
			});
		});
	</script>

	<h3><?php _e( 'Add Terms', 'bimeson-post' ); ?></h3>
	<p>
		<input type="radio" value="taxonomy" name="add_terms" id="add-taxonomies-terms" />
		<label for="add-taxonomies-terms"><?php _e( 'Add taxonomies (categories) and terms that import file contains', 'bimeson-post' ); ?></label>
	</p>
	<p>
		<input type="radio" value="term" name="add_terms" id="add-terms" />
		<label for="add-terms"><?php _e( 'Add terms that import file contains', 'bimeson-post' ); ?></label>
	</p>

	<p class="submit"><input type="submit" name="submit" disabled class="button" value="<?php esc_attr_e( 'Submit', 'bimeson-post' ); ?>" /></p>
</form>
<?php
		echo '<p id="error" style="display: none;"><strong>' . __( 'Sorry, there has been an error.', 'bimeson-post' ) . '</strong><br />';
	}


	// Step 2 ------------------------------------------------------------------


	private function _import( $json ) {
		add_filter( 'http_request_timeout', function ( $val ) { return 60; } );

		$this->_import_start( $json );
		wp_suspend_cache_invalidation( true );
		process_terms( $this->_items, $this->_add_taxonomies, $this->_add_terms );
		process_items( $this->_items, $this->_file_name );
		wp_suspend_cache_invalidation( false );
		$this->_import_end();
	}

	private function _import_start( $json ) {
		$data = json_decode( $json, true );
		if ( $data === null ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'bimeson-post' ) . '</strong><br />';
			echo __( 'The file is wrong, please try again.', 'bimeson-post' ) . '</p>';
			$this->_footer();
			die();
		}
		$this->_items = $data;

		do_action( 'import_start' );
	}

	private function _import_end() {
		wp_import_cleanup( $this->_id );
		wp_cache_flush();

		echo '<p>' . __( 'All done.', 'bimeson-post' ) . ' <a href="' . admin_url() . '">' . __( 'Have fun!', 'bimeson-post' ) . '</a>' . '</p>';

		do_action( 'import_end' );
	}

}
