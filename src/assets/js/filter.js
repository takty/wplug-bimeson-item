/**
 *
 * Bimeson List Filter
 *
 * @author Takuto Yanagida
 * @version 2021-07-19
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	const SEL_FILTER = '.bimeson-filter';
	const SEL_LIST   = '.bimeson-list';

	const SEL_FILTER_KEY      = '.bimeson-filter-key';
	const SEL_FILTER_SWITCH   = '.bimeson-filter-switch';
	const SEL_FILTER_CHECKBOX = 'input:not(.bimeson-filter-switch)';

	const SEL_FILTER_SELECT = '.bimeson-filter-select';
	const KEY_YEAR          = '_year';
	const VAL_YEAR_ALL      = 'all';

	const SUB_TAX_CLS_BASE  = document.getElementById('bimeson-sub-tax-cls-base')?.value  ?? 'bm-cat-';
	const SUB_TAX_QVAR_BASE = document.getElementById('bimeson-sub-tax-qvar-base')?.value ?? 'bm_';
	const YEAR_CLS_BASE     = document.getElementById('bimeson-year-cls-base')?.value     ?? 'bm-year-';
	const YEAR_QVAR         = document.getElementById('bimeson-year-qvar')?.value         ?? 'bm_year';

	const DS_KEY   = 'key';    // For filters
	const DS_COUNT = 'count';  // For headings and lists
	const DS_DEPTH = 'depth';  // For headings

	const fs = document.querySelectorAll(SEL_FILTER);
	for (let i = 0; i < fs.length; i += 1) {
		const f = fs[i];
		const t = f.getAttribute('for');
		if (t) {
			let l = document.querySelector(SEL_LIST + '#' + t);
			if (!l) l = document.querySelector(SEL_LIST);
			if (l) initialize(f, l);
		} else {
			const l = document.querySelector(SEL_LIST);
			if (l) initialize(f, l);
		}
	}

	function initialize(filterElm, listElm) {
		const fkElms  = filterElm.querySelectorAll(SEL_FILTER_KEY);
		const allElms = listElm.querySelectorAll(':scope > *');

		const keyToSwAndCbs = {};
		let yearSelect = null;

		for (let i = 0; i < fkElms.length; i += 1) {
			const elm = fkElms[i];
			if (elm.dataset[DS_KEY] === KEY_YEAR) {
				yearSelect = elm.querySelector(SEL_FILTER_SELECT);
			} else {
				const sw  = elm.querySelector(SEL_FILTER_SWITCH);
				const cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
				if (sw && cbs) keyToSwAndCbs[elm.dataset[DS_KEY]] = [sw, cbs];
			}
		}
		for (let key in keyToSwAndCbs) {
			const [sw, cbs] = keyToSwAndCbs[key];
			assignEventListener(sw, cbs, () => { update(allElms, keyToSwAndCbs, yearSelect); });
		}
		if (yearSelect) {
			assignEventListenerSelect(yearSelect, () => { update(allElms, keyToSwAndCbs, yearSelect); });
		}
		update(allElms, keyToSwAndCbs, yearSelect);
	}

	function update(allElms, keyToSwAndCbs, yearSelect) {
		const keyToVals = getKeyToVals(keyToSwAndCbs);
		const year = (yearSelect && yearSelect.value !== VAL_YEAR_ALL) ? yearSelect.value : null;
		filterLists(allElms, keyToVals, year);
		countUpItems(allElms);
		setUrlParams(keyToVals, yearSelect);
	}


	// -------------------------------------------------------------------------


	function setUrlParams(keyToVals, yearSelect) {
		const ps = [];
		for (let key in keyToVals) {
			const vs = keyToVals[key];
			ps.push(`${SUB_TAX_QVAR_BASE}${key}=${vs.join(',')}`);
		}
		if (yearSelect && yearSelect.value !== VAL_YEAR_ALL) ps.push(`${YEAR_QVAR}=${yearSelect.value}`);
		if (ps.length > 0) {
			const ret = '?' + ps.join('&');
			history.replaceState('', '', ret);
		} else {
			history.replaceState('', '', document.location.origin + document.location.pathname);
		}
	}


	// -------------------------------------------------------------------------


	function assignEventListener(sw, cbs, update) {
		sw.addEventListener('click', function () {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = true;
			}
			if (!sw.checked && isCheckedAll(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = false;
			}
			update();
		});
		for (let i = 0; i < cbs.length; i += 1) {
			cbs[i].addEventListener('click', function () {
				sw.checked = isCheckedAtLeastOne(cbs);
				update();
			});
		}
	}

	function assignEventListenerSelect(yearSelect, update) {
		yearSelect.addEventListener('change', function() {
			update();
		});
	}

	function isCheckedAtLeastOne(cbs) {
		for (let i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) return true;
		}
		return false;
	}

	function isCheckedAll(cbs) {
		for (let i = 0; i < cbs.length; i += 1) {
			if (!cbs[i].checked) return false;
		}
		return true;
	}

	function getKeyToVals(keyToSwAndCbs) {
		const kvs = {};
		for (let key in keyToSwAndCbs) {
			const [sw, cbs] = keyToSwAndCbs[key];
			if (sw.checked) kvs[key] = getCheckedVals(cbs);
		}
		return kvs;
	}

	function getCheckedVals(cbs) {
		const vs = [];
		for (let i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) vs.push(cbs[i].value);
		}
		return vs;
	}


	// -------------------------------------------------------------------------


	function filterLists(elms, keyToVals, year) {
		for (let j = 0, J = elms.length; j < J; j += 1) {
			const elm = elms[j];
			if (elm.tagName !== 'OL' && elm.tagName !== 'UL') continue;
			const lis = elm.getElementsByTagName('li');
			let showCount = 0;
			for (let i = 0, I = lis.length; i < I; i += 1) {
				const li = lis[i];
				const show = isMatch(li, keyToVals, year);
				if (show) {
					showCount += 1;
					li.removeAttribute('hidden');
				} else {
					li.setAttribute('hidden', '');
				}
			}
			elm.dataset[DS_COUNT] = showCount;
		}
	}

	function isMatch(itemElm, keyToVals, year) {
		if (year !== null) {
			const cls = `${YEAR_CLS_BASE}${year}`.replace('_', '-');
			if (!itemElm.classList.contains(cls)) return false;
		}
		for (let key in keyToVals) {
			const vs = keyToVals[key];
			let contains = false;

			for (let i = 0; i < vs.length; i += 1) {
				const cls = `${SUB_TAX_CLS_BASE}${key}-${vs[i]}`.replace('_', '-');
				if (itemElm.classList.contains(cls)) {
					contains = true;
					break;
				}
			}
			if (!contains) return false;
		}
		return true;
	}


	// -------------------------------------------------------------------------


	function countUpItems(elms) {
		for (let i = 0, I = elms.length; i < I; i += 1) {
			const elm = elms[i];
			if (elm.dataset[DS_DEPTH]) elm.dataset[DS_COUNT] = 0;  // 'elm' is heading
		}
		const headers = [];
		for (let i = 0, I = elms.length; i < I; i += 1) {
			const elm = elms[i];
			if (elm.dataset[DS_DEPTH]) {  // 'elm' is heading
				const hi = parseInt(elm.dataset[DS_DEPTH]);
				while (headers.length > 0) {
					const l = headers[headers.length - 1];
					if (hi > parseInt(l.dataset[DS_DEPTH])) break;
					headers.length -= 1;
				}
				headers.push(elm);
			} else {  // 'elm' is list
				const itemCount = parseInt(elm.dataset[DS_COUNT]);
				for (let j = 0; j < headers.length; j += 1) {
					const h = headers[j];
					h.dataset[DS_COUNT] = parseInt(h.dataset[DS_COUNT]) + itemCount;
				}
			}
		}
	}

});
