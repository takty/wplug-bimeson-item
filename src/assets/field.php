<?php
/**
 * Custom Field Utilities
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

namespace wplug\bimeson_item;

/**
 * Stores a post meta value.
 *
 * @param int      $post_id Post ID.
 * @param string   $key     Meta key.
 * @param callable $filter  Filter function.
 * @param mixed    $default Default value.
 */
function save_post_meta( int $post_id, string $key, $filter = null, $default = null ) {
	$val = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;  // phpcs:ignore
	if ( null !== $filter && null !== $val ) {
		$val = $filter( $val );
	}
	if ( empty( $val ) ) {
		if ( null === $default ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $default;
	}
	update_post_meta( $post_id, $key, $val );
}

/**
 * Stores a post meta value after applying WordPress filters.
 *
 * @param int      $post_id   Post ID.
 * @param string   $key       Meta key.
 * @param callable $hook_name The name of the filter hook.
 * @param mixed    $default   Default value.
 */
function save_post_meta_with_wp_filter( int $post_id, string $key, $hook_name = null, $default = null ) {
	$val = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;  // phpcs:ignore
	if ( null !== $hook_name && null !== $val ) {
		$val = apply_filters( $hook_name, $val );
	}
	if ( empty( $val ) ) {
		if ( null === $default ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $default;
	}
	update_post_meta( $post_id, $key, $val );
}

/**
 * Outputs an input field.
 *
 * @param string $label Label.
 * @param string $key   Input name.
 * @param mixed  $val   Value.
 * @param string $type  Input field type.
 */
function output_input_row( string $label, string $key, $val, $type = 'text' ) {
	wp_enqueue_style( 'wplug-bimeson-item-field' );
	$val = isset( $val ) ? $val : '';
	?>
	<div class="wplug-bimeson-item-field-single">
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<input <?php name_id( $key ); ?> type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $val ); ?>" size="64">
		</label>
	</div>
	<?php
}

/**
 * Echos name and id attributes.
 *
 * @param string $key The key.
 */
function name_id( string $key ) {
	$_key = esc_attr( $key );
	echo "name=\"$_key\" id=\"$_key\"";  // phpcs:ignore
}

/**
 * Normalizes date string.
 *
 * @param string $str A string.
 * @return string Normalized string.
 */
function normalize_date( string $str ): string {
	$str  = mb_convert_kana( $str, 'n', 'utf-8' );
	$nums = preg_split( '/\D/', $str );
	$vals = array();
	foreach ( $nums as $num ) {
		$v = (int) trim( $num );
		if ( 0 !== $v && is_numeric( $v ) ) {
			$vals[] = $v;
		}
	}
	if ( 3 <= count( $vals ) ) {
		$str = sprintf( '%04d-%02d-%02d', $vals[0], $vals[1], $vals[2] );
	} elseif ( count( $vals ) === 2 ) {
		$str = sprintf( '%04d-%02d', $vals[0], $vals[1] );
	} elseif ( count( $vals ) === 1 ) {
		$str = sprintf( '%04d', $vals[0] );
	}
	return $str;
}


// -----------------------------------------------------------------------------


/**
 * Adds a rich editor meta box.
 *
 * @param string  $key     Post meta key.
 * @param string  $title   Title of the meta box.
 * @param ?string $screen  The screen or screens on which to show the box.
 * @param string  $context The context within the screen where the box should display.
 * @param array   $opts    Options for wp_editor.
 */
function add_rich_editor_meta_box( string $key, string $title, ?string $screen = null, string $context = 'advanced', array $opts = array() ) {
	$priority = isset( $opts['priority'] ) ? $opts['priority'] : 'default';
	\add_meta_box(
		$key . '_mb',
		$title,
		function ( $post ) use ( $key, $opts ) {
			wp_nonce_field( $key, "{$key}_nonce" );
			$value = get_post_meta( $post->ID, $key, true );
			wp_editor( $value, $key, $opts );
		},
		$screen,
		$context,
		$priority
	);
}

/**
 * Stores the content of the rich editor meta box.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Post meta key.
 */
function save_rich_editor_meta_box( int $post_id, string $key ) {
	if ( ! isset( $_POST[ "{$key}_nonce" ] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( $_POST[ "{$key}_nonce" ] ), $key ) ) {
		return;
	}
	save_post_meta_with_wp_filter( $post_id, $key, 'content_save_pre' );
}


// -----------------------------------------------------------------------------


/**
 * Sets admin columns.
 *
 * @param string $post_type   Post type.
 * @param array  $all_columns Array of columns.
 */
function set_admin_columns( string $post_type, array $all_columns ) {
	$default_columns = array(
		'cb'     => '<input type="checkbox">',
		'title'  => _x( 'Title', 'column name', 'default' ),
		'author' => __( 'Author', 'default' ),
		'date'   => __( 'Date', 'default' ),
		'order'  => __( 'Order', 'default' ),
	);

	$columns = array();
	$styles  = array();
	$val_fns = array();

	foreach ( $all_columns as $c ) {
		if ( is_array( $c ) ) {
			if ( taxonomy_exists( $c['name'] ) ) {
				$l = empty( $c['label'] ) ? get_taxonomy( $c['name'] )->labels->name : $c['label'];

				$columns[ 'taxonomy-' . $c['name'] ] = $l;
			} else {
				$columns[ $c['name'] ] = empty( $c['label'] ) ? $c['name'] : $c['label'];
			}
			// Column Styles.
			if ( isset( $c['name'] ) && isset( $c['width'] ) ) {
				$tax      = taxonomy_exists( $c['name'] ) ? 'taxonomy-' : '';
				$styles[] = ".column-$tax{$c['name']} {width: {$c['width']} !important;}";
			}
			// Column Value Functions.
			if ( isset( $c['value'] ) && is_callable( $c['value'] ) ) {
				$val_fns[ $c['name'] ] = $c['value'];
			}
		} else {
			if ( taxonomy_exists( $c ) ) {
				$columns[ 'taxonomy-' . $c ] = get_taxonomy( $c )->labels->name;
			} else {
				$columns[ $c ] = $default_columns[ $c ];
			}
		}
	}
	add_filter(
		"manage_edit-{$post_type}_columns",
		function () use ( $columns ) {
			return $columns;
		}
	);
	add_action(
		'admin_head',
		function () use ( $post_type, $styles ) {
			if ( get_query_var( 'post_type' ) === $post_type ) {
				?>
				<style>
				<?php echo implode( "\n", $styles ); // phpcs:ignore ?>
				</style>
				<?php
			}
		}
	);
	add_action(
		"manage_{$post_type}_posts_custom_column",
		function ( $column_name, $post_id ) use ( $val_fns ) {
			if ( isset( $val_fns[ $column_name ] ) ) {
				$fn = $val_fns[ $column_name ];
				echo call_user_func( $fn, get_post_meta( $post_id, $column_name, true ) );  // phpcs:ignore
			}
		},
		10,
		2
	);
}

/**
 * Sets admin columns sortable.
 *
 * @param string $post_type        Post type.
 * @param array  $sortable_columns Array of sortable columns.
 */
function set_admin_columns_sortable( string $post_type, array $sortable_columns ) {
	$names = array();
	$types = array();
	foreach ( $sortable_columns as $c ) {
		if ( is_array( $c ) ) {
			$names[] = $c['name'];
			if ( isset( $c['type'] ) ) {
				$types[ $c['name'] ] = $c['type'];
			}
		} else {
			$names[] = $c;
		}
	}
	add_filter(
		"manage_edit-{$post_type}_sortable_columns",
		function ( $cols ) use ( $names ) {
			foreach ( $names as $name ) {
				$tax = taxonomy_exists( $name ) ? 'taxonomy-' : '';

				$cols[ $tax . $name ] = $name;
			}
			return $cols;
		}
	);
	add_filter(
		'request',
		function ( $vars ) use ( $names, $types ) {
			if ( ! isset( $vars['orderby'] ) ) {
				return $vars;
			}
			$key = $vars['orderby'];
			if ( in_array( $key, $names, true ) && ! taxonomy_exists( $key ) ) {
				$orderby = array(
					'meta_key' => $key,  // phpcs:ignore
					'orderby'  => 'meta_value',
				);
				if ( isset( $types[ $key ] ) ) {
					$orderby['meta_type'] = $types[ $key ];
				}
				$vars = array_merge( $vars, $orderby );
			}
			return $vars;
		}
	);
}
