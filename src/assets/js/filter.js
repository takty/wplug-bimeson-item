/**
 * Filter (Common)
 *
 * @author Takuto Yanagida
 * @version 2023-11-10
 */

document.addEventListener('DOMContentLoaded', () => {

	const SEL_FILTER = '.wplug-bimeson-filter';
	const SEL_LIST   = '.wplug-bimeson-list';

	const SEL_FILTER_KEY      = '.wplug-bimeson-filter-key';
	const SEL_FILTER_ENABLED  = '.wplug-bimeson-filter-enabled';
	const SEL_FILTER_RELATION = '.wplug-bimeson-filter-relation';
	const SEL_FILTER_CHECKBOX = '.wplug-bimeson-filter-cbs input';

	const SEL_FILTER_SELECT = '.wplug-bimeson-filter-select';
	const KEY_YEAR          = '_year';
	const VAL_YEAR_ALL      = 'all';

	const SUB_TAX_CLS_BASE  = document.getElementById('wplug-bimeson-sub-tax-cls-base')?.value  ?? 'bm-cat-';
	const SUB_TAX_QVAR_BASE = document.getElementById('wplug-bimeson-sub-tax-qvar-base')?.value ?? 'bm_';
	const YEAR_CLS_BASE     = document.getElementById('wplug-bimeson-year-cls-base')?.value     ?? 'bm-year-';
	const YEAR_QVAR         = document.getElementById('wplug-bimeson-year-qvar')?.value         ?? 'bm_year';

	const DS_KEY   = 'key';    // For filters
	const DS_COUNT = 'count';  // For headings and lists
	const DS_DEPTH = 'depth';  // For headings

	const fs = document.querySelectorAll(SEL_FILTER);
	for (const f of fs) {
		const t = f.getAttribute('for');
		if (t) {
			let l = document.querySelector(SEL_LIST + '#' + t);
			if (!l) l = document.querySelector(SEL_LIST);
			if (l) initialize(f, l);
		} else {
			const l = document.querySelector(SEL_LIST);
			if (l) initialize(f, l);
		}

		f.removeAttribute('hidden');
	}

	function initialize(filterElm, listElm) {
		const fkElms  = filterElm.querySelectorAll(SEL_FILTER_KEY);
		const allElms = listElm.querySelectorAll(':scope > *');

		const keyToUis = new Map();
		let yearSel = null;

		for (const elm of fkElms) {
			if (elm.dataset[DS_KEY] === KEY_YEAR) {
				yearSel = elm.querySelector(SEL_FILTER_SELECT);
			} else {
				const ena = elm.querySelector(SEL_FILTER_ENABLED);
				const cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
				const rel = elm.querySelector(SEL_FILTER_RELATION);
				if (ena && cbs) {
					keyToUis.set(elm.dataset[DS_KEY], [ena, cbs, rel]);
				}
			}
		}
		for (const [key, [ena, cbs, rel]] of keyToUis) {
			assignEventListener(ena, cbs, rel, () => { update(allElms, keyToUis, yearSel); });
		}
		if (yearSel) {
			assignEventListenerSelect(yearSel, () => { update(allElms, keyToUis, yearSel); });
		}
		update(allElms, keyToUis, yearSel);
	}

	function update(allElms, keyToUis, yearSel) {
		const keyToVals = getKeyToVals(keyToUis);
		const year = (yearSel && yearSel.value !== VAL_YEAR_ALL) ? yearSel.value : null;
		filterLists(allElms, keyToVals, year);
		countUpItems(allElms);
		setUrlParams(keyToVals, yearSel);
	}


	// -------------------------------------------------------------------------


	function setUrlParams(keyToVals, yearSel) {
		const rs = { or: '', and: '.' };
		const ps = [];
		for (const [key, [vs, oa]] of keyToVals) {
			if (vs.length) {
				ps.push(`${SUB_TAX_QVAR_BASE}${key}=${rs[oa]}${vs.join(',')}`);
			}
		}
		if (yearSel && yearSel.value !== VAL_YEAR_ALL) {
			ps.push(`${YEAR_QVAR}=${yearSel.value}`);
		}
		if (ps.length > 0) {
			const ret = '?' + ps.join('&');
			history.replaceState('', '', ret);
		} else {
			history.replaceState('', '', document.location.origin + document.location.pathname);
		}
	}


	// -------------------------------------------------------------------------


	function assignEventListener(sw, cbs, rel, update) {
		sw.addEventListener('click', () => {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (const cb of cbs) cb.checked = true;
			}
			if (!sw.checked && isCheckedAll(cbs)) {
				for (const cb of cbs) cb.checked = false;
			}
			update();
		});
		for (const cb of cbs) {
			cb.addEventListener('click', () => {
				sw.checked = isCheckedAtLeastOne(cbs);
				update();
			});
		}
		if (rel) {
			rel.addEventListener('click', () => {
				update();
			});
		}
	}

	function assignEventListenerSelect(yearSel, update) {
		yearSel.addEventListener('change', () => {
			update();
		});
	}

	function isCheckedAtLeastOne(cbs) {
		for (const cb of cbs) {
			if (cb.checked) return true;
		}
		return false;
	}

	function isCheckedAll(cbs) {
		for (const cb of cbs) {
			if (!cb.checked) return false;
		}
		return true;
	}

	function getKeyToVals(keyToUis) {
		const kvs = new Map();
		for (const [key, [ena, cbs, rel]] of keyToUis) {
			if (ena.checked) {
				const oa = (rel && rel.checked) ? 'and' : 'or';
				kvs.set(key, [getCheckedVals(cbs), oa]);
			}
		}
		return kvs;
	}

	function getCheckedVals(cbs) {
		const vs = [];
		for (const cb of cbs) {
			if (cb.checked) vs.push(cb.value);
		}
		return vs;
	}


	// -------------------------------------------------------------------------


	function filterLists(elms, keyToVals, year) {
		for (const elm of elms) {
			if (elm.tagName !== 'OL' && elm.tagName !== 'UL') continue;
			const lis = elm.getElementsByTagName('li');
			let showCount = 0;
			for (const li of lis) {
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
		for (const [key, [vs, oa]] of keyToVals) {
			switch (oa) {
				case 'or':
					if (!isMatchOneOr(itemElm, key, vs)) return false;
					break;
				case 'and':
					if (!isMatchOneAnd(itemElm, key, vs)) return false;
					break;
			}
		}
		return true;
	}

	function isMatchOneOr(itemElm, key, vs) {
		for (const v of vs) {
			const c = `${SUB_TAX_CLS_BASE}${key}-${v}`.replace('_', '-');
			if (itemElm.classList.contains(c)) {
				return true;
			}
		}
		return false;
	}

	function isMatchOneAnd(itemElm, key, vs) {
		for (const v of vs) {
			const c = `${SUB_TAX_CLS_BASE}${key}-${v}`.replace('_', '-');
			if (!itemElm.classList.contains(c)) {
				return false;
			}
		}
		return true;
	}


	// -------------------------------------------------------------------------


	function countUpItems(elms) {
		for (const elm of elms) {
			if (elm.dataset[DS_DEPTH]) elm.dataset[DS_COUNT] = 0;  // 'elm' is heading
		}
		const headers = [];
		for (const elm of elms) {
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
				for (const h of headers) {
					h.dataset[DS_COUNT] = parseInt(h.dataset[DS_COUNT]) + itemCount;
				}
			}
		}
	}

});
