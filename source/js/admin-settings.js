/**
 * Typesense Search — Admin Settings JS
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Password visibility toggles ──────────────────────────────────────────
    document.querySelectorAll('.ts-field__toggle-visibility').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const input    = document.getElementById(targetId);
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const eyeIcon    = btn.querySelector('.ts-icon-eye');
            const eyeOffIcon = btn.querySelector('.ts-icon-eye-off');

            if (eyeIcon)    eyeIcon.style.display    = isHidden ? 'none' : '';
            if (eyeOffIcon) eyeOffIcon.style.display = isHidden ? ''     : 'none';
        });
    });

    // ── Shared AJAX helper ────────────────────────────────────────────────────
    async function ajaxPost(params) {
        const body = new URLSearchParams(params);
        const response = await fetch(tsSettings.ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        });
        return response.json();
    }

    function setButtonLoading(btn, loading) {
        btn.disabled = loading;
        btn.classList.toggle('is-loading', loading);
    }

    function showResult(area, data) {
        const msgEl = area.querySelector('.ts-connection-result__message');
        if (msgEl) msgEl.textContent = data?.data?.message ?? (data.success ? 'OK' : 'Unknown error.');
        area.className = 'ts-connection-result ' + (data.success ? 'is-success' : 'is-error');
        area.hidden = false;
    }

    if (typeof tsSettings === 'undefined') return;

    // ── Test connection ───────────────────────────────────────────────────────
    const testBtn         = document.getElementById('ts-test-connection');
    const resultArea      = document.getElementById('ts-connection-result');
    const createColWrap   = document.getElementById('ts-create-collection-field');

    if (testBtn && resultArea) {
        testBtn.addEventListener('click', async () => {
            const remote         = document.getElementById('ts-remote')?.value?.trim()     ?? '';
            const adminKey       = document.getElementById('ts-admin-key')?.value?.trim()  ?? '';
            const collectionName = document.getElementById('ts-index-name')?.value?.trim() ?? '';

            setButtonLoading(testBtn, true);
            resultArea.hidden = true;
            if (createColWrap) createColWrap.hidden = true;

            let data;
            try {
                data = await ajaxPost({
                    action:          tsSettings.action,
                    nonce:           tsSettings.nonce,
                    remote,
                    admin_key:       adminKey,
                    collection_name: collectionName,
                });
            } catch (err) {
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
            } finally {
                setButtonLoading(testBtn, false);
            }

            showResult(resultArea, data);

            // Show "Create collection" only when connected but collection is missing
            if (createColWrap) {
                createColWrap.hidden = !(data.success && data.data?.collectionExists === false && collectionName);
            }
        });
    }

    // ── Create collection ─────────────────────────────────────────────────────
    const createColBtn    = document.getElementById('ts-create-collection');
    const createColResult = document.getElementById('ts-create-collection-result');

    if (createColBtn && createColResult) {
        createColBtn.addEventListener('click', async () => {
            const remote         = document.getElementById('ts-remote')?.value?.trim()     ?? '';
            const adminKey       = document.getElementById('ts-admin-key')?.value?.trim()  ?? '';
            const collectionName = document.getElementById('ts-index-name')?.value?.trim() ?? '';

            setButtonLoading(createColBtn, true);
            createColResult.hidden = true;

            let data;
            try {
                data = await ajaxPost({
                    action:          tsSettings.actionCreateCol,
                    nonce:           tsSettings.nonceCreateCol,
                    remote,
                    admin_key:       adminKey,
                    collection_name: collectionName,
                });
            } catch (err) {
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
            } finally {
                setButtonLoading(createColBtn, false);
            }

            showResult(createColResult, data);

            // On success, hide the create collection field (collection now exists)
            if (data.success && createColWrap) {
                createColWrap.hidden = true;
            }
        });
    }

    // ── Generate search key ───────────────────────────────────────────────────
    const genKeyBtn     = document.getElementById('ts-generate-search-key');
    const genKeyResult  = document.getElementById('ts-gen-key-result');
    const genKeyWrap    = document.getElementById('ts-gen-key-wrap');
    const searchKeyInput = document.getElementById('ts-search-key');

    // Show/hide generate button based on whether the search key field has a value
    function syncGenKeyVisibility() {
        if (!genKeyWrap) return;
        genKeyWrap.hidden = !!searchKeyInput?.value?.trim();
    }
    syncGenKeyVisibility();
    searchKeyInput?.addEventListener('input', syncGenKeyVisibility);

    if (genKeyBtn && genKeyResult) {
        genKeyBtn.addEventListener('click', async () => {
            const remote         = document.getElementById('ts-remote')?.value?.trim()     ?? '';
            const adminKey       = document.getElementById('ts-admin-key')?.value?.trim()  ?? '';
            const collectionName = document.getElementById('ts-index-name')?.value?.trim() ?? '';

            setButtonLoading(genKeyBtn, true);
            genKeyResult.hidden = true;

            let data;
            try {
                data = await ajaxPost({
                    action:          tsSettings.actionGenKey,
                    nonce:           tsSettings.nonceGenKey,
                    remote,
                    admin_key:       adminKey,
                    collection_name: collectionName,
                });
            } catch (err) {
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
            } finally {
                setButtonLoading(genKeyBtn, false);
            }

            showResult(genKeyResult, data);

            // Populate the search key input on success
            if (data.success && data.data?.key) {
                if (searchKeyInput) {
                    searchKeyInput.value = data.data.key;
                    searchKeyInput.type  = 'text'; // reveal so user can see it was set
                    const eyeBtn = searchKeyInput.closest('.ts-field__input-wrap')?.querySelector('.ts-field__toggle-visibility');
                    if (eyeBtn) {
                        eyeBtn.querySelector('.ts-icon-eye').style.display    = 'none';
                        eyeBtn.querySelector('.ts-icon-eye-off').style.display = '';
                    }
                    syncGenKeyVisibility(); // field now has value → hide button
                }
            }
        });
    }

    // ── Unsaved-changes guard on content tab ─────────────────────────────────
    const contentForm = document.querySelector('#ts-tab-content form');
    if (contentForm) {
        let isDirty = false;

        contentForm.addEventListener('change', () => { isDirty = true; });
        contentForm.addEventListener('submit', () => { isDirty = false; });

        window.addEventListener('beforeunload', (e) => {
            if (!isDirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    }

});
