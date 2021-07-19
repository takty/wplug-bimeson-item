<?php
/**
 * Bimeson (Shortcode)
 *
 * @author Takuto Yanagida
 * @version 2021-07-15
 */

namespace wplug\bimeson_post;

function add_shortcode() {
	\add_shortcode( 'publication', function ( $atts, $content = null ) {
		$inst = _get_instance();
		$params = [
			'date'        => '',
			'date_start'  => '',
			'date_end'    => '',
			'count'       => '-1',
			'show_filter' => ''
		];
		$rss = get_root_slugs();
		foreach ( $rss as $rs ) {
			$params[ $rs ] = '';
		}
		$atts = shortcode_atts( $params, $atts );

		$tq = [];
		$al = '';
		if ( class_exists( '\st\Multilang' ) ) {
			$pls = [ $inst->default_lang ];
			$sl = \st\Multilang::get_instance()->get_site_lang();
			if ( in_array( $sl, $inst->additional_langs, true ) ) {
				$al = $sl;
				$pls[] = $al;
			}
			$tq[] = [ 'taxonomy' => $inst->lang_tax, 'field' => 'slug', 'terms' => $pls ];
		}

		foreach ( $rss as $rs ) {
			if ( empty( $atts[ $rs ] ) ) continue;
			$sub_tax = root_term_to_sub_tax( $rs );
			$tmp = str_replace( ' ', '', $atts[ $rs ] );
			$slugs = explode( ',', $tmp );
			$tq[] = [ 'taxonomy' => $sub_tax, 'field' => 'slug', 'terms' => $slugs ];
		}

		$mq = [];
		if ( ! empty( $atts['date'] ) ) {
			$date_s = _normalize_date( $atts['date'], '0' );
			$date_e = _normalize_date( $atts['date'], '9' );

			$mq['meta_date'] = [
				'key'     => $inst::IT_DATE_NUM,
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
				'value'   => [ $date_s, $date_e ],
			];
		}
		if ( ! empty( $atts['date_start'] ) && ! empty( $atts['date_end'] ) ) {
			$date_s = _normalize_date( $atts['date_start'], '0' );
			$date_e = _normalize_date( $atts['date_end'], '9' );

			$mq['meta_date'] = [
				'key'     => $inst::IT_DATE_NUM,
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
				'value'   => [ $date_s, $date_e ],
			];
		}
		if ( ! empty( $atts['date_start'] ) && empty( $atts['date_end'] ) ) {
			$mq['meta_date'] = [
				'key'     => $inst::IT_DATE_NUM,
				'type'    => 'NUMERIC',
				'compare' => '>=',
				'value'   => _normalize_date( $atts['date_start'], '0' ),
			];
		}
		if ( empty( $atts['date_start'] ) && ! empty( $atts['date_end'] ) ) {
			$mq['meta_date'] = [
				'key'     => $inst::IT_DATE_NUM,
				'type'    => 'NUMERIC',
				'compare' => '<=',
				'value'   => _normalize_date( $atts['date_end'], '9' ),
			];
		}
		if ( ! isset( $mq['meta_date'] ) ) {
			$mq['meta_date'] = [
				'key'  => $inst::IT_DATE_NUM,
				'type' => 'NUMERIC',
			];
		}
		$ps = get_posts( [
			'post_type'      => $inst::PT,
			'posts_per_page' => intval( $atts['count'] ),
			'tax_query'      => $tq,
			'meta_query'     => $mq,
			'orderby'        => [ 'meta_date' => 'desc', 'date' => 'desc' ]
		] );
		if ( count( $ps ) === 0 ) return '';

		ob_start();
		if ( ! empty( $atts['show_filter'] ) ) {
			$tmp = str_replace( ' ', '', $atts['show_filter'] );
			$filter_slugs = explode( ',', $tmp );
			if ( count( $filter_slugs) ) echo_filter( $filter_slugs );
		}

		if ( ! is_null( $content ) ) {
			$content = str_replace( '<p></p>', '', balanceTags( $content, true ) );
			echo $content;
		}
		_echo_list_element( $ps, $al );
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	} );
}

function _normalize_date( $val, $pad_num ) {
	$val = str_replace( ['-', '/'], '', trim( $val ) );
	return str_pad( $val, 8, $pad_num, STR_PAD_RIGHT );
}
