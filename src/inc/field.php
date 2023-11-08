<?php
/**
 * Custom Field Utilities
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2023-06-25
 */

namespace wplug\bimeson_item;

/**
 * Stores a post meta value.
 *
 * @param int      $post_id Post ID.
 * @param string   $key     Meta key.
 * @param callable $filter  Filter function.
 * @param mixed    $def     Default value.
 */
function save_post_meta( int $post_id, string $key, $filter = null, $def = null ): void {
	$val = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;  // phpcs:ignore
	if ( null !== $filter && null !== $val ) {
		$val = $filter( $val );
	}
	if ( empty( $val ) ) {
		if ( null === $def ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $def;
	}
	update_post_meta( $post_id, $key, $val );
}

/**
 * Stores a post meta value after applying WordPress filters.
 *
 * @param int     $post_id   Post ID.
 * @param string  $key       Meta key.
 * @param ?string $hook_name The name of the filter hook.
 * @param mixed   $def       Default value.
 */
function save_post_meta_with_wp_filter( int $post_id, string $key, ?string $hook_name = null, $def = null ): void {
	$val = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;  // phpcs:ignore
	if ( null !== $hook_name && null !== $val ) {
		$val = apply_filters( $hook_name, $val );
	}
	if ( empty( $val ) ) {
		if ( null === $def ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $def;
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
function output_input_row( string $label, string $key, $val, string $type = 'text' ): void {
	wp_enqueue_style( 'wplug-bimeson-item-field' );
	$val = is_string( $val ) ? $val : '';
	?>
	<div class="wplug-bimeson-item-field-single">
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<input name="<?php echo esc_attr( $key ); ?> id="<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $val ); ?>" size="64">
		</label>
	</div>
	<?php
}


// -----------------------------------------------------------------------------


/**
 * Adds a rich editor meta box.
 *
 * @psalm-suppress ArgumentTypeCoercion
 *
 * @param string                     $key     Post meta key.
 * @param string                     $title   Title of the meta box.
 * @param string|null                $screen  The screen or screens on which to show the box.
 * @param 'advanced'|'normal'|'side' $context The context within the screen where the box should display.
 * @param array<string, mixed>       $opts    Options for wp_editor.
 */
function add_rich_editor_meta_box( string $key, string $title, ?string $screen = null, string $context = 'advanced', array $opts = array() ): void {
	$priority = isset( $opts['priority'] ) ? $opts['priority'] : 'default';
	\add_meta_box(
		$key . '_mb',
		$title,
		function ( \WP_Post $post ) use ( $key, $opts ) {
			wp_nonce_field( $key, "{$key}_nonce" );
			$val = get_post_meta( $post->ID, $key, true );
			$val = is_string( $val ) ? $val : '';
			wp_editor( $val, $key, $opts );  // @phpstan-ignore-line
		},
		$screen,
		$context,
		$priority  // @phpstan-ignore-line
	);
}

/**
 * Stores the content of the rich editor meta box.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Post meta key.
 */
function save_rich_editor_meta_box( int $post_id, string $key ): void {
	$nonce = $_POST[ "{$key}_nonce" ] ?? null;  // phpcs:ignore
	if ( ! is_string( $nonce ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( $nonce ), $key ) ) {
		return;
	}
	/** phpcs:ignore
	 *
	 * @psalm-suppress UndefinedFunction
	 */
	save_post_meta_with_wp_filter( $post_id, $key, 'content_save_pre' );
}


// -----------------------------------------------------------------------------


/**
 * Sets admin columns.
 *
 * @param string                             $post_type   Post type.
 * @param array<string|array<string, mixed>> $all_columns Array of columns.
 */
function set_admin_columns( string $post_type, array $all_columns ): void {
	$def_cols = array(
		'cb'     => '<input type="checkbox">',
		'title'  => _x( 'Title', 'column name', 'default' ),
		'author' => __( 'Author', 'default' ),
		'date'   => __( 'Date', 'default' ),
		'order'  => __( 'Order', 'default' ),
	);

	$cols    = array();
	$styles  = array();
	$val_fns = array();

	foreach ( $all_columns as $c ) {
		if ( is_array( $c ) ) {
			if ( isset( $c['name'] ) && is_string( $c['name'] ) ) {
				if ( taxonomy_exists( $c['name'] ) ) {
					if ( empty( $c['label'] ) ) {
						$tx = \get_taxonomy( $c['name'] );
						if ( $tx ) {
							$l = $tx->labels->name;
						} else {
							$l = $c['name'];
						}
					} else {
						$l = $c['label'];
					}
					$cols[ 'taxonomy-' . $c['name'] ] = $l;
				} else {
					$cols[ $c['name'] ] = empty( $c['label'] ) ? $c['name'] : $c['label'];
				}
				// Column Styles.
				if ( isset( $c['width'] ) && is_string( $c['width'] ) ) {
					$tax      = taxonomy_exists( $c['name'] ) ? 'taxonomy-' : '';
					$styles[] = ".column-$tax{$c['name']} {width: {$c['width']} !important;}";
				}
				// Column Value Functions.
				if ( isset( $c['value'] ) && is_callable( $c['value'] ) ) {
					$val_fns[ $c['name'] ] = $c['value'];
				}
			}
		} else {  // phpcs:ignore
			if ( taxonomy_exists( $c ) ) {
				$tx = \get_taxonomy( $c );
				if ( $tx ) {
					$cols[ "taxonomy-$c" ] = $tx->labels->name;
				}
			} else {
				$cols[ $c ] = $def_cols[ $c ];
			}
		}
	}
	add_filter(
		"manage_edit-{$post_type}_columns",
		function () use ( $cols ) {
			return $cols;
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
		function ( string $column_name, int $post_id ) use ( $val_fns ) {
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
 * @param string                             $post_type        Post type.
 * @param array<string|array<string, mixed>> $sortable_columns Array of sortable columns.
 */
function set_admin_columns_sortable( string $post_type, array $sortable_columns ): void {
	$names = array();
	$types = array();
	foreach ( $sortable_columns as $c ) {
		if ( is_array( $c ) && is_string( $c['name'] ) ) {
			$names[] = $c['name'];
			if ( isset( $c['type'] ) ) {
				$types[ $c['name'] ] = $c['type'];
			}
		} elseif ( is_string( $c ) ) {
			$names[] = $c;
		}
	}
	add_filter(
		"manage_edit-{$post_type}_sortable_columns",
		function ( array $cols ) use ( $names ) {
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
