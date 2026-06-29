import { ajaxPost } from '../admin/ajax';
import { setButtonLoading } from '../admin/button';

declare const tsSettings: {
    actionCheckStatus: string;
    nonceCheckStatus: string;
    actionStatusCreateCol: string;
    nonceStatusCreateCol: string;
    actionFixSearchKey: string;
    nonceFixSearchKey: string;
} & Record<string, string>;

function i18n(key: string, fallback: string): string {
    return (window as Record<string, unknown> & { tsAdminI18n?: Record<string, string> }).tsAdminI18n?.[key] ?? fallback;
}

const ICON_OK   = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`;
const ICON_FAIL = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;

function setStatusItem(id: string, ok: boolean, message: string): void {
    const item      = document.getElementById(id);
    if (!item) return;
    const iconEl    = item.querySelector('.ts-status-item__icon');
    const messageEl = item.querySelector('.ts-status-item__message');
    item.classList.toggle('is-ok',   ok);
    item.classList.toggle('is-fail', !ok);
    if (iconEl)    iconEl.innerHTML       = ok ? ICON_OK : ICON_FAIL;
    if (messageEl) messageEl.textContent  = message;
}

export function init(): void {
    if (!document.getElementById('ts-tab-status')) return;

    const statusLoading    = document.getElementById('ts-status-loading');
    const statusResults    = document.getElementById('ts-status-results');
    const statusRefreshBtn = document.getElementById('ts-status-refresh');
    const fixWrap          = document.getElementById('ts-status-fix-wrap');
    const fixKeyBtn        = document.getElementById('ts-status-fix-key');
    const fixResult        = document.getElementById('ts-status-fix-result');
    const regenHint        = document.getElementById('ts-status-regen-hint');
    const createColWrap    = document.getElementById('ts-status-create-col-wrap');
    const createColBtn     = document.getElementById('ts-status-create-col');
    const createColResult  = document.getElementById('ts-status-create-col-result');

    async function loadStatus(): Promise<void> {
        if (statusLoading)   statusLoading.hidden   = false;
        if (statusResults)   statusResults.hidden   = true;
        if (fixWrap)         fixWrap.hidden          = true;
        if (regenHint)       regenHint.hidden        = true;
        if (fixResult)       fixResult.hidden        = true;
        if (createColWrap)   createColWrap.hidden    = true;
        if (createColResult) createColResult.hidden  = true;
        if (statusRefreshBtn) setButtonLoading(statusRefreshBtn, true);

        let data: Record<string, unknown>;
        try {
            data = await ajaxPost({ action: tsSettings.actionCheckStatus, nonce: tsSettings.nonceCheckStatus }) as Record<string, unknown>;
        } catch (err) {
            data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
        } finally {
            if (statusLoading)    statusLoading.hidden    = true;
            if (statusRefreshBtn) setButtonLoading(statusRefreshBtn, false);
        }

        if (!data.success) {
            const msg = (data?.data as Record<string, string>)?.message ?? i18n('unknownError', 'Unknown error.');
            setStatusItem('ts-status-connection', false, msg);
            setStatusItem('ts-status-admin-key',  false, '');
            setStatusItem('ts-status-collection',  false, '');
            setStatusItem('ts-status-search-key', false, '');
            if (statusResults) statusResults.hidden = false;
            return;
        }

        const { connection, adminKey, collection, searchKey, collectionCanFix, searchKeyCanFix } = data.data as Record<string, { ok: boolean; message: string }> & { collectionCanFix: boolean; searchKeyCanFix: boolean };
        setStatusItem('ts-status-connection', connection.ok,  connection.message);
        setStatusItem('ts-status-admin-key',  adminKey.ok,    adminKey.message);
        setStatusItem('ts-status-collection',  collection.ok,  collection.message);
        setStatusItem('ts-status-search-key', searchKey.ok,   searchKey.message);

        if (!collection.ok && collectionCanFix && createColWrap) createColWrap.hidden = false;
        if (!searchKey.ok && searchKey.message) {
            if (searchKeyCanFix) { if (fixWrap) fixWrap.hidden = false; }
            else                 { if (regenHint) regenHint.hidden = false; }
        }
        if (statusResults) statusResults.hidden = false;
    }

    createColBtn?.addEventListener('click', async () => {
        setButtonLoading(createColBtn, true);
        if (createColResult) createColResult.hidden = true;

        let data: Record<string, unknown>;
        try {
            data = await ajaxPost({ action: tsSettings.actionStatusCreateCol, nonce: tsSettings.nonceStatusCreateCol }) as Record<string, unknown>;
        } catch (err) {
            data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
        } finally {
            setButtonLoading(createColBtn, false);
        }

        if (createColResult) {
            createColResult.textContent = (data?.data as Record<string, string>)?.message ?? (data.success ? i18n('ok', 'Done.') : i18n('unknownError', 'Unknown error.'));
            createColResult.className   = 'ts-status-fix__result ' + (data.success ? 'is-success' : 'is-error');
            createColResult.hidden      = false;
        }
        if (data.success) setTimeout(() => void loadStatus(), 800);
    });

    fixKeyBtn?.addEventListener('click', async () => {
        setButtonLoading(fixKeyBtn, true);
        if (fixResult) fixResult.hidden = true;

        let data: Record<string, unknown>;
        try {
            data = await ajaxPost({ action: tsSettings.actionFixSearchKey, nonce: tsSettings.nonceFixSearchKey }) as Record<string, unknown>;
        } catch (err) {
            data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
        } finally {
            setButtonLoading(fixKeyBtn, false);
        }

        if (fixResult) {
            fixResult.textContent = (data?.data as Record<string, string>)?.message ?? (data.success ? i18n('ok', 'Done.') : i18n('unknownError', 'Unknown error.'));
            fixResult.className   = 'ts-status-fix__result ' + (data.success ? 'is-success' : 'is-error');
            fixResult.hidden      = false;
        }
        if (data.success) setTimeout(() => void loadStatus(), 800);
    });

    void loadStatus();
    statusRefreshBtn?.addEventListener('click', () => void loadStatus());
}
