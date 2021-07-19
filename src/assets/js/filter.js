/**
 *
 * Publication List Filter (JS)
 *
 * @author Takuto Yanagida
 * @version 2021-07-15
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	const SEL_ITEM_ALL        = "*[data-bm]";
	const SEL_FILTER_KEY      = '.bimeson-filter-key';
	const SEL_FILTER_SWITCH   = '.bimeson-filter-switch';
	const SEL_FILTER_CHECKBOX = 'input:not(.bimeson-filter-switch)';

	const keyToSwAndCbs = {};
	const fkElms = document.querySelectorAll(SEL_FILTER_KEY);

	for (let i = 0; i < fkElms.length; i += 1) {
		const elm = fkElms[i];
		const sw  = elm.querySelector(SEL_FILTER_SWITCH);
		const cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
		if (sw && cbs) keyToSwAndCbs[elm.dataset.key] = [sw, cbs];
	}

	for (let key in keyToSwAndCbs) {
		const sw  = keyToSwAndCbs[key][0];
		const cbs = keyToSwAndCbs[key][1];
		assignEventListener(sw, cbs, update);
	}

	const allElms = document.querySelectorAll(SEL_ITEM_ALL);
	update();

	function update() {
		const keyToVals = getKeyToVals(keyToSwAndCbs);
		filterLists(allElms, keyToVals);
		setUrlParams(keyToSwAndCbs);
	}


	// -------------------------------------------------------------------------


	function setUrlParams(keyToSwAndCbs) {
		const ps = [];
		for (let key in keyToSwAndCbs) {
			const sw  = keyToSwAndCbs[key][0];
			const cbs = keyToSwAndCbs[key][1];
			if (sw.checked) ps.push('bm_cat_' + key + '=' + concatCheckedQvals(cbs));
		}
		if (ps.length > 0) {
			const ret = '?' + ps.join('&');
			history.replaceState('', '', ret);
		} else {
			history.replaceState('', '', document.location.origin + document.location.pathname);
		}
	}

	function concatCheckedQvals(cbs) {
		const vs = [];
		for (let i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) vs.push(cbs[i].value);
		}
		return vs.join(',');
	}


	// -------------------------------------------------------------------------


	function assignEventListener(sw, cbs, update) {
		sw.addEventListener('click', function () {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = true;
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

	function isCheckedAtLeastOne(cbs) {
		for (let i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) return true;
		}
		return false;
	}

	function getKeyToVals(keyToSwAndCbs) {
		const kvs = {};
		for (let key in keyToSwAndCbs) {
			const fs  = keyToSwAndCbs[key][0];
			const cbs = keyToSwAndCbs[key][1];
			if (fs.checked) kvs[key] = getCheckedVals(cbs);
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


	function filterLists(elms, fkeyToVals) {
		for (let j = 0, J = elms.length; j < J; j += 1) {
			const elm = elms[j];
			if (elm.tagName !== 'OL' && elm.tagName !== 'UL') continue;
			const lis = elm.getElementsByTagName('li');
			let showCount = 0;
			for (var i = 0, I = lis.length; i < I; i += 1) {
				const li = lis[i];
				const show = isMatch(li, fkeyToVals);
				li.classList.remove('bm-filtered');
				if (!show) li.classList.add('bm-filtered');
				if (show) showCount += 1;
			}
			elm.dataset['count'] = showCount;
		}
	}

	function isMatch(itemElm, fkeyToVals) {
		for (let key in fkeyToVals) {
			const fvals = fkeyToVals[key];
			let contains = false;

			for (let i = 0; i < fvals.length; i += 1) {
				let cls = 'bm-cat-' + key + '-' + fvals[i];
				cls = cls.replace('_', '-');
				if (itemElm.classList.contains(cls)) {
					contains = true;
					break;
				}
			}
			if (!contains) return false;
		}
		return true;
	}

});
