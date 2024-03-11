/**
 * Bimeson File Importer
 *
 * @author Takuto Yanagida
 * @version 2024-03-11
 */

document.addEventListener('DOMContentLoaded', () => {

	const ID_FILE_URL    = 'import-url';
	const ID_MSG_SUCCESS = 'msg-success';
	const ID_MSG_FAILURE = 'msg-failure';

	const KEY_BODY = '_body';

	const items = [];

	let receiveCount = 0;
	let successCount = 0;

	const elmFileUrl = document.getElementById(ID_FILE_URL);
	if (!elmFileUrl) return;
	const fileUrl = elmFileUrl.value;
	loadFiles([fileUrl]);

	function loadFiles(urls) {
		if (urls.length === 0 || !urls[0]) {
			console.log('Complete filtering (No data)');
			return;
		}
		for (const url of urls) {
			console.log('Requesting file...');
			const req = new XMLHttpRequest();
			req.open('GET', url, true);
			req.responseType = 'arraybuffer';
			req.onload = makeListener(req, url, urls.length);
			req.send();
		}
	}

	function makeListener(req, url, urlSize) {
		return () => {
			if (!req.response) {
				console.log('Did not receive file (' + url + ')');
				return;
			}
			console.log('Received file: ' + req.response.byteLength + ' bytes (' + url + ')');
			if (process(req.response)) successCount += 1;
			if (++receiveCount === urlSize) {
				finished(receiveCount === successCount);
			}
		};
	}

	function process(response) {
		const data = new Uint8Array(response);
		const arr  = new Array();
		for (let i = 0, I = data.length; i < I; i += 1) {
			arr[i] = String.fromCharCode(data[i]);
		}
		const bstr = arr.join('');

		try {
			const book      = XLSX.read(bstr, { type: 'binary', cellNF: false });
			const sheetName = retrieveSheetName(book);
			const sheet     = book.Sheets[sheetName];
			if (sheet) {
				processSheet(sheet, items);
				console.log('Finish filtering file');
				return true;
			}
		} catch (e) {
		}
		console.log('Error while filtering file');
		return false;
	}

	function finished(successAll) {
		console.log('Complete filtering (' + items.length + ' items)');
		if (successAll) {
			btnStartImport.removeAttribute('disabled');
		} else {
			msgFailure.removeAttribute('hidden');
		}
	}

	function retrieveSheetName(book) {
		for (const name of book.SheetNames) {
			const nn = name.trim().toLowerCase();
			if ('_list' === nn) {
				return name;
			}
		}
		return book.SheetNames[0];
}


	// -------------------------------------------------------------------------


	function processSheet(sheet, retItems) {
		const range = XLSX.utils.decode_range(sheet['!ref']);
		const x0 = range.s.c;
		const y0 = range.s.r;
		let x1 = Math.min(range.e.c, 40) + 1;
		let y1 = range.e.r + 1;

		let colCount = 0;
		const colToKey = {};

		for (let x = x0; x < x1; x += 1) {
			const cell = sheet[XLSX.utils.encode_cell({ c: x, r: y0 })];
			if (!cell || cell.w === '') {
				colToKey[x] = null;
			} else {
				colToKey[x] = normalizeKey(cell.w + '', true);
			}
			colCount += 1;
		}
		x1 = x0 + colCount;

		for (let y = y0 + 1; y < y1; y += 1) {  // skip header
			const item = {};
			const bs   = new Map();
			let count = 0;

			for (let x = x0; x < x1; x += 1) {
				const cell = sheet[XLSX.utils.encode_cell({ c: x, r: y })];
				const key  = colToKey[x];

				if (key === null) continue;
				if (key.startsWith(KEY_BODY)) {
					if (cell && cell.h && cell.h.length > 0) {
						count += 1;
						const text = prepareBodyText(cell.h);
						extractBodyText(bs, key, text);
					}
				} else if (key.startsWith('_')) {
					if (cell && cell.w && cell.w.length > 0) {
						count += 1;
						item[key] = cell.w;
					}
				} else {
					if (cell && cell.w && cell.w.length > 0) {
						count += 1;
						const vals = cell.w.split(/\s*,\s*/);
						item[key] = vals.map( x => normalizeKey(x, false) );
					}
				}
			}
			if (0 < count) {
				storeBodyText(bs, item);
				retItems.push(item);
			}
		}
	}

	function extractBodyText(bs, key, text) {
		const k = key.replace(/\[[0-9]\]$/, '');
		if (!bs.has(k)) {
			bs.set(k, { s: null, a: new Map() });
		}
		const b = bs.get(k);
		const m = key.match(/\[([0-9])\]$/);
		if (m) {
			const i = parseInt(m[1], 10);
			if (!isNaN(i)) b.a.set(i, text);
		} else {
			b.s = text;
		}
	}

	function storeBodyText(bs, item) {
		for (const k of bs.keys()) {
			const b = bs.get(k);
			if (b.s) {
				item[k] = b.s;
			} else {
				let text = '';
				for (let i = 0; i < 10; ++i) {
					text += b.a.get(i) ?? '';
				}
				item[k] = text;
			}
		}
	}

	function normalizeKey(str, isKey) {
		str = str.replace(/[Ａ-Ｚａ-ｚ０-９]/g, s => String.fromCharCode(s.charCodeAt(0) - 0xFEE0));
		str = str.replace(/[_＿]/g, '_');
		str = str.replace(/[\-‐―ー]/g, '-');
		if (isKey) {
			str = str.replace(/[^A-Za-z0-9_\- \[\]]/g, '');
		} else {
			str = str.replace(/[^A-Za-z0-9_\- ]/g, '');
		}
		str = str.toLowerCase().trim();
		if (0 < str.length) {
			if (isKey && str[0] === '_') {  // Underscore separation
				str = str.replace(/[_\- ]+/g, '_');
			} else {  // Hyphen separation
				str = str.replace(/[_\- ]+/g, '-');
				str = str.replace(/^[_\-]+/, '');
			}
		}
		return str;
	}


	// -------------------------------------------------------------------------


	function prepareBodyText(str) {
		str = stripUnnecessarySpan(str);  // remove automatically inserted 'span' tag.
		str = str.replace(/<br\/>/g, '<br>');
		str = str.replace(/&#x000d;&#x000a;/g, '<br>');
		str = restoreEscapedTag(str);
		str = stripEmptyTag(str);
		return str;
	}

	function stripUnnecessarySpan(str) {
		str = str.replace(/<span +([^>]*)>/gi, (m, p1) => {
			const as = p1.trim().toLowerCase().match(/style *= *"([^"]*)"/);
			if (as && as.length === 2) {
				const style = as[1].trim();
				if (style.search(/text-decoration *: *underline/gi) !== -1) {
					return '<span style="text-decoration:underline;">';
				}
			}
			return '<span>';
		});
		str = str.replace(/< +\/ +span +>/gi, '</span>');
		str = str.replace(/<span>(.+?)<\/span>/gi, (m, p1) => { return p1; });
		return str;
	}

	function restoreEscapedTag(str) {
		for (const t of ['b', 'i', 'sub', 'sup']) {
			str = str.replace(new RegExp(`&lt;${t}&gt;`, 'g'), `<${t}>`);
			str = str.replace(new RegExp(`&lt;\/${t}&gt;`, 'g'), `</${t}>`);
		}
		str = str.replace(/&lt;u&gt;/g, '<span style="text-decoration:underline;">');
		str = str.replace(/&lt;\/u&gt;/g, '</span>');
		str = str.replace(/&lt;br&gt;/g, '<br>');

		str = str.replace(/&lt;span +((?!&gt;).*?)&gt;/gi, (m, p1) => {
			const mc = p1.trim().toLowerCase().replaceAll('&quot;', '"').match(/class *= *"([^"]*)"/);
			if (mc && mc.length === 2) {
				const cls = mc[1].trim();
				return `<span class="${cls}">`;
			}
			return '<span>';
		});
		str = str.replace(/&lt;\/span&gt;/g, '</span>');
		return str;
	}

	function stripEmptyTag(str) {
		for (const t of ['b', 'i', 'sub', 'sup', 'span']) {
			str = str.replace(new RegExp(`<${t}><\/${t}>`, 'g'), '');  // Remove empty tag
		}
		str = str.replace(/<span +[^>]*><\/span>/gi, '');
		str = str.replace(/, , /g, ', ');
		return str.trim();
	}


	// -------------------------------------------------------------------------


	const CHUNK_SIZE = 8;

	const ID_BTN_IMPORT  = 'btn-start-import';
	const ID_SECTION_OPT = 'section-option';

	const ID_AJAX_URL  = 'import-ajax';
	const ID_FILE_ID   = 'import-file-id';
	const ID_FILE_NAME = 'import-file-name';
	const ID_MSG_RES   = 'msg-response';

	const ID_DO_NOTHING = 'do-nothing';
	const ID_ADD_TAX    = 'add-tax';
	const ID_ADD_TERM   = 'add-term';

	const btnStartImport = document.getElementById(ID_BTN_IMPORT)
	btnStartImport.addEventListener('click', () => {
		ajaxSendItems();
		btnStartImport.setAttribute('disabled', '');
		const sectionOpt = document.getElementById(ID_SECTION_OPT)
		sectionOpt.setAttribute('hidden', '');
	})
	const msgSuccess = document.getElementById(ID_MSG_SUCCESS)
	const msgFailure = document.getElementById(ID_MSG_FAILURE)
	const elmMsgRes  = document.getElementById(ID_MSG_RES);

	const ajaxUrl  = document.getElementById(ID_AJAX_URL).value;
	const fileId   = document.getElementById(ID_FILE_ID).value;
	const fileName = document.getElementById(ID_FILE_NAME).value;

	let addTax
	let addTerm;

	function ajaxSendItems() {
		if (document.getElementById(ID_DO_NOTHING).checked) {
			addTax  = false;
			addTerm = false;
		} else if (document.getElementById(ID_ADD_TAX).checked) {
			addTax  = true;
			addTerm = true;
		} else if (document.getElementById(ID_ADD_TERM).checked) {
			addTax  = false;
			addTerm = true;
		}
		console.log('ajaxSendItems: ' + ajaxUrl);
		sendStart();
	}

	function receive(req) {
		if (req.readyState !== 4) return;
		if (200 <= req.status && req.status < 300) {
			const d = JSON.parse(req.response);
			if (d['success'] === true && d['data']) {
				const msgs  = d['data']['msgs']  ?? [];
				procMsgs(msgs);

				const index = d['data']['index'] ?? 0;
				if (items.length <= index) {
					sendEnd();
					msgSuccess.removeAttribute('hidden');
				} else {
					sendItems(index, items);
				}
				return;
			}
		}
		console.log('Ajax Error!');
		msgFailure.removeAttribute('hidden');
	}

	function procMsgs(msgs) {
		for (const m of msgs) {
			const p = document.createElement('p');
			p.innerHTML = m;
			elmMsgRes.appendChild(p);
		}
		elmMsgRes.scrollTop = elmMsgRes.scrollHeight;
	}

	function sendStart() {
		const req = new XMLHttpRequest();
		req.onreadystatechange = () => { receive(req); };
		req.open('POST', ajaxUrl);
		req.setRequestHeader('content-type', 'application/json');
		req.send(JSON.stringify({
			status: 'start'
		}));
	}

	function sendItems(index, items) {
		const sub = items.slice(index, index + CHUNK_SIZE);

		const req = new XMLHttpRequest();
		req.onreadystatechange = () => { receive(req); };
		req.open('POST', ajaxUrl);
		req.setRequestHeader('content-type', 'application/json');
		req.send(JSON.stringify({
			status      : 'items',
			next_index  : index + CHUNK_SIZE,
			items       : sub,
			add_taxonomy: addTax,
			add_term    : addTerm,
			file_name   : fileName
		}));
	}

	function sendEnd() {
		const req = new XMLHttpRequest();
		req.open('POST', ajaxUrl);
		req.setRequestHeader('content-type', 'application/json');
		req.send(JSON.stringify({
			status : 'end',
			file_id: fileId,
		}));
	}

});
