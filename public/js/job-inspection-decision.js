(() => {
    'use strict';

    document.querySelectorAll('[data-job-decision-panel]').forEach((panel) => {
        const forms = Array.from(panel.querySelectorAll('form[data-decision-action]'));
        const fields = Array.from(panel.querySelectorAll('[data-decision-fields]'));
        const status = panel.querySelector('[data-decision-status]');
        const error = panel.querySelector('[data-decision-error]');
        const errorMessage = panel.querySelector('[data-decision-error-message]');
        const errorList = panel.querySelector('[data-decision-error-list]');
        const reload = panel.querySelector('[data-decision-reload]');
        const actions = panel.querySelector('[data-decision-actions]');
        const returnLink = panel.querySelector('[data-decision-return]');
        const badge = document.querySelector('[data-job-inspection-status]');
        let busy = false;
        let locked = false;

        const setBusy = (value) => {
            busy = value;
            panel.setAttribute('aria-busy', String(value));
            fields.forEach((field) => { field.disabled = value || locked; });
        };
        const showError = (message, errors = {}) => {
            status.hidden = true;
            errorMessage.textContent = message;
            errorList.replaceChildren();
            Object.values(errors || {}).flat().forEach((text) => {
                if (typeof text !== 'string') return;
                const item = document.createElement('li');
                item.textContent = text;
                errorList.append(item);
            });
            error.hidden = false;
            error.focus();
        };
        const stopUntilReload = (message) => {
            locked = true;
            reload.hidden = false;
            showError(message);
        };
        reload.addEventListener('click', () => window.location.reload());
        setBusy(false);

        forms.forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (busy || locked || !form.reportValidity()) return;
                if (form.dataset.decisionAction === 'approve'
                    && !window.confirm('Setujui lowongan ini agar dipublikasikan?')) return;

                // Capture enabled fields before disabling all three forms.
                const payload = new FormData(form);
                error.hidden = true;
                reload.hidden = true;
                status.className = 'alert alert-info';
                status.textContent = 'Sedang menyimpan keputusan…';
                status.hidden = false;
                setBusy(true);
                try {
                    const response = await fetch(form.getAttribute('action'), {
                        method: 'POST', credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': payload.get('_token') || '' },
                        body: payload,
                    });
                    let body = null;
                    try { body = await response.json(); } catch (_) { /* Non-JSON responses need a safe fallback. */ }
                    if (response.redirected) {
                        stopUntilReload('Sesi tidak tersedia. Muat ulang halaman dan masuk kembali.');
                    } else if (response.status === 422) {
                        showError('Keputusan belum disimpan. Periksa informasi berikut.', body?.errors);
                    } else if ([401, 403, 404, 409, 419].includes(response.status)) {
                        const messages = {
                            401: 'Sesi berakhir. Muat ulang halaman dan masuk kembali.',
                            403: 'Anda tidak berwenang menyimpan keputusan ini. Muat ulang halaman.',
                            404: 'Pengajuan tidak ditemukan atau tidak lagi tersedia. Kembali ke antrean atau muat ulang halaman.',
                            409: 'Status pengajuan telah berubah. Muat ulang halaman sebelum melanjutkan.',
                            419: 'Sesi atau token keamanan kedaluwarsa. Muat ulang halaman sebelum mencoba kembali.',
                        };
                        stopUntilReload(messages[response.status]);
                    } else if (!response.ok || !body?.data || String(body.data.id) !== panel.dataset.jobId
                        || !['published', 'rejected'].includes(body.data.status)) {
                        stopUntilReload('Hasil penyimpanan belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                    } else {
                        locked = true;
                        actions.hidden = true;
                        returnLink.hidden = false;
                        status.className = 'alert alert-success';
                        status.textContent = typeof body.message === 'string' ? body.message : 'Keputusan berhasil disimpan.';
                        status.hidden = false;
                        if (badge) {
                            badge.textContent = body.data.status === 'published' ? 'Disetujui / Tayang' : 'Ditolak / Perlu Perbaikan';
                            badge.className = body.data.status === 'published' ? 'badge bg-success text-wrap' : 'badge bg-danger text-wrap';
                        }
                        status.focus();
                    }
                } catch (_) {
                    // The server may already have saved the decision; never retry automatically.
                    stopUntilReload('Hasil penyimpanan belum dapat dipastikan. Periksa koneksi lalu muat ulang halaman.');
                } finally {
                    setBusy(false);
                }
            });
        });
    });
})();
