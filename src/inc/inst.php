<?php
/**
 * Bimeson (Instance)
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2022-06-15
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

		// Template Admin.

		const KEY_VISIBLE = '_visible';

		/**
		 * The meta key of list config.
		 *
		 * @var 1.0
		 */
		public $fld_list_cfg = '_bimeson';

		// Post Type.

		const PT = 'bimeson';  // Each post means publication (bimeson).

		// Item.

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

		// Common.

		/**
		 * First heading level of publication lists.
		 *
		 * @var 1.0
		 */
		public $head_level = 3;

		/**
		 * Year heading format.
		 *
		 * @var 1.0
		 */
		public $year_format = null;

		/**
		 * Callable for getting term names.
		 *
		 * @var 1.0
		 */
		public $term_name_getter = null;

		/**
		 * Data cache.
		 *
		 * @var 1.0
		 */
		public $cache = array();

		/**
		 * The array of root slug to sub slug indices.
		 *
		 * @var 1.0
		 */
		public $rs_idx = null;

		/**
		 * The array of root slug to options.
		 *
		 * @var 1.0
		 */
		public $rs_opts = null;

		/**
		 * Additional languages.
		 *
		 * @var 1.0
		 */
		public $additional_langs;

		// Taxonomy.

		const KEY_IS_HIDDEN           = '_bimeson_is_hidden';
		const KEY_SORT_UNCAT_LAST     = '_bimeson_sort_uncat_last';
		const KEY_OMIT_LAST_CAT_GROUP = '_bimeson_omit_last_cat_group';

		const DEFAULT_TAXONOMY          = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE      = 'bm_cat_';
		const DEFAULT_SUB_TAX_CLS_BASE  = 'bm-cat-';
		const DEFAULT_SUB_TAX_QVAR_BASE = 'bm_';

		/**
		 * Root taxonomy slug.
		 *
		 * @var 1.0
		 */
		public $root_tax;

		/**
		 * Slug base of sub taxonomies.
		 *
		 * @var 1.0
		 */
		public $sub_tax_base;

		/**
		 * Class base of sub taxonomies.
		 *
		 * @var 1.0
		 */
		public $sub_tax_cls_base;

		/**
		 * Query variable name base of sub taxonomies.
		 *
		 * @var 1.0
		 */
		public $sub_tax_qvar_base;

		const DEFAULT_YEAR_CLS_BASE = 'bm-year-';
		const DEFAULT_YEAR_QVAR     = 'bm_year';

		/**
		 * Class base of year.
		 *
		 * @var 1.0
		 */
		public $year_cls_base;

		/**
		 * Query variable name of year.
		 *
		 * @var 1.0
		 */
		public $year_qvar;

		/**
		 * The sub taxonomy slugs.
		 *
		 * @var 1.0
		 */
		public $sub_taxes = array();

		/**
		 * Previously edited taxonomy.
		 *
		 * @var 1.0
		 */
		public $old_tax = array();

		/**
		 * Previously edited terms.
		 *
		 * @var 1.0
		 */
		public $old_terms = array();

		/**
		 * The root terms.
		 *
		 * @var 1.0
		 */
		public $root_terms = null;

		/**
		 * The sub terms.
		 *
		 * @var 1.0
		 */
		public $sub_tax_to_terms = array();

		// Filter.

		const KEY_YEAR     = '_year';
		const VAL_YEAR_ALL = 'all';

		/**
		 * Label of year select markup.
		 *
		 * @var 1.0
		 */
		public $year_select_label;

		/**
		 * Label of heading meaning 'uncategorized'.
		 *
		 * @var 1.0
		 */
		public $uncat_label;

	};
	return $values;
}

/**
 * Sets the meta key of list config.
 *
 * @access private
 *
 * @param string $key Key.
 */
function _set_key( string $key ) {
	$inst = _get_instance();

	$inst->fld_list_cfg = $key;
}
