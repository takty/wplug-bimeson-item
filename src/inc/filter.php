<?php
/**
 * Bimeson (Filter)
 *
 * @author Takuto Yanagida
 * @version 2021-07-19
 */

namespace wplug\bimeson_post;

function initialize_filter() {
	add_filter( 'query_vars', '\wplug\bimeson_list\_cb_query_vars_filter' );
}

function _cb_query_vars_filter( $query_vars ) {
	$inst = _get_instance();
	$query_vars[] = $inst::QVAR_YEAR;
	return $query_vars;
}


// -----------------------------------------------------------------------------


function echo_filter( ?array $filter_state, array $years_exist ) {
	$rs_to_terms = get_root_slug_to_sub_terms();

	$state = _get_filter_state_from_query();

	echo '<div class="bimeson-filter">';
	if ( $filter_slugs[0] === 'all' ) {
		foreach ( $rs_to_terms as $rs => $terms ) {
			_echo_tax_checkboxes( $rs, $terms, $state );
		}
	} else {
		foreach ( $rs_to_terms as $rs => $terms ) {
			if ( in_array( $rs, $filter_slugs, true ) ) {
				_echo_tax_checkboxes( $rs, $terms, $state );
			}
		}
	}
	echo '</div>';
}

function _echo_tax_checkboxes( $root_slug, $terms, $state ) {
	$inst = _get_instance();
	$t = get_term_by( 'slug', $root_slug, $inst->root_tax );
	if ( is_callable( $inst->term_name_getter ) ) {
		$_cat_label = esc_html( ( $inst->term_name_getter )( $t ) );
	} else {
		$_cat_label = esc_html( $t->name );
	}
	$_slug = esc_attr( $root_slug );
	$qvs   = $state[ $root_slug ];
?>
	<div class="bimeson-filter-key" data-key="<?php echo $_slug ?>">
		<div class="bimeson-filter-key-inner">
			<div>
				<input type="checkbox" class="bimeson-filter-switch tgl tgl-light" id="<?php echo $_slug; ?>" <?php if ( ! empty( $qvs ) ) echo 'checked'; ?>>
				<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
				<div class="bimeson-filter-cat"><?php echo $_cat_label; ?></div>
			</div>
			<div class="bimeson-filter-cbs">
<?php
	foreach ( $terms as $t ) :
		$_name = esc_attr( root_term_to_sub_tax( $t ) );
		$_val  = esc_attr( $t->slug );
		if ( is_callable( $inst->term_name_getter ) ) {
			$_label = esc_html( ( $inst->term_name_getter )( $t ) );
		} else {
			$_label = esc_html( $t->name );
		}
		$checked = in_array( $t->slug, $qvs, true ) ? ' checked' : '';
?>
				<label>
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


function _get_filter_state_from_query() {
	$inst = _get_instance();
	$ret  = [];

	foreach ( get_root_slugs() as $rs ) {
		$val        = get_query_var( get_query_var_name( $rs ) );
		$ret[ $rs ] = empty( $val ) ? [] : explode( ',', $val );
	}
	$val = get_query_var( $inst::QVAR_YEAR );
	$ret[ $inst::KEY_YEAR ] = $val;
	return $ret;
}
