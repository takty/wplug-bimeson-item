<?php
/**
 * Utilities for Bimeson Item
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_item;

function make_title( string $body, string $date ): string {
	$body = normalize_body( $body );
	$words = explode( ' ', $body );
	$first_word = empty( $words ) ? '' : $words[0];
	$dp = explode( '-', $date );
	$year = ( 0 < count( $dp ) ) ? $dp[0] : '';
	return $first_word . $year;
}

function make_digest( string $body ): string {
	$body = normalize_body( $body );
	$body = str_replace( ' ', '', $body );
	return hash( 'sha224', $body );
}

function normalize_body( string $body ): string {
	$body = strip_tags( trim( $body ) );
	$body = mb_convert_kana( $body, 'rnasKV' );
	$body = mb_strtolower( $body );
	$body = preg_replace( '/[\s!-\/:-@[-`{-~]|[、。，．・：；？！´｀¨＾￣＿―‐／＼～∥｜…‥‘’“”（）〔〕［］｛｝〈〉《》「」『』【】＊※]/u', ' ', $body );
	$body = preg_replace( '/\s(?=\s)/', '', $body );
	$body = trim( $body );
	return $body;
}


// -----------------------------------------------------------------------------


function get_the_sub_content( string $meta_key, ?int $post_id = null ): string {
	global $post;
	if ( is_null( $post_id ) ) $post_id = $post->ID;
	$content = get_post_meta( $post_id, $meta_key, true );
	return $content;
}


// -----------------------------------------------------------------------------


function is_post_type( string $post_type ): bool {
	$post_id = get_post_id();
	$pt = get_post_type_in_admin( $post_id );
	return $post_type === $pt;
}

function get_post_id(): int {
	$post_id = '';
	if ( isset( $_GET['post'] ) || isset( $_POST['post_ID'] ) ) {
		$post_id = isset( $_GET['post'] ) ? $_GET['post'] : $_POST['post_ID'];
	}
	return intval( $post_id );
}

function get_post_type_in_admin( int $post_id ): string {
	$p = get_post( $post_id );
	if ( $p === null ) {
		if ( isset( $_GET['post_type'] ) ) return $_GET['post_type'];
		return '';
	}
	return $p->post_type;
}


// -----------------------------------------------------------------------------


function get_file_uri( string $path ): string {
	$path = wp_normalize_path( $path );

	if ( is_child_theme() ) {
		$theme_path = wp_normalize_path( defined( 'CHILD_THEME_PATH' ) ? CHILD_THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();

		// When child theme is used, and libraries exist in the parent theme
		$tlen = strlen( $theme_path );
		$len  = strlen( $path );
		if ( $tlen >= $len || 0 !== strncmp( $theme_path . $path[ $tlen ], $path, $tlen + 1 ) ) {
			$theme_path = wp_normalize_path( defined( 'THEME_PATH' ) ? THEME_PATH : get_template_directory() );
			$theme_uri  = get_template_directory_uri();
		}
		return str_replace( $theme_path, $theme_uri, $path );
	} else {
		$theme_path = wp_normalize_path( defined( 'THEME_PATH' ) ? THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();
		return str_replace( $theme_path, $theme_uri, $path );
	}
}

function abs_url( string $base, string $rel ): string {
	if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) return $rel;
	$base = trailingslashit( $base );
	if ( $rel[0] === '#' || $rel[0] === '?' ) return $base . $rel;

	$pu = parse_url( $base );
	$scheme = isset( $pu['scheme'] ) ? $pu['scheme'] . '://' : '';
	$host   = isset( $pu['host'] )   ? $pu['host']           : '';
	$port   = isset( $pu['port'] )   ? ':' . $pu['port']     : '';
	$path   = isset( $pu['path'] )   ? $pu['path']           : '';

	$path = preg_replace( '#/[^/]*$#', '', $path );
	if ( $rel[0] === '/' ) $path = '';
	$abs = "$host$port$path/$rel";
	$re = [ '#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#' ];
	for ( $n = 1; $n > 0; $abs = preg_replace( $re, '/', $abs, -1, $n ) ) {}
	return $scheme . $abs;
}
