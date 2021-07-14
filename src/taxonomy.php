<?php
/**
 * Bimeson (Taxonomy)
 *
 * @author Takuto Yanagida
 * @version 2021-07-13
 */

namespace wplug\bimeson_post;

class Bimeson_Taxonomy {

	const DEFAULT_TAXONOMY     = 'bm_cat';
	const DEFAULT_SUB_TAX_BASE = 'bm_cat_';

	private $_post_type;
	private $_labels;
	private $_tax_root;
	private $_tax_sub_base;

	private $_old_tax   = [];
	private $_old_terms = [];

	private $_root_terms       = null;
	private $_sub_tax_to_terms = [];

	public function __construct( $post_type, $labels, $taxonomy = false, $sub_tax_base = false ) {
		$this->_post_type    = $post_type;
		$this->_labels       = $labels;
		$this->_tax_root     = ( $taxonomy === false )     ? self::DEFAULT_TAXONOMY     : $taxonomy;
		$this->_tax_sub_base = ( $sub_tax_base === false ) ? self::DEFAULT_SUB_TAX_BASE : $sub_tax_base;

		if ( ! taxonomy_exists( $this->_tax_root ) ) {
			register_taxonomy( $this->_tax_root, null, [
				'hierarchical'       => true,
				'label'              => $this->_labels['taxonomy'],
				'public'             => false,
				'show_ui'            => true,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => false,
				'rewrite'            => false,
			] );
		}
		register_taxonomy_for_object_type( $this->_tax_root, $this->_post_type );
		// \st\ordered_term\make_terms_ordered( [ $this->_tax_root ] );
		$this->_register_sub_tax_all();

		add_action( "edit_terms",                [ $this, '_cb_edit_taxonomy' ], 10, 2 );
		add_action( "edited_{$this->_tax_root}", [ $this, '_cb_edited_taxonomy' ], 10, 2 );
		add_filter( 'query_vars',                [ $this, '_cb_query_vars' ] );
	}

	private function _register_sub_tax_all() {
		$roots = $this->_get_root_terms();
		$sub_taxes = [];
		foreach ( $roots as $r ) {
			$sub_tax = $this->term_to_taxonomy( $r );
			$sub_taxes[] = $sub_tax;
			$this->register_sub_tax( $sub_tax, $r->name );
		}
		// \st\ordered_term\make_terms_ordered( $sub_taxes );
	}

	public function get_query_var_name( $slug ) {
		$name = "{$this->_tax_sub_base}{$slug}";
		return str_replace( '_', '-', $name );
	}

	public function register_sub_tax( $tax, $name ) {
		if ( ! taxonomy_exists( $tax ) ) {
			register_taxonomy( $tax, null, [
				'hierarchical'       => true,
				'label'              => "{$this->_labels['taxonomy']} ($name)",
				'public'             => true,
				'show_ui'            => true,
				'rewrite'            => false,
				'sort'               => true,
				'show_admin_column'  => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => false
			] );
		}
		$this->_sub_tax_to_terms[ $tax ] = false;
		register_taxonomy_for_object_type( $tax, $this->_post_type );
	}


	// -------------------------------------------------------------------------


	public function get_taxonomy() {
		return $this->_tax_root;
	}

	private function _get_root_terms() {
		if ( $this->_root_terms ) return $this->_root_terms;
		$this->_root_terms = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ] );
		return $this->_root_terms;
	}

	public function get_root_slugs() {
		$roots = $this->_get_root_terms();
		return array_map( function ( $e ) { return $e->slug; }, $roots );
	}

	public function term_to_taxonomy( $term ) {
		$slug = is_string( $term ) ? $term : $term->slug;
		return $this->_tax_sub_base . str_replace( '-', '_', $slug );
	}

	public function get_sub_taxonomies() {
		$rss = $this->get_root_slugs();
		$slugs = [];
		foreach( $rss as $rs ) $slugs[] = $this->term_to_taxonomy( $rs );
		return $slugs;
	}

	public function get_root_slugs_to_sub_terms() {
		$roots = $this->_get_root_terms();
		$terms = [];
		foreach( $roots as $r ) {
			$sub_tax = $this->term_to_taxonomy( $r );
			$terms[ $r->slug ] = $this->_get_sub_terms( $sub_tax );
		}
		return $terms;
	}

	public function get_root_slugs_to_sub_slugs() {
		$subs = $this->get_root_slugs_to_sub_terms();
		$slugs = [];
		foreach ( $subs as $slug => $terms ) {
			$slugs[ $slug ] = array_map( function ( $e ) { return $e->slug; }, $terms );
		}
		return $slugs;
	}

	private function _get_sub_terms( $sub_tax ) {
		if ( $this->_sub_tax_to_terms[ $sub_tax ] !== false ) return $this->_sub_tax_to_terms[ $sub_tax ];
		$this->_sub_tax_to_terms[ $sub_tax ] = get_terms( $sub_tax, [ 'hide_empty' => 0 ] );
		return $this->_sub_tax_to_terms[ $sub_tax ];
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

		$this->_old_tax = $this->term_to_taxonomy( $term );

		$terms = get_terms( $this->_old_tax, [ 'hide_empty' => 0 ]  );
		foreach ( $terms as $t ) {
			$this->_old_terms[] = [ 'slug' =>  $t->slug, 'name' => $t->name, 'term_id' => $t->term_id ];
		}
	}

	public function _cb_edited_taxonomy( $term_id, $tt_id ) {
		$term = get_term_by( 'id', $term_id, $this->_tax_root );
		$new_taxonomy = $this->term_to_taxonomy( $term );

		if ( $this->_old_tax !== $new_taxonomy ) {
			$this->register_sub_tax( $new_taxonomy, $term->name );
			foreach ( $this->_old_terms as $t ) {
				wp_delete_term( $t['term_id'], $this->_old_tax );
				wp_insert_term( $t['name'], $new_taxonomy, [ 'slug' => $t['slug'] ] );
			}
		}
	}

	public function _cb_query_vars( $query_vars ) {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ] );
		foreach ( $roots as $r ) {
			$query_vars[] = $this->get_query_var_name( $r->slug );
		}
		return $query_vars;
	}

}
