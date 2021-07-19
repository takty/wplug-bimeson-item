<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida
 * @version 2021-07-15
 */

namespace wplug\bimeson_post;

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/inc/inst.php';
require_once __DIR__ . '/inc/shortcode.php';
require_once __DIR__ . '/inc/taxonomy.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/filter.php';
require_once __DIR__ . '/inc/list.php';

function initialize( $additional_langs = [], $taxonomy = false, $sub_tax_base = false, $post_lang_tax = 'post_lang', $default_lang = 'en', $url_to = false ) {
	$inst = _get_instance();

	$inst->lang_tax    = $post_lang_tax;
	$inst->additional_langs = $additional_langs;
	$inst->default_lang     = $default_lang;

	_register_post_type();
	initialize_taxonomy( $taxonomy, $sub_tax_base );
	initialize_filter();

	if ( $inst->lang_tax !== false ) {
		register_taxonomy_for_object_type( $inst->lang_tax, $inst::PT );
	}
	if ( is_admin() ) {
		if ( $url_to === false ) $url_to = get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );
		initialize_admin( $url_to );
	} else {
		add_shortcode();
	}
}

function _register_post_type() {
	$inst = _get_instance();
	register_post_type( $inst::PT, [
		'label'         => _x( 'Publication', 'admin', 'bimeson_post' ),
		'labels'        => [],
		'public'        => true,
		'show_ui'       => true,
		'menu_position' => 5,
		'menu_icon'     => 'dashicons-analytics',
		'has_archive'   => false,
		'rewrite'       => false,
		'supports'      => [ 'title', 'editor' ],
	] );
}

function set_heading_level( $level ) {
	$inst = _get_instance();
	$inst->head_level = $level;
}

function set_year_format( $format ) {
	$inst = _get_instance();
	$inst->year_format = $format;
}

function set_term_name_getter( $func ) {
	$inst = _get_instance();
	$inst->term_name_getter = $func;
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

function enqueue_script( $url_to = false ) {
	if ( ! is_admin() ) {
		if ( $url_to === false ) $url_to = get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );

		wp_enqueue_style(  'bimeson_post_filter', $url_to . '/assets/css/filter.min.css' );
		wp_enqueue_script( 'bimeson_post_filter', $url_to . '/assets/js/filter.min.js' );
	}
}


// -------------------------------------------------------------------------


// -------------------------------------------------------------------------


