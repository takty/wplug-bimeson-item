<?php
/**
 * Bimeson (Template Admin)
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_item;

function get_template_admin_config( int $post_id ): array {
	$inst = _get_instance();

	$year_bgn = _get_post_meta_int( $post_id, $inst->FLD_YEAR_START, 1970, 3000 );
	$year_end = _get_post_meta_int( $post_id, $inst->FLD_YEAR_END,   1970, 3000 );
	if ( is_int( $year_bgn ) && is_int( $year_end ) && $year_end < $year_bgn ) {
		[ $year_bgn, $year_end ] = [ $year_end, $year_bgn ];
	}
	$count = _get_post_meta_int( $post_id, $inst->FLD_COUNT, 1, 9999 );

	$sort_by_date_first = get_post_meta( $post_id, $inst->FLD_SORT_BY_DATE_FIRST, true ) === 'true';
	$dup_multi_cat      = get_post_meta( $post_id, $inst->FLD_DUP_MULTI_CAT, true ) === 'true';
	$filter_state       = json_decode( get_post_meta( $post_id, $inst->FLD_JSON_PARAMS, true ), true );

	$show_filter     = get_post_meta( $post_id, $inst->FLD_SHOW_FILTER, true ) === 'true';
	$omit_single_cat = get_post_meta( $post_id, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, true ) === 'true';

	return compact(
		'year_bgn', 'year_end', 'count',
		'sort_by_date_first', 'dup_multi_cat',
		'show_filter', 'omit_single_cat',
		'filter_state',
	);
}


// -----------------------------------------------------------------------------


function add_meta_box_template_admin( string $label, string $screen ) {
	\add_meta_box( 'bimeson_admin_mb', $label, '\wplug\bimeson_item\_cb_output_html_template_admin', $screen );
}

function save_meta_box_template_admin( int $post_id ) {
	$inst = _get_instance();
	if ( ! isset( $_POST['bimeson_admin_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['bimeson_admin_nonce'], 'bimeson_admin' ) ) return;

	$state              = _get_filter_state_from_env();
	$sort_by_date_first = empty( $_POST[ $inst->FLD_SORT_BY_DATE_FIRST ] ) ? 'false' : 'true';
	$dup_multi_cat      = empty( $_POST[ $inst->FLD_DUP_MULTI_CAT ] ) ? 'false' : 'true';
	$show_filter        = empty( $_POST[ $inst->FLD_SHOW_FILTER ] ) ? 'false' : 'true';
	$omit_single_cat    = empty( $_POST[ $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT ] ) ? 'false' : 'true';

	update_post_meta( $post_id, $inst->FLD_JSON_PARAMS, json_encode( $state ) );
	update_post_meta( $post_id, $inst->FLD_SORT_BY_DATE_FIRST, $sort_by_date_first );
	update_post_meta( $post_id, $inst->FLD_DUP_MULTI_CAT, $dup_multi_cat );
	update_post_meta( $post_id, $inst->FLD_SHOW_FILTER, $show_filter );
	update_post_meta( $post_id, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, $omit_single_cat );

	save_post_meta( $post_id, $inst->FLD_COUNT );
	save_post_meta( $post_id, $inst->FLD_YEAR_START );
	save_post_meta( $post_id, $inst->FLD_YEAR_END );
}

function _cb_output_html_template_admin( \WP_Post $post ) {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_admin', 'bimeson_admin_nonce' );

	$temp       = get_post_meta( $post->ID, $inst->FLD_YEAR_START, true );
	$year_start = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? '' : (int) $temp;
	$temp       = get_post_meta( $post->ID, $inst->FLD_YEAR_END, true );
	$year_end   = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? '' : (int) $temp;
	$temp       = get_post_meta( $post->ID, $inst->FLD_COUNT, true );
	$count      = ( empty( $temp ) || (int) $temp < 1 ) ? '' : (int) $temp;

	$sort_by_date_first = get_post_meta( $post->ID, $inst->FLD_SORT_BY_DATE_FIRST, true );
	$dup_multi_cat      = get_post_meta( $post->ID, $inst->FLD_DUP_MULTI_CAT, true );
	$show_filter        = get_post_meta( $post->ID, $inst->FLD_SHOW_FILTER, true );
	$omit_single_cat    = get_post_meta( $post->ID, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, true );
?>
	<div class="bimeson-admin">
		<div class="bimeson-admin-setting-row">
			<label>
				<?php echo _x( 'Target period (years):', 'admin dest', 'bimeson_item' ); ?>
				<input type="number" size="4" name="<?php echo $inst->FLD_YEAR_START ?>" value="<?php echo $year_start ?>">
				<span>-</span>
				<input type="number" size="4" name="<?php echo $inst->FLD_YEAR_END ?>" value="<?php echo $year_end ?>">
			</label>
		</div>
		<div class="bimeson-admin-setting-row">
			<label>
				<?php echo _x( 'Specify the number of items to be displayed:', 'admin dest', 'bimeson_item' ); ?>
				<input type="number" size="4" name="<?php echo $inst->FLD_COUNT ?>" value="<?php echo $count ?>">
			</label>
			&nbsp;<span><?php echo _x( '(If specified, the heading will be hidden)', 'admin dest', 'bimeson_item' ); ?></span>
		</div>
		<div class="bimeson-admin-setting-cbs">
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_SORT_BY_DATE_FIRST ?>" value="true" <?php checked( $sort_by_date_first, 'true' ) ?>>
				<?php echo _x( 'Sort by date first', 'admin dest', 'bimeson_item' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_DUP_MULTI_CAT ?>" value="true" <?php checked( $dup_multi_cat, 'true' ) ?>>
				<?php echo _x( 'Duplicate multi-category items', 'admin dest', 'bimeson_item' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_SHOW_FILTER ?>" value="true" <?php checked( $show_filter, 'true' ) ?>>
				<?php echo _x( 'Show filter', 'admin dest', 'bimeson_item' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT ?>" value="true" <?php checked( $omit_single_cat, 'true' ) ?>>
				<?php echo _x( 'Omit headings for categories with only one item', 'admin dest', 'bimeson_item' ); ?>
			</label>
		</div>
		<div class="bimeson-admin-filter-row">
			<?php _echo_filter( $post ); ?>
		</div>
	</div>
<?php
}

function _echo_filter( \WP_Post $post ) {
	$state = _get_filter_state_from_meta( $post );
	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		_echo_tax_checkboxes_admin( $rs, $terms, $state );
	}
}

function _echo_tax_checkboxes_admin( string $root_slug, array $terms, array $state ) {
	$inst = _get_instance();
	$func = is_callable( $inst->term_name_getter ) ? $inst->term_name_getter : function ( $t ) { return $t->name; };

	$t          = get_term_by( 'slug', $root_slug, $inst->root_tax );
	$_cat_label = esc_html( $func( $t ) );
	$_slug      = esc_attr( $root_slug );
	$qvs        = $state[ $root_slug ];
	$checked    = ( ! empty( $qvs ) ) ? ' checked' : '';
?>
	<div class="bimeson-admin-filter-key" data-key="<?php echo $_slug; ?>">
		<div class="bimeson-admin-filter-key-inner">
			<div>
				<input type="checkbox" class="bimeson-admin-filter-switch tgl tgl-light" id="<?php echo $_slug; ?>" name="<?php echo $_slug; ?>"<?php echo $checked; ?> value="1">
				<label class="tgl-btn" for="<?php echo $_slug; ?>"></label>
				<span class="bimeson-admin-filter-cat"><label for="<?php echo $_slug; ?>"><?php echo $_cat_label; ?></label></span>
			</div>
			<div class="bimeson-admin-filter-cbs">
<?php
	foreach ( $terms as $t ) :
		$_name   = esc_attr( sub_term_to_name( $t ) );
		$_val    = esc_attr( $t->slug );
		$_label  = esc_html( $func( $t ) );
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


function _get_post_meta_int( int $post_id, string $key, int $min, int $max ): ?int {
	$temp = get_post_meta( $post_id, $key, true );
	if ( empty( $temp ) ) return null;
	$val = (int) $temp;
	return ( $val < $min || $max < $val ) ? false : $val;
}

function _get_filter_state_from_meta( \WP_Post $post ): array {
	$inst = _get_instance();
	$ret  = json_decode( get_post_meta( $post->ID, $inst->FLD_JSON_PARAMS, true ), true );

	foreach ( get_root_slugs() as $rs ) {
		if ( ! isset( $ret[ $rs ] ) ) $ret[ $rs ] = [];
	}
	return $ret;
}

function _get_filter_state_from_env(): array {
	$ret = [];

	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		if ( ! isset( $_POST[ $rs ] ) ) continue;
		$temp = [];

		foreach ( $terms as $t ) {
			$name = sub_term_to_name( $t );
			$val  = $_POST[ $name ] ?? false;
			if ( $val ) $temp[] = $t->slug;
		}
		$ret[ $rs ] = $temp;
	}
	return $ret;
}
