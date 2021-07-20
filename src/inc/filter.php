<?php
/**
 * Bimeson (Filter)
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_post;

function initialize_filter() {
	add_filter( 'query_vars', '\wplug\bimeson_post\_cb_query_vars_filter' );
}

function _cb_query_vars_filter( array $query_vars ): array {
	$inst         = _get_instance();
	$query_vars[] = $inst->year_qvar;
	return $query_vars;
}

function echo_the_filter( ?array $filter_state, array $years_exist, string $before = '<div class="bimeson-filter"%s>', string $after = '</div>', string $for = 'bml' ) {
	wp_enqueue_style(  'bimeson_post_filter' );
	wp_enqueue_script( 'bimeson_post_filter' );

	if ( ! empty( $for ) ) $for = " for=\"$for\"";
	$before = sprintf( $before, $for );

	echo $before;
	echo_filter( $filter_state, $years_exist );
	echo $after;
}


// -----------------------------------------------------------------------------


function echo_filter( ?array $filter_state, array $years_exist ) {
	$rs_to_terms = get_root_slug_to_sub_terms( false, true );
	$state       = _get_filter_state_from_query();

	if ( ! empty( $years_exist ) ) _echo_year_select( $years_exist, $state );
	if ( is_null( $filter_state ) ) {
		foreach ( $rs_to_terms as $rs => $terms ) {
			_echo_tax_checkboxes( $rs, $terms, $state );
		}
	} else {
		foreach ( $rs_to_terms as $rs => $terms ) {
			$set = isset( $filter_state[ $rs ] );
			if ( ! $set || 1 < count( $filter_state[ $rs ] ) ) {
				_echo_tax_checkboxes( $rs, $terms, $state, $set ? $filter_state[ $rs ] : null );
			}
		}
	}
	$inst = _get_instance();
	echo "<input type=\"hidden\" value=\"{$inst->sub_tax_cls_base}\" id=\"bimeson-sub-tax-cls-base\">";
	echo "<input type=\"hidden\" value=\"{$inst->sub_tax_qvar_base}\" id=\"bimeson-sub-tax-qvar-base\">";
	echo "<input type=\"hidden\" value=\"{$inst->year_cls_base}\" id=\"bimeson-year-cls-base\">";
	echo "<input type=\"hidden\" value=\"{$inst->year_qvar}\" id=\"bimeson-year-qvar\">";
}

function _echo_year_select( array $years, array $state ) {
	$inst = _get_instance();
	$val  = $state[ $inst::KEY_YEAR ];
	$yf   = is_string( $inst->year_format ) ? $inst->year_format : '%d';
?>
	<div class="bimeson-filter-key" data-key="<?php echo $inst::KEY_YEAR ?>">
		<div class="bimeson-filter-key-inner">
			<label class="select">
				<select name="<?php echo $inst::KEY_YEAR ?>" class="bimeson-filter-select">
					<option value="<?php echo $inst::VAL_YEAR_ALL ?>"><?php echo esc_html( $inst->year_select_label ) ?></option>
<?php
	foreach ( $years as $y ) {
		$_label   = esc_html( sprintf( $yf, (int) $y ) );
		$selected = ( (int) $y === (int) $val ) ? ' selected' : '';
		echo "<option value=\"$y\"" . $selected . ">$_label</option>";
	}
?>
				</select>
			</label>
		</div>
	</div>
<?php
}

function _echo_tax_checkboxes( string $root_slug, array $terms, array $state, ?array $filtered = null ) {
	$inst = _get_instance();
	$func = is_callable( $inst->term_name_getter ) ? $inst->term_name_getter : function ( $t ) { return $t->name; };

	$t          = get_term_by( 'slug', $root_slug, $inst->root_tax );
	$_cat_label = esc_html( $func( $t ) );
	$_slug      = esc_attr( $root_slug );
	$qvs        = $state[ $root_slug ];
	$checked    = ( ! empty( $qvs ) ) ? ' checked' : '';
?>
	<div class="bimeson-filter-key" data-key="<?php echo $_slug; ?>">
		<div class="bimeson-filter-key-inner">
			<div>
				<input type="checkbox" class="bimeson-filter-switch tgl tgl-light" id="<?php echo $_slug; ?>" name="<?php echo $_slug; ?>"<?php echo $checked; ?>>
				<label class="tgl-btn" for="<?php echo $_slug; ?>"></label>
				<div class="bimeson-filter-cat"><label for="<?php echo $_slug; ?>"><?php echo $_cat_label; ?></label></div>
			</div>
			<div class="bimeson-filter-cbs">
<?php
	foreach ( $terms as $t ) :
		if ( ! is_null( $filtered ) && ! in_array( $t->slug, $filtered, true ) ) continue;
		$_name   = esc_attr( sub_term_to_name( $t ) );
		$_val    = esc_attr( $t->slug );
		$_label  = esc_html( $func( $t ) );
		$checked = in_array( $t->slug, $qvs, true ) ? ' checked' : '';
?>
				<label class="checkbox">
					<input type="checkbox" name="<?php echo $_name; ?>"<?php echo $checked; ?> value="<?php echo $_val; ?>">
					<?php echo $_label; ?>
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


function _get_filter_state_from_query(): array {
	$inst = _get_instance();
	$ret  = [];

	foreach ( get_root_slugs() as $rs ) {
		$val        = get_query_var( get_query_var_name( $rs ) );
		$ret[ $rs ] = empty( $val ) ? [] : explode( ',', $val );
	}
	$val = get_query_var( $inst->year_qvar );
	$ret[ $inst::KEY_YEAR ] = $val;
	return $ret;
}
