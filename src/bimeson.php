<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida
 * @version 2021-07-12
 */

namespace wplug\bimeson_post;

require_once __DIR__ . '/asset/util.php';
require_once __DIR__ . '/taxonomy.php';
require_once __DIR__ . '/admin.php';

class Bimeson {

	const PT_BIMESON      = 'bimeson';  // Each post means publication (bimeson)

	const FLD_BODY        = '_body';
	const FLD_DATE        = '_date';
	const FLD_DOI         = '_doi';
	const FLD_LINK_URL    = '_link_url';
	const FLD_LINK_TITLE  = '_link_title';

	const FLD_DATE_NUM    = '_date_num';
	const FLD_IMPORT_FROM = '_import_from';
	const FLD_DIGEST      = '_digest';

	static private $_instance = null;
	static public function get_instance() {
		if ( self::$_instance === null ) self::$_instance = new Bimeson();
		return self::$_instance;
	}

	private $_tax   = null;
	private $_admin = null;
	private $_additional_langs;
	private $_post_lang_tax;
	private $_default_post_lang_slug;

	private function __construct() {}

	public function initialize( $additional_langs = [], $taxonomy = false, $sub_tax_base = false, $post_lang_tax = 'post_lang', $default_post_lang_slug = 'en' ) {
		$this->_additional_langs       = $additional_langs;
		$this->_post_lang_tax          = $post_lang_tax;
		$this->_default_post_lang_slug = $default_post_lang_slug;

		$this->_register_post_type();
		$this->_add_shortcodes();

		if ( $this->_post_lang_tax !== false ) {
			register_taxonomy_for_object_type( $this->_post_lang_tax, Bimeson::PT_BIMESON );
		}
		$this->_tax = new Bimeson_Taxonomy( self::PT_BIMESON, [ 'taxonomy' => '分類' ], $taxonomy, $sub_tax_base );
		if ( is_admin() ) $this->_admin = new Bimeson_Admin( $this->_tax, $this->_additional_langs, $post_lang_tax, $default_post_lang_slug );
	}

	private function _register_post_type() {
		register_post_type( self::PT_BIMESON, [
			'label'         => '業績',
			'labels'        => [],
			'public'        => true,
			'show_ui'       => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-analytics',
			'has_archive'   => false,
			'rewrite'       => false,
			'supports'      => [ 'title', 'editor' ],
		] );
	}

	private function _add_shortcodes() {
		add_shortcode( 'publication', function ( $atts, $content = null ) {
			$params = [
				'date'        => '',
				'date_start'  => '',
				'date_end'    => '',
				'count'       => '-1',
				'show_filter' => ''
			];
			$rss = $this->_tax->get_root_slugs();
			foreach ( $rss as $rs ) {
				$params[ $rs ] = '';
			}
			$atts = shortcode_atts( $params, $atts );

			$tq = [];
			$al = '';
			if ( class_exists( '\st\Multilang' ) ) {
				$pls = [ $this->_default_post_lang_slug ];
				$sl = \st\Multilang::get_instance()->get_site_lang();
				if ( in_array( $sl, $this->_additional_langs, true ) ) {
					$al = $sl;
					$pls[] = $al;
				}
				$tq[] = [ 'taxonomy' => $this->_post_lang_tax, 'field' => 'slug', 'terms' => $pls ];
			}

			foreach ( $rss as $rs ) {
				if ( empty( $atts[ $rs ] ) ) continue;
				$sub_tax = $this->_tax->term_to_taxonomy( $rs );
				$tmp = str_replace( ' ', '', $atts[ $rs ] );
				$slugs = explode( ',', $tmp );
				$tq[] = [ 'taxonomy' => $sub_tax, 'field' => 'slug', 'terms' => $slugs ];
			}

			$mq = [];
			if ( ! empty( $atts['date'] ) ) {
				$date_s = $this->_normalize_date( $atts['date'], '0' );
				$date_e = $this->_normalize_date( $atts['date'], '9' );

				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
					'value'   => [ $date_s, $date_e ],
				];
			}
			if ( ! empty( $atts['date_start'] ) && ! empty( $atts['date_end'] ) ) {
				$date_s = $this->_normalize_date( $atts['date_start'], '0' );
				$date_e = $this->_normalize_date( $atts['date_end'], '9' );

				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
					'value'   => [ $date_s, $date_e ],
				];
			}
			if ( ! empty( $atts['date_start'] ) && empty( $atts['date_end'] ) ) {
				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => '>=',
					'value'   => $this->_normalize_date( $atts['date_start'], '0' ),
				];
			}
			if ( empty( $atts['date_start'] ) && ! empty( $atts['date_end'] ) ) {
				$mq['meta_date'] = [
					'key'     => self::FLD_DATE_NUM,
					'type'    => 'NUMERIC',
					'compare' => '<=',
					'value'   => $this->_normalize_date( $atts['date_end'], '9' ),
				];
			}
			if ( ! isset( $mq['meta_date'] ) ) {
				$mq['meta_date'] = [
					'key'  => self::FLD_DATE_NUM,
					'type' => 'NUMERIC',
				];
			}
			$ps = get_posts( [
				'post_type'      => self::PT_BIMESON,
				'posts_per_page' => intval( $atts['count'] ),
				'tax_query'      => $tq,
				'meta_query'     => $mq,
				'orderby'        => [ 'meta_date' => 'desc', 'date' => 'desc' ]
			] );
			if ( count( $ps ) === 0 ) return '';

			ob_start();
			if ( ! empty( $atts['show_filter'] ) ) {
				$tmp = str_replace( ' ', '', $atts['show_filter'] );
				$slugs = explode( ',', $tmp );
				$this->the_filter( $slugs );
			}

			if ( ! is_null( $content ) ) {
				$content = str_replace( '<p></p>', '', balanceTags( $content, true ) );
				echo $content;
			}
			$this->_echo_list( $ps, $al );
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;
		} );
	}

	private function _normalize_date( $val, $pad_num ) {
		$val = str_replace( ['-', '/'], '', trim( $val ) );
		return str_pad( $val, 8, $pad_num, STR_PAD_RIGHT );
	}


	// -------------------------------------------------------------------------


	public function get_sub_taxonomies() {
		return $this->_tax->get_sub_taxonomies();
	}

	public function enqueue_script( $url_to = false ) {
		if ( ! is_admin() ) {
			if ( $url_to === false ) $url_to = get_file_uri( __DIR__ );
			$url_to = untrailingslashit( $url_to );
			wp_enqueue_style(  'bimeson-post-filter', $url_to . '/asset/css/filter.min.css' );
			wp_enqueue_script( 'bimeson-post-filter', $url_to . '/asset/js/filter.min.js' );
		}
	}

	public function the_filter( $slugs ) {
		$slug_to_terms = $this->_tax->get_root_slugs_to_sub_terms();
		foreach ( $slug_to_terms as $slug => $terms ) {
			if ( $slugs[0] === 'all' || in_array( $slug, $slugs, true ) ) {
				$this->_tax->show_tax_checkboxes( $terms, $slug );
			}
		}
	}

	public function show_tax_checkboxes( $terms, $slug ) {
		$v = get_query_var( $this->_tax->get_query_var_name( $slug ) );
		$_slug = esc_attr( $slug );
		$qvals = empty( $v ) ? [] : explode( ',', $v );
	?>
		<div class="bm-list-filter-cat" data-key="<?php echo $_slug ?>">
			<div class="bm-list-filter-cat-inner">
				<input type="checkbox" class="bm-list-filter-switch tgl tgl-light" id="<?php echo $_slug ?>" <?php if ( ! empty( $qvals ) ) echo 'checked' ?>></input>
				<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
				<div class="bm-list-filter-cbs">
	<?php
		foreach ( $terms as $t ) :
			$_id  = esc_attr( $this->_tax->term_to_taxonomy( $t ) );
			$_val = esc_attr( $t->slug );
			if ( class_exists( '\st\Multilang' ) ) {
				$_name = esc_html( \st\Multilang::get_instance()->get_term_name( $t ) );
			} else {
				$_name = esc_html( $t->name );
			}
	?>
					<label>
						<input type="checkbox" id="<?php echo $_id ?>" <?php if ( in_array( $t->slug, $qvals, true ) ) echo 'checked' ?> data-val="<?php echo $_val ?>"></input>
						<?php echo $_name ?>
					</label>
	<?php
		endforeach;
	?>
				</div>
			</div>
		</div>
	<?php
	}

	private function _echo_list( $ps, $al ) {
		if ( count( $ps ) === 1 ) {
			echo "<ul data-bm=\"on\">\n";
			foreach ( $ps as $p ) $this->_echo_list_item( $p, $al );
			echo "</ul>\n";
		} else {
			echo "<ol data-bm=\"on\">\n";
			foreach ( $ps as $p ) $this->_echo_list_item( $p, $al );
			echo "</ol>\n";
		}
	}

	private function _echo_list_item( $p, $al ) {
		$body = '';
		if ( ! empty( $al ) ) $body = get_the_sub_content( "_post_content_$al", $p->ID );
		if ( empty( $body ) ) $body = $p->post_content;

		$doi    = get_post_meta( $p->ID, self::FLD_DOI,        true);
		$lurl   = get_post_meta( $p->ID, self::FLD_LINK_URL,   true);
		$ltitle = get_post_meta( $p->ID, self::FLD_LINK_TITLE, true);

		$_link = '';
		if ( ! empty( $lurl ) ) {
			$_url   = esc_url( $lurl );
			$_title = empty( $ltitle ) ? $_url : esc_html( $ltitle );
			$_link  = "<span class=\"link\"><a href=\"$_url\">$_title</a></span>";
		}
		$_doi = '';
		if ( ! empty( $doi ) ) {
			$_url   = esc_url( "https://doi.org/$doi" );
			$_title = esc_html( $doi );
			$_doi   = "<span class=\"doi\">DOI: <a href=\"$_url\">$_title</a></span>";
		}

		$_cls = esc_attr( implode( ' ', $this->_make_cls_array( $p ) ) );
		$_edit_tag = $this->_make_edit_tag( $p );

		echo "<li class=\"$_cls\"><div>";
		echo_content( $body );
		echo "$_link$_doi $_edit_tag</div></li>\n";
	}

	private function _make_cls_array( $p ) {
		$cs = [];
		$rss = $this->_tax->get_root_slugs();
		foreach ( $rss as $rs ) {
			$sub_tax = $this->_tax->term_to_taxonomy( $rs );
			$ts = get_the_terms( $p->ID, $sub_tax );
			if ( $ts === false ) continue;
			foreach ( $ts as $t ) {
				$cs[] = str_replace( '_', '-', "{$sub_tax}-{$t->slug}" );
			}
		}
		return $cs;
	}

	private function _make_edit_tag( $p ) {
		$_tag = '';
		if ( is_user_logged_in() && current_user_can( 'edit_post', $p->ID ) ) {
			$_url = admin_url( "post.php?post={$p->ID}&action=edit" );
			$_tag = "<a href=\"$_url\">EDIT</a>";
		}
		return $_tag;
	}

}
