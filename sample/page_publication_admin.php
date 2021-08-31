<?php
/**
 * Admin for the Template for Publication Static Pages
 *
 * @package Sample
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

/**
 * Sets up the template admin.
 */
function setup_template_admin() {
	\wplug\bimeson_item\add_meta_box( __( 'Publication List' ), 'page' );

	add_action(
		'save_post_page',
		function ( $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
			\wplug\bimeson_item\save_meta_box( $post_id );
		}
	);
}
