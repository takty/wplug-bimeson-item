<?php
/**
 * Bimeson Item (Retriever)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_item;

function retrieve_items( array $items, ?int $count, bool $sort_by_date_first, bool $dup_multi_cat ) {
	$rs_idx = _make_rs_idx();

	$items = _align_sub_slugs( $items, $rs_idx );
	if ( $dup_multi_cat ) $items = _duplicate_items( $items );
	_sort_list_items( $items, $sort_by_date_first, $rs_idx );

	if ( is_numeric( $count ) && 0 < $count ) array_splice( $items, $count );
	$items = _assign_cat_key( $items );
	$years_exist = _collect_existing_year( $items );

	return [ $items, $years_exist ];
}


// -----------------------------------------------------------------------------


function _make_rs_idx() {
	$rs_idx = [];
	foreach ( get_root_slug_to_sub_slugs() as $rs => $ss ) {
		$rs_idx[ $rs ] = array_flip( $ss );
	}
	return $rs_idx;
}

function _align_sub_slugs( array $items, array $rs_idx ): array {
	$inst = _get_instance();

	foreach ( $items as $idx => &$it ) {
		foreach ( $rs_idx as $rs => $ss ) {
			if ( isset( $it[ $rs ] ) ) {
				usort( $it[ $rs ], function ( $a, $b ) use ( $ss ) { return $ss[ $a ] <=> $ss[ $b ]; } );
			}
		}
	}
	return $items;
}


// -----------------------------------------------------------------------------


function _duplicate_items( array $items ): array {
	$inst = _get_instance();
	$rss  = get_root_slugs();

	$ret = [];
	foreach ( $items as $it ) {
		$array = [];
		$do = false;
		foreach ( $rss as $rs ) {
			$vs = $it[ $rs ] ?? [];
			if ( 1 < count( $vs ) ) {
				$do = true;
			}
			$array[ $rs ] = $vs;
		}
		if ( $do ) {
			$comb = _generate_combination( $array );
			foreach ( $comb as $c ) {
				$it_new = $it;
				foreach ( $c as $rs => $v ) {
					$it_new[ $rs ] = [ $v ];
				}
				$ret[] = $it_new;
			}
		} else {
			$ret[] = $it;
		}
	}
	return $ret;
}

/**
 * Generates combinations of given strings.
 *
 * @access private
 *
 * @param array $arrays An array of string arrays.
 * @return array The array of combinations.
 */
function _generate_combination( array $arrays ): array {
	$counts = array_map( 'count', $arrays );
	$total  = array_product( $counts );
	$cycles = array();

	$c = $total;
	foreach ( $arrays as $k => $vs ) {
		$c /= $counts[ $k ];

		$cycles[ $k ] = $c;
	}
	$res = array();
	for ( $i = 0; $i < $total; ++$i ) {
		$temp = array();
		foreach ( $arrays as $k => $vs ) {
			$temp[ $k ] = $vs[ ( $i / $cycles[ $k ] ) % $counts[ $k ] ];
		}
		$res[] = $temp;
	}
	return $res;
}


// -----------------------------------------------------------------------------


function _sort_list_items( array &$items, bool $sort_by_date_first, array $rs_idx ) {
	$inst = _get_instance();

	$inst->rs_idx = $rs_idx;
	if ( $sort_by_date_first ) {
		usort( $items, '\wplug\bimeson_item\_compare_item_by_date_cat' );
	} else {
		usort( $items, '\wplug\bimeson_item\_compare_item_by_cat' );
	}
}

function _compare_item_by_date_cat( array $a, array $b ): int {
	$ret = _compare_item_by_date( $a, $b );
	return ( $ret !== 0 ) ? $ret : _compare_item_by_cat( $a, $b );
}

function _compare_item_by_date( array $a, array $b ): int {
	$inst = _get_instance();
	if ( isset( $a[ $inst::IT_DATE_NUM ] ) && isset( $b[ $inst::IT_DATE_NUM ] ) ) {
		if ( $a[ $inst::IT_DATE_NUM ] !== $b[ $inst::IT_DATE_NUM ] ) {
			return $a[ $inst::IT_DATE_NUM ] < $b[ $inst::IT_DATE_NUM ] ? 1 : -1;
		}
	}
	return 0;
}

function _compare_item_by_cat( array $a, array $b ): int {
	$inst   = _get_instance();
	$rs_idx = $inst->rs_idx;

	foreach ( get_root_slug_to_sub_slugs() as $rs => $slugs ) {
		$ai = _get_first_sub_slug_index( $a, $rs, $rs_idx );
		$bi = _get_first_sub_slug_index( $b, $rs, $rs_idx );

		if ( $ai === -1 && $bi === -1) {
			continue;
		} else {
			if ( $ai < $bi ) return -1;
			if ( $ai > $bi ) return 1;
		}
	}
	$ret = _compare_item_by_date( $a, $b );
	if ( $ret !== 0 ) return $ret;

	$inst = _get_instance();
	return $a[ $inst::IT_INDEX ] < $b[ $inst::IT_INDEX ] ? -1 : 1;
}

function _get_first_sub_slug_index( array $item, string $rs, array $rs_idx ): int {
	$v0 = $item[ $rs ][0] ?? '';
	if ( empty( $v0 ) ) return -1;
	return $rs_idx[ $rs ][ $v0 ] ?? -1;
}


// -----------------------------------------------------------------------------


function _assign_cat_key( array $items ): array {
	$inst = _get_instance();

	$rs_to_slugs       = get_root_slug_to_sub_slugs();
	$rs_to_depths      = get_root_slug_to_sub_depths();
	$slug_to_ancestors = get_sub_slug_to_ancestors();

	foreach ( $items as &$it ) {
		$it[ $inst::IT_CAT_KEY ] = _make_cat_key( $it, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors );
	}
	return $items;
}

function _make_cat_key( array $it, array $rs_to_slugs, array $rs_to_depths, array $slug_to_ancestors ): string {
	$cats = [];
	foreach ( $rs_to_slugs as $rs => $slugs ) {
		if ( ! isset( $rs_to_depths[ $rs ] ) ) continue;
		$depth = $rs_to_depths[ $rs ];

		$s = $it[ $rs ][0] ?? null;
		if ( $s ) {
			if ( isset( $slug_to_ancestors[ $s ] ) ) {
				$cats = array_merge( $cats, $slug_to_ancestors[ $s ] );
			}
			$cats[] = $s;
			$depth -= count( $cats );
		}
		for ( $i = 0; $i < $depth; $i += 1 ) $cats[] = '';
	}
	return implode( ',', $cats );
}


// -----------------------------------------------------------------------------


function _collect_existing_year( array $items ): array {
	$inst  = _get_instance();
	$years = [];

	foreach ( $items as $it ) {
		if ( ! isset( $it[ $inst::IT_DATE_NUM ] ) ) continue;
		$years[ substr( $it[ $inst::IT_DATE_NUM ], 0, 4 ) ] = 1;
	}
	$years = array_keys( $years );
	rsort( $years );
	return $years;
}
