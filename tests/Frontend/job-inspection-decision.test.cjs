const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/job-inspection-decision.js'), 'utf8');

class Element {
    constructor() { this.hidden = true; this.disabled = true; this.textContent = ''; this.children = []; this.events = {}; this.attrs = {}; }
    addEventListener(name, callback) { this.events[name] = callback; }
    setAttribute(name, value) { this.attrs[name] = value; }
    replaceChildren() { this.children = []; }
    append(element) { this.children.push(element); }
    focus() { this.focused = true; }
}

function fixture() {
    const selectors = ['status', 'error', 'error-message', 'error-list', 'reload', 'actions', 'return'];
    const elements = Object.fromEntries(selectors.map((key) => [key, new Element()]));
    elements.actions.hidden = false;
    const fields = [new Element(), new Element(), new Element()];
    const forms = ['approve', 'reject', 'request_changes'].map((action) => {
        const form = new Element();
        const endpoint = 'http://localhost/pemerintah/api/pemeriksaan-lowongan/7/keputusan';
        // Real HTMLFormElement.action is shadowed by the hidden input named action.
        form.action = { toString: () => '[object HTMLInputElement]' };
        form.getAttribute = (name) => name === 'action' ? endpoint : null;
        form.dataset = { decisionAction: action };
        form.valid = true;
        form.reportValidity = () => form.valid;
        form.data = { action, _token: 'synthetic-csrf', ...(action === 'approve' ? {} : { rejection_reason: 'Catatan pengajuan' }) };
        return form;
    });
    const panel = new Element();
    panel.dataset = { jobId: '7' };
    panel.querySelectorAll = (selector) => selector.startsWith('form') ? forms : fields;
    panel.querySelector = (selector) => elements[selector.slice('[data-decision-'.length, -1)];
    const badge = new Element();
    badge.textContent = 'Menunggu Tinjauan';
    const calls = [];
    let confirmations = 0;
    let reloads = 0;
    let confirmResult = true;
    let fetchImpl = async (_url, options) => ({
        ok: true, status: 200,
        json: async () => ({ message: 'Keputusan berhasil disimpan.', data: { id: 7, status: options.body.get('action') === 'approve' ? 'published' : 'rejected' } }),
    });
    const document = {
        querySelectorAll: () => [panel], querySelector: () => badge, createElement: () => new Element(),
    };
    class FormDataMock {
        constructor(form) {
            assert.ok(fields.every((field) => !field.disabled), 'Payload must be captured before disabling forms');
            this.data = { ...form.data };
        }
        get(name) { return this.data[name]; }
    }
    const context = {
        document, FormData: FormDataMock,
        window: { confirm: () => { confirmations++; return confirmResult; }, location: { reload: () => { reloads++; } } },
        fetch: async (url, options) => { calls.push({ url, options }); return fetchImpl(url, options); },
    };
    vm.runInNewContext(script, context, { filename: 'job-inspection-decision.js' });
    return {
        panel, forms, fields, elements, badge, calls,
        submit: (action) => forms.find((form) => form.dataset.decisionAction === action).events.submit({ preventDefault() {} }),
        setFetch: (callback) => { fetchImpl = callback; },
        cancelConfirmation: () => { confirmResult = false; },
        confirmations: () => confirmations, reloads: () => reloads,
    };
}

for (const action of ['approve', 'reject', 'request_changes']) {
    test(`${action}: sends the existing endpoint payload and session CSRF, then updates the display`, async () => {
        const ui = fixture();
        await ui.submit(action);
        assert.equal(ui.calls.length, 1);
        const { url, options } = ui.calls[0];
        assert.equal(url, ui.forms[0].getAttribute('action'));
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers.Accept, 'application/json');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
        assert.equal(options.body.get('_token'), 'synthetic-csrf');
        assert.equal(options.body.get('action'), action);
        if (action !== 'approve') assert.equal(options.body.get('rejection_reason'), 'Catatan pengajuan');
        assert.equal(ui.confirmations(), action === 'approve' ? 1 : 0);
        assert.equal(ui.elements.actions.hidden, true);
        assert.equal(ui.elements.return.hidden, false);
        assert.equal(ui.elements.status.hidden, false);
        assert.equal(ui.elements.status.textContent, 'Keputusan berhasil disimpan.');
        assert.equal(ui.badge.textContent, action === 'approve' ? 'Disetujui / Tayang' : 'Ditolak / Perlu Perbaikan');
        assert.ok(ui.fields.every((field) => field.disabled));
        await ui.submit(action);
        assert.equal(ui.calls.length, 1, 'Completed decisions cannot be resent');
    });
}

test('cancelled approval does not send a request', async () => {
    const ui = fixture();
    ui.cancelConfirmation();
    await ui.submit('approve');
    assert.equal(ui.calls.length, 0);
    assert.ok(ui.fields.every((field) => !field.disabled));
});

test('native form validation can block submission', async () => {
    const ui = fixture();
    ui.forms[1].valid = false;
    await ui.submit('reject');
    assert.equal(ui.calls.length, 0);
});

test('all three actions are disabled while a single request is pending', async () => {
    const ui = fixture();
    let resolve;
    ui.setFetch(() => new Promise((done) => { resolve = done; }));
    const pending = ui.submit('approve');
    assert.equal(ui.panel.attrs['aria-busy'], 'true');
    assert.ok(ui.fields.every((field) => field.disabled));
    await ui.submit('reject');
    await ui.submit('approve');
    assert.equal(ui.calls.length, 1);
    resolve({ ok: true, status: 200, json: async () => ({ data: { id: 7, status: 'published' } }) });
    await pending;
    assert.equal(ui.panel.attrs['aria-busy'], 'false');
    assert.ok(ui.fields.every((field) => field.disabled));
});

test('422 displays server validation safely, preserves the note, and permits correction', async () => {
    const ui = fixture();
    const untrusted = '<img src=x onerror=alert(1)>';
    ui.setFetch(async () => ({ ok: false, status: 422, json: async () => ({ errors: { rejection_reason: [untrusted] } }) }));
    await ui.submit('reject');
    assert.equal(ui.elements.error.hidden, false);
    assert.equal(ui.elements.error.focused, true);
    assert.equal(ui.elements['error-list'].children[0].textContent, untrusted);
    assert.equal(ui.elements['error-list'].children[0].innerHTML, undefined);
    assert.equal(ui.forms[1].data.rejection_reason, 'Catatan pengajuan');
    assert.ok(ui.fields.every((field) => !field.disabled));
    assert.equal(ui.badge.textContent, 'Menunggu Tinjauan');
    await ui.submit('reject');
    assert.equal(ui.calls.length, 2);
});

for (const [status, fragment] of [[401, 'Sesi berakhir'], [403, 'tidak berwenang'], [404, 'tidak ditemukan'], [409, 'telah berubah'], [419, 'kedaluwarsa']]) {
    test(`${status}: shows an actionable error and prevents retries before reload`, async () => {
        const ui = fixture();
        ui.setFetch(async () => ({ ok: false, status, json: async () => ({}) }));
        await ui.submit('approve');
        assert.ok(ui.elements['error-message'].textContent.includes(fragment));
        assert.equal(ui.elements.reload.hidden, false);
        assert.ok(ui.fields.every((field) => field.disabled));
        await ui.submit('approve');
        assert.equal(ui.calls.length, 1);
        ui.elements.reload.events.click();
        assert.equal(ui.reloads(), 1);
    });
}

test('network failure leaves result uncertain and prevents automatic retry', async () => {
    const ui = fixture();
    ui.setFetch(async () => { throw new Error('connection lost'); });
    await ui.submit('approve');
    assert.ok(ui.elements['error-message'].textContent.includes('belum dapat dipastikan'));
    assert.ok(ui.fields.every((field) => field.disabled));
    await ui.submit('approve');
    assert.equal(ui.calls.length, 1);
});

test('non-JSON success is not misreported as saved', async () => {
    const ui = fixture();
    ui.setFetch(async () => ({ ok: true, status: 200, json: async () => { throw new Error('HTML response'); } }));
    await ui.submit('approve');
    assert.ok(ui.elements['error-message'].textContent.includes('belum dapat dipastikan'));
    assert.equal(ui.badge.textContent, 'Menunggu Tinjauan');
});

test('response for a different job is not accepted as a successful decision', async () => {
    const ui = fixture();
    ui.setFetch(async () => ({ ok: true, status: 200, json: async () => ({ data: { id: 8, status: 'published' } }) }));
    await ui.submit('approve');
    assert.equal(ui.elements.error.hidden, false);
    assert.equal(ui.elements.actions.hidden, false);
    assert.ok(ui.fields.every((field) => field.disabled));
});

test('login redirects do not display a success message', async () => {
    const ui = fixture();
    ui.setFetch(async () => ({ redirected: true, ok: true, status: 200, json: async () => ({}) }));
    await ui.submit('approve');
    assert.ok(ui.elements['error-message'].textContent.includes('Sesi tidak tersedia'));
    assert.ok(ui.fields.every((field) => field.disabled));
});

test('server error does not expose raw response text', async () => {
    const ui = fixture();
    ui.setFetch(async () => ({ ok: false, status: 500, json: async () => ({ message: 'internal stack trace' }) }));
    await ui.submit('approve');
    assert.ok(!ui.elements['error-message'].textContent.includes('stack trace'));
    assert.ok(ui.elements['error-message'].textContent.includes('belum dapat dipastikan'));
});
