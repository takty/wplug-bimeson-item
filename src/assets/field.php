<?php
/**
 * Custom Field Utilities
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_item;

function save_post_meta( int $post_id, string $key, $filter = null, $default = null ) {
	$val = isset( $_POST[ $key ] ) ? $_POST[ $key ] : null;
	if ( $filter !== null && $val !== null ) {
		$val = $filter( $val );
	}
	if ( empty( $val ) ) {
		if ( $default === null ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $default;
	}
	update_post_meta( $post_id, $key, $val );
}

function save_post_meta_with_wp_filter( int $post_id, string $key, $filter_name = null, $default = null ) {
	$val = isset( $_POST[ $key ] ) ? $_POST[ $key ] : null;
	if ( $filter_name !== null && $val !== null ) {
		$val = apply_filters( $filter_name, $val );
	}
	if ( empty( $val ) ) {
		if ( $default === null ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $default;
	}
	update_post_meta( $post_id, $key, $val );
}

function output_input_row( string $label, string $key, $val, $type = 'text' ) {
	wp_enqueue_style( 'wplug-bimeson-item-field' );
	$val = isset( $val ) ? esc_attr( $val ) : '';
?>
	<div class="wplug-bimeson-item-field-single">
		<label>
			<span><?php echo esc_html( $label ) ?></span>
			<input <?php name_id( $key ) ?> type="<?php echo esc_attr( $type ) ?>" value="<?php echo $val ?>" size="64">
		</label>
	</div>
<?php
}

function name_id( string $key ) {
	$_key = esc_attr( $key );
	echo "name=\"$_key\" id=\"$_key\"";
}

function normalize_date( string $str ): string {
	$str = mb_convert_kana( $str, 'n', 'utf-8' );
	$nums = preg_split( '/\D/', $str );
	$vals = [];
	foreach ( $nums as $num ) {
		$v = (int) trim( $num );
		if ( $v !== 0 ) $vals[] = $v;
	}
	if ( 3 <= count( $vals ) ) {
		$str = sprintf( '%04d-%02d-%02d', $vals[0], $vals[1], $vals[2] );
	} else if ( count( $vals ) === 2 ) {
		$str = sprintf( '%04d-%02d', $vals[0], $vals[1] );
	} else if ( count( $vals ) === 1 ) {
		$str = sprintf( '%04d', $vals[0] );
	}
	return $str;
}


// Custom Meta Box -------------------------------------------------------------


function add_rich_editor_meta_box( string $key, string $label, string $screen, string $context = 'advanced', array $opts = [] ) {
	$priority = isset( $opts['priority'] ) ? $opts['priority'] : 'default';
	\add_meta_box(
		$key . '_mb', $label,
		function ( $post ) use ( $key, $opts ) {
			wp_nonce_field( $key, "{$key}_nonce" );
			$value = get_post_meta( $post->ID, $key, true );
			wp_editor( $value, $key, $opts );
		},
		$screen, $context, $priority
	);
}

function save_rich_editor_meta_box( int $post_id, string $key ) {
	if ( ! isset( $_POST["{$key}_nonce"] ) ) return;
	if ( ! wp_verify_nonce( $_POST["{$key}_nonce"], $key ) ) return;

	save_post_meta_with_wp_filter( $post_id, $key, 'content_save_pre' );
}


// Admin Columns ---------------------------------------------------------------


function set_admin_columns( string $post_type, array $all_columns, array $sortable_columns = [] ) {
	$DEFAULT_COLUMNS = [
		'cb'     => '<input type="checkbox" />',
		'title'  => _x( 'Title', 'column name', 'default' ),
		'author' => __( 'Author', 'default' ),
		'date'   => __( 'Date', 'default' ),
		'order'  => __( 'Order', 'default' ),
	];
	$columns = [];
	$styles  = [];
	$val_fns = [];

	foreach ( $all_columns as $c ) {
		if ( is_array( $c ) ) {
			if ( taxonomy_exists( $c['name'] ) ) {
				$l = empty( $c['label'] ) ? get_taxonomy( $c['name'] )->labels->name : $c['label'];
				$columns[ 'taxonomy-' . $c['name'] ] = $l;
			} else {
				$columns[ $c['name'] ] = empty( $c['label'] ) ? $c['name'] : $c['label'];
			}
			// Column Styles
			if ( isset( $c['name'] ) && isset( $c['width'] ) ) {
				$tax = taxonomy_exists( $c['name'] ) ? 'taxonomy-' : '';
				$styles[] = ".column-$tax{$c['name']} {width: {$c['width']} !important;}";
			}
			// Column Value Functions
			if ( isset( $c['value'] ) && is_callable( $c['value'] ) ) {
				$val_fns[ $c['name'] ] = $c['value'];
			}
		} else {
			if ( taxonomy_exists( $c ) ) {
				$columns[ 'taxonomy-' . $c ] = get_taxonomy( $c )->labels->name;
			} else {
				$columns[ $c ] = $DEFAULT_COLUMNS[ $c ];
			}
		}
	}
	add_filter( "manage_edit-{$post_type}_columns", function () use ( $columns ) {
		return $columns;
	} );
	add_action( 'admin_head', function () use ( $post_type, $styles ) {
		if ( get_query_var( 'post_type' ) === $post_type ) {
			?><style>
			<?php echo implode( "\n", $styles ); ?>
			</style><?php
		}
	} );
	add_action( "manage_{$post_type}_posts_custom_column", function ( $column_name, $post_id ) use ( $val_fns ) {
		if ( isset( $val_fns[ $column_name ] ) ) {
			$fn = $val_fns[ $column_name ];
			echo call_user_func( $fn, get_post_meta( $post_id, $column_name, true ) );
		}
	}, 10, 2 );

	if ( count( $sortable_columns ) > 0 ) set_admin_columns_sortable( $post_type, $sortable_columns );
}

function set_admin_columns_sortable( string $post_type, array $sortable_columns ) {
	$names = [];
	$types = [];
	foreach ( $sortable_columns as $c ) {
		if ( is_array( $c ) ) {
			$names[] = $c['name'];
			if ( isset( $c['type'] ) ) $types[ $c['name'] ] = $c['type'];
		} else {
			$names[] = $c;
		}
	}
	add_filter( "manage_edit-{$post_type}_sortable_columns", function ( $cols ) use ( $names ) {
		foreach ( $names as $name ) {
			$tax = taxonomy_exists( $name ) ? 'taxonomy-' : '';
			$cols[ $tax . $name ] = $name;
		}
		return $cols;
	} );
	add_filter( 'request', function ( $vars ) use ( $names, $types ) {
		if ( ! isset( $vars['orderby'] ) ) return $vars;
		$key = $vars['orderby'];
		if ( in_array( $key, $names, true ) && ! taxonomy_exists( $key ) ) {
			$orderby = [ 'meta_key' => $key, 'orderby' => 'meta_value' ];
			if ( isset( $types[ $key ] ) ) {
				$orderby['meta_type'] = $types[ $key ];
			}
			$vars = array_merge( $vars, $orderby );
		}
		return $vars;
	} );
}
