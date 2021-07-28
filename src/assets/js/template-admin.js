/**
 *
 * Bimeson (Template Admin)
 *
 * @author Takuto Yanagida
 * @version 2021-07-28
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	const SEL_FILTER_KEY      = '.bimeson-admin-filter-key';
	const SEL_FILTER_SWITCH   = '.bimeson-admin-filter-switch';
	const SEL_FILTER_VISIBLE  = '.bimeson-admin-filter-visible';
	const SEL_FILTER_CHECKBOX = 'input:not(.bimeson-admin-filter-switch):not(.bimeson-admin-filter-visible)';

	const keyToSwAndCbs = {};
	const fkElms = document.querySelectorAll(SEL_FILTER_KEY);
	for (let i = 0; i < fkElms.length; i += 1) {
		const elm = fkElms[i];
		const sw  = elm.querySelector(SEL_FILTER_SWITCH);
		const sv  = elm.querySelector(SEL_FILTER_VISIBLE);
		const cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
		keyToSwAndCbs[elm.dataset.key] = [sw, sv, cbs];
	}

	for (let key in keyToSwAndCbs) {
		const [sw, sv, cbs] = keyToSwAndCbs[key];
		if (isCheckedOne(cbs)) {
			sv.setAttribute('disabled', '');
		} else {
			sv.removeAttribute('disabled');
		}
		assignEventListener(sw, sv, cbs);
	}


	// -------------------------------------------------------------------------


	function assignEventListener(sw, sv, cbs) {
		sw.addEventListener('click', () => {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = true;
			}
			if (!sw.checked && isCheckedAll(cbs)) {
				for (let i = 0; i < cbs.length; i += 1) cbs[i].checked = false;
			}
		});
		for (let i = 0; i < cbs.length; i += 1) {
			cbs[i].addEventListener('click', () => {
				sw.checked = isCheckedAtLeastOne(cbs);
				if (isCheckedOne(cbs)) {
					sv.setAttribute('disabled', '');
				} else {
					sv.removeAttribute('disabled');
				}
			});
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

	function isCheckedOne(cbs) {
		let c = 0;
		for (let i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) c += 1;
		}
		return c === 1;
	}

});
