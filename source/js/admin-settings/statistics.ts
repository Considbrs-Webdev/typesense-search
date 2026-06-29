import { ajaxPost } from '../admin/ajax';
import { setButtonLoading } from '../admin/button';
import { esc, escAttr } from '../admin/dom';

declare const tsSettings: {
    actionGetStats: string;
    nonceGetStats: string;
    actionClearType: string;
    nonceClearType: string;
    actionReindexType: string;
    nonceReindexType: string;
} & Record<string, string>;

function i18n(key: string, fallback: string): string {
    return (window as Record<string, unknown> & { tsAdminI18n?: Record<string, string> }).tsAdminI18n?.[key] ?? fallback;
}

const PIE_COLORS = [
    '#2271b1', '#00a32a', '#d63638', '#f0b849', '#8e44ad',
    '#16a085', '#e67e22', '#2980b9', '#27ae60', '#c0392b',
];

type Facet = { slug: string; label: string; count: number; external: boolean; color: string };

// ---------------------------------------------------------------------------
// Chart
// ---------------------------------------------------------------------------

function drawDonutChart(container: HTMLElement, segments: Facet[], total: number): void {
    const size = 180, cx = 90, cy = 90, rOuter = 70, rInner = 42;
    let paths = '';

    if (total === 0) {
        paths = `<path d="M ${cx} ${cy - rOuter} A ${rOuter} ${rOuter} 0 1 1 ${cx - 0.001} ${cy - rOuter} Z" fill="#e0e0e0"/>`;
    } else {
        let startAngle = -Math.PI / 2;
        segments.forEach((seg) => {
            const angle    = (seg.count / total) * 2 * Math.PI;
            const endAngle = startAngle + angle;
            const large    = angle > Math.PI ? 1 : 0;
            const [x1, y1] = [cx + rOuter * Math.cos(startAngle), cy + rOuter * Math.sin(startAngle)];
            const [x2, y2] = [cx + rOuter * Math.cos(endAngle),   cy + rOuter * Math.sin(endAngle)];
            const [ix1, iy1] = [cx + rInner * Math.cos(endAngle), cy + rInner * Math.sin(endAngle)];
            const [ix2, iy2] = [cx + rInner * Math.cos(startAngle), cy + rInner * Math.sin(startAngle)];
            paths += `<path d="M ${x1.toFixed(3)} ${y1.toFixed(3)} A ${rOuter} ${rOuter} 0 ${large} 1 ${x2.toFixed(3)} ${y2.toFixed(3)} L ${ix1.toFixed(3)} ${iy1.toFixed(3)} A ${rInner} ${rInner} 0 ${large} 0 ${ix2.toFixed(3)} ${iy2.toFixed(3)} Z" fill="${seg.color}" stroke="#fff" stroke-width="2"/>`;
            startAngle = endAngle;
        });
    }

    container.innerHTML = `
        <svg viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${escAttr(i18n('pieChartLabel', 'Pie chart showing document distribution'))}">
            ${paths}
            <circle cx="${cx}" cy="${cy}" r="${rInner}" fill="#fff"/>
            <text x="${cx}" y="${cy - 8}" text-anchor="middle" font-size="22" font-weight="700" fill="#1d2327" font-family="sans-serif">${total.toLocaleString()}</text>
            <text x="${cx}" y="${cy + 12}" text-anchor="middle" font-size="10" fill="#646970" font-family="sans-serif">${esc(i18n('documentsLabel', 'documents'))}</text>
        </svg>`;
}

// ---------------------------------------------------------------------------
// Notice
// ---------------------------------------------------------------------------

function showStatsNotice(message: string, type: 'success' | 'error'): void {
    const noticeEl  = document.getElementById('ts-stats-notice');
    const messageEl = document.getElementById('ts-stats-notice-message');
    if (!noticeEl || !messageEl) return;
    messageEl.textContent = message;
    noticeEl.classList.remove('ts-stats-notice--success', 'ts-stats-notice--error');
    noticeEl.classList.add(type === 'success' ? 'ts-stats-notice--success' : 'ts-stats-notice--error');
    noticeEl.hidden = false;
}

function hideStatsNotice(): void {
    const el = document.getElementById('ts-stats-notice');
    if (el) el.hidden = true;
}

// ---------------------------------------------------------------------------
// Row actions
// ---------------------------------------------------------------------------

async function handleClearPostType(btn: HTMLElement, loadStats: () => Promise<void>): Promise<void> {
    const postType = btn.dataset.postType ?? '';
    const label    = btn.dataset.label || postType;
    const row      = btn.closest('.ts-stats-row');
    const feedback = row?.querySelector('.ts-stats-row__feedback') as HTMLElement | null;

    if (!confirm(i18n('confirmClearType', 'Remove all "%s" documents from the Typesense index? This cannot be undone.').replace('%s', label))) return;

    (btn as HTMLButtonElement).disabled = true;
    if (feedback) feedback.hidden = true;

    let data: Record<string, unknown>;
    try {
        data = await ajaxPost({ action: tsSettings.actionClearType, nonce: tsSettings.nonceClearType, post_type: postType }) as Record<string, unknown>;
    } catch (err) {
        data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
    }

    if (data.success) {
        await loadStats();
    } else {
        (btn as HTMLButtonElement).disabled = false;
        if (feedback) {
            feedback.textContent = (data?.data as Record<string, string>)?.message ?? i18n('error', 'Error.');
            feedback.className   = 'ts-stats-row__feedback is-error';
            feedback.hidden      = false;
        }
    }
}

async function handleReindexPostType(btn: HTMLElement, loadStats: () => Promise<void>): Promise<void> {
    const postType = btn.dataset.postType ?? '';
    const label    = btn.dataset.label || postType;
    const row      = btn.closest('.ts-stats-row');
    const feedback = row?.querySelector('.ts-stats-row__feedback') as HTMLElement | null;
    const clearBtn = row?.querySelector('.ts-stats-row__clear') as HTMLElement | null;

    if (!confirm(i18n('confirmReindexType', 'Re-index all "%s" documents? Existing entries will be re-processed and overwritten.').replace('%s', label))) return;

    setButtonLoading(btn, true);
    hideStatsNotice();
    if (feedback) feedback.hidden = true;
    if (clearBtn) (clearBtn as HTMLButtonElement).disabled = true;

    let data: Record<string, unknown>;
    try {
        data = await ajaxPost({ action: tsSettings.actionReindexType, nonce: tsSettings.nonceReindexType, post_type: postType }) as Record<string, unknown>;
    } catch (err) {
        data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
    } finally {
        setButtonLoading(btn, false);
        if (clearBtn) (clearBtn as HTMLButtonElement).disabled = false;
    }

    const message = (data?.data as Record<string, string>)?.message ?? (data.success ? i18n('ok', 'OK') : i18n('error', 'Error.'));
    showStatsNotice(message, data.success ? 'success' : 'error');
    if (data.success) await loadStats();
}

// ---------------------------------------------------------------------------
// Breakdown list
// ---------------------------------------------------------------------------

function renderBreakdownList(listEl: HTMLElement, facets: Facet[], total: number, loadStats: () => Promise<void>): void {
    listEl.innerHTML = '';

    facets.forEach((facet) => {
        const pct = total > 0 ? ((facet.count / total) * 100).toFixed(1) : '0.0';
        const li  = document.createElement('li');
        li.className       = 'ts-stats-row';
        li.dataset.postType = facet.slug;

        li.innerHTML = `
            <span class="ts-stats-row__swatch" style="background:${facet.color}" aria-hidden="true"></span>
            <span class="ts-stats-row__label">${esc(facet.label)}<span class="ts-stats-row__slug">${esc(facet.slug)}</span>${facet.external ? `<span class="ts-stats-row__badge ts-stats-row__badge--external">${esc(i18n('externalBadge', 'External'))}</span>` : ''}</span>
            <span class="ts-stats-row__count">${facet.count.toLocaleString()}</span>
            <span class="ts-stats-row__pct">${pct}%</span>
            <button type="button" class="button button-small ts-stats-row__clear" data-post-type="${escAttr(facet.slug)}" data-label="${escAttr(facet.label)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                ${esc(i18n('clearBtn', 'Clear'))}
            </button>
            <button type="button" class="button button-small ts-stats-row__reindex" data-post-type="${escAttr(facet.slug)}" data-label="${escAttr(facet.label)}"${facet.external ? ` disabled title="${escAttr(i18n('reindexExternalTitle', 'Managed by an external strategy — use WP-CLI to reindex.'))}"` : ''}>
                <svg class="ts-stats-row__reindex-spinner" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                <svg class="ts-stats-row__reindex-icon" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M552.2 64C538.9 64 528.2 74.7 528.2 88L528.2 166.1L501.1 139C401.1 39 239 39 139.1 139C39.2 239 39.1 401.1 139.1 501C239.1 600.9 401.2 601 501.1 501C516 486.1 528.7 469.8 539.2 452.5C546.1 441.2 542.4 426.4 531.1 419.5C519.8 412.6 505 416.3 498.1 427.6C489.6 441.6 479.3 454.9 467.1 467C385.9 548.2 254.2 548.2 172.9 467C91.6 385.8 91.7 254.1 172.9 172.8C254.1 91.5 385.8 91.6 467.1 172.8L494.2 199.9L416 199.9C402.7 199.9 392 210.6 392 223.9C392 237.2 402.7 247.9 416 247.9L552.1 247.9C565.4 247.9 576.1 237.2 576.1 223.9L576.1 87.9C576.1 74.6 565.4 63.9 552.1 63.9z"/></svg>
                ${esc(i18n('reindexBtn', 'Reindex'))}
            </button>
            <span class="ts-stats-row__feedback" hidden></span>`;

        li.querySelector('.ts-stats-row__clear')?.addEventListener('click', (e) => void handleClearPostType(e.currentTarget as HTMLElement, loadStats));
        li.querySelector('.ts-stats-row__reindex')?.addEventListener('click', (e) => void handleReindexPostType(e.currentTarget as HTMLElement, loadStats));

        listEl.appendChild(li);
    });
}

// ---------------------------------------------------------------------------
// Load stats
// ---------------------------------------------------------------------------

async function loadStats(statsRefreshBtn: HTMLElement | null): Promise<void> {
    const loadingEl  = document.getElementById('ts-stats-loading');
    const errorEl    = document.getElementById('ts-stats-error');
    const errorMsgEl = document.getElementById('ts-stats-error-message');
    const contentEl  = document.getElementById('ts-stats-content');
    const totalEl    = document.getElementById('ts-stats-total');
    const typesEl    = document.getElementById('ts-stats-types');
    const colEl      = document.getElementById('ts-stats-collection');
    const pieEl      = document.getElementById('ts-pie-chart');
    const listEl     = document.getElementById('ts-stats-list');

    if (loadingEl)       loadingEl.hidden  = false;
    if (errorEl)         errorEl.hidden    = true;
    if (contentEl)       contentEl.hidden  = true;
    if (statsRefreshBtn) setButtonLoading(statsRefreshBtn, true);

    let data: Record<string, unknown>;
    try {
        data = await ajaxPost({ action: tsSettings.actionGetStats, nonce: tsSettings.nonceGetStats }) as Record<string, unknown>;
    } catch (err) {
        data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
    } finally {
        if (loadingEl)       loadingEl.hidden = true;
        if (statsRefreshBtn) setButtonLoading(statsRefreshBtn, false);
    }

    if (!data.success) {
        if (errorEl)    errorEl.hidden  = false;
        if (errorMsgEl) errorMsgEl.textContent = (data?.data as Record<string, string>)?.message ?? i18n('unknownError', 'Unknown error.');
        return;
    }

    const payload = data.data as { total: number; facets: Facet[]; collectionName: string };
    const { total, facets, collectionName } = payload;

    const coloredFacets: Facet[] = (facets || []).map((f, i) => ({ ...f, color: PIE_COLORS[i % PIE_COLORS.length] }));

    if (totalEl) totalEl.textContent = (total || 0).toLocaleString();
    if (typesEl) typesEl.textContent = String(coloredFacets.length);
    if (colEl)   colEl.textContent   = collectionName || '—';

    if (pieEl)  drawDonutChart(pieEl, coloredFacets, total || 0);
    if (listEl) renderBreakdownList(listEl, coloredFacets, total || 0, () => loadStats(statsRefreshBtn));

    if (contentEl) contentEl.hidden = false;
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

export function init(): void {
    if (!document.getElementById('ts-tab-statistics')) return;

    const statsRefreshBtn = document.getElementById('ts-stats-refresh');

    void loadStats(statsRefreshBtn);

    statsRefreshBtn?.addEventListener('click', () => void loadStats(statsRefreshBtn));

    document.getElementById('ts-stats-notice-dismiss')?.addEventListener('click', hideStatsNotice);
}
