<?php
/**
 * Bimeson (Taxonomy)
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 */

namespace wplug\bimeson_post;
class Bimeson_Taxonomy {

	const DEFAULT_TAXONOMY     = 'bm_cat';
	const DEFAULT_SUB_TAX_BASE = 'bm_cat_';

	private $_post_type;
	private $_labels;
	private $_tax_root;
	private $_tax_sub_base;

	private $_old_taxonomy = [];
	private $_old_terms = [];

	public function __construct( $post_type, $labels, $taxonomy = false, $sub_tax_base = false ) {
		$this->_post_type    = $post_type;
		$this->_labels       = $labels;
		$this->_tax_root     = ( $taxonomy === false )     ? self::DEFAULT_TAXONOMY     : $taxonomy;
		$this->_tax_sub_base = ( $sub_tax_base === false ) ? self::DEFAULT_SUB_TAX_BASE : $sub_tax_base;

		register_taxonomy( $this->_tax_root, $this->_post_type, [
			'hierarchical'       => false,
			'label'              => $this->_labels['taxonomy'],
			'public'             => false,
			'show_ui'            => true,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false,
			'rewrite'            => false,
		] );
		register_taxonomy_for_object_type( $this->_tax_root, $this->_post_type );
		// \st\ordered_term\make_terms_ordered( [ $this->_tax_root ] );
		$this->_register_sub_tax_all();

		add_action( "edit_terms",                [ $this, '_cb_edit_taxonomy' ], 10, 2 );
		add_action( "edited_{$this->_tax_root}", [ $this, '_cb_edited_taxonomy' ], 10, 2 );
		add_filter( 'query_vars',                [ $this, '_cb_query_vars' ] );
	}

	private function _register_sub_tax_all() {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		$sub_taxes = [];
		foreach ( $roots as $r ) {
			$sub_tax = $this->term_to_taxonomy( $r );
			$sub_taxes[] = $sub_tax;
			$this->register_sub_tax( $sub_tax, $r->name );
		}
		// \st\ordered_term\make_terms_ordered( $sub_taxes );
	}

	private function _get_query_var_name( $slug ) {
		$slug = str_replace( '-', '_', $slug );
		return "{$this->_tax_sub_base}{$slug}";
	}

	public function register_sub_tax( $tax, $name ) {
		register_taxonomy( $tax, $this->_post_type, [
			'hierarchical'      => true,
			'label'             => "{$this->_labels['taxonomy']} ($name)",
			'public'            => true,
			'show_ui'           => true,
			'rewrite'           => false,
			'sort'              => true,
			'show_admin_column' => true
		] );
	}

	public function get_taxonomy() {
		return $this->_tax_root;
	}

	public function term_to_taxonomy( $term ) {
		$slug = '';
		if ( is_string( $term ) ) {
			$slug = $term;
		} else {
			$slug = $term->slug;
		}
		$slug = str_replace( '-', '_', $slug );
		return $this->_tax_sub_base . $slug;
	}

	public function get_root_slugs() {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		return array_map( function ( $e ) { return $e->slug; }, $roots );
	}

	public function get_sub_taxonomies() {
		$rss = $this->get_root_slugs();
		$slugs = [];
		foreach( $rss as $rs ) $slugs[] = $this->term_to_taxonomy( $rs );
		return $slugs;
	}

	public function get_root_slugs_to_sub_slugs() {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		$slugs = [];
		foreach( $roots as $r ) {
			$terms = get_terms( $this->term_to_taxonomy( $r ), [ 'hide_empty' => 0 ]  );
			$slugs[ $r->slug ] = array_map( function ( $e ) { return $e->slug; }, $terms );;
		}
		return $slugs;
	}

	public function get_root_slugs_to_sub_terms() {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		$terms = [];
		foreach( $roots as $r ) {
			$terms[ $r->slug ] = get_terms( $this->term_to_taxonomy( $r ), [ 'hide_empty' => 0 ]  );
		}
		return $terms;
	}

	public function show_tax_checkboxes( $terms, $slug ) {
		$v = get_query_var( $this->_get_query_var_name( $slug ) );
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
			$_id  = esc_attr( str_replace( '_', '-', "{$this->_tax_sub_base}{$t->slug}" ) );
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


	// Callback Functions ------------------------------------------------------


	public function _cb_edit_taxonomy( $term_id, $taxonomy ) {
		if ( $taxonomy !== $this->_tax_root ) return;

		$term = get_term_by( 'id', $term_id, $taxonomy );
		$s = $term->slug;
		if ( 32 < strlen( $s ) + strlen( $this->_tax_sub_base ) ) {
			$s = substr( $s, 0, 32 - ( strlen( $this->_tax_sub_base ) ) );
			wp_update_term( $term_id, $taxonomy, [ 'slug' => $s ] );
		}

		$this->_old_taxonomy = $this->term_to_taxonomy( $term );

		$terms = get_terms( $this->_old_taxonomy, [ 'hide_empty' => 0 ]  );
		foreach ( $terms as $t ) {
			$this->_old_terms[] = [ 'slug' =>  $t->slug, 'name' => $t->name, 'term_id' => $t->term_id ];
		}
	}

	public function _cb_edited_taxonomy( $term_id, $tt_id ) {
		$term = get_term_by( 'id', $term_id, $this->_tax_root );
		$new_taxonomy = $this->term_to_taxonomy( $term );

		if ( $this->_old_taxonomy !== $new_taxonomy ) {
			$this->register_sub_tax( $new_taxonomy, $term->name );
			foreach ( $this->_old_terms as $t ) {
				wp_delete_term( $t['term_id'], $this->_old_taxonomy );
				wp_insert_term( $t['name'], $new_taxonomy, [ 'slug' => $t['slug'] ] );
			}
		}
	}

	public function _cb_query_vars( $query_vars ) {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ] );
		foreach ( $roots as $r ) {
			$query_vars[] = $this->_get_query_var_name( $r->slug );
		}
		return $query_vars;
	}

	static private function _boolean_form( $term, $key, $label ) {
		$val = get_term_meta( $term->term_id, $key, true );
		?>
		<tr class="form-field">
			<th style="padding-top: 20px; padding-bottom: 20px;"><label for="<?php echo $key ?>"><?php echo esc_html( $label ) ?></label></th>
			<td style="padding-top: 20px; padding-bottom: 20px;">
				<input type="checkbox" name="<?php echo $key ?>" id="<?php echo $key ?>" <?php checked( $val, 1 ) ?>/>
			</td>
		</tr>
		<?php
	}

}
