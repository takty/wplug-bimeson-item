<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-15
 */

namespace wplug\bimeson_item;

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/assets/field.php';
require_once __DIR__ . '/inc/inst.php';
require_once __DIR__ . '/inc/post-type.php';
require_once __DIR__ . '/inc/taxonomy.php';
require_once __DIR__ . '/inc/retriever.php';
require_once __DIR__ . '/inc/filter.php';
require_once __DIR__ . '/inc/list.php';
require_once __DIR__ . '/inc/template-admin.php';
require_once __DIR__ . '/inc/shortcode.php';

function initialize( array $args = [] ) {
	$inst = _get_instance();

	_set_key( $args['key'] ?? '_bimeson' );

	$url_to = untrailingslashit( $args['url_to'] ?? get_file_uri( __DIR__ ) );
	$lang   = $args['lang']     ?? '';

	$inst->head_level        = $args['heading_level']     ?? 3;
	$inst->year_format       = $args['year_format']       ?? null;
	$inst->term_name_getter  = $args['term_name_getter']  ?? null;
	$inst->year_select_label = $args['year_select_label'] ?? __( 'Select Year' );

	$inst->root_tax          = $args['taxonomy']          ?? $inst::DEFAULT_TAXONOMY;
	$inst->sub_tax_base      = $args['sub_tax_base']      ?? $inst::DEFAULT_SUB_TAX_BASE;
	$inst->sub_tax_cls_base  = $args['sub_tax_cls_base']  ?? $inst::DEFAULT_SUB_TAX_CLS_BASE;
	$inst->sub_tax_qvar_base = $args['sub_tax_qvar_base'] ?? $inst::DEFAULT_SUB_TAX_QVAR_BASE;

	$inst->year_cls_base = $args['year_cls_base'] ?? $inst::DEFAULT_YEAR_CLS_BASE;
	$inst->year_qvar     = $args['year_qvar']     ?? $inst::DEFAULT_YEAR_QVAR;

	$inst->additional_langs = $args['additional_langs'] ?? [];

	initialize_post_type( $url_to );  // Do before initializing taxonomies
	initialize_taxonomy();
	initialize_filter();

	_register_script( $url_to );
	if ( ! is_admin() ) register_shortcode( $lang );
}

function _register_script( string $url_to ) {
	if ( is_admin() ) {
		if ( ! _is_the_post_type() ) {
			add_action( 'admin_enqueue_scripts', function () use ( $url_to ) {
				wp_enqueue_style(  'bimeson_item_template_admin', $url_to . '/assets/css/template-admin.min.css' );
				wp_enqueue_script( 'bimeson_item_template_admin', $url_to . '/assets/js/template-admin.min.js' );
			} );
		}
	} else {
		add_action( 'wp_enqueue_scripts', function () use ( $url_to ) {
			wp_register_style(  'bimeson_item_filter', $url_to . '/assets/css/filter.min.css' );
			wp_register_script( 'bimeson_item_filter', $url_to . '/assets/js/filter.min.js' );
		} );
	}
}

function _is_the_post_type() {
	$inst = _get_instance();
	global $pagenow;
	return in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) && is_post_type( $inst::PT );
}


// -------------------------------------------------------------------------


function get_taxonomy() {
	$inst = _get_instance();
	return $inst->root_tax;
}

function get_sub_taxonomies() {
	$inst = _get_instance();
	return $inst->sub_taxes;
}

function add_meta_box( string $label, string $screen ) {
	add_meta_box_template_admin( $label, $screen );
}

function save_meta_box( int $post_id ) {
	save_meta_box_template_admin( $post_id );
}


// -------------------------------------------------------------------------


function the_filter( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-filter"%s>', string $after = '</div>', string $for = 'bml' ) {
	$post = get_post( $post_id );
	$d    = _get_data( $post->ID, $lang );

	if ( ! $d || ! $d['show_filter'] ) return;
	echo_the_filter( $d['filter_state'], $d['years_exist'], $before, $after, $for );
}

function the_list( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ) {
	$post = get_post( $post_id );
	$d    = _get_data( $post->ID, $lang );

	if ( ! $d ) return;
	echo_the_list( $d, $lang, $before, $after, $id );
}

function _get_data( int $post_id, string $lang ): array {
	$inst = _get_instance();
	if ( isset( $inst->cache["$post_id$lang"] ) ) return $inst->cache["$post_id$lang"];

	$d = get_template_admin_config( $post_id );

	// Bimeson Item
	$items = get_filtered_items( $lang, (string) $d['year_bgn'], (string) $d['year_end'], $d['filter_state'] );
	[ $items, $years_exist ] = retrieve_items( $items, $d['count'], $d['sort_by_date_first'], $d['dup_multi_cat'], $d['filter_state'] );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;

	$inst->cache["$post_id$lang"] = $d;
	return $d;
}


// -----------------------------------------------------------------------------


function get_filtered_items( string $lang, ?string $date_bgn, ?string $date_end, ?array $filter_state ) {
	$inst = _get_instance();
	if ( isset( $filter_state[ $inst::KEY_VISIBLE ] ) ) unset( $filter_state[ $inst::KEY_VISIBLE ] );

	$tq = [];
	$mq = [];

	foreach ( $filter_state as $rs => $slugs ) {
		$sub_tax        = root_term_to_sub_tax( $rs );
		$slugs          = implode( ',', $slugs );
		$tq[ $sub_tax ] = [ 'taxonomy' => $sub_tax, 'field' => 'slug', 'terms' => $slugs ];
	}
	$by_date = ( ! empty( $date_bgn ) || ! empty( $date_end ) );
	if ( $by_date ) {
		$date_b  = (int) str_pad( empty( $date_bgn ) ? '' : $date_bgn, 8, '0', STR_PAD_RIGHT );
		$date_e  = (int) str_pad( empty( $date_end ) ? '' : $date_end, 8, '9', STR_PAD_RIGHT );

		$mq['date_num'] = [
			'key'     => $inst::IT_DATE_NUM,
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
			'value'   => [ $date_b, $date_e ],
		];
	} else {
		$mq['date_num'] = [
			'key'  => $inst::IT_DATE_NUM,
			'type' => 'NUMERIC',
		];
	}
	$ps = get_posts( [
		'post_type'      => $inst::PT,
		'posts_per_page' => -1,
		'tax_query'      => $tq,
		'meta_query'     => $mq,
		'orderby'        => [ 'date_num' => 'desc', 'date' => 'desc' ],
	] );

	$ret = [];
	foreach ( $ps as $idx => $p ) {
		$it = _convert_post_to_item( $p, $idx );
		if ( empty( $it[ $inst::IT_BODY . "_$lang" ] ) && empty( $it[ $inst::IT_BODY ] ) ) continue;
		$ret[] = $it;
	}
	return $ret;
}

function _convert_post_to_item( \WP_Post $p, int $idx ): array {
	$inst = _get_instance();

	$date       = get_post_meta( $p->ID, $inst::IT_DATE,       true );
	$doi        = get_post_meta( $p->ID, $inst::IT_DOI,        true );
	$link_url   = get_post_meta( $p->ID, $inst::IT_LINK_URL,   true );
	$link_title = get_post_meta( $p->ID, $inst::IT_LINK_TITLE, true );
	$date_num   = get_post_meta( $p->ID, $inst::IT_DATE_NUM,   true );

	$it = [];
	if ( ! empty( $date ) )        $it[ $inst::IT_DATE ]       = $date;
	if ( ! empty( $doi ) )         $it[ $inst::IT_DOI ]        = $doi;
	if ( ! empty( $link_url ) )    $it[ $inst::IT_LINK_URL ]   = $link_url;
	if ( ! empty( $link_title ) )  $it[ $inst::IT_LINK_TITLE ] = $link_title;
	if ( ! empty( $date_num ) )    $it[ $inst::IT_DATE_NUM ]   = $date_num;

	$body = _make_content( $p->post_content );
	if ( ! empty( $body ) )  $it[ $inst::IT_BODY ] = $body;

	foreach ( $inst->additional_langs as $al ) {
		$key = "_post_content_$al";
		$c = get_post_meta( $p->ID, $key, true );
		$body = _make_content( $c );
		if ( ! empty( $body ) )  $it[ $inst::IT_BODY . "_$al" ] = $body;
	}
	foreach ( get_sub_taxonomies() as $rs => $sub_tax ) {
		$ts = get_the_terms( $p->ID, $sub_tax );
		if ( ! is_array( $ts ) ) {
			$it[ $rs ] = [];
		} else {
			$it[ $rs ] = array_map( function ( $t ) { return $t->slug; }, $ts );
		}
	}
	$it[ $inst::IT_INDEX ]    = $idx;
	$it[ $inst::IT_EDIT_URL ] = _make_edit_url( $p );
	return $it;
}

function _make_content( $c ) {
	$c = apply_filters( 'the_content', $c );  // Shortcodes are expanded here.
	$c = str_replace( ']]>', ']]&gt;', $c );
	return $c;
}

function _make_edit_url( $p ) {
	if ( is_user_logged_in() && current_user_can( 'edit_post', $p->ID ) ) {
		return admin_url( "post.php?post={$p->ID}&action=edit" );
	}
	return '';
}
