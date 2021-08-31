<?php
/**
 * Bimeson (Post Type)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

namespace wplug\bimeson_item;

require_once __DIR__ . '/class-bimeson-importer.php';

/**
 * Initializes the post type.
 *
 * @param string $url_to Base URL.
 */
function initialize_post_type( string $url_to ) {
	$inst = _get_instance();
	register_post_type(
		$inst::PT,
		array(
			'label'         => __( 'Publication', 'wplug_bimeson_item' ),
			'labels'        => array(),
			'public'        => true,
			'show_ui'       => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-analytics',
			'has_archive'   => false,
			'rewrite'       => false,
			'supports'      => array( 'title', 'editor' ),
		)
	);

	if ( is_admin() ) {
		add_action( 'wp_loaded', '\wplug\bimeson_item\_cb_wp_loaded' );
		add_action( 'admin_menu', '\wplug\bimeson_item\_cb_admin_menu_post_type' );
		add_action( 'save_post_' . $inst::PT, '\wplug\bimeson_item\_cb_save_post_post_type' );

		if ( _is_the_post_type() ) {
			add_action(
				'admin_enqueue_scripts',
				function () use ( $url_to ) {
					wp_enqueue_style( 'wplug-bimeson-item-field', $url_to . '/assets/css/field.min.css', array(), '1.0' );
				}
			);
		}
		if ( class_exists( '\wplug\bimeson_item\Bimeson_Importer' ) ) {
			\wplug\bimeson_item\Bimeson_Importer::register( $url_to );
		}
	}
}


// -------------------------------------------------------------------------


/**
 * Callback function for 'wp_loaded' action.
 *
 * @access private
 */
function _cb_wp_loaded() {
	$inst = _get_instance();
	$cs   = array( 'cb', 'title' );
	$cs[] = array(
		'label' => __( 'Published date', 'wplug_bimeson_item' ),
		'name'  => $inst::IT_DATE,
		'width' => '10%',
		'value' => 'esc_html',
	);
	foreach ( get_root_slugs() as $taxonomy ) {
		$cs[] = array(
			'name'  => root_term_to_sub_tax( $taxonomy ),
			'width' => '14%',
		);
	}
	$cs[] = 'date';
	$cs[] = array(
		'label' => __( 'Import from', 'wplug_bimeson_item' ),
		'name'  => $inst::IT_IMPORT_FROM,
		'width' => '10%',
		'value' => 'esc_html',
	);
	set_admin_columns( $inst::PT, $cs );
	set_admin_columns_sortable( $inst::PT, array( $inst::IT_DATE, $inst::IT_IMPORT_FROM ) );
}

/**
 * Callback function for 'admin_menu' action.
 *
 * @access private
 */
function _cb_admin_menu_post_type() {
	$inst = _get_instance();
	if ( ! is_post_type( $inst::PT ) ) {
		return;
	}
	foreach ( $inst->additional_langs as $al ) {
		add_rich_editor_meta_box( "_post_content_$al", __( 'Content' ) . " [$al]", $inst::PT );
	}
	\add_meta_box( 'wplug_bimeson_item_mb', __( 'Publication data', 'wplug_bimeson_item' ), '\wplug\bimeson_item\_cb_output_html_post_type', $inst::PT, 'side', 'high' );
}

/**
 * Callback function for the meta box.
 *
 * @access private
 */
function _cb_output_html_post_type() {
	$inst = _get_instance();
	wp_nonce_field( 'wplug_bimeson_item', 'wplug_bimeson_item_nonce' );
	$post_id = get_the_ID();

	// phpcs:disable
	$date       = get_post_meta( $post_id, $inst::IT_DATE,       true );
	$doi        = get_post_meta( $post_id, $inst::IT_DOI,        true );
	$link_url   = get_post_meta( $post_id, $inst::IT_LINK_URL,   true );
	$link_title = get_post_meta( $post_id, $inst::IT_LINK_TITLE, true );
	// phpcs:enable

	output_input_row( __( 'Published date', 'wplug_bimeson_item' ), $inst::IT_DATE, $date );
	output_input_row( 'DOI', $inst::IT_DOI, $doi );
	output_input_row( __( 'Link URL', 'wplug_bimeson_item' ), $inst::IT_LINK_URL, $link_url );
	output_input_row( __( 'Link title', 'wplug_bimeson_item' ), $inst::IT_LINK_TITLE, $link_title );
}

/**
 * Callback function for 'save_post_{$post->post_type}' action.
 *
 * @access private
 *
 * @param int $post_id Post ID.
 */
function _cb_save_post_post_type( $post_id ) {
	$inst = _get_instance();
	if ( ! isset( $_POST['wplug_bimeson_item_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wplug_bimeson_item_nonce'] ), 'wplug_bimeson_item' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( $inst->additional_langs as $al ) {
		save_rich_editor_meta_box( $post_id, "_post_content_$al" );
	}
	$date = ( ! empty( $_POST[ $inst::IT_DATE ] ) ) ? normalize_date( wp_unslash( $_POST[ $inst::IT_DATE ] ) ) : '';  // phpcs:ignore
	if ( $date ) {
		$date_num = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
		update_post_meta( $post_id, $inst::IT_DATE_NUM, $date_num );
	}
	save_post_meta( $post_id, $inst::IT_DATE, '\\wplug\\bimeson_item\\normalize_date' );
	save_post_meta( $post_id, $inst::IT_DOI );
	save_post_meta( $post_id, $inst::IT_LINK_URL );
	save_post_meta( $post_id, $inst::IT_LINK_TITLE );
}


// -----------------------------------------------------------------------------


/**
 * Processes an array of items.
 * Called by the importer.
 *
 * @param array  $items     Items.
 * @param string $file_name The file name of a publication list.
 * @return array Messages.
 */
function process_items( array &$items, string $file_name ): array {
	$inst       = _get_instance();
	$roots_subs = get_root_slug_to_sub_slugs();
	$msgs       = array();

	foreach ( $items as $item ) {
		$body = ( ! empty( $item[ $inst::IT_BODY ] ) ) ? trim( $item[ $inst::IT_BODY ] ) : '';
		if ( empty( $body ) ) {
			continue;
		}
		$date       = ( ! empty( $item[ $inst::IT_DATE ] ) ) ? normalize_date( $item[ $inst::IT_DATE ] ) : '';
		$date_num   = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
		$doi        = ( ! empty( $item[ $inst::IT_DOI ] ) ) ? $item[ $inst::IT_DOI ] : '';
		$link_url   = ( ! empty( $item[ $inst::IT_LINK_URL ] ) ) ? $item[ $inst::IT_LINK_URL ] : '';
		$link_title = ( ! empty( $item[ $inst::IT_LINK_TITLE ] ) ) ? $item[ $inst::IT_LINK_TITLE ] : '';

		$a_bodies = array();
		foreach ( $inst->additional_langs as $al ) {
			$b = ( ! empty( $item[ $inst::IT_BODY . "_$al" ] ) ) ? $item[ $inst::IT_BODY . "_$al" ] : '';
			if ( ! empty( $b ) ) {
				$a_bodies[ $al ] = $b;
			}
		}

		$digest = make_digest( $body );
		$title  = make_title( $body, $date );

		$olds = get_posts(
			array(
				'post_type'  => $inst::PT,
				'meta_query' => array(  // phpcs:ignore
					array(
						'key'   => $inst::IT_DIGEST,
						'value' => $digest,
					),
				),
			)
		);

		$old = false;
		if ( ! empty( $olds ) ) {
			$old = $olds[0];
		}
		$args = array(
			'post_content' => $body,
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_type'    => $inst::PT,
		);
		if ( false !== $old ) {
			$args['ID'] = $old->ID;
		}
		$post_id = wp_insert_post( $args );
		if ( 0 === $post_id ) {
			continue;
		}
		// phpcs:disable
		add_post_meta( $post_id, $inst::IT_DATE,        $date );
		add_post_meta( $post_id, $inst::IT_DOI,         $doi );
		add_post_meta( $post_id, $inst::IT_LINK_URL,    $link_url );
		add_post_meta( $post_id, $inst::IT_LINK_TITLE,  $link_title );

		add_post_meta( $post_id, $inst::IT_DATE_NUM,    $date_num );
		add_post_meta( $post_id, $inst::IT_IMPORT_FROM, $file_name );
		add_post_meta( $post_id, $inst::IT_DIGEST,      $digest );
		// phpcs:enable

		foreach ( $a_bodies as $l => $cont ) {
			add_post_meta( $post_id, "_post_content_$l", wp_kses_post( $cont ) );
		}
		foreach ( $item as $key => $vals ) {
			if ( '_' === $key[0] ) {
				continue;
			}
			if ( ! isset( $roots_subs[ $key ] ) ) {
				continue;
			}
			if ( ! is_array( $vals ) ) {
				$vals = array( $vals );
			}
			$sub_tax = root_term_to_sub_tax( $key );
			$slugs   = array();
			foreach ( $vals as $v ) {
				if ( in_array( $v, $roots_subs[ $key ], true ) ) {
					$slugs[] = $v;
				}
			}
			if ( ! empty( $slugs ) ) {
				wp_add_object_terms( $post_id, $slugs, $sub_tax );
			}
		}
		$m      = false === $old ? __( 'New', 'wplug_bimeson_item' ) : __( 'Updated', 'wplug_bimeson_item' );
		$msgs[] = "<strong>$m</strong>" . ' ' . wp_kses_post( $body );
	}
	return $msgs;
}
