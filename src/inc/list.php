<?php
/**
 * Bimeson (List)
 *
 * @author Takuto Yanagida
 * @version 2021-07-14
 */

namespace wplug\bimeson_post;



// -----------------------------------------------------------------------------


function _echo_list_element( $ps, $lang ) {
	$tag = ( count( $ps ) === 1 ) ? 'ul' : 'ol';
	echo "<$tag data-bm>\n";
	foreach ( $ps as $p ) _echo_list_item( $p, $lang );
	echo "</$tag>\n";
}

function _echo_list_item( $p, $lang ) {
	$inst = _get_instance();

	$body = '';
	if ( ! empty( $lang ) ) $body = get_the_sub_content( "_post_content_$lang", $p->ID );
	if ( empty( $body ) ) $body = $p->post_content;
	$body = _make_content( $body );

	$year  = get_post_meta( $p->ID, $inst::IT_DATE_NUM,   true );
	$doi   = get_post_meta( $p->ID, $inst::IT_DOI,        true );
	$url   = get_post_meta( $p->ID, $inst::IT_LINK_URL,   true );
	$title = get_post_meta( $p->ID, $inst::IT_LINK_TITLE, true );

	$_year = '';
	if ( ! empty( $year ) ) {
		$_year = esc_attr( substr( '' . $year, 0, 4 ) );
	}
	$_doi = '';
	if ( ! empty( $doi ) ) {
		$_url   = esc_url( "https://doi.org/$doi" );
		$_title = esc_html( $doi );
		$_doi   = "<span class=\"doi\">DOI: <a href=\"$_url\">$_title</a></span>";
	}
	$_link = '';
	if ( ! empty( $url ) ) {
		$_url   = esc_url( $url );
		$_title = empty( $title ) ? $_url : esc_html( $title );
		$_link  = "<span class=\"link\"><a href=\"$_url\">$_title</a></span>";
	}
	$_cls  = esc_attr( _make_cls( $p ) );
	$_edit = _make_edit_tag( $p );

	echo "<li class=\"$_cls\" data-year=\"$_year\"><div>\n";
	echo "<span class=\"body\">$body</span>$_link$_doi\n";
	echo "$_edit_tag</div></li>\n";
}

function _make_cls( $p ) {
	$inst = _get_instance();
	$cs   = [];

	foreach ( $inst->sub_taxes as $rs => $sub_tax ) {
		$ts = get_the_terms( $p->ID, $sub_tax );
		if ( $ts === false ) continue;
		foreach ( $ts as $t ) $cs[] = "$sub_tax-{$t->slug}";
	}
	return str_replace( '_', '-', implode( ' ', $cs ) );
}

function _make_content( $c ) {
	$c = apply_filters( 'the_content', $c );  // Shortcodes are expanded here.
	$c = str_replace( ']]>', ']]&gt;', $c );
	return $c;
}

function _make_edit_tag( $p ) {
	if ( is_user_logged_in() && current_user_can( 'edit_post', $p->ID ) ) {
		$_url = admin_url( "post.php?post={$p->ID}&action=edit" );
		return "<a href=\"$_url\">EDIT</a>";
	}
	return '';
}
