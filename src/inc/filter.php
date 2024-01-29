<?php
/**
 * Filter (Common)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2024-01-29
 */

declare(strict_types=1);

namespace wplug\bimeson_item;

require_once __DIR__ . '/../bimeson.php';
require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Initializes the filter.
 */
function initialize_filter(): void {
	add_filter( 'query_vars', '\wplug\bimeson_item\_cb_query_vars_filter' );
}

/**
 * Callback function for 'query_vars' filter.
 *
 * @access private
 *
 * @param string[] $query_vars The array of allowed query variable names.
 * @return string[] Filtered array.
 */
function _cb_query_vars_filter( array $query_vars ): array {
	$inst         = _get_instance();
	$query_vars[] = get_query_var_name( $inst::KEY_YEAR );  // @phpstan-ignore-line
	return $query_vars;
}

/**
 * Displays the filter.
 *
 * @param array<string, mixed>|null $filter_state Array of filter states.
 * @param string[]                  $years_exist  Array of existing years.
 * @param string                    $before       Content to prepend to the output.
 * @param string                    $after        Content to append to the output.
 * @param string                    $for_at       Attribute of 'for'.
 */
function echo_the_filter( ?array $filter_state, array $years_exist, string $before = '<div class="wplug-bimeson-filter" hidden%s>', string $after = '</div>', string $for_at = 'bml' ): void {
	wp_enqueue_style( 'wplug-bimeson-filter' );
	wp_enqueue_script( 'wplug-bimeson-filter' );

	if ( ! empty( $for_at ) ) {
		$for_at = " for=\"$for_at\"";
	}
	$before = sprintf( $before, $for_at );

	echo $before;  // phpcs:ignore
	echo_filter( $filter_state, $years_exist );
	echo $after;  // phpcs:ignore
}


// -----------------------------------------------------------------------------


/**
 * Displays a filter itself.
 *
 * @param array<string, mixed>|null $filter_state Array of filter states.
 * @param string[]                  $years_exist  Array of existing years.
 */
function echo_filter( ?array $filter_state, array $years_exist ): void {
	$inst        = _get_instance();
	$rs_to_terms = get_root_slug_to_sub_terms();
	$vs          = get_visible_root_slugs( $filter_state );
	$state       = _get_filter_state_from_query();

	if ( ! empty( $years_exist ) ) {
		_echo_year_select( $years_exist, $state );
	}
	if ( is_null( $vs ) ) {
		foreach ( $rs_to_terms as $rs => $terms ) {
			_echo_tax_checkboxes( $rs, $terms, $state );
		}
	} else {
		foreach ( $rs_to_terms as $rs => $terms ) {
			$fs = isset( $filter_state[ $rs ] ) && is_array( $filter_state[ $rs ] ) ? $filter_state[ $rs ] : array();
			if ( 1 !== count( $fs ) && in_array( $rs, $vs, true ) ) {
				_echo_tax_checkboxes( $rs, $terms, $state, empty( $fs ) ? null : $fs );
			}
		}
	}
	echo '<input type="hidden" value="' . esc_attr( $inst->filter_qvar_base ) . '" id="wplug-bimeson-filter-qvar-base">';
	echo '<input type="hidden" value="' . esc_attr( $inst->filter_cls_base ) . '" id="wplug-bimeson-filter-cls-base">';
}

/**
 * Displays a year select.
 *
 * @access private
 *
 * @param string[]             $years Array of years.
 * @param array<string, mixed> $state Array of filter states.
 */
function _echo_year_select( array $years, array $state ): void {
	$inst = _get_instance();
	$val  = $state[ $inst::KEY_YEAR ] ?? null;  // @phpstan-ignore-line
	$val  = is_numeric( $val ) ? (int) $val : 0;
	$yf   = is_string( $inst->year_format ) ? $inst->year_format : '%d';
	?>
	<div class="wplug-bimeson-filter-key" data-key="<?php echo esc_attr( $inst::KEY_YEAR );  // @phpstan-ignore-line ?>">
		<label class="select">
			<select name="<?php echo esc_attr( $inst::KEY_YEAR );  // @phpstan-ignore-line ?>" class="wplug-bimeson-filter-select">
				<option value=""><?php echo esc_html( $inst->year_select_label ); ?></option>
	<?php
	foreach ( $years as $y ) {
		if ( is_numeric( $y ) ) {
			$_label   = esc_html( sprintf( $yf, (int) $y ) );
			$selected = ( (int) $y === $val ) ? ' selected' : '';
			echo "<option value=\"$y\"" . $selected . ">$_label</option>";  // phpcs:ignore
		}
	}
	?>
			</select>
		</label>
	</div>
	<?php
}

/**
 * Displays taxonomy checkboxes.
 *
 * @access private
 *
 * @param string               $slug     A root slug.
 * @param \WP_Term[]           $terms    Corresponding terms.
 * @param array<string, mixed> $state    Filter states.
 * @param string[]|null        $filtered Filtered term slugs.
 */
function _echo_tax_checkboxes( string $slug, array $terms, array $state, ?array $filtered = null ): void {
	$inst = _get_instance();
	$func = $inst->term_name_getter;
	if ( ! is_callable( $func ) ) {
		$func = function ( \WP_Term $t ): string {
			return $t->name;
		};
	}
	$t         = get_term_by( 'slug', $slug, $inst->root_tax );
	$cat_label = ( $t instanceof \WP_Term ) ? $func( $t ) : '';

	if ( isset( $state[ $slug ] ) && is_array( $state[ $slug ] ) ) {
		list( $qvs, $oa ) = $state[ $slug ];
		$chk_ena          = count( $qvs ) ? ' checked' : '';
		$chk_rel          = 'and' === $oa ? ' checked' : '';
	} else {
		$qvs     = array();
		$chk_ena = '';
		$chk_rel = '';
	}
	?>
	<div class="wplug-bimeson-filter-key" data-key="<?php echo esc_attr( $slug ); ?>">
		<div>
			<input type="checkbox" class="wplug-bimeson-filter-enabled"<?php echo $chk_ena; // phpcs:ignore ?>>
			<label></label>
			<label><?php echo esc_html( $cat_label ); ?></label>
	<?php if ( $inst->do_show_relation_switch ) : ?>
			<input type="checkbox" class="wplug-bimeson-filter-relation"<?php echo $chk_rel; // phpcs:ignore ?>>
			<label><span>OR</span><span>AND</span></label>
	<?php endif; ?>
		</div>
		<div class="wplug-bimeson-filter-values">
	<?php
	foreach ( $terms as $t ) :
		if ( ! is_null( $filtered ) && ! in_array( $t->slug, $filtered, true ) ) {
			continue;
		}
		$val     = $t->slug;
		$label   = $func( $t );
		$checked = in_array( $t->slug, $qvs, true ) ? ' checked' : '';
		?>
			<label><input type="checkbox" value="<?php echo esc_attr( $val ); ?>"<?php echo esc_html( $checked ); ?>><?php echo esc_html( $label ); ?></label>
		<?php
	endforeach;
	?>
		</div>
	</div>
	<?php
}


// -----------------------------------------------------------------------------


/**
 * Retrieves filter states from query.
 *
 * @access private
 *
 * @return array<string, array{ string[], string }|string> Filter state.
 */
function _get_filter_state_from_query(): array {
	$inst = _get_instance();
	$ret  = array();

	foreach ( get_root_slugs() as $rs ) {
		$val = get_query_var( get_query_var_name( $rs ) );
		if ( is_string( $val ) && ! empty( $val ) ) {
			$oa = 'or';
			if ( '.' === $val[0] ) {
				$oa  = 'and';
				$val = substr( $val, 1 );
			}
			$ret[ $rs ] = array( explode( ',', $val ), $oa );
		} else {
			$ret[ $rs ] = array( array(), 'or' );
		}
	}
	$val = get_query_var( get_query_var_name( $inst::KEY_YEAR ) );  // @phpstan-ignore-line
	if ( is_string( $val ) ) {
		$ret[ (string) $inst::KEY_YEAR ] = $val;  // @phpstan-ignore-line
	}
	return $ret;
}
