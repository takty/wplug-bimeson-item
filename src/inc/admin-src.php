<?php
/**
 * Bimeson (Admin)
 *
 * @author Takuto Yanagida
 * @version 2021-07-15
 */

namespace wplug\bimeson_post;

require_once __DIR__ . '/importer.php';
require_once __DIR__ . '/../assets/field.php';

function initialize_admin( $url_to ) {
	$inst = _get_instance();

	add_action( 'wp_loaded',              '\wplug\bimeson_post\_cb_wp_loaded' );
	add_action( 'admin_menu',             '\wplug\bimeson_post\_cb_admin_menu_admin' );
	add_filter( 'the_content',            '\wplug\bimeson_post\_cb_the_content' );
	add_filter( 'wp_insert_post_data',    '\wplug\bimeson_post\_cb_wp_insert_post_data', 10, 2 );
	add_action( 'save_post_' . $inst::PT, '\wplug\bimeson_post\_cb_save_post' );

	wp_register_style( 'wplug-bimeson-post-field', $url_to . '/assets/css/field.min.css' );

	if ( class_exists( '\wplug\bimeson_post\Bimeson_Importer' ) ) {
		\wplug\bimeson_post\Bimeson_Importer::register( $url_to );
	}
}


// -------------------------------------------------------------------------


function _cb_the_content( $content ) {
	$inst = _get_instance();
	$post = get_post();
	if ( $post->post_type === $inst::PT ) {
		if ( _is_empty( $content ) ) {
			foreach ( $inst->additional_langs as $al ) {
				$key = "_post_content_$al";
				$c = get_post_meta( $post->ID, $key, true );
				if ( ! _is_empty( $c ) ) return $c;
			}
		}
	}
	return $content;
}

function _cb_wp_loaded() {
	$inst = _get_instance();
	$cs = [ 'cb', 'title' ];
	$cs[] = [ 'label' => '公開日付', 'name' => $inst::IT_DATE, 'width' => '10%', 'value' => 'esc_html' ];
	foreach ( get_root_slugs() as $taxonomy ) {
		$cs[] = [ 'name' => root_term_to_sub_tax( $taxonomy ), 'width' => '14%' ];
	}
	$cs[] = 'date';
	$cs[] = [ 'label' => 'インポート元', 'name' => $inst::IT_IMPORT_FROM, 'width' => '10%', 'value' => 'esc_html' ];
	set_admin_columns( 'bimeson', $cs, [ $inst::IT_DATE, $inst::IT_IMPORT_FROM ] );
}

function _cb_admin_menu_admin() {
	$inst = _get_instance();
	if ( ! is_post_type( $inst::PT ) ) return;

	foreach ( $inst->additional_langs as $al ) {
		add_rich_editor_meta_box( "_post_content_$al", "本文 [$al]", 'bimeson' );
	}
	add_meta_box( 'bimeson_mb', '業績情報', '\wplug\bimeson_post\_cb_output_html_admin', 'bimeson', 'side', 'high' );
}

function _cb_output_html_admin() {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_post', 'bimeson_post_nonce' );
	$post_id = get_the_ID();

	$date       = get_post_meta( $post_id, $inst::IT_DATE,       true );
	$doi        = get_post_meta( $post_id, $inst::IT_DOI,        true );
	$link_url   = get_post_meta( $post_id, $inst::IT_LINK_URL,   true );
	$link_title = get_post_meta( $post_id, $inst::IT_LINK_TITLE, true );

	output_input_row( '公開日付', $inst::IT_DATE, $date );
	output_input_row( 'DOI', $inst::IT_DOI, $doi );
	output_input_row( 'リンクURL', $inst::IT_LINK_URL, $link_url );
	output_input_row( 'リンク表題', $inst::IT_LINK_TITLE, $link_title );
}

function _cb_wp_insert_post_data( $data, $postarr ) {
	$inst = _get_instance();
	if ( $postarr['post_type'] === $inst::PT ) {
		$inst->_is_current_post_content_empty = _is_empty( $data['post_content'] );
		if ( $inst->_is_current_post_content_empty ) $data['post_content'] = '';
	}
	return $data;
}

function _cb_save_post( $post_id ) {
	$inst = _get_instance();
	if ( ! isset( $_POST['bimeson_post_nonce'] ) || ! wp_verify_nonce( $_POST['bimeson_post_nonce'], 'bimeson_post' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	foreach ( $inst->additional_langs as $al ) {
		save_rich_editor_meta_box( $post_id, "_post_content_$al" );
	}
	$date = ( ! empty( $_POST[ $inst::IT_DATE ] ) ) ? normalize_date( $_POST[ $inst::IT_DATE ] ) : '';
	if ( $date ) {
		$date_num = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
		update_post_meta( $post_id, $inst::IT_DATE_NUM, $date_num );
	}
	save_post_meta( $post_id, $inst::IT_DATE, '\\wplug\\bimeson_post\\normalize_date' );
	save_post_meta( $post_id, $inst::IT_DOI );
	save_post_meta( $post_id, $inst::IT_LINK_URL );
	save_post_meta( $post_id, $inst::IT_LINK_TITLE );

	if ( $inst->lang_tax !== false ) $inst->_set_post_lang_terms( $post_id );
}

function _set_post_lang_terms( $post_id ) {
	$inst = _get_instance();
	$post_langs = [];
	if ( ! $inst->_is_current_post_content_empty ) {
		$post_langs[] = $inst->default_lang;
	}
	foreach ( $inst->additional_langs as $al ) {
		$key = "_post_content_$al";
		$is_empty = isset( $_POST[ $key ] ) ? _is_empty( $_POST[ $key ] ) : true;
		if ( ! $is_empty ) $post_langs[] = $al;
	}
	wp_set_object_terms( $post_id, $post_langs, $inst->lang_tax );
}

function _is_empty( $content ) {
	return empty( trim( wp_strip_all_tags( $content, true ) ) );
}


// -----------------------------------------------------------------------------


function process_items( $items, $file_name ) {  // Called by importer
	$inst       = _get_instance();
	$roots_subs = get_root_slug_to_sub_slugs();

	foreach ( $items as $item ) {
		$body = ( ! empty( $item[$inst::IT_BODY] ) ) ? trim( $item[$inst::IT_BODY] ) : '';
		if ( empty( $body ) ) continue;

		$date       = ( ! empty( $item[$inst::IT_DATE] ) )       ? normalize_date( $item[$inst::IT_DATE] ) : '';
		$date_num   = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
		$doi        = ( ! empty( $item[$inst::IT_DOI] ) )        ? $item[$inst::IT_DOI] : '';
		$link_url   = ( ! empty( $item[$inst::IT_LINK_URL] ) )   ? $item[$inst::IT_LINK_URL] : '';
		$link_title = ( ! empty( $item[$inst::IT_LINK_TITLE] ) ) ? $item[$inst::IT_LINK_TITLE] : '';

		$a_bodies = [];
		foreach ( $inst->additional_langs as $al ) {
			$b = ( ! empty( $item[ $inst::IT_BODY . "_$al" ] ) ) ? $item[ $inst::IT_BODY . "_$al" ] : '';
			if ( ! empty( $b ) ) $a_bodies[ $al ] = $b;
		}

		$digest = make_digest( $body );
		$title  = make_title( $body, $date );

		$olds = get_posts( [
			'post_type' => 'bimeson',
			'meta_query' => [ [
				'key'   => $inst::IT_DIGEST,
				'value' => $digest,
			] ],
		] );
		$old = false;
		if ( ! empty( $olds ) ) $old = $olds[0];
		$args = [
			'post_content' => $body,
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_type'    => 'bimeson',
		];
		if ( $old !== false ) $args['ID'] = $old->ID;
		$post_id = wp_insert_post( $args );
		if ( $post_id === 0 ) continue;

		add_post_meta( $post_id, $inst::IT_DATE,        $date );
		add_post_meta( $post_id, $inst::IT_DOI,         $doi );
		add_post_meta( $post_id, $inst::IT_LINK_URL,    $link_url );
		add_post_meta( $post_id, $inst::IT_LINK_TITLE,  $link_title );

		add_post_meta( $post_id, $inst::IT_DATE_NUM,    $date_num );
		add_post_meta( $post_id, $inst::IT_IMPORT_FROM, $file_name );
		add_post_meta( $post_id, $inst::IT_DIGEST,      $digest );

		foreach ( $a_bodies as $l => $cont ) {
			add_post_meta( $post_id, "_post_content_$l", wp_kses_post( $cont ) );
		}
		foreach ( $item as $key => $vals ) {
			if ( $key[0] === '_' ) continue;
			if ( ! isset( $roots_subs[ $key ] ) ) continue;
			if ( ! is_array( $vals ) ) $vals = [ $vals ];
			$sub_tax = root_term_to_sub_tax( $key );
			$slugs = [];
			foreach ( $vals as $v ) {
				if ( in_array( $v, $roots_subs[ $key ], true ) ) $slugs[] = $v;
			}
			if ( ! empty( $slugs ) ) wp_add_object_terms( $post_id, $slugs, $sub_tax );
		}
		echo '<p>' . ( $old === false ? 'New ' : 'Updated ' );
		echo wp_kses_post( $body ) . '</p>';
	}
}
