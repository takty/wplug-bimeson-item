<?php
/**
 * Bimeson (Template Admin)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-04
 */

namespace wplug\bimeson_item;

function get_template_admin_config( int $post_id ): array {
	$inst = _get_instance();
	$cfg_json = get_post_meta( $post_id, $inst->FLD_LIST_CFG, true );
	if ( ! empty( $cfg_json ) ) {
		$cfg = json_decode( $cfg_json, true );
		if ( $cfg !== null ) {
			return $cfg;
		}
	}
	return null;
}


// -----------------------------------------------------------------------------


function add_meta_box_template_admin( string $label, string $screen ) {
	\add_meta_box( 'bimeson_admin_mb', $label, '\wplug\bimeson_item\_cb_output_html_template_admin', $screen );
}

function save_meta_box_template_admin( int $post_id ) {
	if ( ! isset( $_POST['bimeson_admin_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['bimeson_admin_nonce'], 'bimeson_admin' ) ) return;

	$inst = _get_instance();
	$cfg  = [];

	$cfg['year_bgn'] = empty( $_POST['_bimeson_year_bgn'] ) ? null : intval( $_POST['_bimeson_year_bgn'] );
	$cfg['year_end'] = empty( $_POST['_bimeson_year_end'] ) ? null : intval( $_POST['_bimeson_year_end'] );
	$cfg['count']    = empty( $_POST['_bimeson_count'] )    ? null : intval( $_POST['_bimeson_count'] );

	if ( $cfg['year_bgn'] ) $cfg['year_bgn'] = max( 1970, min( 3000, $cfg['year_bgn'] ) );
	if ( $cfg['year_end'] ) $cfg['year_end'] = max( 1970, min( 3000, $cfg['year_end'] ) );
	if ( $cfg['count'] )    $cfg['count']    = max(    1, min( 9999, $cfg['count'] ) );

	if ( is_int( $cfg['year_bgn'] ) && is_int( $cfg['year_end'] ) && $cfg['year_end'] < $cfg['year_bgn'] ) {
		[ $cfg['year_bgn'], $cfg['year_end'] ] = [ $cfg['year_end'], $cfg['year_bgn'] ];
	}

	$cfg['sort_by_date_first'] = ! empty( $_POST['_bimeson_sort_by_date_first'] );
	$cfg['dup_multi_cat']      = ! empty( $_POST['_bimeson_dup_multi_cat'] );
	$cfg['show_filter']        = ! empty( $_POST['_bimeson_show_filter'] );
	$cfg['omit_single_cat']    = ! empty( $_POST['_bimeson_omit_single_cat'] );

	$cfg['filter_state'] = _get_filter_state_from_env();

	$json = json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	update_post_meta( $post_id, $inst->FLD_LIST_CFG, addslashes( $json ) );  // Because the meta value is passed through the stripslashes() function upon being stored.
}

function _cb_output_html_template_admin( \WP_Post $post ) {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_admin', 'bimeson_admin_nonce' );
	$cfg = get_template_admin_config( $post->ID );

	$year_bgn = $cfg['year_bgn'] ?? null;
	$year_end = $cfg['year_end'] ?? null;
	$count    = $cfg['count']    ?? null;

	$sort_by_date_first = $cfg['sort_by_date_first'] ?? false;
	$dup_multi_cat      = $cfg['dup_multi_cat']      ?? false;
	$show_filter        = $cfg['show_filter']        ?? false;
	$omit_single_cat    = $cfg['omit_single_cat']    ?? false;

	$state = $cfg['filter_state'] ?? [];
?>
	<div class="bimeson-admin">
		<div class="bimeson-admin-config-row">
			<label>
				<?php esc_html_e( 'Target period (years):', 'bimeson_item' ); ?>
				<input type="number" size="4" name="_bimeson_year_bgn" value="<?php echo $year_bgn ?>">
				<span>-</span>
				<input type="number" size="4" name="_bimeson_year_end" value="<?php echo $year_end ?>">
			</label>
		</div>
		<div class="bimeson-admin-config-row">
			<label>
				<?php esc_html_e( 'Specify the number of items to be displayed:', 'bimeson_item' ); ?>
				<input type="number" size="4" name="_bimeson_count" value="<?php echo $count ?>">
			</label>
			&nbsp;<span><?php esc_html_e( '(If specified, the heading will be hidden)', 'bimeson_item' ); ?></span>
		</div>
		<div class="bimeson-admin-config-cbs">
			<label>
				<input type="checkbox" name="_bimeson_sort_by_date_first" value="true" <?php checked( $sort_by_date_first ) ?>>
				<?php esc_html_e( 'Sort by date first', 'bimeson_item' ); ?>
			</label>
			<label>
				<input type="checkbox" name="_bimeson_dup_multi_cat" value="true" <?php checked( $dup_multi_cat ) ?>>
				<?php esc_html_e( 'Duplicate multi-category items', 'bimeson_item' ); ?>
			</label>
			<label>
				<input type="checkbox" name="_bimeson_show_filter" value="true" <?php checked( $show_filter ) ?>>
				<?php esc_html_e( 'Show filter', 'bimeson_item' ); ?>
			</label>
			<label>
				<input type="checkbox" name="_bimeson_omit_single_cat" value="true" <?php checked( $omit_single_cat ) ?>>
				<?php esc_html_e( 'Omit headings for categories with only one item', 'bimeson_item' ); ?>
			</label>
		</div>
		<div class="bimeson-admin-filter-row">
			<?php _echo_filter( $state ); ?>
		</div>
	</div>
<?php
}

function _echo_filter( $state ) {
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
	$_sub_tax   = esc_attr( root_term_to_sub_tax( $root_slug ) );
	$qvs        = $state[ $root_slug ] ?? [];
	$checked    = ( ! empty( $qvs ) ) ? ' checked' : '';

	$visible = empty( $state[ $inst::KEY_VISIBLE ] ) ? true : in_array( $root_slug, $state[ $inst::KEY_VISIBLE ], true );
	$vc      = $visible ? ' checked' : '';
?>
	<div class="bimeson-admin-filter-key" data-key="<?php echo $_slug; ?>">
		<div class="bimeson-admin-filter-key-inner">
			<div>
				<input type="checkbox" class="bimeson-admin-filter-switch tgl tgl-light" id="<?php echo $_slug; ?>" name="<?php echo $_sub_tax; ?>"<?php echo $checked; ?> value="1">
				<label class="tgl-btn" for="<?php echo $_slug; ?>"></label>
				<span class="bimeson-admin-filter-cat"><label for="<?php echo $_slug; ?>"><?php echo $_cat_label; ?></label></span>
				<label>
					<input type="checkbox" class="bimeson-admin-filter-visible" name="_bimeson_visible[]"<?php echo $vc; ?> value="<?php echo $_slug; ?>">
					<?php esc_html_e( 'Visible' ); ?>
				</label>
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
					<input type="checkbox" name="<?php echo $_sub_tax . '_slugs[]'; ?>"<?php echo $checked; ?> value="<?php echo $_val; ?>">
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


function _get_filter_state_from_env(): array {
	$ret = [];

	foreach ( get_root_slug_to_sub_slugs() as $rs => $slugs ) {
		$sub_tax = root_term_to_sub_tax( $rs );
		if ( ! isset( $_POST[ $sub_tax ] ) || ! isset( $_POST[ "{$sub_tax}_slugs" ] ) ) continue;
		$ret[ $rs ] = array_values( array_intersect( $slugs, $_POST[ "{$sub_tax}_slugs" ] ) );
	}
	$inst = _get_instance();
	if ( isset( $_POST['_bimeson_visible'] ) ) {
		$ret[ $inst::KEY_VISIBLE ] = array_values( array_intersect( get_root_slugs(), $_POST['_bimeson_visible'] ) );
	}
	return $ret;
}
