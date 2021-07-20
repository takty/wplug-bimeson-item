<?php
/**
 * Bimeson (Shortcode)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-07-20
 *
 * [publication list="<slug or post ID>" count="10" date-sort omit-single dup-item date="2020-2021" taxonomy="slug1, slug2, ..."]
 */

namespace wplug\bimeson_item;

function register_shortcode( $lang ) {
	static $serial = 0;

	\add_shortcode( 'publication', function ( $atts, ?string $content = null ) use ( $lang, $serial ): string {
		if ( is_string( $atts ) ) $atts = [];

		$d      =  _get_date_shortcode( $atts, $lang );
		$serial += 1;
		$id     =  "bml-sc$serial";

		ob_start();
		if ( $d && $d['show_filter'] ) {
			echo_the_filter( $d['filter_state'], $d['years_exist'], '<div class="bimeson-filter"%s>', '</div>', $id );
		}
		if ( ! is_null( $content ) ) {
			echo str_replace( '<p></p>', '', balanceTags( $content, true ) );
		}
		if ( $d ) {
			echo_the_list( $d, $lang, '<div class="bimeson-list"%s>', '</div>', $id );
		}
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	} );
}

function _get_date_shortcode( array $atts, string $lang ) {
	[ $date_bgn, $date_end ] = _extract_date_atts( $atts );

	$count              = isset( $atts['count'] ) ? ( (int) $atts['count'] ) : null;
	$sort_by_date_first = in_array( 'date-sort', $atts, true ) || (bool) ( $atts['date-sort'] ?? false );
	$dup_multi_cat      = in_array( 'dup-item', $atts, true ) || (bool) ( $atts['dup-item'] ?? false );
	$omit_single_cat    = in_array( 'omit-single', $atts, true ) || (bool) ( $atts['omit-single'] ?? false );

	$filter_state = _extract_filter_state( $atts );

	// Bimeson Item
	$items = get_filtered_items( $lang, $date_bgn, $date_end, $filter_state );
	[ $items, $years_exist ] = retrieve_items( $items, $count, $sort_by_date_first, $dup_multi_cat );

	$d = compact( 'count', 'sort_by_date_first', 'omit_single_cat', 'filter_state' );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;
	$d['show_filter'] = isset( $atts['show-filter'] );
	return $d;
}

function _extract_date_atts( array $atts ): array {
	$ds = [];
	foreach ( [ 'date', 'date-start', 'date-end' ] as $key ) {
		if ( ! empty( $atts[ $key ] ) ) {
			if ( is_numeric( $atts[ $key ] ) ) {
				$ds[] = $atts[ $key ];
			} else {
				$vs = array_map( '\trim', explode( '-', $atts[ $key ] ) );
				foreach ( $vs as $v ) {
					if ( is_numeric( $v ) ) $ds[] = $v;
				}
			}
		}
	}
	sort( $ds );  // Sort as strings

	if ( 0 === count( $ds ) ) return [ null, null ];
	return [ $ds[0], end( $ds ) ];
}

function _extract_filter_state( array $atts ): ?array {
	$fs = [];
	foreach ( get_root_slugs() as $rs ) {
		if ( isset( $atts[ $rs ] ) ) {
			$vs = array_map( '\trim', explode( ',', $atts[ $rs ] ) );
			$fs[ $rs ] = $vs;
		}
	}
	return count( $fs ) ? $fs : null;
}
