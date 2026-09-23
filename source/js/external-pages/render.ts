import { state, t } from './state';
import type { ExternalPage } from './types';
import { esc } from '../admin/dom';

const app = document.getElementById('ts-external-pages-app');

// ---------------------------------------------------------------------------
// Status helpers
// ---------------------------------------------------------------------------

function statusClass(page: ExternalPage): string {
    if (page.sync_status === 'error')  return 'is-error';
    if (page.sync_status === 'synced') return 'is-synced';
    return 'is-pending';
}

function statusText(page: ExternalPage): string {
    if (!page.id)                      return t('newStatus', 'New');
    if (page.sync_status === 'synced') return t('syncedStatus', 'Synced');
    if (page.sync_status === 'error')  return t('errorStatus', 'Error');
    return t('pendingStatus', 'Pending');
}

function filteredPages(): ExternalPage[] {
    const normalized = state.pageFilter.trim().toLowerCase();
    if (!normalized) return state.pages;
    return state.pages.filter((p) => p.title.toLowerCase().includes(normalized) || p.url.toLowerCase().includes(normalized));
}

// ---------------------------------------------------------------------------
// Section renderers
// ---------------------------------------------------------------------------

function renderPageList(): string {
    const visible = filteredPages();

    if (!state.pages.length) {
        return `<p class="ts-ext__empty">${esc(t('noPages', 'No external pages yet.'))}</p>`;
    }
    if (!visible.length) {
        return `<p class="ts-ext__empty">${esc(t('noFilteredPages', 'No external pages match your filter.'))}</p>`;
    }

    return `
        <div class="ts-ext__table-body">
            ${visible.map((page) => `
                <button type="button" class="ts-ext__rule-row ${page.id === state.selectedId ? 'is-active' : ''}" data-action="select" data-id="${page.id}">
                    <span class="ts-ext__rule-terms">${esc(page.title)}</span>
                    <span class="ts-ext__sync ${statusClass(page)}">
                        <span class="dashicons ${page.sync_status === 'synced' ? 'dashicons-yes-alt' : page.sync_status === 'error' ? 'dashicons-warning' : 'dashicons-clock'}" aria-hidden="true"></span>
                        ${statusText(page)}
                    </span>
                </button>
            `).join('')}
        </div>
    `;
}

function renderEditor(): string {
    const title           = state.draft.id ? state.draft.title || t('newPage', 'New external page') : t('newPage', 'New external page');
    const saveButtonLabel = state.saveState === 'saving' ? t('saving', 'Saving...') : state.saveState === 'saved' ? t('saved', 'Saved') : t('saveChanges', 'Save changes');

    return `
        <section class="ts-ext__editor" aria-label="${esc(t('editorLabel', 'External page editor'))}">
            <div class="ts-ext__editor-inner">
                <div class="ts-ext__editor-actions">
                    <button type="button" class="button button-primary ts-ext__save ${state.saveState === 'saved' ? 'is-saved' : ''}" data-action="save" ${state.saveState === 'saving' ? 'disabled' : ''}>
                        <span>${esc(saveButtonLabel)}</span>
                    </button>
                    ${state.draft.id ? `<button type="button" class="button-link-delete ts-ext__delete-rule" data-action="delete">${esc(t('deletePage', 'Delete external page'))}</button>` : ''}
                </div>

                <div class="ts-ext__title-row">
                    <h2>${esc(title)}</h2>
                </div>

                <div class="ts-ext__group">
                    <label class="ts-ext__field">
                        <span>${esc(t('titleLabel', 'Title'))}</span>
                        <small class="ts-ext__field-help">${esc(t('titleHelp', 'Shown as the heading of the search hit.'))}</small>
                        <input type="text" id="ts-ext-title" value="${esc(state.draft.title)}" maxlength="191" autocomplete="off">
                    </label>
                    <label class="ts-ext__field">
                        <span>${esc(t('urlLabel', 'URL'))}</span>
                        <small class="ts-ext__field-help">${esc(t('urlHelp', 'Where the search hit links to. Must start with http:// or https://.'))}</small>
                        <input type="url" id="ts-ext-url" value="${esc(state.draft.url)}" placeholder="https://" autocomplete="off">
                    </label>
                    <label class="ts-ext__field">
                        <span>${esc(t('contentLabel', 'Content'))}</span>
                        <small class="ts-ext__field-help">${esc(t('contentHelp', 'Plain text that is searched and used for the hit excerpt.'))}</small>
                        <textarea id="ts-ext-content" rows="8">${esc(state.draft.content)}</textarea>
                    </label>
                    ${state.editorError ? `<span class="ts-ext__field-error">${esc(state.editorError)}</span>` : ''}
                </div>

                ${state.draft.sync_error ? `<div class="notice notice-error inline"><p>${esc(state.draft.sync_error)}</p></div>` : ''}
            </div>
        </section>
    `;
}

// ---------------------------------------------------------------------------
// Root render
// ---------------------------------------------------------------------------

export function render(focusId?: string): void {
    if (!app) return;

    if (!window.tsExternalPages) {
        app.innerHTML = `<div class="notice notice-error"><p>${esc(t('missingConfig', 'Missing external pages configuration.'))}</p></div>`;
        return;
    }

    if (state.isLoading) {
        app.innerHTML = `
            <div class="ts-ext__loading" role="status" aria-live="polite">
                <span class="ts-ext__loading-spinner" aria-hidden="true"></span>
                <span>${esc(t('loadingPages', 'Loading external pages...'))}</span>
            </div>
        `;
        return;
    }

    const noticeClass = state.noticeType === 'error' ? 'is-error' : state.noticeType === 'success' ? 'is-success' : 'is-info';
    const noticeRole  = state.noticeType === 'error' ? 'alert' : 'status';

    app.innerHTML = `
        <div class="ts-ext__page">
            <header class="ts-ext__page-header">
                <h1>${esc(t('externalPages', 'External pages'))}</h1>
                <div class="ts-ext__toolbar">
                    <button type="button" class="button button-primary ts-ext__add" data-action="new" ${state.isSyncing ? 'disabled' : ''}>${esc(t('addPage', 'Add external page'))}</button>
                    <button type="button" class="button ts-ext__sync-button ${state.isSyncing ? 'is-syncing' : ''}" data-action="sync" ${state.isSyncing ? 'disabled' : ''}>
                        <span>${esc(state.isSyncing ? t('syncing', 'Syncing...') : t('syncToTypesense', 'Sync to Typesense'))}</span>
                        <span class="${state.isSyncing ? 'ts-ext__sync-spinner' : 'dashicons dashicons-update'}" aria-hidden="true"></span>
                    </button>
                </div>
            </header>
            ${state.notice ? `<div class="ts-ext__toast ${noticeClass}" role="${noticeRole}" aria-live="${state.noticeType === 'error' ? 'assertive' : 'polite'}"><p>${esc(state.notice)}</p></div>` : ''}
            <div class="ts-ext__layout ${state.isSyncing ? 'is-dimmed' : ''}" ${state.isSyncing ? 'aria-busy="true"' : ''}>
                <aside class="ts-ext__sidebar">
                    <div class="ts-ext__rule-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="ts-ext-page-search" value="${esc(state.pageFilter)}" placeholder="${esc(t('searchPages', 'Search external pages...'))}" autocomplete="off">
                    </div>
                    <div class="ts-ext__rules">${renderPageList()}</div>
                    <div class="ts-ext__intro">
                        <h3>${esc(t('instructionsHeading', 'Instructions'))}</h3>
                        <p>
                            ${esc(t('introText', 'Add pages that do not exist in WordPress, such as a booking system or a page on another domain, so they show up as hits in search results.'))}
                            ${esc(t('introSync', 'Changes reach the search index when you sync to Typesense.'))}
                        </p>
                    </div>
                </aside>
                ${renderEditor()}
            </div>
        </div>
    `;

    if (focusId) {
        const el = document.getElementById(focusId);
        if (el instanceof HTMLInputElement) {
            el.focus();
            const end = el.value.length;
            el.setSelectionRange(end, end);
        }
    }
}
