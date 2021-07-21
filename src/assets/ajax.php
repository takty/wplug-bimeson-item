<?php
/**
 * Ajax
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-07-21
 */

namespace wplug\bimeson_item;

class Ajax {

	static public function send_success( $data = null ) {
		wp_send_json_success( $data );
	}

	static public function send_error( $data = null ) {
		wp_send_json_error( $data );
	}

	private $_action;
	private $_response;
	private $_nonce;

	function __construct( string $action, $response, bool $public = false, ?string $nonce = null ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $action ) ) {
			wp_die( "Invalid string for $action." );
		}
		$this->_action   = $action;
		$this->_response = $response;
		$this->_nonce    = ( $nonce === null ) ? $action : $nonce;

		add_action( "wp_ajax_$action", [ $this, '_cb_ajax_action' ] );
		if ( $public ) {
			add_action( "wp_ajax_nopriv_$action", [ $this, '_cb_ajax_action' ] );
		}
	}

	public function get_url( array $query = [] ): string {
		$query['action'] = $this->_action;
		$query['nonce']  = wp_create_nonce( $this->_nonce );

		$url = admin_url( 'admin-ajax.php' );
		foreach ( $query as $key => $val ) {
			$url = add_query_arg( $key, $val, $url );
		}
		return $url;
	}

	public function _cb_ajax_action() {
		check_ajax_referer( $this->_nonce, 'nonce' );
		nocache_headers();
		$data = file_get_contents( 'php://input' );
		$data = json_decode( $data, true );
		call_user_func( $this->_response, $data );
	}

}
