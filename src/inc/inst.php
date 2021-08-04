<?php
/**
 * Bimeson (Instance)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-04
 */

namespace wplug\bimeson_item;

/**
 * Gets instance.
 *
 * @access private
 *
 * @return object Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {

		// Template Admin

		const KEY_VISIBLE  = '_visible';

		public $FLD_LIST_CFG = '_bimeson';

		// Post Type

		const PT = 'bimeson';  // Each post means publication (bimeson)

		// Item

		const IT_BODY       = '_body';
		const IT_DATE       = '_date';
		const IT_DOI        = '_doi';
		const IT_LINK_URL   = '_link_url';
		const IT_LINK_TITLE = '_link_title';

		const IT_DATE_NUM = '_date_num';
		const IT_CAT_KEY  = '_cat_key';
		const IT_INDEX    = '_index';

		const IT_IMPORT_FROM = '_import_from';
		const IT_DIGEST      = '_digest';
		const IT_EDIT_URL    = '_edit_url';

		public $_is_current_post_content_empty;

		// Common

		public $head_level       = 3;
		public $year_format      = null;
		public $term_name_getter = null;

		public $cache  = [];
		public $rs_idx = null;

		public $additional_langs;

		// Taxonomy

		const KEY_LAST_CAT_OMITTED = '_bimeson_last_cat_omitted';
		const KEY_IS_HIDDEN        = '_bimeson_is_hidden';

		const DEFAULT_TAXONOMY          = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE      = 'bm_cat_';
		const DEFAULT_SUB_TAX_CLS_BASE  = 'bm-cat-';
		const DEFAULT_SUB_TAX_QVAR_BASE = 'bm_';

		public $root_tax;
		public $sub_tax_base;
		public $sub_tax_cls_base;
		public $sub_tax_qvar_base;

		const DEFAULT_YEAR_CLS_BASE = 'bm-year-';
		const DEFAULT_YEAR_QVAR     = 'bm_year';

		public $year_cls_base;
		public $year_qvar;

		public $sub_taxes        = [];
		public $old_tax          = [];
		public $old_terms        = [];
		public $root_terms       = null;
		public $sub_tax_to_terms = [];

		// Filter

		const KEY_YEAR     = '_year';
		const VAL_YEAR_ALL = 'all';

		public $year_select_label;

	};
	return $values;
}

function _set_key( string $key ) {
	$inst = _get_instance();
	$inst->FLD_LIST_CFG = $key;
}
