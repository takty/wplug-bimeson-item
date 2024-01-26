<?php
/**
 * Instance
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2024-01-26
 */

declare(strict_types=1);

namespace wplug\bimeson_item;

/**
 * Gets instance.
 *
 * @access private
 *
 * @return object{
 *     fld_list_cfg           : string,
 *     additional_langs       : string[],
 *     head_level             : int,
 *     year_format            : string|null,
 *     term_name_getter       : callable|null,
 *     year_select_label      : string,
 *     uncat_label            : string,
 *     root_tax               : string,
 *     sub_tax_base           : string,
 *     filter_qvar_base       : string,
 *     filter_cls_base        : string,
 *     do_show_relation_switch: bool,
 *     cache                  : array<string, mixed>[],
 *     rs_idx                 : array<string, int[]>|null,
 *     rs_opts                : array<string, array<string, mixed>>|null,
 *     sub_taxes              : array<string, string>,
 *     old_tax                : string,
 *     old_terms              : array{ slug: string, name: string, term_id: int }[],
 *     root_terms             : \WP_Term[],
 *     sub_tax_to_terms       : array<string, \WP_Term[]|null>,
 * } Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {
		/**
		 * Template Admin.
		 *
		 * @var string
		 */
		const KEY_VISIBLE = '_visible';

		/**
		 * The meta key of list config.
		 *
		 * @var string
		 */
		public $fld_list_cfg = '_bimeson';

		/**
		 * Post Type.
		 *
		 * @var string
		 */
		const PT = 'bimeson';  // Each post means publication (bimeson).

		/**
		 * Additional language slugs.
		 *
		 * @var string[]
		 */
		public $additional_langs = array();

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
		 * @var int
		 */
		public $head_level = 3;

		/**
		 * Year heading format.
		 *
		 * @var string|null
		 */
		public $year_format = null;

		// Filter.

		const KEY_YEAR = 'year';

		/**
		 * Label of year select markup.
		 *
		 * @var string
		 */
		public $year_select_label = '';

		/**
		 * Label of heading meaning 'uncategorized'.
		 *
		 * @var string
		 */
		public $uncat_label = '';

		/**
		 * Callable for getting term names.
		 *
		 * @var callable|null
		 */
		public $term_name_getter = null;

		/**
		 * Data cache.
		 *
		 * @var array<string, mixed>[]
		 */
		public $cache = array();

		/**
		 * The array of root slug to sub slug indices.
		 *
		 * @var array<string, int[]>|null
		 */
		public $rs_idx = null;

		/**
		 * The array of root slug to options.
		 *
		 * @var array<string, array<string, mixed>>|null
		 */
		public $rs_opts = null;

		// Taxonomy.

		const KEY_IS_HIDDEN           = '_bimeson_is_hidden';
		const KEY_SORT_UNCAT_LAST     = '_bimeson_sort_uncat_last';
		const KEY_OMIT_LAST_CAT_GROUP = '_bimeson_omit_last_cat_group';

		const DEFAULT_TAXONOMY     = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE = 'bm_cat_';

		const DEFAULT_FILTER_QVAR_BASE = 'bm_%key%';
		const DEFAULT_FILTER_CLS_BASE  = 'bm-%key%-%value%';

		/**
		 * Root taxonomy slug.
		 *
		 * @var string
		 */
		public $root_tax = '';

		/**
		 * Slug base of sub taxonomies.
		 *
		 * @var string
		 */
		public $sub_tax_base = '';

		/**
		 * Query variable name base of sub taxonomies.
		 *
		 * @var string
		 */
		public $filter_qvar_base = '';

		/**
		 * Class base of sub taxonomies.
		 *
		 * @var string
		 */
		public $filter_cls_base = '';

		/**
		 * Whether to show relation switches.
		 *
		 * @var bool
		 */
		public $do_show_relation_switch = false;

		/**
		 * The sub taxonomy slugs.
		 *
		 * @var array<string, string>
		 */
		public $sub_taxes = array();

		/**
		 * Previously edited taxonomy.
		 *
		 * @var string
		 */
		public $old_tax = '';

		/**
		 * Previously edited terms.
		 *
		 * @var array{ slug: string, name: string, term_id: int }[]
		 */
		public $old_terms = array();

		/**
		 * The root terms.
		 *
		 * @var \WP_Term[]
		 */
		public $root_terms = array();

		/**
		 * The sub terms.
		 *
		 * @var array<string, \WP_Term[]|null>
		 */
		public $sub_tax_to_terms = array();
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
function _set_key( string $key ): void {
	$inst = _get_instance();

	$inst->fld_list_cfg = $key;  // @phpstan-ignore-line
}
