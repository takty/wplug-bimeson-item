<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-31
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

/**
 * Initializes bimeson.
 *
 * @param array $args {
 *     (Optional) Array of arguments.
 *
 *     @type int      'heading_level'     First heading level of publication lists. Default 3.
 *     @type string   'year_format'       Year heading format. Default null.
 *     @type callable 'term_name_getter'  Callable for getting term names. Default null.
 *     @type string   'year_select_label' Label of year select markup. Default __( 'Select Year' ).
 *     @type string   'taxonomy'          Root taxonomy slug.
 *     @type string   'sub_tax_base'      Slug base of sub taxonomies.
 *     @type string   'sub_tax_cls_base'  Class base of sub taxonomies.
 *     @type string   'sub_tax_qvar_base' Query variable name base of sub taxonomies.
 *     @type string   'year_cls_base'     Class base of year.
 *     @type string   'year_qvar'         Query variable name of year.
 * }
 */
function initialize( array $args = array() ) {
	$inst = _get_instance();

	_set_key( $args['key'] ?? '_bimeson' );

	$url_to = untrailingslashit( $args['url_to'] ?? get_file_uri( __DIR__ ) );
	$lang   = $args['lang'] ?? '';

	// phpcs:disable
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
	// phpcs:enable

	$inst->additional_langs = $args['additional_langs'] ?? array();

	initialize_post_type( $url_to );  // Do before initializing taxonomies.
	initialize_taxonomy();
	initialize_filter();

	_register_script( $url_to );
	if ( ! is_admin() ) {
		register_shortcode( $lang );
	}
}

/**
 * Registers the scripts and styles.
 *
 * @access private
 *
 * @param string $url_to Base URL.
 */
function _register_script( string $url_to ) {
	if ( is_admin() ) {
		if ( ! _is_the_post_type() ) {
			add_action(
				'admin_enqueue_scripts',
				function () use ( $url_to ) {
					wp_enqueue_style( 'bimeson_item_template_admin', abs_url( $url_to, './assets/css/template-admin.min.css' ), array(), '1.0' );
					wp_enqueue_script( 'bimeson_item_template_admin', abs_url( $url_to, './assets/js/template-admin.min.js' ), array(), '1.0', false );
				}
			);
		}
	} else {
		add_action(
			'wp_enqueue_scripts',
			function () use ( $url_to ) {
				wp_register_style( 'bimeson_item_filter', abs_url( $url_to, './assets/css/filter.min.css' ), array(), '1.0' );
				wp_register_script( 'bimeson_item_filter', abs_url( $url_to, './assets/js/filter.min.js' ), array(), '1.0', false );
			}
		);
	}
}

/**
 * Check whether the post type is bimeson.
 *
 * @access private
 *
 * @return bool True if the post type is bimeson.
 */
function _is_the_post_type() {
	$inst = _get_instance();
	global $pagenow;
	return in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && is_post_type( $inst::PT );
}


// -------------------------------------------------------------------------


/**
 * Gets the root taxonomy slug.
 */
function get_taxonomy() {
	$inst = _get_instance();
	return $inst->root_tax;
}

/**
 * Gets the sub taxonomy slugs.
 */
function get_sub_taxonomies() {
	$inst = _get_instance();
	return $inst->sub_taxes;
}

/**
 * Adds the meta box.
 *
 * @param string  $title  Title of the meta box.
 * @param ?string $screen (Optional) The screen or screens on which to show the box.
 */
function add_meta_box( string $title, ?string $screen = null ) {
	add_meta_box_template_admin( $title, $screen );
}

/**
 * Stores the data of the meta box.
 *
 * @param int $post_id Post ID.
 */
function save_meta_box( int $post_id ) {
	save_meta_box_template_admin( $post_id );
}


// -------------------------------------------------------------------------


/**
 * Display the filter.
 *
 * @param ?int   $post_id Post ID.
 * @param string $lang    Language.
 * @param string $before  Content to prepend to the output.
 * @param string $after   Content to append to the output.
 * @param string $for     Attribute of 'for'.
 */
function the_filter( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-filter"%s>', string $after = '</div>', string $for = 'bml' ) {
	$post = get_post( $post_id );
	$d    = _get_data( $post->ID, $lang );

	if ( ! $d || ! $d['show_filter'] ) {
		return;
	}
	echo_the_filter( $d['filter_state'], $d['years_exist'], $before, $after, $for );
}

/**
 * Display the list.
 *
 * @param ?int   $post_id Post ID.
 * @param string $lang    Language.
 * @param string $before  Content to prepend to the output.
 * @param string $after   Content to append to the output.
 * @param string $id      Attribute of 'id'.
 */
function the_list( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ) {
	$post = get_post( $post_id );
	$d    = _get_data( $post->ID, $lang );

	if ( ! $d ) {
		return;
	}
	echo_the_list( $d, $lang, $before, $after, $id );
}

/**
 * Retrieves the data
 *
 * @access private
 *
 * @param int    $post_id Post ID.
 * @param string $lang    Language.
 * @return array Data.
 */
function _get_data( int $post_id, string $lang ): array {
	$inst = _get_instance();
	if ( isset( $inst->cache[ "$post_id$lang" ] ) ) {
		return $inst->cache[ "$post_id$lang" ];
	}
	$d = get_template_admin_config( $post_id );

	// Bimeson Item.
	$items = get_filtered_items( $lang, (string) $d['year_bgn'], (string) $d['year_end'], $d['filter_state'] );

	list( $items, $years_exist ) = retrieve_items( $items, $d['count'], $d['sort_by_date_first'], $d['dup_multi_cat'], $d['filter_state'] );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;

	$inst->cache[ "$post_id$lang" ] = $d;
	return $d;
}


// -----------------------------------------------------------------------------


/**
 * Retrieves filtered items.
 *
 * @param string  $lang         Language.
 * @param ?string $date_bgn     Date from.
 * @param ?string $date_end     Date to.
 * @param ?array  $filter_state Filter states.
 * @return array Items.
 */
function get_filtered_items( string $lang, ?string $date_bgn, ?string $date_end, ?array $filter_state ) {
	$inst = _get_instance();
	if ( isset( $filter_state[ $inst::KEY_VISIBLE ] ) ) {
		unset( $filter_state[ $inst::KEY_VISIBLE ] );
	}
	$tq = array();
	$mq = array();

	foreach ( $filter_state as $rs => $slugs ) {
		$sub_tax        = root_term_to_sub_tax( $rs );
		$slugs          = implode( ',', $slugs );
		$tq[ $sub_tax ] = array(
			'taxonomy' => $sub_tax,
			'field'    => 'slug',
			'terms'    => $slugs,
		);
	}
	$by_date = ( ! empty( $date_bgn ) || ! empty( $date_end ) );
	if ( $by_date ) {
		$date_b = (int) str_pad( empty( $date_bgn ) ? '' : $date_bgn, 8, '0', STR_PAD_RIGHT );
		$date_e = (int) str_pad( empty( $date_end ) ? '' : $date_end, 8, '9', STR_PAD_RIGHT );

		$mq['date_num'] = array(
			'key'     => $inst::IT_DATE_NUM,
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
			'value'   => array( $date_b, $date_e ),
		);
	} else {
		$mq['date_num'] = array(
			'key'  => $inst::IT_DATE_NUM,
			'type' => 'NUMERIC',
		);
	}
	$ps = get_posts(
		array(
			'post_type'      => $inst::PT,
			'posts_per_page' => -1,
			'tax_query'      => $tq,  // phpcs:ignore
			'meta_query'     => $mq,  // phpcs:ignore
			'orderby'        => array(
				'date_num' => 'desc',
				'date'     => 'desc',
			),
		)
	);

	$ret = array();
	foreach ( $ps as $idx => $p ) {
		$it = _convert_post_to_item( $p, $idx );
		if ( empty( $it[ $inst::IT_BODY . "_$lang" ] ) && empty( $it[ $inst::IT_BODY ] ) ) {
			continue;
		}
		$ret[] = $it;
	}
	return $ret;
}

/**
 * Converts a post to an item.
 *
 * @access private
 *
 * @param \WP_Post $p   A post.
 * @param int      $idx The index of the post.
 * @return array The item.
 */
function _convert_post_to_item( \WP_Post $p, int $idx ): array {
	$inst = _get_instance();

	// phpcs:disable
	$date       = get_post_meta( $p->ID, $inst::IT_DATE,       true );
	$doi        = get_post_meta( $p->ID, $inst::IT_DOI,        true );
	$link_url   = get_post_meta( $p->ID, $inst::IT_LINK_URL,   true );
	$link_title = get_post_meta( $p->ID, $inst::IT_LINK_TITLE, true );
	$date_num   = get_post_meta( $p->ID, $inst::IT_DATE_NUM,   true );
	// phpcs:enable

	$it = array();
	if ( ! empty( $date ) ) {
		$it[ $inst::IT_DATE ] = $date;
	}
	if ( ! empty( $doi ) ) {
		$it[ $inst::IT_DOI ] = $doi;
	}
	if ( ! empty( $link_url ) ) {
		$it[ $inst::IT_LINK_URL ] = $link_url;
	}
	if ( ! empty( $link_title ) ) {
		$it[ $inst::IT_LINK_TITLE ] = $link_title;
	}
	if ( ! empty( $date_num ) ) {
		$it[ $inst::IT_DATE_NUM ] = $date_num;
	}
	$body = _make_content( $p->post_content );
	if ( ! empty( $body ) ) {
		$it[ $inst::IT_BODY ] = $body;
	}
	foreach ( $inst->additional_langs as $al ) {
		$key  = "_post_content_$al";
		$c    = get_post_meta( $p->ID, $key, true );
		$body = _make_content( $c );
		if ( ! empty( $body ) ) {
			$it[ $inst::IT_BODY . "_$al" ] = $body;
		}
	}
	foreach ( get_sub_taxonomies() as $rs => $sub_tax ) {
		$ts = get_the_terms( $p->ID, $sub_tax );
		if ( ! is_array( $ts ) ) {
			$it[ $rs ] = array();
		} else {
			$it[ $rs ] = array_map(
				function ( $t ) {
					return $t->slug;
				},
				$ts
			);
		}
	}
	$it[ $inst::IT_INDEX ]    = $idx;
	$it[ $inst::IT_EDIT_URL ] = _make_edit_url( $p );
	return $it;
}

/**
 * Makes a content.
 *
 * @access private
 *
 * @param string $c A content.
 * @return string Filtered content.
 */
function _make_content( string $c ): string {
	$c = apply_filters( 'the_content', $c );  // Shortcodes are expanded here.
	$c = str_replace( ']]>', ']]&gt;', $c );
	return $c;
}

/**
 * Makes an edit URL.
 *
 * @access private
 *
 * @param \WP_Post $p A post.
 * @return string Edit URL.
 */
function _make_edit_url( \WP_Post $p ): string {
	if ( is_user_logged_in() && current_user_can( 'edit_post', $p->ID ) ) {
		return admin_url( "post.php?post={$p->ID}&action=edit" );
	}
	return '';
}
