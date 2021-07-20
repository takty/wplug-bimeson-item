<?php
/**
 * Bimeson (Taxonomy)
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_item;

function initialize_taxonomy() {
	$inst = _get_instance();

	if ( ! taxonomy_exists( $inst->root_tax ) ) {
		register_taxonomy( $inst->root_tax, null, [
			'hierarchical'       => true,
			'label'              => __( 'Category group', 'bimeson_item' ),
			'public'             => false,
			'show_ui'            => true,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false,
			'rewrite'            => false,
		] );
	}
	register_taxonomy_for_object_type( $inst->root_tax, $inst::PT );

	add_action( "{$inst->root_tax}_edit_form_fields", '\wplug\bimeson_item\_cb_taxonomy_edit_form_fields', 10, 2 );
	add_action( "edit_terms",                         '\wplug\bimeson_item\_cb_edit_taxonomy', 10, 2 );
	add_action( "edited_{$inst->root_tax}",           '\wplug\bimeson_item\_cb_edited_taxonomy', 10, 2 );
	add_filter( 'query_vars',                         '\wplug\bimeson_item\_cb_query_vars_taxonomy' );

	_register_sub_tax_all();

	foreach ( $inst->sub_taxes as $sub_tax ) {
		add_action( "{$sub_tax}_edit_form_fields", '\wplug\bimeson_item\_cb_taxonomy_edit_form_fields', 10, 2 );
		add_action( "edited_{$sub_tax}",           '\wplug\bimeson_item\_cb_edited_taxonomy', 10, 2 );
	}
}

function _register_sub_tax_all() {
	$inst  = _get_instance();
	$roots = _get_root_terms();

	foreach ( $roots as $r ) {
		$sub_tax = root_term_to_sub_tax( $r );
		$inst->sub_taxes[ $r->slug ] = $sub_tax;
		_register_sub_tax( $sub_tax, $r->name );
	}
}

function get_query_var_name( string $slug ): string {
	$inst = _get_instance();
	$name = "{$inst->sub_tax_qvar_base}{$slug}";
	return str_replace( '-', '_', $name );
}

function sub_term_to_name( \WP_Term $sub_term ): string {
	$id = "{$sub_term->taxonomy}_{$sub_term->slug}";
	return str_replace( '-', '_', $id );
}

function _register_sub_tax( string $tax, string $name ) {
	$inst = _get_instance();
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy( $tax, null, [
			'hierarchical'       => true,
			'label'              => __( 'Category', 'bimeson_item' ) . " ($name)",
			'public'             => true,
			'show_ui'            => true,
			'rewrite'            => false,
			'sort'               => true,
			'show_admin_column'  => true,
			'show_in_quick_edit' => true,
		] );
	}
	$inst->sub_tax_to_terms[ $tax ] = null;
	register_taxonomy_for_object_type( $tax, $inst::PT );
}


// -----------------------------------------------------------------------------


function get_root_slugs(): array {
	$roots = _get_root_terms();
	return array_map( function ( $e ) { return $e->slug; }, $roots );
}

function root_term_to_sub_tax( $term ): string {
	$inst = _get_instance();
	$slug = is_string( $term ) ? $term : $term->slug;
	return $inst->sub_tax_base . str_replace( '-', '_', $slug );
}

function get_root_slug_to_sub_slugs( bool $do_omit_first = false, bool $do_hide = false ): array {
	$subs  = get_root_slug_to_sub_terms( $do_omit_first, $do_hide );
	$slugs = [];
	foreach ( $subs as $slug => $terms ) {
		$slugs[ $slug ] = array_map( function ( $e ) { return $e->slug; }, $terms );
	}
	return $slugs;
}

function get_root_slug_to_sub_terms( bool $do_omit_first = false, bool $do_hide = false ): array {
	$inst  = _get_instance();
	$roots = _get_root_terms();
	$terms = [];
	foreach( $roots as $idx => $r ) {
		if ( $do_omit_first && $idx === 0 ) continue;
		if ( $do_hide ) {
			$val_hide = get_term_meta( $r->term_id, $inst::KEY_IS_HIDDEN, true );
			if ( $val_hide ) continue;
		}
		$sub_tax = root_term_to_sub_tax( $r );
		$terms[ $r->slug ] = _get_sub_terms( $sub_tax );
	}
	return $terms;
}

function _get_root_terms(): array {
	$inst = _get_instance();
	if ( $inst->root_terms ) return $inst->root_terms;

	$idx_ts = [];
	$ts     = get_terms( $inst->root_tax, [ 'hide_empty' => 0 ] );
	foreach ( $ts as $t ) {
		$idx      = (int) get_term_meta( $t->term_id, '_menu_order', true );
		$idx_ts[] = [ $idx, $t ];
	}
	usort( $idx_ts, function ( $a, $b ) {
		if ( $a[0] === $b[0] ) return 0;
		return ( $a[0] < $b[0] ) ? -1 : 1;
	} );
	$inst->root_terms = array_column( $idx_ts, 1 );
	return $inst->root_terms;
}

function _get_sub_terms( string $sub_tax ): array {
	$inst = _get_instance();
	if ( ! is_null( $inst->sub_tax_to_terms[ $sub_tax ] ) ) return $inst->sub_tax_to_terms[ $sub_tax ];
	$inst->sub_tax_to_terms[ $sub_tax ] = get_terms( $sub_tax, [ 'hide_empty' => 0 ] );
	return $inst->sub_tax_to_terms[ $sub_tax ];
}


// -----------------------------------------------------------------------------


function get_sub_slug_to_last_omit(): array {
	$inst = _get_instance();

	$slug_to_last_omit = [];
	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		foreach ( $terms as $t ) {
			$val = get_term_meta( $t->term_id, $inst::KEY_LAST_CAT_OMITTED, true );
			if ( $val === '1' ) $slug_to_last_omit[ $t->slug ] = true;
		}
	}
	return $slug_to_last_omit;
}

function get_sub_slug_to_ancestors(): array {
	$inst = _get_instance();
	$keys = [];

	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		foreach ( $terms as $t ) {
			$a = _get_sub_slug_to_ancestors( root_term_to_sub_tax( $rs ), $t );
			if ( ! empty( $a ) ) $keys[ $t->slug ] = $a;
		}
	}
	return $keys;
}

function _get_sub_slug_to_ancestors( string $sub_tax, \WP_Term $term ): array {
	$ret = [];
	while ( true ) {
		$pid = $term->parent;
		if ( $pid === 0 ) break;
		$term = get_term_by( 'id', $pid, $sub_tax );
		$ret[] = $term->slug;
	}
	return array_reverse( $ret );
}

function get_root_slug_to_sub_depths(): array {
	$rs_to_depth = [];

	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		$depth = 0;
		foreach ( $terms as $t ) {
			$d = _get_sub_tax_depth( root_term_to_sub_tax( $rs ), $t );
			if ( $depth < $d ) $depth = $d;
		}
		$rs_to_depth[ $rs ] = $depth;
	}
	return $rs_to_depth;
}

function _get_sub_tax_depth( string $sub_tax, \WP_Term $term ): int {
	$ret = 1;
	while ( true ) {
		$pid = $term->parent;
		if ( $pid === 0 ) break;
		$term = get_term_by( 'id', $pid, $sub_tax );
		$ret++;
	}
	return $ret;
}


// Callback Functions ----------------------------------------------------------


function _cb_edit_taxonomy( int $term_id, string $tax ) {
	$inst = _get_instance();
	if ( $tax !== $inst->root_tax ) return;

	$term = get_term_by( 'id', $term_id, $tax );
	$s = $term->slug;
	if ( 32 < strlen( $s ) + strlen( $inst->sub_tax_base ) ) {
		$s = substr( $s, 0, 32 - ( strlen( $inst->sub_tax_base ) ) );
		wp_update_term( $term_id, $tax, [ 'slug' => $s ] );
	}

	$inst->old_tax = root_term_to_sub_tax( $term );

	$terms = get_terms( $inst->old_tax, [ 'hide_empty' => 0 ] );
	foreach ( $terms as $t ) {
		$inst->old_terms[] = [ 'slug' =>  $t->slug, 'name' => $t->name, 'term_id' => $t->term_id ];
	}
}

function _cb_edited_taxonomy( int $term_id, int $tt_id ) {
	$inst = _get_instance();
	_update_term_meta_by_post( $term_id, $inst::KEY_LAST_CAT_OMITTED );
	_update_term_meta_by_post( $term_id, $inst::KEY_IS_HIDDEN );

	$term    = get_term_by( 'term_taxonomy_id', $tt_id );
	$new_tax = root_term_to_sub_tax( $term );

	if ( $term->taxonomy !== $inst->root_tax ) return;

	if ( $inst->old_tax !== $new_tax ) {
		_register_sub_tax( $new_tax, $term->name );
		foreach ( $inst->old_terms as $t ) {
			wp_delete_term( $t['term_id'], $inst->old_tax );
			wp_insert_term( $t['name'], $new_tax, [ 'slug' => $t['slug'] ] );
		}
	}
}

function _cb_query_vars_taxonomy( array $query_vars ): array {
	$root_slugs = get_root_slugs();
	foreach ( $root_slugs as $r ) {
		$query_vars[] = get_query_var_name( $r );
	}
	return $query_vars;
}


// -----------------------------------------------------------------------------


function _cb_taxonomy_edit_form_fields( \WP_Term $term, string $tax ) {
	$inst = _get_instance();
	if ( $tax === $inst->root_tax ) {
		_bool_field( $term, $inst::KEY_IS_HIDDEN, __( 'Hide from view screen', 'bimeson_item' ) );
	} else {
		_bool_field( $term, $inst::KEY_LAST_CAT_OMITTED, __( 'Omit the last category group', 'bimeson_item' ) );
	}
}

function _bool_field( \WP_Term $term, string $key, string $label ) {
	$val = get_term_meta( $term->term_id, $key, true );
	?>
	<tr class="form-field">
		<th style="padding-bottom: 20px;"><label for="<?php echo $key ?>"><?php esc_html_e( 'Filter', 'bimeson_item' ); ?></label></th>
		<td style="padding-bottom: 20px;">
			<label>
				<input type="checkbox" name="<?php echo $key ?>" id="<?php echo $key ?>" <?php checked( $val, 1 ) ?>/>
				<?php echo esc_html( $label ); ?>
			</label>
		</td>
	</tr>
	<?php
}

function _update_term_meta_by_post( int $term_id, string $key ) {
	if ( empty( $_POST[ $key ] ) ) {
		delete_term_meta( $term_id, $key );
	} else {
		update_term_meta( $term_id, $key, 1 );
	}
}


// -----------------------------------------------------------------------------


function process_terms( array $items, bool $add_taxonomies = false, bool $add_terms = false ) {
	$inst         = _get_instance();
	$roots_subs   = get_root_slug_to_sub_slugs();
	$new_tax_term = [];
	$new_term     = [];

	foreach ( $items as $item ) {
		foreach ( $item as $key => $vals ) {
			if ( $key[0] === '_' ) continue;
			if ( ! is_array( $vals ) ) $vals = [ $vals ];
			if ( ! isset( $roots_subs[ $key ] ) ) {
				if ( ! isset( $new_tax_term[ $key ] ) ) $new_tax_term[ $key ] = [];
				foreach ( $vals as $v ) {
					if ( ! in_array( $v, $new_tax_term[ $key ], true ) ) $new_tax_term[ $key ][] = $v;
				}
			} else {
				$slugs = $roots_subs[ $key ];
				if ( ! isset( $new_term[ $key ] ) ) $new_term[ $key ] = [];
				foreach ( $vals as $v ) {
					if ( ! in_array( $v, $slugs, true ) && ! in_array( $v, $new_term[ $key ], true ) ) $new_term[ $key ][] = $v;
				}
			}
		}
	}
	if ( $add_taxonomies ) {
		foreach ( $new_tax_term as $slug => $terms ) {
			wp_insert_term( $slug, $inst->root_tax, [ 'slug' => $slug ] );

			$sub_tax = root_term_to_sub_tax( $slug );
			_register_sub_tax( $sub_tax, $sub_tax );

			if ( $add_terms ) {
				foreach ( $terms as $t ) {
					$ret = wp_insert_term( $t, $sub_tax, [ 'slug' => $t ] );
					if ( is_array( $ret ) ) {
						if ( ! isset( $roots_subs[ $slug ] ) ) $roots_subs[ $slug ] = [];
						$roots_subs[ $slug ][] = $t;
					}
				}
			}
		}
	}
	if ( $add_terms ) {
		foreach ( $new_term as $slug => $terms ) {
			$sub_tax = root_term_to_sub_tax( $slug );
			if ( ! taxonomy_exists( $sub_tax ) ) continue;

			foreach ( $terms as $t ) {
				$ret = wp_insert_term( $t, $sub_tax, [ 'slug' => $t ] );
				if ( is_array( $ret ) ) {
					if ( ! isset( $roots_subs[ $slug ] ) ) $roots_subs[ $slug ] = [];
					$roots_subs[ $slug ][] = $t;
				}
			}
		}
	}
}
