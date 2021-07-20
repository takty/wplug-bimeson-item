<?php
/**
 *
 * Admin for the Template for Publication Static Pages
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 *
 */


function setup_template_admin() {
	\wplug\bimeson_item\add_meta_box( __( 'Publication list' ), 'page' );

	add_action( 'save_post_page', function ( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		\wplug\bimeson_item\save_meta_box( $post_id );
	} );
}
