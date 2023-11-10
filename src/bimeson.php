<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2023-09-08
 */

namespace wplug\bimeson_item;

require_once __DIR__ . '/assets/admin-current-post.php';
require_once __DIR__ . '/assets/asset-url.php';
require_once __DIR__ . '/inc/filter.php';
require_once __DIR__ . '/inc/util.php';
require_once __DIR__ . '/inc/inst.php';
require_once __DIR__ . '/inc/list.php';
require_once __DIR__ . '/inc/post-type.php';
require_once __DIR__ . '/inc/retriever.php';
require_once __DIR__ . '/inc/shortcode.php';
require_once __DIR__ . '/inc/taxonomy.php';
require_once __DIR__ . '/inc/template-admin.php';

/** phpcs:ignore
 * Initializes bimeson.
 *
 * phpcs:ignore
 * @param array{
 *     key?                    : string,
 *     url_to?                 : string,
 *     lang?                   : string,
 *     heading_level?          : int,
 *     year_format?            : string|null,
 *     term_name_getter?       : callable|null,
 *     year_select_label?      : string,
 *     uncategorized_label?    : string,
 *     taxonomy?               : string,
 *     sub_tax_base?           : string,
 *     sub_tax_cls_base?       : string,
 *     sub_tax_qvar_base?      : string,
 *     year_cls_base?          : string,
 *     year_qvar?              : string,
 *     do_show_relation_switch?: bool,
 *     additional_langs?       : string[],
 * } $args (Optional) Array of arguments.
 * $args {
 *     (Optional) Array of arguments.
 *
 *     @type string   'key'                     Post meta key for config data. Default '_bimeson'.
 *     @type string   'url_to'                  URL to the plugin directory.
 *     @type string   'lang'                    Language. Default ''.
 *     @type int      'heading_level'           First heading level of publication lists. Default 3.
 *     @type string   'year_format'             Year heading format. Default null.
 *     @type callable 'term_name_getter'        Callable for getting term names. Default null.
 *     @type string   'year_select_label'       Label of year select markup. Default __( 'Select Year' ).
 *     @type string   'uncategorized_label'     Label of year select markup. Default __( 'Uncategorized' ).
 *     @type string   'taxonomy'                Root taxonomy slug.
 *     @type string   'sub_tax_base'            Slug base of sub taxonomies.
 *     @type string   'sub_tax_cls_base'        Class base of sub taxonomies.
 *     @type string   'sub_tax_qvar_base'       Query variable name base of sub taxonomies.
 *     @type string   'year_cls_base'           Class base of year.
 *     @type string   'year_qvar'               Query variable name of year.
 *     @type bool     'do_show_relation_switch' Whether to show relation switches.
 *     @type string[] 'additional_langs'        Additional language slugs.
 * }
 */
function initialize( array $args = array() ): void {
	$inst = _get_instance();

	_set_key( $args['key'] ?? '_bimeson' );

	$url_to = untrailingslashit( $args['url_to'] ?? \wplug\get_file_uri( __DIR__ ) );
	$lang   = $args['lang'] ?? '';

	// phpcs:disable
	$inst->head_level        = $args['heading_level']       ?? 3;  // @phpstan-ignore-line
	$inst->year_format       = $args['year_format']         ?? null;  // @phpstan-ignore-line
	$inst->term_name_getter  = $args['term_name_getter']    ?? null;  // @phpstan-ignore-line
	$inst->year_select_label = $args['year_select_label']   ?? __( 'Select Year' );  // @phpstan-ignore-line
	$inst->uncat_label       = $args['uncategorized_label'] ?? __( 'Uncategorized' );  // @phpstan-ignore-line

	$inst->root_tax          = $args['taxonomy']          ?? $inst::DEFAULT_TAXONOMY;  // @phpstan-ignore-line
	$inst->sub_tax_base      = $args['sub_tax_base']      ?? $inst::DEFAULT_SUB_TAX_BASE;  // @phpstan-ignore-line
	$inst->sub_tax_cls_base  = $args['sub_tax_cls_base']  ?? $inst::DEFAULT_SUB_TAX_CLS_BASE;  // @phpstan-ignore-line
	$inst->sub_tax_qvar_base = $args['sub_tax_qvar_base'] ?? $inst::DEFAULT_SUB_TAX_QVAR_BASE;  // @phpstan-ignore-line

	$inst->year_cls_base = $args['year_cls_base'] ?? $inst::DEFAULT_YEAR_CLS_BASE;  // @phpstan-ignore-line
	$inst->year_qvar     = $args['year_qvar']     ?? $inst::DEFAULT_YEAR_QVAR;  // @phpstan-ignore-line

	$inst->do_show_relation_switch = $args['do_show_relation_switch'] ?? false;  // @phpstan-ignore-line
	$inst->additional_langs        = $args['additional_langs'] ?? array();  // @phpstan-ignore-line
	// phpcs:enable

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
function _register_script( string $url_to ): void {
	if ( is_admin() ) {
		if ( ! _is_the_post_type() ) {
			add_action(
				'admin_enqueue_scripts',
				function () use ( $url_to ) {
					wp_enqueue_style( 'wplug-bimeson-template-admin', \wplug\abs_url( $url_to, './assets/css/template-admin.min.css' ), array(), '1.0' );
					wp_enqueue_script( 'wplug-bimeson-template-admin', \wplug\abs_url( $url_to, './assets/js/template-admin.min.js' ), array(), '1.0', false );
				}
			);
		}
	} else {
		add_action(
			'wp_enqueue_scripts',
			function () use ( $url_to ) {
				wp_register_style( 'wplug-bimeson-filter', \wplug\abs_url( $url_to, './assets/css/filter.min.css' ), array(), '1.0' );
				wp_register_script( 'wplug-bimeson-filter', \wplug\abs_url( $url_to, './assets/js/filter.min.js' ), array(), '1.0', false );
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
function _is_the_post_type(): bool {
	$inst = _get_instance();
	global $pagenow;
	return in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && \wplug\is_admin_post_type( $inst::PT );  // @phpstan-ignore-line
}


// -----------------------------------------------------------------------------


/**
 * Gets the root taxonomy slug.
 *
 * @return string Root taxonomy slug.
 */
function get_taxonomy(): string {
	$inst = _get_instance();
	return $inst->root_tax;
}

/**
 * Gets the sub taxonomy slugs.
 *
 * @return array<string, string> Sub taxonomy slugs.
 */
function get_sub_taxonomies(): array {
	$inst = _get_instance();
	return $inst->sub_taxes;
}

/**
 * Adds the meta box.
 *
 * @param string      $title  Title of the meta box.
 * @param string|null $screen (Optional) The screen or screens on which to show the box.
 */
function add_meta_box( string $title, ?string $screen = null ): void {
	add_meta_box_template_admin( $title, $screen );
}

/**
 * Stores the data of the meta box.
 *
 * @param int $post_id Post ID.
 */
function save_meta_box( int $post_id ): void {
	save_meta_box_template_admin( $post_id );
}


// -----------------------------------------------------------------------------


/**
 * Gets visible root slugs.
 *
 * @param array<string, mixed>|null $filter_state Filter states.
 * @return string[]|null Visible root slugs.
 */
function get_visible_root_slugs( ?array $filter_state ): ?array {
	$vs = $filter_state[ _get_instance()::KEY_VISIBLE ] ?? null;  // @phpstan-ignore-line

	$ro  = get_root_slug_to_options();
	$vst = array();
	foreach ( $ro as $rs => $opts ) {
		if ( ! $opts['is_hidden'] ) {
			$vst[] = $rs;
		}
	}
	if ( ! is_array( $vs ) ) {
		return count( $vst ) === count( $ro ) ? null : $vst;
	}
	return array_values( array_intersect( $vs, $vst ) );
}


// -----------------------------------------------------------------------------


/**
 * Display the filter.
 *
 * @param int|null $post_id Post ID.
 * @param string   $lang    Language.
 * @param string   $before  Content to prepend to the output.
 * @param string   $after   Content to append to the output.
 * @param string   $for_at  Attribute of 'for'.
 */
function the_filter( ?int $post_id = null, string $lang = '', string $before = '<div class="wplug-bimeson-filter" hidden%s>', string $after = '</div>', string $for_at = 'bml' ): void {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof \WP_Post ) ) {
		return;
	}
	$d = _get_data( $post->ID, $lang );

	if ( ! $d || ! $d['show_filter'] ) {
		return;
	}
	echo_the_filter( $d['filter_state'], $d['years_exist'], $before, $after, $for_at );
}

/**
 * Display the list.
 *
 * @param int|null $post_id Post ID.
 * @param string   $lang    Language.
 * @param string   $before  Content to prepend to the output.
 * @param string   $after   Content to append to the output.
 * @param string   $id      Attribute of 'id'.
 */
function the_list( ?int $post_id = null, string $lang = '', string $before = '<div class="wplug-bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ): void {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof \WP_Post ) ) {
		return;
	}
	$d = _get_data( $post->ID, $lang );

	if ( ! $d ) {
		return;
	}
	echo_the_list( $d, $lang, $before, $after, $id );
}

/**
 * Retrieves the data
 *
 * @access private
 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
 *
 * @param int    $post_id Post ID.
 * @param string $lang    Language.
 * @return array{
 *     year_bgn          : string,
 *     year_end          : string,
 *     count             : int,
 *     filter_state      : array<string, string[]>,
 *     sort_by_date_first: bool,
 *     dup_multi_cat     : bool,
 *     show_filter       : bool,
 *     omit_single_cat   : bool,
 *     items             : array<string, mixed>[],
 *     years_exist       : string[],
 * }|null Data.
 */
function _get_data( int $post_id, string $lang ): ?array {
	$inst = _get_instance();
	if ( isset( $inst->cache[ "$post_id$lang" ] ) ) {
		return $inst->cache[ "$post_id$lang" ];  // @phpstan-ignore-line
	}
	$d = get_template_admin_config( $post_id );

	// Bimeson Item.
	$items = get_filtered_items( $lang, $d['year_bgn'], $d['year_end'], $d['filter_state'] );

	list( $items, $years_exist ) = retrieve_items( $items, $d['count'], $d['sort_by_date_first'], $d['dup_multi_cat'], $d['filter_state'] );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;

	if ( empty( $items ) ) {
		$d = null;
	}
	$inst->cache[ "$post_id$lang" ] = $d;  // @phpstan-ignore-line
	return $d;
}


// -----------------------------------------------------------------------------


/**
 * Retrieves filtered items.
 *
 * @param string                  $lang         Language.
 * @param string                  $date_bgn     Date from.
 * @param string                  $date_end     Date to.
 * @param array<string, string[]> $filter_state Filter states.
 * @return array<string, mixed>[] Items.
 */
function get_filtered_items( string $lang, string $date_bgn, string $date_end, array $filter_state ): array {
	$inst = _get_instance();
	if ( isset( $filter_state[ $inst::KEY_VISIBLE ] ) ) {  // @phpstan-ignore-line
		unset( $filter_state[ $inst::KEY_VISIBLE ] );  // @phpstan-ignore-line
	}
	$tq = array();
	$mq = array();

	if ( $filter_state ) {
		foreach ( $filter_state as $rs => $slugs ) {
			$sub_tax        = root_term_to_sub_tax( $rs );
			$slugs          = implode( ',', $slugs );
			$tq[ $sub_tax ] = array(
				'taxonomy' => $sub_tax,
				'field'    => 'slug',
				'terms'    => $slugs,
			);
		}
	}
	$by_date = ( ! empty( $date_bgn ) || ! empty( $date_end ) );
	if ( $by_date ) {
		$date_b = (int) str_pad( empty( $date_bgn ) ? '' : $date_bgn, 8, '0', STR_PAD_RIGHT );
		$date_e = (int) str_pad( empty( $date_end ) ? '' : $date_end, 8, '9', STR_PAD_RIGHT );

		$mq['date_num'] = array(
			'key'     => (string) $inst::IT_DATE_NUM,  // @phpstan-ignore-line
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
			'value'   => array( $date_b, $date_e ),
		);
	} else {
		$mq['date_num'] = array(
			'key'  => (string) $inst::IT_DATE_NUM,  // @phpstan-ignore-line
			'type' => 'NUMERIC',
		);
	}
	/**
	 * Posts. This is determined by $args['fields'] being ''.
	 *
	 * @var \WP_Post[] $ps
	 */
	$ps = get_posts(
		array(
			'post_type'      => (string) $inst::PT,  // @phpstan-ignore-line
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
		if ( empty( $it[ $inst::IT_BODY . "_$lang" ] ) && empty( $it[ $inst::IT_BODY ] ) ) {  // @phpstan-ignore-line
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
 * @return array<string, mixed> The item.
 */
function _convert_post_to_item( \WP_Post $p, int $idx ): array {
	$inst = _get_instance();

	// phpcs:disable
	$date       = get_post_meta( $p->ID, $inst::IT_DATE,       true );  // @phpstan-ignore-line
	$doi        = get_post_meta( $p->ID, $inst::IT_DOI,        true );  // @phpstan-ignore-line
	$link_url   = get_post_meta( $p->ID, $inst::IT_LINK_URL,   true );  // @phpstan-ignore-line
	$link_title = get_post_meta( $p->ID, $inst::IT_LINK_TITLE, true );  // @phpstan-ignore-line
	$date_num   = get_post_meta( $p->ID, $inst::IT_DATE_NUM,   true );  // @phpstan-ignore-line
	// phpcs:enable

	$it = array();
	if ( ! empty( $date ) ) {
		$it[ (string) $inst::IT_DATE ] = $date;  // @phpstan-ignore-line
	}
	if ( ! empty( $doi ) ) {
		$it[ (string) $inst::IT_DOI ] = $doi;  // @phpstan-ignore-line
	}
	if ( ! empty( $link_url ) ) {
		$it[ (string) $inst::IT_LINK_URL ] = $link_url;  // @phpstan-ignore-line
	}
	if ( ! empty( $link_title ) ) {
		$it[ (string) $inst::IT_LINK_TITLE ] = $link_title;  // @phpstan-ignore-line
	}
	if ( ! empty( $date_num ) ) {
		$it[ (string) $inst::IT_DATE_NUM ] = $date_num;  // @phpstan-ignore-line
	}
	$body = _make_content( $p->post_content );
	if ( ! empty( $body ) ) {
		$it[ (string) $inst::IT_BODY ] = $body;  // @phpstan-ignore-line
	}
	foreach ( $inst->additional_langs as $al ) {
		$key  = "_post_content_$al";
		$c    = get_post_meta( $p->ID, $key, true );
		$body = is_string( $c ) ? _make_content( $c ) : '';
		if ( ! empty( $body ) ) {
			$it[ (string) $inst::IT_BODY . "_$al" ] = $body;  // @phpstan-ignore-line
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
	$it[ (string) $inst::IT_INDEX ]    = $idx;  // @phpstan-ignore-line
	$it[ (string) $inst::IT_EDIT_URL ] = _make_edit_url( $p );  // @phpstan-ignore-line
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
