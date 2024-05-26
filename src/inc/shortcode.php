<?php
/**
 * Shortcode
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2024-05-26
 *
 * [publication list="<slug or post ID>" count="10" date-sort omit-single dup-item date="2020-2021" taxonomy="slug1, slug2, ..."]
 */

declare(strict_types=1);

namespace wplug\bimeson_item;

require_once __DIR__ . '/../bimeson.php';
require_once __DIR__ . '/filter.php';
require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/list.php';
require_once __DIR__ . '/retriever.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Registers the shortcode.
 *
 * @param string $lang Language.
 */
function register_shortcode( string $lang ): void {
	static $serial = 0;

	\add_shortcode(
		'publication',
		/**
		 * Callback function.
		 *
		 * @param array<string, string>|string $atts    Attributes.
		 * @param string|null                  $content The shortcode content.
		 */
		function ( $atts, ?string $content ) use ( $lang, $serial ): string {
			if ( is_string( $atts ) ) {
				$atts = array();
			}
			$d = _get_data_shortcode( $atts, $lang );
			++$serial;
			$id = "bml-sc$serial";

			ob_start();
			if ( is_array( $d ) && $d['show_filter'] ) {
				echo_the_filter( $d['filter_state'], $d['years_exist'], '<div class="wplug-bimeson-filter"%s>', '</div>', $id );
			}
			if ( is_string( $content ) && ! empty( $content ) ) {
				echo str_replace( '<p></p>', '', balanceTags( $content, true ) );  // phpcs:ignore
			}
			if ( is_array( $d ) ) {
				/**
				 * Additional array shape fields (years_exist, show_filter) was provided.
				 *
				 * @psalm-suppress InvalidArgument
				 */
				echo_the_list( $d, $lang, '<div class="wplug-bimeson-list"%s>', '</div>', $id );
			}
			$ret = (string) ob_get_contents();
			ob_end_clean();
			return $ret;
		}
	);
}

/**
 * Retrieves the data of the shortcode.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @param string               $lang Language.
 * @return null|array{
 *     count             : int|null,
 *     sort_by_date_first: bool,
 *     omit_single_cat   : bool,
 *     filter_state      : array<string, mixed>|null,
 *     items             : array<string, mixed>[],
 *     years_exist       : string[],
 *     show_filter       : bool,
 * } Data.
 */
function _get_data_shortcode( array $atts, string $lang ): ?array {
	// phpcs:disable
	$count              = isset( $atts['count'] ) && is_numeric( $atts['count'] ) ? ( (int) $atts['count'] ) : null;
	$sort_by_date_first = in_array( 'date-sort',   $atts, true ) || (bool) ( $atts['date-sort']   ?? false );
	$dup_multi_cat      = in_array( 'dup-item',    $atts, true ) || (bool) ( $atts['dup-item']    ?? false );
	$omit_single_cat    = in_array( 'omit-single', $atts, true ) || (bool) ( $atts['omit-single'] ?? false );
	// phpcs:enable

	list( $date_bgn, $date_end ) = _extract_date_atts( $atts );
	$filter_state                = _extract_filter_state( $atts );

	// Bimeson Item.
	$items = get_filtered_items( $lang, $date_bgn, $date_end, $filter_state );

	list( $items, $years_exist ) = retrieve_items( $items, $count, $sort_by_date_first, $dup_multi_cat, $filter_state );

	$d = compact( 'count', 'sort_by_date_first', 'omit_single_cat', 'filter_state' );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;
	$d['show_filter'] = isset( $atts['show-filter'] );
	return empty( $items ) ? null : $d;
}

/**
 * Extracts date attribute.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @return array{ string, string } Date.
 */
function _extract_date_atts( array $atts ): array {
	$ds = array();
	foreach ( array( 'date', 'date-start', 'date-end' ) as $key ) {
		if ( isset( $atts[ $key ] ) && is_string( $atts[ $key ] ) && ! empty( $atts[ $key ] ) ) {
			if ( is_numeric( $atts[ $key ] ) ) {
				$ds[] = $atts[ $key ];
			} else {
				$vs = array_map( '\trim', explode( '-', $atts[ $key ] ) );
				foreach ( $vs as $v ) {
					if ( is_numeric( $v ) ) {
						$ds[] = $v;
					}
				}
			}
		}
	}
	sort( $ds );  // Sort as strings.

	if ( empty( $ds ) ) {
		return array( '', '' );
	}
	return array( $ds[0], end( $ds ) );
}

/**
 * Extracts filter state attribute.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @return array<string, string[]> Filter states.
 */
function _extract_filter_state( array $atts ): array {
	$fs = array();
	foreach ( get_root_slugs() as $rs ) {
		if ( isset( $atts[ $rs ] ) && is_string( $atts[ $rs ] ) ) {
			$fs[ $rs ] = array_map( '\trim', explode( ',', $atts[ $rs ] ) );
		}
	}
	return count( $fs ) ? $fs : array();
}
