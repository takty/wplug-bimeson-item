/**
 *
 * Bimeson (Template Admin)
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	const SEL_FILTER_KEY      = '.bimeson-admin-filter-key';
	const SEL_FILTER_SWITCH   = '.bimeson-admin-filter-switch';
	const SEL_FILTER_CHECKBOX = 'input:not(.bimeson-admin-filter-switch)';

	const keyToSwAndCbs = {};
	const fkElms = document.querySelectorAll(SEL_FILTER_KEY);
	for (let i = 0; i < fkElms.length; i += 1) {
		const elm = fkElms[i];
		const sw  = elm.querySelector(SEL_FILTER_SWITCH);
		const cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
		keyToSwAndCbs[elm.dataset.key] = [sw, cbs];
	}

	for (let key in keyToSwAndCbs) {
		const sw  = keyToSwAndCbs[key][0];
		const cbs = keyToSwAndCbs[key][1];
		assignEventListener(sw, cbs);
	}


	// -------------------------------------------------------------------------


	function assignEventListener(sw, cbs) {
		sw.addEventListener('click', () => {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = true;
			}
			if (!sw.checked && isCheckedAll(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = false;
			}
		});
		for (let i = 0; i < cbs.length; i += 1) {
			cbs[i].addEventListener('click', () => { sw.checked = isCheckedAtLeastOne(cbs); });
		}
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

});
