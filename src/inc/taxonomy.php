<?php
/**
 * Bimeson (Taxonomy)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2022-06-15
 */

namespace wplug\bimeson_item;

/**
 * Initializes taxonomy.
 */
function initialize_taxonomy() {
	$inst = _get_instance();

	if ( ! taxonomy_exists( $inst->root_tax ) ) {
		register_taxonomy(
			$inst->root_tax,
			null,
			array(
				'hierarchical'       => true,
				'label'              => __( 'Category group', 'wplug_bimeson_item' ),
				'public'             => false,
				'show_ui'            => true,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => false,
				'rewrite'            => false,
			)
		);
	}
	register_taxonomy_for_object_type( $inst->root_tax, $inst::PT );

	add_action( "{$inst->root_tax}_edit_form_fields", '\wplug\bimeson_item\_cb_taxonomy_edit_form_fields', 10, 2 );
	add_action( 'edit_terms', '\wplug\bimeson_item\_cb_edit_taxonomy', 10, 2 );
	add_action( "edited_{$inst->root_tax}", '\wplug\bimeson_item\_cb_edited_taxonomy', 10, 2 );
	add_filter( 'query_vars', '\wplug\bimeson_item\_cb_query_vars_taxonomy' );

	_register_sub_tax_all();

	foreach ( $inst->sub_taxes as $sub_tax ) {
		add_action( "{$sub_tax}_edit_form_fields", '\wplug\bimeson_item\_cb_taxonomy_edit_form_fields', 10, 2 );
		add_action( "edited_{$sub_tax}", '\wplug\bimeson_item\_cb_edited_taxonomy', 10, 2 );
	}
}

/**
 * Registers all sub taxonomies.
 *
 * @access private
 */
function _register_sub_tax_all() {
	$inst  = _get_instance();
	$roots = _get_root_terms();

	foreach ( $roots as $r ) {
		$sub_tax = root_term_to_sub_tax( $r );

		$inst->sub_taxes[ $r->slug ] = $sub_tax;
		_register_sub_tax( $sub_tax, $r->name );
	}
}

/**
 * Retrieves a query variable name.
 *
 * @param string $slug Slug.
 * @return string A query variable name.
 */
function get_query_var_name( string $slug ): string {
	$inst = _get_instance();
	$name = "{$inst->sub_tax_qvar_base}{$slug}";
	return str_replace( '-', '_', $name );
}

/**
 * Converts a sub term to its input name.
 *
 * @param \WP_Term $sub_term A sub term.
 * @return string Name.
 */
function sub_term_to_name( \WP_Term $sub_term ): string {
	$id = "{$sub_term->taxonomy}_{$sub_term->slug}";
	return str_replace( '-', '_', $id );
}

/**
 * Registers a sub taxonomy.
 *
 * @access private
 *
 * @param string $tax   Taxonomy slug.
 * @param string $label Taxonomy label.
 */
function _register_sub_tax( string $tax, string $label ) {
	$inst = _get_instance();
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy(
			$tax,
			null,
			array(
				'hierarchical'       => true,
				'label'              => __( 'Category', 'wplug_bimeson_item' ) . " ($label)",
				'public'             => true,
				'show_ui'            => true,
				'rewrite'            => false,
				'sort'               => true,
				'show_admin_column'  => true,
				'show_in_quick_edit' => true,
			)
		);
	}
	$inst->sub_tax_to_terms[ $tax ] = null;
	register_taxonomy_for_object_type( $tax, $inst::PT );
}


// -----------------------------------------------------------------------------


/**
 * Retrieves root slugs.
 *
 * @return array Root slugs.
 */
function get_root_slugs(): array {
	$roots = _get_root_terms();
	return array_map(
		function ( $e ) {
			return $e->slug;
		},
		$roots
	);
}

/**
 * Converts a root term to the sub taxonomy slug.
 *
 * @param \WP_Term|string $term A root term.
 * @return string Sub taxonomy slug.
 */
function root_term_to_sub_tax( $term ): string {
	$inst = _get_instance();
	$slug = is_string( $term ) ? $term : $term->slug;
	return $inst->sub_tax_base . str_replace( '-', '_', $slug );
}

/**
 * Retrieves the array of root slugs to sub slugs.
 *
 * @return array Array of root slugs to sub slugs.
 */
function get_root_slug_to_sub_slugs(): array {
	$subs  = get_root_slug_to_sub_terms();
	$slugs = array();

	foreach ( $subs as $slug => $terms ) {
		$slugs[ $slug ] = array_map(
			function ( $e ) {
				return $e->slug;
			},
			$terms
		);
	}
	return $slugs;
}

/**
 * Retrieves the array of root slugs to sub terms.
 *
 * @return array Array of root slugs to sub terms.
 */
function get_root_slug_to_sub_terms(): array {
	$roots = _get_root_terms();
	$terms = array();

	foreach ( $roots as $idx => $r ) {
		$sub_tax           = root_term_to_sub_tax( $r );
		$terms[ $r->slug ] = _get_sub_terms( $sub_tax );
	}
	return $terms;
}

/**
 * Retrieves the array of root slugs to options.
 *
 * @return array Array of root slugs to options.
 */
function get_root_slug_to_options(): array {
	$inst  = _get_instance();
	$roots = _get_root_terms();
	$terms = array();

	foreach ( $roots as $idx => $r ) {
		$is_hidden  = get_term_meta( $r->term_id, $inst::KEY_IS_HIDDEN, true );
		$uncat_last = get_term_meta( $r->term_id, $inst::KEY_SORT_UNCAT_LAST, true );

		$terms[ $r->slug ] = array(
			'is_hidden'  => $is_hidden ? true : false,
			'uncat_last' => $uncat_last ? true : false,
		);
	}
	return $terms;
}

/**
 * Retrieves root terms.
 *
 * @access private
 *
 * @return array Root terms.
 */
function _get_root_terms(): array {
	$inst = _get_instance();
	if ( $inst->root_terms ) {
		return $inst->root_terms;
	}
	$idx_ts = array();
	$ts     = get_terms( $inst->root_tax, array( 'hide_empty' => 0 ) );
	foreach ( $ts as $t ) {
		$idx      = (int) get_term_meta( $t->term_id, '_menu_order', true );
		$idx_ts[] = array( $idx, $t );
	}
	usort(
		$idx_ts,
		function ( $a, $b ) {
			if ( $a[0] === $b[0] ) {
				return 0;
			}
			return ( $a[0] < $b[0] ) ? -1 : 1;
		}
	);
	$inst->root_terms = array_column( $idx_ts, 1 );
	return $inst->root_terms;
}

/**
 * Retrieves sub terms.
 *
 * @access private
 *
 * @param string $sub_tax Sub taxonomy slug.
 * @return array Terms.
 */
function _get_sub_terms( string $sub_tax ): array {
	$inst = _get_instance();
	if ( ! is_null( $inst->sub_tax_to_terms[ $sub_tax ] ) ) {
		return $inst->sub_tax_to_terms[ $sub_tax ];
	}
	$inst->sub_tax_to_terms[ $sub_tax ] = get_terms( $sub_tax, array( 'hide_empty' => 0 ) );
	return $inst->sub_tax_to_terms[ $sub_tax ];
}


// -----------------------------------------------------------------------------


/**
 * Retrieves the array of sub slugs to flags whether to omit last one.
 *
 * @return array Array.
 */
function get_sub_slug_to_last_omit(): array {
	$inst = _get_instance();

	$slug_to_last_omit = array();
	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		foreach ( $terms as $t ) {
			$val = get_term_meta( $t->term_id, $inst::KEY_OMIT_LAST_CAT_GROUP, true );
			if ( '1' === $val ) {
				$slug_to_last_omit[ $t->slug ] = true;
			}
		}
	}
	return $slug_to_last_omit;
}

/**
 * Retrieves the array of sub slugs to their ancestors.
 *
 * @return array Array.
 */
function get_sub_slug_to_ancestors(): array {
	$inst = _get_instance();
	$keys = array();

	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		foreach ( $terms as $t ) {
			$a = _get_sub_term_ancestors( root_term_to_sub_tax( $rs ), $t );
			if ( ! empty( $a ) ) {
				$keys[ $t->slug ] = $a;
			}
		}
	}
	return $keys;
}

/**
 * Retrieves ancestors of a sub term.
 *
 * @access private
 *
 * @param string   $sub_tax Sub taxonomy slug.
 * @param \WP_Term $term    Sub term.
 * @return array Slugs of the ancestors.
 */
function _get_sub_term_ancestors( string $sub_tax, \WP_Term $term ): array {
	$ret = array();
	while ( true ) {
		$pid = $term->parent;
		if ( 0 === $pid ) {
			break;
		}
		$term  = get_term_by( 'id', $pid, $sub_tax );
		$ret[] = $term->slug;
	}
	return array_reverse( $ret );
}

/**
 * Retrieves the array of root slugs to their sub taxonomies depths.
 *
 * @return array Array.
 */
function get_root_slug_to_sub_depths(): array {
	$rs_to_depth = array();

	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		$depth = 0;
		foreach ( $terms as $t ) {
			$d = _get_sub_tax_depth( root_term_to_sub_tax( $rs ), $t );
			if ( $depth < $d ) {
				$depth = $d;
			}
		}
		$rs_to_depth[ $rs ] = $depth;
	}
	return $rs_to_depth;
}

/**
 * Retrieves the depth of a sub taxonomy.
 *
 * @access private
 *
 * @param string   $sub_tax Sub taxonomy.
 * @param \WP_Term $term    Term.
 * @return int Depth.
 */
function _get_sub_tax_depth( string $sub_tax, \WP_Term $term ): int {
	$ret = 1;
	while ( true ) {
		$pid = $term->parent;
		if ( 0 === $pid ) {
			break;
		}
		$term = get_term_by( 'id', $pid, $sub_tax );
		$ret++;
	}
	return $ret;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'edit_terms' action.
 *
 * @access private
 *
 * @param int    $term_id Term ID.
 * @param string $tax     Taxonomy slug.
 */
function _cb_edit_taxonomy( int $term_id, string $tax ) {
	$inst = _get_instance();
	if ( $tax !== $inst->root_tax ) {
		return;
	}
	$term = get_term_by( 'id', $term_id, $tax );
	$s    = $term->slug;
	if ( 32 < strlen( $s ) + strlen( $inst->sub_tax_base ) ) {
		$s = substr( $s, 0, 32 - ( strlen( $inst->sub_tax_base ) ) );
		wp_update_term( $term_id, $tax, array( 'slug' => $s ) );
	}
	$inst->old_tax = root_term_to_sub_tax( $term );

	$terms = get_terms( $inst->old_tax, array( 'hide_empty' => 0 ) );
	foreach ( $terms as $t ) {
		$inst->old_terms[] = array(
			'slug'    => $t->slug,
			'name'    => $t->name,
			'term_id' => $t->term_id,
		);
	}
}

/**
 * Callback function for 'edited_{$taxonomy}' action.
 *
 * @access private
 *
 * @param int $term_id Term ID.
 * @param int $tt_id   Term taxonomy ID.
 */
function _cb_edited_taxonomy( int $term_id, int $tt_id ) {
	$inst = _get_instance();
	_update_term_meta_by_post( $term_id, $inst::KEY_IS_HIDDEN );
	_update_term_meta_by_post( $term_id, $inst::KEY_SORT_UNCAT_LAST );
	_update_term_meta_by_post( $term_id, $inst::KEY_OMIT_LAST_CAT_GROUP );

	$term    = get_term_by( 'term_taxonomy_id', $tt_id );
	$new_tax = root_term_to_sub_tax( $term );

	if ( $term->taxonomy !== $inst->root_tax ) {
		return;
	}
	if ( $inst->old_tax !== $new_tax ) {
		_register_sub_tax( $new_tax, $term->name );
		foreach ( $inst->old_terms as $t ) {
			wp_delete_term( $t['term_id'], $inst->old_tax );
			wp_insert_term( $t['name'], $new_tax, array( 'slug' => $t['slug'] ) );
		}
	}
}

/**
 * Callback function for 'query_vars' filter.
 *
 * @access private
 *
 * @param array $query_vars The array of allowed query variable names.
 * @return array Filtered names.
 */
function _cb_query_vars_taxonomy( array $query_vars ): array {
	$root_slugs = get_root_slugs();
	foreach ( $root_slugs as $r ) {
		$query_vars[] = get_query_var_name( $r );
	}
	return $query_vars;
}


// -----------------------------------------------------------------------------


/**
 * Callback function for '{$taxonomy}_edit_form_fields' action.
 *
 * @access private
 *
 * @param \WP_Term $term Current taxonomy term object.
 * @param string   $tax  Current taxonomy slug.
 */
function _cb_taxonomy_edit_form_fields( \WP_Term $term, string $tax ) {
	$inst = _get_instance();
	if ( $tax === $inst->root_tax ) {
		_bool_field( $term, $inst::KEY_IS_HIDDEN, __( 'List', 'wplug_bimeson_item' ), __( 'Hide from view screen', 'wplug_bimeson_item' ) );
		_bool_field( $term, $inst::KEY_SORT_UNCAT_LAST, __( 'Sort', 'wplug_bimeson_item' ), __( 'Sort uncategorized items last', 'wplug_bimeson_item' ) );
	} else {
		_bool_field( $term, $inst::KEY_OMIT_LAST_CAT_GROUP, __( 'List', 'wplug_bimeson_item' ), __( 'Omit the heading of the last category group', 'wplug_bimeson_item' ) );
	}
}

/**
 * Outputs a bool input field.
 *
 * @access private
 *
 * @param \WP_Term $term    Current term.
 * @param string   $key     Input name.
 * @param string   $heading Heading.
 * @param string   $label   Label.
 */
function _bool_field( \WP_Term $term, string $key, string $heading, string $label ) {
	$val = get_term_meta( $term->term_id, $key, true );
	?>
	<tr class="form-field">
		<th style="padding-bottom: 20px;"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $heading ); ?></label></th>
		<td style="padding-bottom: 20px;">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" <?php checked( $val, 1 ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
		</td>
	</tr>
	<?php
}

/**
 * Updates a term meta.
 *
 * @access private
 *
 * @param int    $term_id Term ID.
 * @param string $key     Input key.
 */
function _update_term_meta_by_post( int $term_id, string $key ) {
	if ( empty( $_POST[ sanitize_key( $key ) ] ) ) {  // phpcs:ignore
		delete_term_meta( $term_id, $key );
	} else {
		update_term_meta( $term_id, $key, 1 );
	}
}


// -----------------------------------------------------------------------------


/**
 * Processes items for registering taxonomies and terms.
 * Called by the importer.
 *
 * @param array $items          Items.
 * @param bool  $add_taxonomies Whether to add taxonomies.
 * @param bool  $add_terms      Whether to add terms.
 */
function process_terms( array $items, bool $add_taxonomies = false, bool $add_terms = false ): array {
	$inst         = _get_instance();
	$roots_subs   = get_root_slug_to_sub_slugs();
	$new_tax_term = array();
	$new_term     = array();
	$msgs         = array();

	foreach ( $items as $item ) {
		foreach ( $item as $key => $vals ) {
			if ( '_' === $key[0] ) {
				continue;
			}
			if ( ! is_array( $vals ) ) {
				$vals = array( $vals );
			}
			if ( ! isset( $roots_subs[ $key ] ) ) {
				if ( ! isset( $new_tax_term[ $key ] ) ) {
					$new_tax_term[ $key ] = array();
				}
				foreach ( $vals as $v ) {
					if ( ! in_array( $v, $new_tax_term[ $key ], true ) ) {
						$new_tax_term[ $key ][] = $v;
					}
				}
			} else {
				$slugs = $roots_subs[ $key ];
				if ( ! isset( $new_term[ $key ] ) ) {
					$new_term[ $key ] = array();
				}
				foreach ( $vals as $v ) {
					if ( ! in_array( $v, $slugs, true ) && ! in_array( $v, $new_term[ $key ], true ) ) {
						$new_term[ $key ][] = $v;
					}
				}
			}
		}
	}
	if ( $add_taxonomies ) {
		foreach ( $new_tax_term as $rs => $terms ) {
			wp_insert_term( $rs, $inst->root_tax, array( 'slug' => $rs ) );

			$sub_tax = root_term_to_sub_tax( $rs );
			_register_sub_tax( $sub_tax, $sub_tax );

			/* translators: New taxonomy (category group). */
			$m      = sprintf( __( 'Add new category group "%s"', 'wplug_bimeson_item' ), $rs );
			$msgs[] = $m;

			if ( $add_terms ) {
				foreach ( $terms as $t ) {
					$ret = wp_insert_term( $t, $sub_tax, array( 'slug' => $t ) );
					if ( is_array( $ret ) ) {
						/* translators: 1: New category, 2: Taxonomy (category group) added to. */
						$m      = sprintf( __( 'Add new category "%1$s" to "%2$s"', 'wplug_bimeson_item' ), $t, $rs );
						$msgs[] = $m;
					}
				}
			}
		}
	}
	if ( $add_terms ) {
		foreach ( $new_term as $rs => $terms ) {
			$sub_tax = root_term_to_sub_tax( $rs );
			if ( ! taxonomy_exists( $sub_tax ) ) {
				continue;
			}
			foreach ( $terms as $t ) {
				$ret = wp_insert_term( $t, $sub_tax, array( 'slug' => $t ) );
				if ( is_array( $ret ) ) {
					/* translators: 1: New category, 2: Taxonomy (category group) added to. */
					$m      = sprintf( __( 'Add new category "%1$s" to "%2$s"', 'wplug_bimeson_item' ), $t, $rs );
					$msgs[] = $m;
				}
			}
		}
	}
	return $msgs;
}
