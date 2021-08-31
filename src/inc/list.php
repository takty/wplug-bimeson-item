<?php
/**
 * Bimeson (List)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

namespace wplug\bimeson_item;

require_once __DIR__ . '/class-itembuffer.php';

/**
 * Displays the list.
 *
 * @param array  $args   Array of arguments.
 * @param string $lang   Language.
 * @param string $before Content to prepend to the output.
 * @param string $after  Content to append to the output.
 * @param string $id     The ID of the output markup.
 */
function echo_the_list( array $args, string $lang, string $before = '<div class="wplug-bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ) {
	if ( ! empty( $id ) ) {
		$id = " id=\"$id\"";
	}
	$before = sprintf( $before, $id );

	echo $before;  // phpcs:ignore
	if ( is_null( $args['count'] ) ) {
		_echo_heading_list_element( $args['items'], $lang, $args['sort_by_date_first'], $args['omit_single_cat'], $args['filter_state'] );
	} else {
		_echo_list_element( $args['items'], $lang );
	}
	echo $after;  // phpcs:ignore
}


// -----------------------------------------------------------------------------


/**
 * Displays headings and lists.
 *
 * @access private
 *
 * @param array  $its                Array of items.
 * @param string $lang               Language.
 * @param bool   $sort_by_date_first Whether to sort by date first.
 * @param bool   $omit_single_cat    Whether to omit categories which have one item.
 * @param ?array $filter_state       Filter states.
 */
function _echo_heading_list_element( array $its, string $lang, bool $sort_by_date_first, bool $omit_single_cat, ?array $filter_state ) {
	$inst = _get_instance();
	$vs   = $filter_state[ $inst::KEY_VISIBLE ] ?? null;

	$root_slug_to_depth    = get_root_slug_to_sub_depths();
	$sub_slug_to_last_omit = get_sub_slug_to_last_omit();

	$last_cat_depth  = $root_slug_to_depth[ array_key_last( $root_slug_to_depth ) ];
	$omitted_heading = $omit_single_cat ? _make_omitted_heading( $filter_state ) : null;

	$hr_size_orig  = 0;
	$hr_to_sub_tax = array();
	foreach ( $root_slug_to_depth as $rs => $d ) {
		if ( ! is_null( $vs ) && ! in_array( $rs, $vs, true ) ) {
			continue;
		}
		$hr_size_orig += $d;
		for ( $i = 0; $i < $d; $i++ ) {
			$hr_to_sub_tax[] = $inst->sub_taxes[ $rs ];
		}
	}
	$prev_cat_slug = array_pad( array(), $hr_size_orig, '' );

	$buf      = new ItemBuffer( $lang );
	$cur_year = '';
	foreach ( $its as $it ) {
		if ( $sort_by_date_first && isset( $it[ $inst::IT_DATE_NUM ] ) ) {
			$year = substr( $it[ $inst::IT_DATE_NUM ], 0, 4 );
			if ( $cur_year !== $year ) {
				$buf->echo();
				_echo_heading_year( 2, $it[ $inst::IT_DATE_NUM ], $inst->year_format );
				$cur_year = $year;
			}
		}
		$cat_slugs = _get_cat_slug( $it, $hr_size_orig );
		$hr        = -1;
		$hr_size   = $hr_size_orig;

		for ( $h = 0; $h < $hr_size_orig; $h++ ) {
			$key = $cat_slugs[ $h ];
			if ( isset( $sub_slug_to_last_omit[ $key ] ) ) {
				$hr_size = $hr_size_orig - $last_cat_depth;
			}
			if ( $key !== $prev_cat_slug[ $h ] ) {
				$hr = $h;
				break;
			}
		}
		if ( -1 !== $hr && $hr < $hr_size ) {
			$is_cat_exist = false;
			for ( $h = $hr; $h < $hr_size; $h++ ) {
				if ( ! empty( $cat_slugs[ $h ] ) ) {
					$is_cat_exist = _is_cat_exist( $hr_to_sub_tax[ $h ], $cat_slugs[ $h ] );
					if ( $is_cat_exist ) {
						break;
					}
				}
			}
			if ( $is_cat_exist ) {
				$buf->echo();
			}
			for ( $h = $hr; $h < $hr_size; $h++ ) {
				if ( ! empty( $cat_slugs[ $h ] ) ) {
					if ( is_null( $omitted_heading ) || ! $omitted_heading[ $hr_to_sub_tax[ $h ] ] ) {
						_echo_heading( $h, $sort_by_date_first ? 1 : 0, $hr_to_sub_tax[ $h ], $cat_slugs[ $h ] );
					}
				}
			}
		}
		$prev_cat_slug = $cat_slugs;  // Clone!
		$buf->add( $it );
	}
	$buf->echo();
}


// -----------------------------------------------------------------------------


/**
 * Creates omitted heading data.
 *
 * @access private
 *
 * @param ?array $filter_state Filter states.
 * @return ?array Omitted heading data.
 */
function _make_omitted_heading( ?array $filter_state ): ?array {
	if ( is_null( $filter_state ) ) {
		return null;
	}
	$inst = _get_instance();
	$ret  = array();
	foreach ( $inst->sub_taxes as $rs => $sub_tax ) {
		$ret[ $sub_tax ] = false;

		$s = $filter_state[ $rs ] ?? array();
		if ( 1 === count( $s ) ) {
			$ret[ $sub_tax ] = true;
		}
	}
	return $ret;
}

/**
 * Retrieves category slugs.
 *
 * @access private
 *
 * @param array $it      The item.
 * @param int   $hr_size Heading hierarchy size.
 * @return array Slugs.
 */
function _get_cat_slug( array $it, int $hr_size ): array {
	$inst      = _get_instance();
	$cat_slugs = $it[ $inst::IT_CAT_KEY ];
	if ( count( $cat_slugs ) !== $hr_size ) {  // for invalid sort key.
		$cat_slugs = array_pad( $cat_slugs, $hr_size, '' );
	}
	return $cat_slugs;
}

/**
 * Checks whether the category exists.
 *
 * @access private
 *
 * @param string $sub_tax Sub taxonomy slug.
 * @param string $slug    Slug.
 * @return bool True if the category exists.
 */
function _is_cat_exist( string $sub_tax, string $slug ): bool {
	$t = get_term_by( 'slug', $slug, $sub_tax );
	if ( ! $t ) {
		return false;
	}
	return true;
}


// -----------------------------------------------------------------------------


/**
 * Displays a heading markup.
 *
 * @access private
 *
 * @param int    $hr                 Hierarchy level.
 * @param bool   $sort_by_date_first Whether to sort by date first.
 * @param string $sub_tax            Sub taxonomy slug.
 * @param string $slug               Slug.
 */
function _echo_heading( int $hr, bool $sort_by_date_first, string $sub_tax, string $slug ) {
	$inst = _get_instance();

	$t = get_term_by( 'slug', $slug, $sub_tax );
	if ( $t ) {
		if ( is_callable( $inst->term_name_getter ) ) {
			$_label = esc_html( ( $inst->term_name_getter )( $t ) );
		} else {
			$_label = esc_html( $t->name );
		}
	} else {
		$_label = esc_html( is_numeric( $slug ) ? $slug : "[$slug]" );
	}
	$level = $hr + $inst->head_level + ( $sort_by_date_first ? 1 : 0 );
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;
	echo "<$tag class=\"$slug\" data-depth=\"$depth\">$_label</$tag>\n";  // phpcs:ignore
}

/**
 * Displays a year heading markup.
 *
 * @access private
 *
 * @param int    $level    Hierarchy level.
 * @param string $date_num String of number representing date.
 * @param string $format   String format.
 */
function _echo_heading_year( int $level, string $date_num, string $format ) {
	$year = substr( $date_num, 0, 4 );
	if ( is_string( $format ) ) {
		$_label = esc_html( sprintf( $format, (int) $year ) );
	} else {
		$_label = esc_html( $year );
	}
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;
	echo "<$tag class=\"year\" data-depth=\"$depth\">$_label</$tag>\n";  // phpcs:ignore
}


// -----------------------------------------------------------------------------


/**
 * Displays a list markup.
 *
 * @access private
 *
 * @param array  $its  Items.
 * @param string $lang Language.
 */
function _echo_list_element( array $its, string $lang ) {
	$tag = ( count( $its ) === 1 ) ? 'ul' : 'ol';
	echo "<$tag data-bm>\n";  // phpcs:ignore
	foreach ( $its as $it ) {
		_echo_list_item( $it, $lang );
	}
	echo "</$tag>\n";  // phpcs:ignore
}

/**
 * Displays the markup of an item.
 *
 * @access private
 *
 * @param array  $it   The item.
 * @param string $lang Language.
 */
function _echo_list_item( array $it, string $lang ) {
	$inst = _get_instance();

	$body = '';
	if ( ! empty( $lang ) ) {
		$body = $it[ $inst::IT_BODY . "_$lang" ] ?? '';
	}
	if ( empty( $body ) ) {
		$body = $it[ $inst::IT_BODY ] ?? '';
	}
	if ( empty( $body ) ) {
		return;
	}
	// phpcs:disable
	$doi   = $it[ $inst::IT_DOI ]        ?? '';
	$url   = $it[ $inst::IT_LINK_URL ]   ?? '';
	$title = $it[ $inst::IT_LINK_TITLE ] ?? '';
	// phpcs:enable

	$_doi = '';
	if ( ! empty( $doi ) ) {
		$doi = preg_replace( '/^https?:\/\/doi\.org\//i', '', $doi );
		$doi = trim( ltrim( $doi, '/' ) );
		if ( ! empty( $doi ) ) {
			$_url   = esc_url( "https://doi.org/$doi" );
			$_title = esc_html( $doi );
			$_doi   = "<span class=\"doi\">DOI: <a href=\"$_url\">$_title</a></span>";
		}
	}
	$_link = '';
	if ( ! empty( $url ) ) {
		$_url   = esc_url( $url );
		$_title = empty( $title ) ? $_url : esc_html( $title );
		$_link  = "<span class=\"link\"><a href=\"$_url\">$_title</a></span>";
	}
	$_cls = esc_attr( _make_cls( $it ) );

	echo "<li class=\"$_cls\"><div>\n";  // phpcs:ignore
	echo "<span class=\"body\">$body</span>$_link$_doi\n";  // phpcs:ignore
	echo "</div></li>\n";  // phpcs:ignore
}

/**
 * Creates CSS class of an item.
 *
 * @access private
 *
 * @param array $it The item.
 * @return string CSS class.
 */
function _make_cls( array $it ): string {
	$inst = _get_instance();
	$cs   = array();

	$year = $it[ $inst::IT_DATE_NUM ] ?? '';
	if ( ! empty( $year ) ) {
		$cs[] = $inst->year_cls_base . substr( '' . $year, 0, 4 );
	}
	foreach ( $inst->sub_taxes as $rs => $sub_tax ) {
		$ss = $it[ $rs ] ?? array();
		foreach ( $ss as $s ) {
			$cs[] = $inst->sub_tax_cls_base . "$rs-$s";
		}
	}
	return str_replace( '_', '-', implode( ' ', $cs ) );
}
