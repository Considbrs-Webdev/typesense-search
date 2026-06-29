import { ajaxPost } from '../admin/ajax';
import { setButtonLoading } from '../admin/button';

declare const tsSettings: {
    ajaxUrl: string;
    action: string;
    nonce: string;
    actionCreateCol: string;
    nonceCreateCol: string;
    actionGenKey: string;
    nonceGenKey: string;
} & Record<string, string>;

function i18n(key: string, fallback: string): string {
    return (window as Record<string, unknown> & { tsAdminI18n?: Record<string, string> }).tsAdminI18n?.[key] ?? fallback;
}

function readFields(): { remote: string; adminKey: string; collectionName: string } {
    return {
        remote:         (document.getElementById('ts-remote') as HTMLInputElement)?.value?.trim()     ?? '',
        adminKey:       (document.getElementById('ts-admin-key') as HTMLInputElement)?.value?.trim()  ?? '',
        collectionName: (document.getElementById('ts-index-name') as HTMLInputElement)?.value?.trim() ?? '',
    };
}

function showResult(area: HTMLElement, data: Record<string, unknown>): void {
    const msgEl = area.querySelector('.ts-connection-result__message');
    const success = Boolean(data.success);
    if (msgEl) msgEl.textContent = (data?.data as Record<string, string>)?.message ?? (success ? i18n('ok', 'OK') : i18n('unknownError', 'Unknown error.'));
    area.className = 'ts-connection-result ' + (success ? 'is-success' : 'is-error');
    area.hidden = false;
}

export function init(): void {
    // ── Test connection ───────────────────────────────────────────────────────

    const testBtn       = document.getElementById('ts-test-connection');
    const resultArea    = document.getElementById('ts-connection-result');
    const createColWrap = document.getElementById('ts-create-collection-field');

    if (testBtn && resultArea) {
        testBtn.addEventListener('click', async () => {
            const { remote, adminKey, collectionName } = readFields();
            setButtonLoading(testBtn, true);
            resultArea.hidden = true;
            if (createColWrap) createColWrap.hidden = true;

            let data: Record<string, unknown>;
            try {
                data = await ajaxPost({ action: tsSettings.action, nonce: tsSettings.nonce, remote, admin_key: adminKey, collection_name: collectionName }) as Record<string, unknown>;
            } catch (err) {
                data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
            } finally {
                setButtonLoading(testBtn, false);
            }

            showResult(resultArea, data);

            if (createColWrap) {
                createColWrap.hidden = !(data.success && (data.data as Record<string, unknown>)?.collectionExists === false && collectionName);
            }
        });
    }

    // ── Create collection ─────────────────────────────────────────────────────

    const createColBtn    = document.getElementById('ts-create-collection');
    const createColResult = document.getElementById('ts-create-collection-result');

    if (createColBtn && createColResult) {
        createColBtn.addEventListener('click', async () => {
            const { remote, adminKey, collectionName } = readFields();
            setButtonLoading(createColBtn, true);
            createColResult.hidden = true;

            let data: Record<string, unknown>;
            try {
                data = await ajaxPost({ action: tsSettings.actionCreateCol, nonce: tsSettings.nonceCreateCol, remote, admin_key: adminKey, collection_name: collectionName }) as Record<string, unknown>;
            } catch (err) {
                data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
            } finally {
                setButtonLoading(createColBtn, false);
            }

            showResult(createColResult, data);
            if (data.success && createColWrap) createColWrap.hidden = true;
        });
    }

    // ── Generate search key ───────────────────────────────────────────────────

    const genKeyBtn      = document.getElementById('ts-generate-search-key');
    const genKeyResult   = document.getElementById('ts-gen-key-result');
    const genKeyWrap     = document.getElementById('ts-gen-key-wrap');
    const searchKeyInput = document.getElementById('ts-search-key') as HTMLInputElement | null;

    function syncGenKeyVisibility(): void {
        if (!genKeyWrap) return;
        genKeyWrap.hidden = !!searchKeyInput?.value?.trim();
    }
    syncGenKeyVisibility();
    searchKeyInput?.addEventListener('input', syncGenKeyVisibility);

    if (genKeyBtn && genKeyResult) {
        genKeyBtn.addEventListener('click', async () => {
            const { remote, adminKey, collectionName } = readFields();
            setButtonLoading(genKeyBtn, true);
            genKeyResult.hidden = true;

            let data: Record<string, unknown>;
            try {
                data = await ajaxPost({ action: tsSettings.actionGenKey, nonce: tsSettings.nonceGenKey, remote, admin_key: adminKey, collection_name: collectionName }) as Record<string, unknown>;
            } catch (err) {
                data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
            } finally {
                setButtonLoading(genKeyBtn, false);
            }

            showResult(genKeyResult, data);

            if (data.success && searchKeyInput) {
                const key = ((data.data as Record<string, unknown>)?.key as string) ?? '';
                if (key) {
                    searchKeyInput.value = key;
                    searchKeyInput.type  = 'text';
                    const eyeBtn = searchKeyInput.closest('.ts-field__input-wrap')?.querySelector('.ts-field__toggle-visibility');
                    if (eyeBtn) {
                        (eyeBtn.querySelector('.ts-icon-eye') as HTMLElement | null)!.style.display    = 'none';
                        (eyeBtn.querySelector('.ts-icon-eye-off') as HTMLElement | null)!.style.display = '';
                    }
                    syncGenKeyVisibility();
                }
            }
        });
    }
}
