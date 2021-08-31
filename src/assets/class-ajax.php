<?php
/**
 * Ajax
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

namespace wplug\bimeson_item;

/**
 * Ajax.
 */
class Ajax {

	/**
	 * Sends a message of success.
	 *
	 * @param mixed $data Data to be sent.
	 */
	public static function send_success( $data = null ) {
		wp_send_json_success( $data );
	}

	/**
	 * Sends a message of error.
	 *
	 * @param mixed $data Data to be sent.
	 */
	public static function send_error( $data = null ) {
		wp_send_json_error( $data );
	}

	/**
	 * Action.
	 *
	 * @var 1.0
	 */
	private $action;

	/**
	 * Response.
	 *
	 * @var 1.0
	 */
	private $response;

	/**
	 * Nonce
	 *
	 * @var 1.0
	 */
	private $nonce;

	/**
	 * Constructor.
	 *
	 * @param string   $action   Ajax action.
	 * @param callable $response Function called when receive message.
	 * @param bool     $public   Whether this ajax is public.
	 * @param ?string  $nonce    Nonce.
	 */
	public function __construct( string $action, $response, bool $public = false, ?string $nonce = null ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $action ) ) {
			wp_die( 'Invalid string for ' . esc_html( $action ) . '.' );
		}
		$this->action   = $action;
		$this->response = $response;
		$this->nonce    = ( null === $nonce ) ? $action : $nonce;

		add_action( "wp_ajax_$action", array( $this, 'cb_ajax_action' ) );
		if ( $public ) {
			add_action( "wp_ajax_nopriv_$action", array( $this, 'cb_ajax_action' ) );
		}
	}

	/**
	 * Gets the URL of this ajax.
	 *
	 * @param array $query Query arguments.
	 * @return string URL.
	 */
	public function get_url( array $query = array() ): string {
		$query['action'] = $this->action;
		$query['nonce']  = wp_create_nonce( $this->nonce );

		$url = admin_url( 'admin-ajax.php' );
		foreach ( $query as $key => $val ) {
			$url = add_query_arg( $key, $val, $url );
		}
		return $url;
	}

	/**
	 * Callback function for 'wp_ajax_nopriv_{$_REQUEST[‘action’]}' action.
	 */
	public function cb_ajax_action() {
		check_ajax_referer( $this->nonce, 'nonce' );
		nocache_headers();
		$data = file_get_contents( 'php://input' );
		$data = json_decode( $data, true );
		call_user_func( $this->response, $data );
	}

}
