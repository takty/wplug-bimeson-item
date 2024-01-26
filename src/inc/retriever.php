<?php
/**
 * Retriever (Common)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2024-01-26
 */

declare(strict_types=1);

namespace wplug\bimeson_item;

require_once __DIR__ . '/../bimeson.php';
require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Retrieves items.
 *
 * @param array<string, mixed>[]    $items              All items.
 * @param int|null                  $count              Count limit.
 * @param bool                      $sort_by_date_first Whether to sort by date first.
 * @param bool                      $dup_multi_cat      Whether to duplicate multiple category items.
 * @param array<string, mixed>|null $filter_state       Filter states.
 * @return array{ array<string, mixed>[], string[] } Array of retrieved items and existing years.
 */
function retrieve_items( array $items, ?int $count, bool $sort_by_date_first, bool $dup_multi_cat, ?array $filter_state ): array {
	$rs_idx = _make_rs_idx();
	$vs     = get_visible_root_slugs( $filter_state );

	$items = _align_sub_slugs( $items, $rs_idx );
	if ( $dup_multi_cat ) {
		$items = _duplicate_items( $items );
	}
	_sort_list_items( $items, $sort_by_date_first, $rs_idx, $vs );

	if ( is_numeric( $count ) && 0 < $count ) {
		array_splice( $items, $count );
	}
	$items       = _assign_cat_key( $items, $vs );
	$years_exist = _collect_existing_year( $items );

	return array( $items, $years_exist );
}


// -----------------------------------------------------------------------------


/**
 * Makes the array of root slug to sub slug indices.
 *
 * @access private
 *
 * @return array<string, int[]> Indices.
 */
function _make_rs_idx(): array {
	$rs_idx = array();
	foreach ( get_root_slug_to_sub_slugs() as $rs => $ss ) {
		$rs_idx[ $rs ] = array_flip( $ss );
	}
	return $rs_idx;
}

/**
 * Aligns sub slugs.
 *
 * @access private
 *
 * @param array<string, mixed>[] $items  Items.
 * @param array<string, int[]>   $rs_idx The array of root slug to sub slug indices.
 * @return array<string, mixed>[] Items.
 */
function _align_sub_slugs( array $items, array $rs_idx ): array {
	foreach ( $items as &$it ) {
		foreach ( $rs_idx as $rs => $ss ) {
			if ( isset( $it[ $rs ] ) && is_array( $it[ $rs ] ) ) {
				usort(
					$it[ $rs ],
					function ( $a, $b ) use ( $ss ) {
						if ( isset( $ss[ $a ] ) && isset( $ss[ $b ] ) ) {
							return $ss[ $a ] <=> $ss[ $b ];
						}
						return 0;
					}
				);
			}
		}
	}
	return $items;
}


// -----------------------------------------------------------------------------


/**
 * Duplicate items.
 *
 * @access private
 *
 * @param array<string, mixed>[] $items Items.
 * @return array<string, mixed>[] Duplicated items.
 */
function _duplicate_items( array $items ): array {
	$rss = get_root_slugs();

	$ret = array();
	foreach ( $items as $it ) {
		$array = array();
		$do    = false;
		foreach ( $rss as $rs ) {
			$vs = isset( $it[ $rs ] ) && is_array( $it[ $rs ] ) ? $it[ $rs ] : array();
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
					$it_new[ $rs ] = array( $v );
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
 * @param array<string[]> $arrays An array of string arrays.
 * @return array<string[]> The array of combinations.
 */
function _generate_combination( array $arrays ): array {
	$counts = array_map( 'count', $arrays );
	$total  = array_product( $counts );
	$cycles = array();

	$c = $total;
	foreach ( $arrays as $k => $_vs ) {
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


/**
 * Sort list items.
 *
 * @access private
 *
 * @param array<string, mixed>[] $items              Items to be sorted.
 * @param bool                   $sort_by_date_first Whether to sort by date first.
 * @param array<string, int[]>   $rs_idx             The array of root slug to sub slug indices.
 * @param string[]|null          $visible_state      Visibility states.
 */
function _sort_list_items( array &$items, bool $sort_by_date_first, array $rs_idx, ?array $visible_state ): void {
	$inst = _get_instance();

	if ( ! is_null( $visible_state ) ) {
		foreach ( $rs_idx as $rs => $_v ) {
			if ( ! in_array( $rs, $visible_state, true ) ) {
				unset( $rs_idx[ $rs ] );
			}
		}
	}
	$inst->rs_idx  = $rs_idx;  // @phpstan-ignore-line
	$inst->rs_opts = get_root_slug_to_options();  // @phpstan-ignore-line
	if ( $sort_by_date_first ) {
		usort( $items, '\wplug\bimeson_item\_compare_item_by_date_cat' );
	} else {
		usort( $items, '\wplug\bimeson_item\_compare_item_by_cat' );
	}
}

/**
 * Callback function for usort to compare items by dates and categories.
 *
 * @access private
 *
 * @param array<string, mixed> $a An item.
 * @param array<string, mixed> $b An item.
 * @return int Comparison result.
 */
function _compare_item_by_date_cat( array $a, array $b ): int {
	$ret = _compare_item_by_date( $a, $b );
	return ( 0 !== $ret ) ? $ret : _compare_item_by_cat( $a, $b );
}

/**
 * Callback function for usort to compare items by dates.
 *
 * @access private
 *
 * @param array<string, mixed> $a An item.
 * @param array<string, mixed> $b An item.
 * @return int Comparison result.
 */
function _compare_item_by_date( array $a, array $b ): int {
	$inst = _get_instance();
	if ( isset( $a[ $inst::IT_DATE_NUM ] ) && isset( $b[ $inst::IT_DATE_NUM ] ) ) {  // @phpstan-ignore-line
		if ( $a[ $inst::IT_DATE_NUM ] !== $b[ $inst::IT_DATE_NUM ] ) {  // @phpstan-ignore-line
			return $a[ $inst::IT_DATE_NUM ] < $b[ $inst::IT_DATE_NUM ] ? 1 : -1;  // @phpstan-ignore-line
		}
	}
	return 0;
}

/**
 * Callback function for usort to compare items by categories.
 *
 * @access private
 *
 * @param array<string, mixed> $a An item.
 * @param array<string, mixed> $b An item.
 * @return int Comparison result.
 */
function _compare_item_by_cat( array $a, array $b ): int {
	$inst    = _get_instance();
	$rs_idx  = $inst->rs_idx;
	$rs_opts = $inst->rs_opts;

	if ( ! is_array( $rs_idx ) || ! is_array( $rs_opts ) ) {
		return 0;
	}

	foreach ( get_root_slug_to_sub_slugs() as $rs => $_slugs ) {
		$ai = _get_first_sub_slug_index( $a, $rs, $rs_idx );
		$bi = _get_first_sub_slug_index( $b, $rs, $rs_idx );

		if ( -1 === $ai && -1 === $bi ) {
			continue;
		} else {
			if ( $rs_opts[ $rs ]['uncat_last'] ) {
				$ai = ( -1 === $ai ) ? PHP_INT_MAX : $ai;
				$bi = ( -1 === $bi ) ? PHP_INT_MAX : $bi;
			}
			if ( $ai < $bi ) {
				return -1;
			}
			if ( $ai > $bi ) {
				return 1;
			}
		}
	}
	$ret = _compare_item_by_date( $a, $b );
	if ( 0 !== $ret ) {
		return $ret;
	}
	$inst = _get_instance();
	return $a[ $inst::IT_INDEX ] < $b[ $inst::IT_INDEX ] ? -1 : 1;  // @phpstan-ignore-line
}

/**
 * Retrieve the first sub slug index.
 *
 * @access private
 *
 * @param array<string, mixed> $item   An item.
 * @param string               $rs     An root slug.
 * @param array<string, int[]> $rs_idx The array of root slug to sub slug indices.
 * @return int Index.
 */
function _get_first_sub_slug_index( array $item, string $rs, array $rs_idx ): int {
	if ( isset( $item[ $rs ] ) && is_array( $item[ $rs ] ) && ! empty( $item[ $rs ] ) ) {
		$v0 = $item[ $rs ][0];
		return $rs_idx[ $rs ][ $v0 ] ?? -1;
	}
	return -1;
}


// -----------------------------------------------------------------------------


/**
 * Assigns category keys to items.
 *
 * @access private
 *
 * @param array<string, mixed>[] $items         Items.
 * @param string[]|null          $visible_state Visibility states.
 * @return array<string, mixed>[] Items.
 */
function _assign_cat_key( array $items, ?array $visible_state ): array {
	$inst = _get_instance();

	$rs_to_slugs       = get_root_slug_to_sub_slugs();
	$rs_to_depths      = get_root_slug_to_sub_depths();
	$slug_to_ancestors = get_sub_slug_to_ancestors();

	if ( ! is_null( $visible_state ) ) {
		foreach ( $rs_to_depths as $rs => $_d ) {
			if ( ! in_array( $rs, $visible_state, true ) ) {
				unset( $rs_to_depths[ $rs ] );
			}
		}
	}
	foreach ( $items as &$it ) {
		$it[ $inst::IT_CAT_KEY ] = _make_cat_key( $it, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors );  // @phpstan-ignore-line
	}
	return $items;
}

/**
 * Creates category keys.
 *
 * @access private
 *
 * @param array<string, mixed>    $it                Item.
 * @param array<string, string[]> $rs_to_slugs       The array of root slugs to sub slugs.
 * @param array<string, int>      $rs_to_depths      The array of root slugs to depths.
 * @param array<string, string[]> $slug_to_ancestors The array of slugs to ancestors.
 * @return string[] Category keys.
 */
function _make_cat_key( array $it, array $rs_to_slugs, array $rs_to_depths, array $slug_to_ancestors ): array {
	$cats = array();
	foreach ( $rs_to_slugs as $rs => $_slugs ) {
		if ( ! isset( $rs_to_depths[ $rs ] ) ) {
			continue;
		}
		$depth = $rs_to_depths[ $rs ];

		if ( isset( $it[ $rs ] ) && is_array( $it[ $rs ] ) && ! empty( $it[ $rs ] ) ) {
			$s = $it[ $rs ][0];
			if ( isset( $slug_to_ancestors[ $s ] ) ) {
				$cats = array_merge( $cats, $slug_to_ancestors[ $s ] );
			}
			$cats[] = $s;
			$depth -= count( $cats );
		}
		for ( $i = 0; $i < $depth; ++$i ) {
			$cats[] = '';
		}
	}
	return $cats;
}


// -----------------------------------------------------------------------------


/**
 * Collects existing years.
 *
 * @access private
 *
 * @param array<string, mixed>[] $items Items.
 * @return string[] Array of existing years.
 */
function _collect_existing_year( array $items ): array {
	$inst  = _get_instance();
	$years = array();

	foreach ( $items as $it ) {
		if ( ! isset( $it[ $inst::IT_DATE_NUM ] ) ) {  // @phpstan-ignore-line
			continue;
		}
		$years[ substr( $it[ $inst::IT_DATE_NUM ], 0, 4 ) ] = 1;  // @phpstan-ignore-line
	}
	$years = array_keys( $years );
	rsort( $years );
	return $years;
}
