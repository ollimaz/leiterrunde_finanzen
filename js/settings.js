(function(){
const t = (s, v) => window.t('leiterrunde_finanzen', s, v, undefined, { escape: false, sanitize: false });
(async () => {
	const root = document.getElementById('lr-finanzen-settings');
	if (!root) return;
	const base = OC.generateUrl('/apps/leiterrunde_finanzen/api');
	const req = async (p, o = {}) => (await fetch(base + p, { headers: { requesttoken: OC.requestToken }, ...o })).json();
	const s = await req('/settings');
	const form = document.getElementById('lr-fin-form');
	const mk = (label, input) => { form.append(Object.assign(document.createElement('label'), { textContent: label }), input); return input; };
	const host = mk(t('IMAP server'), Object.assign(document.createElement('input'), { value: s.imap_host || '' }));
	const port = mk('Port', Object.assign(document.createElement('input'), { type: 'number', value: s.imap_port || 993 }));
	const tls = mk(t('Encryption'), (() => { const sel = document.createElement('select');
		for (const [v, l] of [['ssl', 'SSL/TLS'], ['starttls', 'STARTTLS'], ['none', t('None')]]) { const o = document.createElement('option'); o.value = v; o.textContent = l; o.selected = s.imap_tls === v; sel.append(o); } return sel; })());
	const user = mk(t('Username'), Object.assign(document.createElement('input'), { value: s.imap_user || '' }));
	const pass = mk(t('Password'), Object.assign(document.createElement('input'), { type: 'password',
		placeholder: s.imap_password === 'gesetzt' ? t('saved – leave empty to keep it') : '' }));
	const folder = mk(t('Folder'), Object.assign(document.createElement('input'), { value: s.imap_folder || 'INBOX' }));
	const interval = mk(t('Fetch interval'), (() => { const sel = document.createElement('select');
		for (const v of [15, 30, 60]) { const o = document.createElement('option'); o.value = v; o.textContent = v + ' ' + t('minutes'); o.selected = String(s.imap_interval) === String(v); sel.append(o); } return sel; })());
	const enabledC = document.createElement('input'); enabledC.type = 'checkbox'; enabledC.className = 'checkbox'; enabledC.checked = s.imap_enabled === '1';
	form.append(Object.assign(document.createElement('label'), {}, enabledC, document.createTextNode(' ' + t('Enable IMAP fetch'))));
	const cats = mk(t('Expense categories (comma-separated)'), Object.assign(document.createElement('input'), { value: s.expense_categories || '' }));
	form.append(Object.assign(document.createElement('p'), { className: 'settings-hint', textContent: t('Last fetch: ') + (s.imap_last_status || '–') }));
	const msg = document.createElement('span'); msg.style.marginLeft = '8px';
	document.getElementById('lr-fin-msg').replaceWith(msg);
	document.getElementById('lr-fin-save').addEventListener('click', async () => {
		const body = { imap_host: host.value, imap_port: port.value, imap_tls: tls.value, imap_user: user.value,
			imap_folder: folder.value, imap_interval: interval.value, imap_enabled: enabledC.checked ? '1' : '0', expense_categories: cats.value };
		if (pass.value) body.imap_password = pass.value;
		const r = await req('/settings', { method: 'PUT', headers: { requesttoken: OC.requestToken, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
		msg.textContent = r.error ? ('Error: ' + r.error) : t('Saved');
	});
	document.getElementById('lr-fin-test').addEventListener('click', async () => {
		const r = await req('/imap/test', { method: 'POST', headers: { requesttoken: OC.requestToken } });
		msg.textContent = r.ok ? t('Connection OK') : ('Error: ' + (r.error || ''));
	});
})();

})();
