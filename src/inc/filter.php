<?php
/**
 * Bimeson (Filter)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2022-07-05
 */

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
	$query_vars[] = $inst->year_qvar;
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
	wp_enqueue_style( 'wplug-bimeson-item-filter' );
	wp_enqueue_script( 'wplug-bimeson-item-filter' );

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
	echo '<input type="hidden" value="' . esc_attr( $inst->sub_tax_cls_base ) . '" id="wplug-bimeson-sub-tax-cls-base">';
	echo '<input type="hidden" value="' . esc_attr( $inst->sub_tax_qvar_base ) . '" id="wplug-bimeson-sub-tax-qvar-base">';
	echo '<input type="hidden" value="' . esc_attr( $inst->year_cls_base ) . '" id="wplug-bimeson-year-cls-base">';
	echo '<input type="hidden" value="' . esc_attr( $inst->year_qvar ) . '" id="wplug-bimeson-year-qvar">';
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
	$val  = $state[ $inst::KEY_YEAR ];  // @phpstan-ignore-line
	$val  = is_numeric( $val ) ? (int) $val : 0;
	$yf   = is_string( $inst->year_format ) ? $inst->year_format : '%d';
	?>
	<div class="wplug-bimeson-filter-key" data-key="<?php echo esc_attr( $inst::KEY_YEAR );  // @phpstan-ignore-line ?>">
		<div class="wplug-bimeson-filter-key-inner">
			<label class="select">
				<select name="<?php echo esc_attr( $inst::KEY_YEAR );  // @phpstan-ignore-line ?>" class="wplug-bimeson-filter-select">
					<option value="<?php echo esc_attr( $inst::VAL_YEAR_ALL );  // @phpstan-ignore-line ?>"><?php echo esc_html( $inst->year_select_label ); ?></option>
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
	</div>
	<?php
}

/**
 * Displays taxonomy checkboxes.
 *
 * @access private
 *
 * @param string               $root_slug A root slug.
 * @param \WP_Term[]           $terms     Corresponding terms.
 * @param array<string, mixed> $state     Filter states.
 * @param string[]|null        $filtered  Filtered term slugs.
 */
function _echo_tax_checkboxes( string $root_slug, array $terms, array $state, ?array $filtered = null ): void {
	$inst = _get_instance();
	$func = $inst->term_name_getter;
	if ( ! is_callable( $func ) ) {
		$func = function ( \WP_Term $t ): string {
			return $t->name;
		};
	}
	$t         = get_term_by( 'slug', $root_slug, $inst->root_tax );
	$cat_label = ( $t instanceof \WP_Term ) ? $func( $t ) : '';
	$slug      = $root_slug;
	$qvs       = isset( $state[ $root_slug ] ) && is_array( $state[ $root_slug ] ) ? $state[ $root_slug ] : array();
	$checked   = ( ! empty( $qvs ) ) ? ' checked' : '';
	?>
	<div class="wplug-bimeson-filter-key" data-key="<?php echo esc_attr( $slug ); ?>">
		<div class="wplug-bimeson-filter-key-inner">
			<div>
				<input type="checkbox" class="wplug-bimeson-filter-switch tgl tgl-light" id="<?php echo esc_attr( $slug ); ?>" name="<?php echo esc_attr( $slug ); ?>"<?php echo $checked; // phpcs:ignore ?>>
				<label class="tgl-btn" for="<?php echo esc_attr( $slug ); ?>"></label>
				<div class="wplug-bimeson-filter-cat"><label for="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $cat_label ); ?></label></div>
			</div>
			<div class="wplug-bimeson-filter-cbs">
	<?php
	foreach ( $terms as $t ) :
		if ( ! is_null( $filtered ) && ! in_array( $t->slug, $filtered, true ) ) {
			continue;
		}
		$name    = sub_term_to_name( $t );
		$val     = $t->slug;
		$label   = $func( $t );
		$checked = in_array( $t->slug, $qvs, true ) ? ' checked' : '';
		?>
				<label class="checkbox">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>"<?php echo $checked; // phpcs:ignore ?> value="<?php echo esc_attr( $val ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
		<?php
	endforeach;
	?>
			</div>
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
 * @return array<string, mixed> Filter state.
 */
function _get_filter_state_from_query(): array {
	$inst = _get_instance();
	$ret  = array();

	foreach ( get_root_slugs() as $rs ) {
		$val        = get_query_var( get_query_var_name( $rs ) );
		$ret[ $rs ] = is_string( $val ) ? explode( ',', $val ) : array();
	}
	$val = get_query_var( $inst->year_qvar );

	$ret[ (string) $inst::KEY_YEAR ] = $val;  // @phpstan-ignore-line
	return $ret;
}
