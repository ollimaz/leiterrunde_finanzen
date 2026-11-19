/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { mountApp, h, badge, toast, formatDate, formatMoney, put, lrPageHeader, field, checkField, formActions, emptyState, listView, persistForm, menuButton, BADGE_LABELS } = window.LeiterrundeUI;
const { t: _t, n: _n } = window.LeiterrundeUI.l10n('leiterrunde_finanzen');

const APP = 'leiterrunde_finanzen';

const NAV = [
	{ route: '#/erfassen', label: _t('New entry'), cap: null, icon: 'plus' },
	{ route: '#/auslagen', label: _t('Expenses'), cap: null, icon: 'receipt' },
	{ route: '#/zahlungen', label: _t('Incoming payments'), cap: 'finance.read', icon: 'clock' },
	{ route: '#/umsaetze', label: _t('Bank transactions'), cap: 'finance.read', icon: 'swap' },
	{ route: '#/buecher', label: _t('Accounting books'), cap: 'finance.read', icon: 'bank' },
	{ route: '#/import', label: _t('Import'), cap: 'finance.write', icon: 'import' },
	{ route: '#/konten', label: _t('Bank accounts'), cap: 'finance.read', icon: 'bank' },
];

const PAYMENT_METHODS = ['personal', 'cash', 'card', 'transfer'];
function methodLabel(m) {
	return { personal: _t('Personal advance'), cash: _t('Cash'), card: _t('Card'), transfer: _t('Bank transfer') }[m] || m;
}

async function paymentSelectors(api, current = {}) {
	const opts = await api('/books/options').catch(() => ({ events: [], books: [], accounts: [] }));
	const eventSel = h('select', {},
		h('option', { value: '' }, _t('No event / general')),
		...opts.events.map(e => h('option', { value: e.id, selected: current.event_id === e.id }, e.title)));
	const newEvent = h('input', { placeholder: _t('…or create new event') });
	const bookSel = h('select', {});
	const methodSel = h('select', {},
		...PAYMENT_METHODS.map(v => h('option', { value: v, selected: (current.payment_method || 'personal') === v }, methodLabel(v))));
	const accSel = h('select', {},
		h('option', { value: '' }, '–'),
		...opts.accounts.map(a => h('option', { value: a.id, selected: current.source_account_id === a.id }, a.name)));
	const refreshBooks = () => {
		bookSel.textContent = '';
		bookSel.append(h('option', { value: '' }, '–'));
		opts.books.filter(b => (b.event_id || null) === (eventSel.value ? +eventSel.value : null))
			.forEach(b => bookSel.append(h('option', { value: b.id, selected: current.book_id === b.id },
				b.name + ' (' + _t(b.kind === 'cash' ? 'Cash book' : 'Bank book') + ')')));
	};
	eventSel.onchange = refreshBooks;
	refreshBooks();
	return {
		opts, eventSel, bookSel, methodSel, accSel, newEvent,
		values: () => ({
			event_id: eventSel.value ? +eventSel.value : 0,
			book_id: bookSel.value ? +bookSel.value : 0,
			payment_method: methodSel.value,
			source_account_id: accSel.value ? +accSel.value : 0,
			new_event: eventSel.value ? '' : newEvent.value.trim(),
		}),
	};
}

function bookSelect(t, books, api, reload) {
	return h('select', { onchange: async (e) => {
		try {
			await api('/transactions/' + t.id + '/assign', { method: 'POST', json: { obligation_id: t.obligation_id, book_id: e.target.value ? +e.target.value : 0 } });
			toast(_t('Saved'), false); reload();
		} catch (er) { toast(er.message); }
	} },
		h('option', { value: '' }, '–'),
		...books.filter(b => b.kind === 'bank' && b.account_id === t.account_id)
			.map(b => h('option', { value: b.id, selected: t.book_id === b.id }, b.name)));
}

function assignSelect(t, open, api, reload) {
	return h('select', { onchange: async (e) => {
		if (e.target.value) {
			try {
				await api('/transactions/' + t.id + '/assign', { method: 'POST', json: { obligation_id: +e.target.value } });
				toast(_t('Assigned'), false); reload();
			} catch (er) { toast(er.message); }
		}
	} },
		h('option', { value: '' }, '–'),
		...open.map(o => h('option', { value: o.id }, o.label + ' (' + formatMoney(o.remaining ?? o.amount_due) + ')')));
}

async function viewUmsaetze(v, ME, { api }) {
	const rows = await api('/transactions?unassigned=1');
	const obligations = await api('/obligations');
	const opts = await api('/books/options').catch(() => ({ books: [] }));
	const open = obligations.filter(o => o.status === 'open' || o.status === 'partial');
	const canWrite = ME.caps['finance.write'];
	const lv = listView({
		scope: 'umsaetze',
		search: { placeholder: _t('Search transactions …'), text: t => [t.counterparty, t.purpose].filter(Boolean).join(' ') },
		filters: [{ key: 'assigned', label: _t('Assignment'), options: [{ value: '', label: _t('All') }, { value: 'open', label: _t('Unassigned') }, { value: 'done', label: _t('Assigned') }],
			apply: (t, val) => val === 'open' ? !t.obligation_id : val === 'done' ? !!t.obligation_id : true }],
		sorts: [
			{ key: 'date', label: _t('Date'), value: t => t.booking_date || '', dirs: [-1, 1] },
			{ key: 'amount', label: _t('Amount'), value: t => +t.amount, dirs: [-1, 1] },
			{ key: 'cp', label: _t('Counterparty'), value: t => t.counterparty || '' },
		],
		headers: [_t('Date'), _t('Amount'), _t('Counterparty'), _t('Purpose'), _t('Assignment'), _t('Book'), canWrite ? '' : null].filter(h => h !== null),
		row: (t) => h('tr', {},
			h('td', {}, formatDate(t.booking_date)), h('td', { class: 'num' }, formatMoney(t.amount)),
			h('td', {}, t.counterparty || ''), h('td', {}, t.purpose || ''),
			h('td', {}, t.obligation_id ? _t('Assigned') + ' #' + t.obligation_id : assignSelect(t, open, api, () => viewUmsaetze(v, ME, { api }))),
			h('td', {}, canWrite && +t.amount > 0 ? bookSelect(t, opts.books, api, () => viewUmsaetze(v, ME, { api })) : '–'),
			canWrite ? h('td', { class: 'lr-actions' }, h('button', { class: 'lr-btn quiet', onclick: async () => {
				if (!confirm(_t('Delete this transaction?'))) return;
				try { await api('/transactions/' + t.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewUmsaetze(v, ME, { api }); }
				catch (e) { toast(e.message); }
			} }, _t('Delete'))) : null),
		empty: _t('No transactions.'),
	});
	lv.setRows(rows);
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Bank transactions'), lede: _n('%n transaction', '%n transactions', rows.length) + ', ' + _n('%n unassigned', '%n unassigned', rows.filter(t => !t.obligation_id).length) + ' – ' + _t('imported bank movements, assign them to incoming payments or books.') }),
		lv.el);
}

async function viewZahlungen(v, ME, { api }) {
	const rows = await api('/obligations');
	const canWrite = ME.caps['finance.write'];
	const opts = await api('/books/options').catch(() => ({ events: [] }));
	const eventName = id => { const e = opts.events.find(x => x.id === id); return e ? e.title : (id ? '#' + id : ''); };
	const lv = listView({
		scope: 'zahlungen',
		search: { placeholder: _t('Search payments …'), text: o => [o.label, o.payer_name, o.reference].filter(Boolean).join(' ') },
		filters: [
			{ key: 'status', label: _t('Status'), options: [{ value: '', label: _t('All') }, ...['open', 'partial', 'paid', 'cancelled'].map(s => ({ value: s, label: BADGE_LABELS[s] || s }))],
				apply: (o, val) => o.status === val },
		],
		sorts: [
			{ key: 'label', label: _t('Label'), value: o => o.label || '' },
			{ key: 'due', label: _t('Due'), value: o => o.due_date || '9999' },
			{ key: 'amount', label: _t('Amount'), value: o => +o.amount_due, dirs: [-1, 1] },
		],
		headers: [_t('Label'), _t('Payer'), _t('Amount'), _t('Open'), _t('Due'), _t('Reference'), _t('Event'), _t('Status'), canWrite ? '' : null].filter(Boolean),
		row: (o) => h('tr', {},
			h('td', {}, o.label), h('td', {}, o.payer_name || ''),
			h('td', {}, formatMoney(o.amount_due)), h('td', {}, o.remaining !== undefined ? formatMoney(o.remaining) : '–'),
			h('td', {}, formatDate(o.due_date) || ''), h('td', {}, o.reference || ''), h('td', {}, eventName(o.event_id)), h('td', {}, badge(o.status)),
			canWrite ? h('td', { class: 'lr-actions' }, menuButton(_t('Actions'), [
				{ label: _t('Edit'), onclick: () => obligationForm(v, ME, { api }, o, opts.events) },
				{ label: _t('Delete'), danger: true, onclick: async () => {
					if (!confirm(_t('Delete payment "{label}"?', { label: o.label }))) return;
					try { await api('/obligations/' + o.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewZahlungen(v, ME, { api }); }
					catch (e) { toast(e.message); }
				} },
			])) : null),
		empty: _t('No incoming payments.'),
	});
	lv.setRows(rows);
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Incoming payments'),
			lede: _t('Money the group still expects. Incoming bank transfers are matched and marked paid automatically.'),
			actions: canWrite ? [h('button', { class: 'lr-btn', onclick: () => { location.hash = '#/erfassen/eingang'; } }, _t('New entry'))] : [] }),
		lv.el);
}

function obligationForm(v, ME, { api }, o, events = []) {
	const label = h('input', { value: o.label }), payer = h('input', { value: o.payer_name || '' }),
		amount = h('input', { type: 'number', step: '0.01', value: o.amount_due }),
		due = h('input', { type: 'date', value: o.due_date || '' }), ref = h('input', { value: o.reference || '' }),
		notes = h('input', { value: o.notes || '' }),
		eventSel = h('select', {}, h('option', { value: '' }, _t('No event / general')),
			...events.map(e => h('option', { value: e.id, selected: o.event_id === e.id }, e.title))),
		status = h('select', {}, ...['open', 'partial', 'paid', 'cancelled'].map(s => h('option', { value: s, selected: o.status === s }, s)));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Edit payment') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Label'), label, 4), field(_t('Payer'), payer, 4), field(_t('Amount'), amount, 2),
			field(_t('Due'), due, 4), field(_t('Reference'), ref, 4), field(_t('Status'), status, 2), field(_t('Notes'), notes, 6),
			field(_t('Event'), eventSel, 4),
			formActions(
				h('button', { class: 'lr-btn', onclick: async () => {
					if (!label.value.trim()) { toast(_t('Please enter a label')); return; }
					const body = { label: label.value, payer_name: payer.value || null, amount_due: +amount.value, due_date: due.value || null, reference: ref.value || null, notes: notes.value || null, event_id: eventSel.value ? +eventSel.value : 0 };
					if (status.value !== o.status) body.status = status.value;
					try { await api('/obligations/' + o.id, { method: 'PUT', json: body }); toast(_t('Saved'), false); viewZahlungen(v, ME, { api }); }
					catch (e) { toast(e.message); }
				} }, _t('Save')),
				h('button', { class: 'lr-btn secondary', onclick: () => viewZahlungen(v, ME, { api }) }, _t('Cancel')))));
}

async function viewAuslagen(v, ME, { api, coreApi }) {
	const rows = await api('/expenses');
	const canDecide = ME.caps['expenses.decide'], canPay = ME.caps['expenses.pay'];
	const isOwnPending = e => ME.person && e.person_id === ME.person.id && e.submitted_by === ME.uid && e.status === 'pending';
	const canMutate = e => canDecide || isOwnPending(e);
	const showActions = canDecide || canPay || rows.some(canMutate);
	const persons = await coreApi('/persons/options').catch(() => []);
	const cats = await api('/expenses/categories').catch(() => []);
	const opts = await api('/books/options').catch(() => ({ events: [] }));
	const eventName = id => { const e = opts.events.find(x => x.id === id); return e ? e.title : (id ? '#' + id : ''); };
	const showMethod = m => methodLabel(m) || '–';
	const name = id => { const p = persons.find(x => x.id === id); return p ? p.last_name + ', ' + p.first_name : '#' + id; };
	const act = async (id, action) => {
		try { await api('/expenses/' + id + '/' + action, { method: 'POST', json: {} }); viewAuslagen(v, ME, { api, coreApi }); }
		catch (e) { toast(e.message); }
	};
	const lv = listView({
		scope: 'auslagen',
		search: { placeholder: _t('Search expenses …'), text: e => [name(e.person_id), e.category, e.description].filter(Boolean).join(' ') },
		filters: [
			{ key: 'status', label: _t('Status'), options: [{ value: '', label: _t('All') }, ...['pending', 'approved', 'rejected', 'paid'].map(s => ({ value: s, label: BADGE_LABELS[s] || s }))],
				apply: (e, val) => e.status === val },
		],
		sorts: [
			{ key: 'date', label: _t('Date'), value: e => e.expense_date || '', dirs: [-1, 1] },
			{ key: 'person', label: _t('Person'), value: e => name(e.person_id) },
			{ key: 'amount', label: _t('Amount'), value: e => +e.amount, dirs: [-1, 1] },
		],
		headers: [_t('Person'), _t('Date'), _t('Amount'), _t('Category'), _t('Event'), _t('Method'), _t('Status'), _t('Receipt'), showActions ? _t('Action') : null].filter(Boolean),
		row: (e) => h('tr', {},
			h('td', {}, name(e.person_id)), h('td', {}, formatDate(e.expense_date)), h('td', { class: 'num' }, formatMoney(e.amount)),
			h('td', {}, e.category || ''), h('td', {}, eventName(e.event_id)), h('td', {}, showMethod(e.payment_method)), h('td', {}, badge(e.status)),
			h('td', {}, e.file_id ? h('a', { href: OC.generateUrl('/apps/' + APP + '/api/expenses/' + e.id + '/file'), target: '_blank' }, e.file_name || _t('Receipt'))
				: (canMutate(e) && e.status === 'pending' ? receiptUpload(e, api, () => viewAuslagen(v, ME, { api, coreApi })) : '–')),
			showActions ? h('td', { class: 'lr-actions' }, menuButton(_t('Actions'), [
				canDecide && e.status === 'pending' ? { label: _t('Approve'), onclick: () => act(e.id, 'approve') } : null,
				canDecide && e.status === 'pending' ? { label: _t('Reject'), onclick: () => act(e.id, 'reject') } : null,
				canPay && e.status === 'approved' ? { label: _t('Pay'), onclick: () => act(e.id, 'pay') } : null,
				canDecide && (e.status === 'approved' || e.status === 'rejected') ? { label: _t('Reopen'), onclick: () => act(e.id, 'reopen') } : null,
				canMutate(e) ? { label: _t('Edit'), onclick: () => expenseForm(v, ME, { api, coreApi }, e, cats) } : null,
				canMutate(e) ? { label: _t('Delete'), danger: true, onclick: async () => {
					if (!confirm(_t('Delete this expense?'))) return;
					try { await api('/expenses/' + e.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewAuslagen(v, ME, { api, coreApi }); }
					catch (er) { toast(er.message); }
				} } : null,
			])) : null),
		empty: _t('No expenses.'),
	});
	lv.setRows(rows);
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Expenses'),
			actions: [h('button', { class: 'lr-btn', onclick: () => { location.hash = '#/erfassen/auslage'; } }, _t('New entry'))] }),
		lv.el);
}

function receiptUpload(e, api, reload) {
	const input = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic', style: 'display:none', onchange: async () => {
		if (!input.files[0]) return;
		const fd = new FormData();
		fd.append('file', input.files[0]);
		try { await api('/expenses/' + e.id + '/file', { method: 'POST', body: fd }); toast(_t('Receipt uploaded'), false); reload(); }
		catch (er) { toast(er.message); }
	} });
	const btn = h('button', { class: 'lr-btn secondary', onclick: () => input.click() }, _t('Upload receipt'));
	return h('span', {}, input, btn);
}

async function expenseForm(v, ME, { api, coreApi }, e, cats) {
	const pay = await paymentSelectors(api, e);
	const date = h('input', { type: 'date', max: new Date().toISOString().slice(0, 10), value: e.expense_date }),
		amount = h('input', { type: 'number', step: '0.01', min: '0.01', inputmode: 'decimal', value: e.amount }),
		cat = cats.length ? h('select', {}, ...cats.map(c => h('option', { value: c, selected: e.category === c }, c))) : h('input', { value: e.category || '' }),
		desc = h('input', { value: e.description || '' });
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Edit expense') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Date'), date, 3), field(_t('Amount'), amount, 3),
			field(_t('Category'), cat, 4), field(_t('Description'), desc, 8),
			field(_t('Event'), pay.eventSel, 4), field(_t('Accounting book'), pay.bookSel, 4),
			field(_t('Payment method'), pay.methodSel, 4), field(_t('Source account'), pay.accSel, 4),
			formActions(
				h('button', { class: 'lr-btn', onclick: async () => {
					if (!date.value || !(+amount.value > 0)) { toast(_t('Please enter a date and an amount')); return; }
					const pv = pay.values();
					try { await api('/expenses/' + e.id, { method: 'PUT', json: { expense_date: date.value, amount: +amount.value, category: cat.value || null, description: desc.value || null, event_id: pv.event_id, book_id: pv.book_id, payment_method: pv.payment_method, source_account_id: pv.source_account_id } }); toast(_t('Saved'), false); viewAuslagen(v, ME, { api, coreApi }); }
					catch (er) { toast(er.message); }
				} }, _t('Save')),
				h('button', { class: 'lr-btn secondary', onclick: () => viewAuslagen(v, ME, { api, coreApi }) }, _t('Cancel')))));
}

async function viewBuecher(v, ME, { api }) {
	const data = await api('/books');
	const canWrite = ME.caps['finance.write'];
	const pay = await paymentSelectors(api);
	const eventName = id => { const e = pay.opts.events.find(x => x.id === id); return e ? e.title : _t('General'); };
	const accName = id => { const a = pay.opts.accounts.find(x => x.id === id); return a ? a.name : '–'; };
	const name = h('input', {}), kind = h('select', {}, h('option', { value: 'bank' }, _t('Bank book')), h('option', { value: 'cash' }, _t('Cash book')));
	let form = null;
	if (canWrite) {
		form = h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Name'), name, 4), field(_t('Kind'), kind, 3), field(_t('Event'), pay.eventSel, 4), field(_t('Bank account'), pay.accSel, 4),
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				if (!name.value.trim()) { toast(_t('Please enter a name')); return; }
				try {
					await api('/books', { method: 'POST', json: { name: name.value, kind: kind.value, event_id: pay.eventSel.value ? +pay.eventSel.value : 0, account_id: kind.value === 'bank' && pay.accSel.value ? +pay.accSel.value : null } });
					toast(_t('Created'), false); viewBuecher(v, ME, { api });
				} catch (e) { toast(e.message); }
			} }, _t('Create'))));
	}
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Accounting books'),
		lede: _t('Income {income} · Outgoing {outgoing} · Committed {committed}', { income: formatMoney(data.income), outgoing: formatMoney(data.outgoing), committed: formatMoney(data.committed) }) }), form,
		data.books.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Name')), h('th', {}, _t('Event')), h('th', {}, _t('Kind')), h('th', {}, _t('Bank account')), h('th', {}, _t('Income')), h('th', {}, _t('Outgoing')), h('th', {}, _t('Committed')), h('th', {}, _t('Balance')))),
			h('tbody', {}, ...data.books.map(b => h('tr', {},
				h('td', {}, b.name), h('td', {}, eventName(b.event_id)),
				h('td', {}, _t(b.kind === 'cash' ? 'Cash book' : 'Bank book')), h('td', {}, accName(b.account_id)),
				h('td', { class: 'num' }, formatMoney(b.income)), h('td', { class: 'num' }, formatMoney(b.outgoing)),
				h('td', { class: 'num' }, formatMoney(b.committed)), h('td', { class: 'num' }, formatMoney(b.balance))))))
			: emptyState(_t('No accounting books yet.')));
}

async function viewImport(v, ME, { api }) {
	const accounts = await api('/accounts');
	const acc = h('select', {}, ...accounts.map(a => h('option', { value: a.id }, a.name)));
	const fmt = h('select', {}, h('option', { value: 'csv' }, 'CSV'), h('option', { value: 'camt053' }, 'camt.053'));
	const file = h('input', { type: 'file', accept: '.csv,.xml' });
	const result = h('p', {});
	const imap = await api('/settings').catch(() => ({}));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Import') }),
		h('h2', { class: 'lr-section' }, _t('File import (CSV / camt.053)')),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Bank account'), acc, 4), field(_t('Format'), fmt, 2), field(_t('File'), file, 6),
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				const fd = new FormData();
				fd.append('account_id', acc.value); fd.append('format', fmt.value);
				if (file.files[0]) fd.append('file', file.files[0]);
				try {
					const r = await api('/transactions/import', { method: 'POST', body: fd });
					result.textContent = _t('{imported} imported, {duplicates} duplicates, {matched} assigned', { imported: r.imported, duplicates: r.duplicates, matched: r.matched });
				} catch (e) { toast(e.message); }
			} }, _t('Import'))), result),
		h('h2', { class: 'lr-section' }, _t('IMAP fetch')),
		h('p', { class: 'lr-muted' }, _t('Last status: ') + (imap.imap_last_status || '–')),
		h('button', { class: 'lr-btn secondary', onclick: async () => {
			try { const r = await api('/imap/poll', { method: 'POST', json: {} }); toast(_t('{fetched} mails: {imported} imported', { fetched: r.fetched, imported: r.imported }), (r.errors || []).length > 0); viewImport(v, ME, { api }); }
			catch (e) { toast(e.message); }
		} }, _t('Fetch now')));
}

async function viewKonten(v, ME, { api }) {
	const accounts = await api('/accounts');
	const parsers = Object.entries(await api('/accounts/parsers').catch(() => ({}))).map(([key, label]) => ({ key, label }));
	const canWrite = ME.caps['finance.write'];
	const name = h('input', {}), iban = h('input', {}), hint = h('input', {});
	const parser = h('select', {}, ...parsers.map(p => h('option', { value: p.key }, p.label || p.key)));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Bank accounts') }),
		canWrite ? h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Name'), name, 3), field(_t('IBAN'), iban, 4),
			field(_t('Parser'), parser, 3), field(_t('Mail hint'), hint, 2),
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				try { await api('/accounts', { method: 'POST', json: { name: name.value, iban: iban.value, parser_key: parser.value, mail_hint: hint.value } }); toast(_t('Created'), false); viewKonten(v, ME, { api }); }
				catch (e) { toast(e.message); }
			} }, _t('Create')))) : null,
		accounts.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Name')), h('th', {}, _t('IBAN')), h('th', {}, _t('Parser')), h('th', {}, _t('Mail hint')), h('th', {}, _t('Active')), canWrite ? h('th', {}, '') : null)),
			h('tbody', {}, ...accounts.map(a => h('tr', {},
				h('td', {}, a.name), h('td', {}, a.iban || ''), h('td', {}, a.parser_key || ''),
				h('td', {}, a.mail_hint || ''), h('td', {}, a.active ? _t('yes') : '–'),
				canWrite ? h('td', { class: 'lr-actions' },
					h('button', { class: 'lr-btn secondary', onclick: () => accountForm(v, ME, { api }, a, parsers) }, _t('Edit')),
					h('button', { class: 'lr-btn quiet', onclick: async () => {
						if (!confirm(_t('Delete bank account "{name}"?', { name: a.name }))) return;
						try { await api('/accounts/' + a.id, { method: 'DELETE' }); toast(_t('Deleted'), false); viewKonten(v, ME, { api }); }
						catch (e) { toast(e.message); }
					} }, _t('Delete'))) : null))))
			: emptyState(_t('No bank accounts.')));
}

function accountForm(v, ME, { api }, a, parsers) {
	const name = h('input', { value: a.name }), iban = h('input', { value: a.iban || '' }), hint = h('input', { value: a.mail_hint || '' });
	const parser = h('select', {}, ...parsers.map(p => h('option', { value: p.key, selected: a.parser_key === p.key }, p.label || p.key)));
	const active = h('input', { type: 'checkbox' });
	active.checked = !!a.active;
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Edit bank account') }),
		h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Name'), name, 3), field(_t('IBAN'), iban, 4),
			field(_t('Parser'), parser, 3), field(_t('Mail hint'), hint, 2),
			h('div', { class: 'lr-f col-12' }, checkField(_t('Active'), active)),
			formActions(
				h('button', { class: 'lr-btn', onclick: async () => {
					if (!name.value.trim()) { toast(_t('Please enter a name')); return; }
					try { await api('/accounts/' + a.id, { method: 'PUT', json: { name: name.value, iban: iban.value || null, parser_key: parser.value, mail_hint: hint.value || null, active: active.checked } }); toast(_t('Saved'), false); viewKonten(v, ME, { api }); }
					catch (e) { toast(e.message); }
				} }, _t('Save')),
				h('button', { class: 'lr-btn secondary', onclick: () => viewKonten(v, ME, { api }) }, _t('Cancel')))));
}

async function viewErfassen(v, ME, { api, coreApi }) {
	const canWrite = ME.caps['finance.write'];
	const kind = location.hash.split('/')[2] === 'eingang' && canWrite ? 'eingang' : 'auslage';
	const typeSel = h('select', { 'aria-label': _t('Type'), 'data-nodraft': '1', onchange: (e) => { location.hash = '#/erfassen/' + e.target.value; } },
		h('option', { value: 'auslage', selected: kind === 'auslage' }, _t('Outgoing – expense / advance')),
		canWrite ? h('option', { value: 'eingang', selected: kind === 'eingang' }, _t('Incoming – someone still has to pay')) : null);
	if (kind === 'eingang') return erfassenEingang(v, ME, { api, coreApi }, typeSel);
	return erfassenAuslage(v, ME, { api, coreApi }, typeSel);
}

async function erfassenAuslage(v, ME, { api, coreApi }, typeSel) {
	const canWrite = ME.caps['finance.write'];
	let pid = canWrite ? null : (ME.person?.id ?? null);
	if (!canWrite && pid === null) {
		put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('New entry') }),
			h('p', { class: 'lr-empty' }, _t('Your account is not linked to a person.')));
		return;
	}
	const persons = canWrite ? await coreApi('/persons/options').catch(() => []) : [];
	const cats = await api('/expenses/categories').catch(() => []);
	const today = new Date().toISOString().slice(0, 10);
	const date = h('input', { type: 'date', max: today, value: today }),
		amount = h('input', { type: 'number', step: '0.01', min: '0.01', inputmode: 'decimal' }),
		cat = cats.length ? h('select', {}, ...cats.map(c => h('option', { value: c }, c))) : h('input', {}),
		desc = h('input', {}), file = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
	let pv = { values: () => ({ event_id: 0, book_id: 0, payment_method: 'personal', source_account_id: 0, new_event: '' }) };
	let extra = null;
	if (canWrite) {
		const pay = await paymentSelectors(api);
		pv = pay;
		extra = h('details', { class: 'lr-details' }, h('summary', {}, _t('Bookkeeping (optional)')),
			h('div', { class: 'lr-form lr-formgrid' },
				field(_t('Event'), pay.eventSel, 6), field(_t('New event'), pay.newEvent, 6),
				field(_t('Accounting book'), pay.bookSel, 6),
				field(_t('Payment method'), pay.methodSel, 6), field(_t('Source account'), pay.accSel, 6)));
	}
	const submit = h('button', { class: 'lr-btn', onclick: async () => {
		if (!pid) { toast(_t('Please select a person')); return; }
		if (!date.value || !(+amount.value > 0)) { toast(_t('Please enter a date and an amount')); return; }
		const fd = new FormData();
		fd.append('person_id', pid); fd.append('expense_date', date.value);
		fd.append('amount', amount.value); fd.append('category', cat.value); fd.append('description', desc.value);
		const pvv = pv.values();
		fd.append('event_id', pvv.event_id); fd.append('book_id', pvv.book_id);
		fd.append('payment_method', pvv.payment_method); fd.append('source_account_id', pvv.source_account_id);
		if (pvv.new_event) fd.append('new_event', pvv.new_event);
		if (file.files[0]) fd.append('file', file.files[0]);
		try { await api('/expenses', { method: 'POST', body: fd }); draft.clear(); toast(_t('Submitted'), false); location.hash = '#/auslagen'; }
		catch (e) { toast(e.message); }
	} }, _t('Submit'));
	const form = h('div', { class: 'lr-form lr-formgrid' },
		field(_t('Type'), typeSel, 4),
		canWrite ? field(_t('Person'), window.LeiterrundeUI.personPicker({ persons, onChange: (id) => { pid = id; } }), 4) : null,
		field(_t('Date'), date, 4), field(_t('Amount'), amount, 4),
		field(_t('Category'), cat, 4), field(_t('Description'), desc, 8),
		field(_t('Receipt'), file, 6, _t('Needed for approval – PDF or photo')),
		formActions(submit));
	const draft = persistForm(form, APP + '.erfassen-auslage');
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('New entry') }), form, extra);
}

async function erfassenEingang(v, ME, { api, coreApi }, typeSel) {
	const persons = await coreApi('/persons/options').catch(() => []);
	const opts = await api('/books/options').catch(() => ({ events: [] }));
	const label = h('input', {}),
		amount = h('input', { type: 'number', step: '0.01', min: '0.01', inputmode: 'decimal' }),
		due = h('input', { type: 'date' }), ref = h('input', {}),
		eventSel = h('select', {}, h('option', { value: '' }, _t('No event / general')),
			...opts.events.map(e => h('option', { value: e.id }, e.title))),
		newEvent = h('input', { placeholder: _t('…or create new event') }),
		gotIt = h('input', { type: 'checkbox' });
	let pid = null, payerName = null;
	const payerCtl = persons.length
		? window.LeiterrundeUI.personPicker({ persons, onChange: (id) => {
			pid = id;
			const p = persons.find(x => x.id === id);
			payerName = p ? p.last_name + ', ' + p.first_name : null;
		} })
		: h('input', { oninput: (e) => { payerName = e.target.value; } });
	const submit = h('button', { class: 'lr-btn', onclick: async () => {
		if (!label.value.trim() || !(+amount.value > 0)) { toast(_t('Please enter a label and an amount')); return; }
		try {
			const o = await api('/obligations', { method: 'POST', json: {
				label: label.value, amount_due: +amount.value,
				payer_name: payerName, person_id: pid,
				due_date: due.value || null, reference: ref.value || null,
				event_id: eventSel.value ? +eventSel.value : 0,
				new_event: eventSel.value ? '' : newEvent.value.trim(),
			} });
			if (gotIt.checked) {
				try { await api('/obligations/' + o.id, { method: 'PUT', json: { status: 'paid' } }); }
				catch (er) { toast(er.message); }
			}
			draft.clear(); toast(_t('Recorded'), false); location.hash = '#/zahlungen';
		} catch (e) { toast(e.message); }
	} }, _t('Submit'));
	const form = h('div', { class: 'lr-form lr-formgrid' },
		field(_t('Type'), typeSel, 4),
		field(_t('Label'), label, 4), field(_t('Amount'), amount, 2),
		field(_t('Payer'), payerCtl, 4), field(_t('Due'), due, 4),
		field(_t('Reference'), ref, 4, _t('Goes on the bank transfer for automatic matching')),
		field(_t('Event'), eventSel, 4), field(_t('New event'), newEvent, 4),
		h('div', { class: 'lr-f col-12' }, checkField(_t('Already received'), gotIt)),
		formActions(submit));
	const draft = persistForm(form, APP + '.erfassen-eingang');
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('New entry'),
			lede: _t('For money the group still expects – e.g. camp fees. Incoming bank payments are matched automatically.') }), form);
}

mountApp({
	appId: APP,
	title: _t('Finances'),
	nav: NAV,
	routes: {
		'#/erfassen': viewErfassen,
		'#/umsaetze': viewUmsaetze,
		'#/zahlungen': viewZahlungen,
		'#/auslagen': viewAuslagen,
		'#/buecher': viewBuecher,
		'#/import': viewImport,
		'#/konten': viewKonten,
	},
	defaultRoute: '#/umsaetze',
});
