<?php
/**
 * List (Common)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2024-01-29
 */

declare(strict_types=1);

namespace wplug\bimeson_item;

require_once __DIR__ . '/../bimeson.php';
require_once __DIR__ . '/class-itembuffer.php';
require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/taxonomy.php';

/** phpcs:ignore
 * Displays the list.
 *
 * phpcs:ignore
 * @param array{
 *     items             : array<string, mixed>[],
 *     count             : int|null,
 *     sort_by_date_first: bool,
 *     omit_single_cat   : bool,
 *     filter_state      : array<string, mixed>|null,
 * } $args   Array of arguments.
 * @param string $lang   Language.
 * @param string $before Content to prepend to the output.
 * @param string $after  Content to append to the output.
 * @param string $id     The ID of the output markup.
 */
function echo_the_list( array $args, string $lang, string $before = '<div class="wplug-bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ): void {
	if ( ! empty( $id ) ) {
		$id = " id=\"$id\"";
	}
	$before = sprintf( $before, $id );

	$blocks = array();
	if ( 0 < $args['count'] ) {
		_echo_list_element( $blocks, $args['items'], $lang );
	} else {
		_echo_heading_list_element( $blocks, $args['items'], $lang, $args['sort_by_date_first'], $args['omit_single_cat'], $args['filter_state'] );
	}

	echo $before . "\n";  // phpcs:ignore
	foreach ( $blocks as $block ) {
		foreach ( $block as $line ) {
			echo "\t" . $line . "\n";  // phpcs:ignore
		}
	}
	echo $after . "\n";  // phpcs:ignore
}


// -----------------------------------------------------------------------------


/**
 * Displays headings and lists.
 *
 * @access private
 *
 * @param array<string[]>           $blocks             Destination.
 * @param array<string, mixed>[]    $its                Array of items.
 * @param string                    $lang               Language.
 * @param bool                      $sort_by_date_first Whether to sort by date first.
 * @param bool                      $omit_single_cat    Whether to omit categories which have one item.
 * @param array<string, mixed>|null $filter_state       Filter states.
 */
function _echo_heading_list_element( array &$blocks, array $its, string $lang, bool $sort_by_date_first, bool $omit_single_cat, ?array $filter_state ): void {
	$inst = _get_instance();
	$vs   = get_visible_root_slugs( $filter_state );

	$root_slug_to_depth    = get_root_slug_to_sub_depths();
	$sub_slug_to_last_omit = get_sub_slug_to_last_omit();
	$root_slug_to_options  = get_root_slug_to_options();

	$lk = array_key_last( $root_slug_to_depth );

	$last_cat_depth  = ( null !== $lk ) ? $root_slug_to_depth[ $lk ] : 0;
	$omitted_heading = $omit_single_cat ? _make_omitted_heading( $filter_state ) : null;

	$hr_size_orig     = 0;
	$hr_to_sub_tax    = array();
	$hr_to_uncat_last = array();
	foreach ( $root_slug_to_depth as $rs => $d ) {
		if ( ! is_null( $vs ) && ! in_array( $rs, $vs, true ) ) {
			continue;
		}
		$hr_size_orig += $d;
		for ( $i = 0; $i < $d; $i++ ) {
			$hr_to_sub_tax[]    = $inst->sub_taxes[ $rs ];
			$hr_to_uncat_last[] = $root_slug_to_options[ $rs ]['uncat_last'];
		}
	}
	$prev_cat_slug = array_pad( array(), $hr_size_orig, '' );

	$buf      = new ItemBuffer( $lang );
	$cur_year = '';
	foreach ( $its as $it ) {
		if ( $sort_by_date_first && isset( $it[ $inst::IT_DATE_NUM ] ) ) {  // @phpstan-ignore-line
			$year = substr( $it[ $inst::IT_DATE_NUM ], 0, 4 );  // @phpstan-ignore-line
			if ( $cur_year !== $year ) {
				$buf->echo( $blocks );
				_echo_heading_year( $blocks, $inst->head_level, $it[ $inst::IT_DATE_NUM ], $inst->year_format );  // @phpstan-ignore-line
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
				$buf->echo( $blocks );
			}
			for ( $h = $hr; $h < $hr_size; $h++ ) {
				if ( ! empty( $cat_slugs[ $h ] ) || ( $hr_to_uncat_last[ $h ] && $h === $hr ) ) {
					if ( is_null( $omitted_heading ) || ! $omitted_heading[ $hr_to_sub_tax[ $h ] ] ) {
						_echo_heading( $blocks, $h, $sort_by_date_first, $hr_to_sub_tax[ $h ], $cat_slugs[ $h ] );
					}
				}
			}
		}
		$prev_cat_slug = $cat_slugs;  // Clone!
		$buf->add( $it );
	}
	$buf->echo( $blocks );
}


// -----------------------------------------------------------------------------


/**
 * Creates omitted heading data.
 *
 * @access private
 *
 * @param array<string, mixed>|null $filter_state Filter states.
 * @return array<string, bool>|null Omitted heading data.
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
		if ( is_array( $s ) && 1 === count( $s ) ) {
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
 * @param array<string, mixed> $it      The item.
 * @param int                  $hr_size Heading hierarchy size.
 * @return string[] Slugs.
 */
function _get_cat_slug( array $it, int $hr_size ): array {
	$inst = _get_instance();
	/**
	 * An array of strings.
	 *
	 * @var string[] $cat_slugs
	 */
	$cat_slugs = $it[ $inst::IT_CAT_KEY ];  // @phpstan-ignore-line
	if ( $cat_slugs && count( $cat_slugs ) !== $hr_size ) {  // for invalid sort key.
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
 * @param array<string[]> $blocks             Destination.
 * @param int             $hr                 Hierarchy level.
 * @param bool            $sort_by_date_first Whether to sort by date first.
 * @param string          $sub_tax            Sub taxonomy slug.
 * @param string          $slug               Slug.
 */
function _echo_heading( array &$blocks, int $hr, bool $sort_by_date_first, string $sub_tax, string $slug ): void {
	$inst = _get_instance();

	if ( empty( $slug ) ) {
		$label = $inst->uncat_label;
		$slug  = 'uncategorized';
	} else {
		$t = get_term_by( 'slug', $slug, $sub_tax );
		if ( $t instanceof \WP_Term ) {
			if ( is_callable( $inst->term_name_getter ) ) {
				$label = ( $inst->term_name_getter )( $t );
			} else {
				$label = $t->name;
			}
		} else {
			$label = is_numeric( $slug ) ? $slug : "[$slug]";
		}
	}
	$level = $hr + $inst->head_level + ( $sort_by_date_first ? 1 : 0 );
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;

	$blocks[] = array( "<$tag class=\"$slug\" data-depth=\"$depth\">" . esc_html( $label ) . "</$tag>" );
}

/**
 * Displays a year heading markup.
 *
 * @access private
 *
 * @param array<string[]> $blocks   Destination.
 * @param int             $level    Hierarchy level.
 * @param string          $date_num String of number representing date.
 * @param string|null     $format   String format.
 */
function _echo_heading_year( array &$blocks, int $level, string $date_num, ?string $format ): void {
	$year = substr( $date_num, 0, 4 );
	if ( is_string( $format ) ) {
		$label = sprintf( $format, (int) $year );
	} else {
		$label = $year;
	}
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;

	$blocks[] = array( "<$tag class=\"year\" data-depth=\"$depth\">" . esc_html( $label ) . "</$tag>" );
}


// -----------------------------------------------------------------------------


/**
 * Displays a list markup.
 *
 * @access private
 *
 * @param array<string[]>        $blocks Destination.
 * @param array<string, mixed>[] $its    Items.
 * @param string                 $lang   Language.
 */
function _echo_list_element( array &$blocks, array $its, string $lang ): void {
	$ls  = array();
	$tag = ( count( $its ) === 1 ) ? 'ul' : 'ol';

	$ls[] = "<$tag data-bm>";
	foreach ( $its as $it ) {
		_echo_list_item( $ls, $it, $lang );
	}
	$ls[] = "</$tag>";

	$blocks[] = $ls;
}

/**
 * Displays the markup of an item.
 *
 * @access private
 *
 * @param string[]             $dest Destination.
 * @param array<string, mixed> $it   The item.
 * @param string               $lang Language.
 */
function _echo_list_item( array &$dest, array $it, string $lang ): void {
	$inst = _get_instance();

	$body = '';
	if ( ! empty( $lang ) ) {
		$body = $it[ $inst::IT_BODY . "_$lang" ] ?? '';  // @phpstan-ignore-line
	}
	if ( empty( $body ) ) {
		$body = $it[ $inst::IT_BODY ] ?? '';  // @phpstan-ignore-line
	}
	if ( ! is_string( $body ) || empty( $body ) ) {
		return;
	}

	$doi  = isset( $it[ $inst::IT_DOI ] ) && is_string( $it[ $inst::IT_DOI ] ) ? $it[ $inst::IT_DOI ] : '';  // @phpstan-ignore-line
	$_doi = '';
	if ( ! empty( $doi ) ) {
		$doi = preg_replace( '/^https?:\/\/doi\.org\//i', '', $doi );
		if ( is_string( $doi ) ) {
			$doi = trim( ltrim( $doi, '/' ) );
			if ( ! empty( $doi ) ) {
				$_url   = esc_url( "https://doi.org/$doi" );
				$_title = esc_html( $doi );
				$_doi   = "<span class=\"doi\">DOI: <a href=\"$_url\">$_title</a></span>";
			}
		}
	}

	$links = _get_links( $it );
	$_link = '';
	foreach ( $links as $link ) {
		list( $url, $title ) = $link;

		$_url   = esc_url( $url );
		$_title = empty( $title ) ? $_url : esc_html( $title );
		$_link .= "<span class=\"link\"><a href=\"$_url\">$_title</a></span>";
	}
	$_cls = esc_attr( _make_cls( $it ) );

	$dest[] = "<li class=\"$_cls\"><div>";
	$dest[] = "<span class=\"body\">$body</span> $_link $_doi";
	$dest[] = '</div></li>';
}

/**
 * Gets link arrays.
 *
 * @access private
 *
 * @param array<string, mixed> $it The item.
 * @return array{ string, string }[] Links.
 */
function _get_links( array $it ): array {
	$inst = _get_instance();

	$links = array();
	for ( $i = 0; $i <= 10; ++$i ) {
		$sf = ( 0 === $i ) ? '' : "_$i";
		// phpcs:disable
		$url   = $it[ $inst::IT_LINK_URL   . $sf ] ?? '';  // @phpstan-ignore-line
		$title = $it[ $inst::IT_LINK_TITLE . $sf ] ?? '';  // @phpstan-ignore-line
		// phpcs:enable

		if ( is_string( $url ) && is_string( $title ) && ! empty( $url ) ) {
			$links[] = array( $url, $title );
		} elseif ( 0 !== $i ) {
			// phpcs:disable
			$url   = $it[ $inst::IT_LINK_URL   . $i ] ?? '';  // @phpstan-ignore-line
			$title = $it[ $inst::IT_LINK_TITLE . $i ] ?? '';  // @phpstan-ignore-line
			// phpcs:enable

			if ( is_string( $url ) && is_string( $title ) && ! empty( $url ) ) {
				$links[] = array( $url, $title );
			}
		}
	}
	return $links;
}

/**
 * Creates CSS class of an item.
 *
 * @access private
 *
 * @param array<string, mixed> $it The item.
 * @return string CSS class.
 */
function _make_cls( array $it ): string {
	$inst = _get_instance();
	$cs   = array();

	$year = $it[ $inst::IT_DATE_NUM ] ?? '';  // @phpstan-ignore-line
	if ( ! empty( $year ) ) {
		$c    = str_replace( '%key%', $inst::KEY_YEAR, $inst->filter_cls_base );  // @phpstan-ignore-line
		$c    = str_replace( '%value%', substr( '' . $year, 0, 4 ), $c );
		$cs[] = $c;
	}
	foreach ( $inst->sub_taxes as $rs => $_sub_tax ) {
		$ss = isset( $it[ $rs ] ) && is_array( $it[ $rs ] ) ? $it[ $rs ] : array();
		foreach ( $ss as $s ) {
			if ( is_string( $s ) ) {
				$c    = str_replace( '%key%', $rs, $inst->filter_cls_base );
				$c    = str_replace( '%value%', $s, $c );
				$cs[] = $c;
			}
		}
	}
	return str_replace( '_', '-', implode( ' ', $cs ) );
}
