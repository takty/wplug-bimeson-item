<?php
/**
 * Importer
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2024-05-26
 */

declare(strict_types=1);

namespace wplug\bimeson_item;

/** phpcs:ignore
 *
 * @psalm-suppress MissingFile
 */
require_once ABSPATH . 'wp-admin/includes/import.php';
if ( false === class_exists( '\WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) ) {
		/** phpcs:ignore
		 *
		 * @psalm-suppress MissingFile
		 */
		require $class_wp_importer;
	}
}
if ( ! class_exists( '\WP_Importer' ) ) {
	return;
}

require_once __DIR__ . '/class-ajax.php';
require_once __DIR__ . '/post-type.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Bimeson importer
 *
 * @api
 */
class Bimeson_Importer extends \WP_Importer {

	/**
	 * Base URL.
	 *
	 * @psalm-suppress UnusedProperty
	 *
	 * @var string
	 */
	private $url_to;

	/**
	 * Ajax request URL.
	 *
	 * @psalm-suppress UnusedProperty
	 *
	 * @var string
	 */
	private $ajax_request_url;

	/**
	 * Uploaded file ID.
	 *
	 * @psalm-suppress UnusedProperty
	 *
	 * @var int
	 */
	private $file_id = 0;

	/**
	 * Constructor.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @param string $url_to Base URL.
	 */
	public function __construct( string $url_to ) {
		$this->url_to = $url_to;
		$this->initialize();
		$this->ajax_request_url = $this->initialize_ajax();
	}

	/**
	 * Initializes the importer.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 */
	private function initialize(): void {
		$GLOBALS['wplug_bimeson_import'] = $this;
		register_importer(
			'wplug_bimeson',
			'Bimeson',
			__( 'Import <strong>publications</strong> from an Excel file.', 'wplug_bimeson_item' ),
			array( $GLOBALS['wplug_bimeson_import'], 'dispatch' )
		);
	}

	/**
	 * Initializes Ajax.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 *
	 * @return string Ajax URL.
	 */
	private function initialize_ajax(): string {
		$ajax = new Ajax(
			'wplug_bimeson_import',
			function ( array $data ) {
				$status = $data['status'] ?? '';
				if ( 'start' === $status ) {
					add_filter(
						'http_request_timeout',
						function () {
							return 60;
						}
					);
					do_action( 'import_start' );
					wp_suspend_cache_invalidation( true );
					Ajax::send_success( array( 'index' => 0 ) );
				} elseif ( 'end' === $status ) {
					wp_suspend_cache_invalidation( false );
					wp_import_cleanup( $data['file_id'] );
					wp_cache_flush();
					do_action( 'import_end' );
				} else {
					set_time_limit( 0 );
					$msgs_t = process_terms( $data['items'], $data['add_taxonomy'], $data['add_term'] );
					$msgs_i = process_items( $data['items'], $data['file_name'] );
					Ajax::send_success(
						array(
							'msgs'  => array_merge( $msgs_t, $msgs_i ),
							'index' => $data['next_index'],
						)
					);
				}
			},
			false
		);
		return $ajax->get_url();
	}


	// -------------------------------------------------------------------------


	/**
	 * Dispatches the request.
	 *
	 * @psalm-suppress UnusedMethod
	 */
	public function dispatch(): void {
		wp_enqueue_script( 'wplug-bimeson-item-importer', $this->url_to . '/assets/js/importer.min.js', array(), '1.0', false );
		wp_enqueue_script( 'xlsx', $this->url_to . '/assets/js/sheetjs/xlsx.full.min.js', array(), '1.0', false );

		$this->header();

		$step = ( isset( $_GET['step'] ) && is_string( $_GET['step'] ) ) ? ( (int) sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) : 0;
		switch ( $step ) {
			case 0:
				$this->greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				$fid = $this->handle_upload();
				if ( ! is_null( $fid ) ) {
					$this->parse_upload( $fid );
				}
				break;
		}

		$this->footer();
	}

	/**
	 * Outputs the header.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 */
	private function header(): void {
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Import Publication List', 'wplug_bimeson_item' ) . '</h2>';
	}

	/**
	 * Outputs the footer.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 */
	private function footer(): void {
		echo '</div>';
	}


	// ----------------------------------------------------------------- Step 0.


	/**
	 * Outputs the greet message.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 */
	private function greet(): void {
		echo '<div class="narrow">';
		echo '<p>' . esc_html__( 'Upload your Bimeson-formatted Excel (xlsx) file and we&#8217;ll import the publications into this site.', 'wplug_bimeson_item' ) . '</p>';
		echo '<p>' . esc_html__( 'Choose an Excel (.xlsx) file to upload, then click Upload file and import.', 'wplug_bimeson_item' ) . '</p>';
		wp_import_upload_form( 'admin.php?import=wplug_bimeson&amp;step=1' );
		echo '</div>';
	}


	// ----------------------------------------------------------------- Step 1.


	/**
	 * Handles uploading.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 *
	 * @return ?int File ID.
	 */
	private function handle_upload(): ?int {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'wplug_bimeson_item' ) . '</strong><br>';
			echo esc_html( $file['error'] ) . '</p>';
			return null;
		} elseif ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'wplug_bimeson_item' ) . '</strong><br>';
			/* translators: Path of uploaded file. */
			printf( esc_html__( 'The file could not be found at <code>%s</code>.', 'wplug_bimeson_item' ), esc_html( $file['file'] ) );
			echo '</p>';
			return null;
		}
		$this->file_id = (int) $file['id'];
		return $this->file_id;
	}

	/**
	 * Parses uploaded file.
	 *
	 * @access private
	 * @psalm-suppress UnusedMethod
	 *
	 * @param int $file_id File ID.
	 */
	private function parse_upload( int $file_id ): void {
		$url = wp_get_attachment_url( $file_id );
		if ( false === $url ) {
			return;
		}
		$file = get_attached_file( $file_id );
		if ( false === $file ) {
			return;
		}
		$fname = pathinfo( $file, PATHINFO_FILENAME );
		$ajax  = $this->ajax_request_url;
		?>
		<input type="hidden" id="import-url" value="<?php echo esc_attr( $url ); ?>">
		<input type="hidden" id="import-file-id" value="<?php echo esc_attr( (string) $file_id ); ?>">
		<input type="hidden" id="import-file-name" value="<?php echo esc_attr( $fname ); ?>">
		<input type="hidden" id="import-ajax" value="<?php echo esc_attr( $ajax ); ?>">
		<div id="section-option">
			<h3><?php esc_html_e( 'Add Terms', 'wplug_bimeson_item' ); ?></h3>
			<p>
				<label>
					<input type="radio" id="do-nothing" checked>
					<?php esc_html_e( 'Do nothing', 'wplug_bimeson_item' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" id="add-tax">
					<?php esc_html_e( 'Add category groups themselves', 'wplug_bimeson_item' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" id="add-term">
					<?php esc_html_e( 'Add categories to the category group', 'wplug_bimeson_item' ); ?>
				</label>
			</p>
		</div>
		<p class="submit">
			<button type="button" id="btn-start-import" class="button"><?php esc_html_e( 'Start Import', 'wplug_bimeson_item' ); ?></button>
		</p>
		<div id="msg-response" style="margin-top:1rem;max-height:50vh;overflow:auto;"></div>
		<p id="msg-success" hidden><strong><?php esc_html_e( 'All done.', 'wplug_bimeson_item' ); ?></strong></p>
		<p id="msg-failure" hidden><strong><?php esc_html_e( 'Sorry, failed to read the file.', 'wplug_bimeson_item' ); ?></strong></p>
		<?php
	}
}
