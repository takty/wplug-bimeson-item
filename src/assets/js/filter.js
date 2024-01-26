/**
 * Filter (Common)
 *
 * @author Takuto Yanagida
 * @version 2024-01-26
 */

import FilterCat from "./filter-cat/filter-cat.min.js";

document.addEventListener('DOMContentLoaded', () => {
	const opts = {
		selFilter: '.wplug-bimeson-filter',
		selList  : '.wplug-bimeson-list',

		selFilterKey: '.wplug-bimeson-filter-key',

		selFilterSelect  : '.wplug-bimeson-filter-select',
		selFilterEnabled : '.wplug-bimeson-filter-enabled',
		selFilterRelation: '.wplug-bimeson-filter-relation',
		selFilterValues  : '.wplug-bimeson-filter-values',

		qvarBase : document.getElementById('wplug-bimeson-filter-qvar-base')?.value ?? 'bm_%key%',
		classBase: document.getElementById('wplug-bimeson-filter-cls-base')?.value ?? 'bm-%key%-%value%',

		doSetHeadingDepth   : false,
		doInitializeByParams: false,
	};

	const fs = Array.from(document.querySelectorAll('.wplug-bimeson-filter'));
	for (const f of fs) {
		const id = f.getAttribute('for');
		new FilterCat(id, opts);
	}
});
