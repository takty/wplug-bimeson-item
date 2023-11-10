/**
 * Bimeson File Importer
 *
 * @author Takuto Yanagida
 * @version 2023-11-10
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
			const book      = XLSX.read(bstr, {type:'binary'});
			const sheetName = book.SheetNames[0];
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
			const cell = sheet[XLSX.utils.encode_cell({c: x, r: y0})];
			if (!cell || cell.w === '') {
				colToKey[x] = false;
			} else {
				colToKey[x] = normalizeKey(cell.w + '', true);
			}
			colCount += 1;
		}
		x1 = x0 + colCount;

		for (let y = y0 + 1; y < y1; y += 1) {  // skip header
			const item = {};
			let count = 0;
			for (let x = x0; x < x1; x += 1) {
				const cell = sheet[XLSX.utils.encode_cell({c: x, r: y})];
				const key = colToKey[x];
				if (key === false) continue;
				if (key === KEY_BODY || key.indexOf(KEY_BODY + '_') === 0) {
					if (cell && cell.h && cell.h.length > 0) {
						let text = stripUnnecessarySpan(cell.h);  // remove automatically inserted 'span' tag.
						text = text.replace(/<br\/>/g, '<br />');
						text = text.replace(/&#x000d;&#x000a;/g, '<br />');
						item[key] = text;
						count += 1;
					}
				} else if (key[0] === '_') {
					if (cell && cell.w && cell.w.length > 0) {
						item[key] = cell.w;
						count += 1;
					}
				} else {
					if (cell && cell.w && cell.w.length > 0) {
						const vals = cell.w.split(/\s*,\s*/);
						item[key] = vals.map(function (x) { return normalizeKey(x, false); });
						count += 1;
					}
				}
			}
			if (0 < count) retItems.push(item);
		}
	}

	function normalizeKey(str, isKey) {
		str = str.replace(/[Ａ-Ｚａ-ｚ０-９]/g, function (s) { return String.fromCharCode(s.charCodeAt(0) - 0xFEE0); });
		str = str.replace(/[_＿]/g, '_');
		str = str.replace(/[\-‐―ー]/g, '-');
		str = str.replace(/[^A-Za-z0-9\-\_]/g, '');
		str = str.toLowerCase();
		str = str.trim();
		if (0 < str.length) {
			if (!isKey && (str[0] === '_' || str[0] === '-')) str = str.replace(/^[_\-]+/, '');
			if (str[0] !== '_') str = str.replace('_', '-');
			if (str[0] === '_') str = str.replace('-', '_');
		}
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
