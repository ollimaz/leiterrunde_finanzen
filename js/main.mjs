/* SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { mountApp, h, badge, toast, formatDate, formatMoney, put, lrPageHeader, field, formActions, emptyState } = window.LeiterrundeUI;
const { t: _t, n: _n } = window.LeiterrundeUI.l10n('leiterrunde_finanzen');

const APP = 'leiterrunde_finanzen';

const NAV = [
	{ route: '#/umsaetze', label: _t('Transactions'), cap: 'finance.read', icon: 'swap' },
	{ route: '#/zahlungen', label: _t('Open payments'), cap: 'finance.read', icon: 'clock' },
	{ route: '#/auslagen', label: _t('Expenses'), cap: null, icon: 'receipt' },
	{ route: '#/import', label: _t('Import'), cap: 'finance.write', icon: 'import' },
	{ route: '#/konten', label: _t('Bank accounts'), cap: 'finance.read', icon: 'bank' },
];

function assignSelect(t, open, api) {
	return h('select', { onchange: async (e) => {
		if (e.target.value) {
			await api('/transactions/' + t.id + '/assign', { method: 'POST', json: { obligation_id: +e.target.value } });
			toast(_t('Assigned'), false);
		}
	} },
		h('option', { value: '' }, '–'),
		...open.map(o => h('option', { value: o.id }, o.label + ' (' + formatMoney(o.remaining ?? o.amount_due) + ')')));
}

async function viewUmsaetze(v, ME, { api }) {
	const rows = await api('/transactions?unassigned=1');
	const obligations = await api('/obligations');
	const open = obligations.filter(o => o.status === 'open' || o.status === 'partial');
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Transactions'), lede: _n('%n transaction', '%n transactions', rows.length) + ', ' + _n('%n unassigned', '%n unassigned', rows.filter(t => !t.obligation_id).length) + '.' }),
		rows.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Date')), h('th', {}, _t('Amount')), h('th', {}, _t('Counterparty')), h('th', {}, _t('Purpose')), h('th', {}, _t('Assignment')))),
			h('tbody', {}, ...rows.map(t => h('tr', {},
				h('td', {}, formatDate(t.booking_date)), h('td', { class: 'num' }, formatMoney(t.amount)),
				h('td', {}, t.counterparty || ''), h('td', {}, t.purpose || ''),
				h('td', {}, t.obligation_id ? _t('Assigned') + ' #' + t.obligation_id : assignSelect(t, open, api))))))
			: emptyState(_t('No transactions.')));
}

async function viewZahlungen(v, ME, { api }) {
	const rows = await api('/obligations');
	const canWrite = ME.caps['finance.write'];
	let form = null;
	if (canWrite) {
		const label = h('input', {}), payer = h('input', {}), amount = h('input', { type: 'number', step: '0.01' }),
			due = h('input', { type: 'date' }), ref = h('input', {});
		form = h('div', { class: 'lr-form lr-formgrid' },
			field(_t('Label'), label, 4), field(_t('Payer'), payer, 4), field(_t('Amount'), amount, 2),
			field(_t('Due'), due, 4), field(_t('Reference'), ref, 4),
			formActions(h('button', { class: 'lr-btn', onclick: async () => {
				try { await api('/obligations', { method: 'POST', json: { label: label.value, payer_name: payer.value, amount_due: +amount.value, due_date: due.value, reference: ref.value } }); toast(_t('Created'), false); viewZahlungen(v, ME, { api }); }
				catch (e) { toast(e.message); }
			} }, _t('Create'))));
	}
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Open payments') }), form,
		rows.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Label')), h('th', {}, _t('Payer')), h('th', {}, _t('Amount')), h('th', {}, _t('Open')), h('th', {}, _t('Due')), h('th', {}, _t('Reference')), h('th', {}, _t('Status')))),
			h('tbody', {}, ...rows.map(o => h('tr', {},
				h('td', {}, o.label), h('td', {}, o.payer_name || ''),
				h('td', {}, formatMoney(o.amount_due)), h('td', {}, o.remaining !== undefined ? formatMoney(o.remaining) : '–'),
				h('td', {}, formatDate(o.due_date) || ''), h('td', {}, o.reference || ''), h('td', {}, badge(o.status))))))
			: emptyState(_t('No open payments.')));
}

async function viewAuslagen(v, ME, { api, coreApi }) {
	const rows = await api('/expenses');
	const canDecide = ME.caps['expenses.decide'], canPay = ME.caps['expenses.pay'];
	const persons = await coreApi('/persons/options').catch(() => []);
	const cats = await api('/expenses/categories').catch(() => []);
	const name = id => { const p = persons.find(x => x.id === id); return p ? p.last_name + ', ' + p.first_name : '#' + id; };
	const forOthers = !!ME.caps['finance.write'];
	const act = async (id, action) => {
		try { await api('/expenses/' + id + '/' + action, { method: 'POST', json: {} }); viewAuslagen(v, ME, { api, coreApi }); }
		catch (e) { toast(e.message); }
	};
	let pid = forOthers ? null : (ME.person?.id ?? null);
	const date = h('input', { type: 'date', max: new Date().toISOString().slice(0, 10) }),
		amount = h('input', { type: 'number', step: '0.01', min: '0.01', inputmode: 'decimal' }),
		cat = cats.length ? h('select', {}, ...cats.map(c => h('option', { value: c }, c))) : h('input', {}),
		desc = h('input', {}), file = h('input', { type: 'file', accept: '.pdf,.jpg,.jpeg,.png,.heic' });
	const submit = h('button', { class: 'lr-btn', onclick: async () => {
		if (!pid) { toast(_t('Please select a person')); return; }
		if (!date.value || !(+amount.value > 0)) { toast(_t('Please enter a date and an amount')); return; }
		const fd = new FormData();
		fd.append('person_id', pid); fd.append('expense_date', date.value);
		fd.append('amount', amount.value); fd.append('category', cat.value); fd.append('description', desc.value);
		if (file.files[0]) fd.append('file', file.files[0]);
		try { await api('/expenses', { method: 'POST', body: fd }); toast(_t('Submitted'), false); viewAuslagen(v, ME, { api, coreApi }); }
		catch (e) { toast(e.message); }
	} }, _t('Submit'));
	const form = (forOthers || pid)
		? h('div', { class: 'lr-form lr-formgrid' },
			forOthers ? field(_t('Person'), window.LeiterrundeUI.personPicker({ persons, onChange: (id) => { pid = id; } }), 6) : null,
			field(_t('Date'), date, 3), field(_t('Amount'), amount, 3),
			field(_t('Category'), cat, 4), field(_t('Description'), desc, 8),
			field(_t('Receipt'), file, 6),
			formActions(submit))
		: h('p', { class: 'lr-empty' }, _t('Your account is not linked to a person.'));
	put(v, lrPageHeader({ kicker: _t('Leiterrunde · Finances'), title: _t('Expenses') }), form,
		rows.length ? h('table', { class: 'lr-table' },
			h('thead', {}, h('tr', {}, h('th', {}, _t('Person')), h('th', {}, _t('Date')), h('th', {}, _t('Amount')), h('th', {}, _t('Category')), h('th', {}, _t('Status')), h('th', {}, _t('Receipt')), (canDecide || canPay) ? h('th', {}, _t('Action')) : null)),
			h('tbody', {}, ...rows.map(e => h('tr', {},
				h('td', {}, name(e.person_id)), h('td', {}, formatDate(e.expense_date)), h('td', { class: 'num' }, formatMoney(e.amount)),
				h('td', {}, e.category || ''), h('td', {}, badge(e.status)),
				h('td', {}, e.file_id ? h('a', { href: OC.generateUrl('/apps/' + APP + '/api/expenses/' + e.id + '/file'), target: '_blank' }, e.file_name || _t('Receipt')) : '–'),
				(canDecide || canPay) ? h('td', { class: 'lr-actions' },
					canDecide && e.status === 'pending' ? h('button', { class: 'lr-btn', onclick: () => act(e.id, 'approve') }, _t('Approve')) : null,
					canDecide && e.status === 'pending' ? h('button', { class: 'lr-btn quiet', onclick: () => act(e.id, 'reject') }, _t('Reject')) : null,
					canPay && e.status === 'approved' ? h('button', { class: 'lr-btn', onclick: () => act(e.id, 'pay') }, _t('Pay')) : null) : null))))
			: emptyState(_t('No expenses.')));
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
			try { const r = await api('/imap/poll', { method: 'POST', json: {} }); toast(_t('{fetched} mails: {imported} imported', { fetched: r.fetched, imported: r.imported }), (r.errors || []).length > 0); }
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
			h('thead', {}, h('tr', {}, h('th', {}, _t('Name')), h('th', {}, _t('IBAN')), h('th', {}, _t('Parser')), h('th', {}, _t('Mail hint')), h('th', {}, _t('Active')))),
			h('tbody', {}, ...accounts.map(a => h('tr', {},
				h('td', {}, a.name), h('td', {}, a.iban || ''), h('td', {}, a.parser_key || ''),
				h('td', {}, a.mail_hint || ''), h('td', {}, a.active ? _t('yes') : '–')))))
			: emptyState(_t('No bank accounts.')));
}

mountApp({
	appId: APP,
	title: _t('Finances'),
	nav: NAV,
	routes: {
		'#/umsaetze': viewUmsaetze,
		'#/zahlungen': viewZahlungen,
		'#/auslagen': viewAuslagen,
		'#/import': viewImport,
		'#/konten': viewKonten,
	},
	defaultRoute: '#/umsaetze',
});
