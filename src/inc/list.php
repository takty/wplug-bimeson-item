<?php
/**
 * Bimeson (List)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-07-28
 */

namespace wplug\bimeson_item;

function echo_the_list( array $args, string $lang, string $before = '<div class="bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ) {
	if ( ! empty( $id ) ) $id = " id=\"$id\"";
	$before = sprintf( $before, $id );

	echo $before;
	if ( is_null( $args['count'] ) ) {
		_echo_heading_list_element( $args['items'], $lang, $args['sort_by_date_first'], $args['omit_single_cat'] ? $args['filter_state'] : null );
	} else {
		_echo_list_element( $args['items'], $lang );
	}
	echo $after;
}


// -----------------------------------------------------------------------------


function _echo_heading_list_element( array $its, string $lang, bool $sort_by_date_first, ?array $filter_state ) {
	$inst = _get_instance();

	$root_slug_to_depth    = get_root_slug_to_sub_depths();
	$sub_slug_to_last_omit = get_sub_slug_to_last_omit();

	$last_cat_depth  = $root_slug_to_depth[ array_key_last( $root_slug_to_depth ) ];
	$omitted_heading = _make_omitted_heading( $filter_state );

	$hr_size_orig = 0;
	$hr_to_sub_tax = [];
	foreach ( $root_slug_to_depth as $rs => $d ) {
		$hr_size_orig += $d;
		for ( $i = 0; $i < $d; $i++ ) $hr_to_sub_tax[] = $inst->sub_taxes[ $rs ];
	}
	$prev_cat_slug = array_pad( [], $hr_size_orig, '' );

	$buf = new ItemBuffer( $lang );
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
		$hr = -1;
		$hr_size = $hr_size_orig;

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
		if ( $hr !== -1 && $hr < $hr_size ) {
			$is_cat_exist = false;
			for ( $h = $hr; $h < $hr_size; $h++ ) {
				if ( ! empty( $cat_slugs[ $h ] ) ) {
					$is_cat_exist = _is_cat_exist( $hr_to_sub_tax[ $h ], $cat_slugs[ $h ] );
					if ( $is_cat_exist ) break;
				}
			}
			if ( $is_cat_exist ) $buf->echo();
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


function _make_omitted_heading( ?array $filter_state ): ?array {
	if ( is_null( $filter_state ) ) return null;
	$inst = _get_instance();
	$ret = [];
	foreach ( $inst->sub_taxes as $rs => $sub_tax ) {
		$ret[ $sub_tax ] = false;
		$s = $filter_state[ $rs ] ?? [];
		if ( count( $s ) === 1 ) $ret[ $sub_tax ] = true;
	}
	return $ret;
}

function _get_cat_slug( array $it, int $hr_size ): array {
	$inst      = _get_instance();
	$cat_slugs = $it[ $inst::IT_CAT_KEY ];
	if ( count( $cat_slugs ) !== $hr_size ) {  // for invalid sort key
		$cat_slugs = array_pad( $cat_slugs, $hr_size, '' );
	}
	return $cat_slugs;
}

function _is_cat_exist( string $sub_tax, string $slug ): bool {
	$t = get_term_by( 'slug', $slug, $sub_tax );
	if ( ! $t ) return false;
	return true;
}


// -----------------------------------------------------------------------------


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
	echo "<$tag class=\"$slug\" data-depth=\"$depth\">$_label</$tag>\n";
}

function _echo_heading_year( int $level, string $date_num, string $format ) {
	$year = substr( $date_num, 0, 4 );
	if ( is_string( $format ) ) {
		$_label = esc_html( sprintf( $format, (int) $year ) );
	} else {
		$_label = esc_html( $year );
	}
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;
	echo "<$tag class=\"year\" data-depth=\"$depth\">$_label</$tag>\n";
}


// -----------------------------------------------------------------------------


class ItemBuffer {

	private $_items = [];
	private $_lang;

	public function __construct( string $lang ) {
		$this->_lang = $lang;
	}

	public function add( array $item ) {
		$this->_items[] = $item;
	}

	public function echo() {
		if ( ! empty( $this->_items ) ) {
			_echo_list_element( $this->_items, $this->_lang );
			$this->_items = [];
		}
	}

	public function has_item(): bool {
		return ! empty( $this->_items );
	}

}

function _echo_list_element( array $its, string $lang ) {
	$tag = ( count( $its ) === 1 ) ? 'ul' : 'ol';
	echo "<$tag data-bm>\n";
	foreach ( $its as $it ) _echo_list_item( $it, $lang );
	echo "</$tag>\n";
}

function _echo_list_item( $it, $lang ) {
	$inst = _get_instance();

	$body = '';
	if ( ! empty( $lang ) ) $body = $it[ $inst::IT_BODY . "_$lang" ] ?? '';
	if ( empty( $body ) ) $body = $it[ $inst::IT_BODY ] ?? '';
	if ( empty( $body ) ) return;

	$doi   = $it[ $inst::IT_DOI ]        ?? '';
	$url   = $it[ $inst::IT_LINK_URL ]   ?? '';
	$title = $it[ $inst::IT_LINK_TITLE ] ?? '';

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

	echo "<li class=\"$_cls\"><div>\n";
	echo "<span class=\"body\">$body</span>$_link$_doi\n";
	echo "</div></li>\n";
}

function _make_cls( array $it ): string {
	$inst = _get_instance();
	$cs   = [];

	$year = $it[ $inst::IT_DATE_NUM ] ?? '';
	if ( ! empty( $year ) ) {
		$cs[] = $inst->year_cls_base . substr( '' . $year, 0, 4 );
	}
	foreach ( $inst->sub_taxes as $rs => $sub_tax ) {
		$ss = $it[ $rs ] ?? [];
		foreach ( $ss as $s ) $cs[] = $inst->sub_tax_cls_base . "$rs-$s";
	}
	return str_replace( '_', '-', implode( ' ', $cs ) );
}
