import { state, t } from './state';
import type { SynonymRule } from './types';
import { esc } from '../admin/dom';

const app = document.getElementById('ts-synonyms-app');

// ---------------------------------------------------------------------------
// Status helpers
// ---------------------------------------------------------------------------

function statusClass(rule: SynonymRule): string {
    if (rule.sync_status === 'error')  return 'is-error';
    if (rule.sync_status === 'synced') return 'is-synced';
    return 'is-pending';
}

function statusText(rule: SynonymRule): string {
    if (!rule.id)                      return t('newStatus', 'New');
    if (rule.sync_status === 'synced') return t('syncedStatus', 'Synced');
    if (rule.sync_status === 'error')  return t('errorStatus', 'Error');
    return t('pendingStatus', 'Pending');
}

function filteredRules(): SynonymRule[] {
    const normalized = state.ruleFilter.trim().toLowerCase();
    if (!normalized) return state.rules;
    return state.rules.filter((r) => r.terms.some((term) => term.toLowerCase().includes(normalized)));
}

// ---------------------------------------------------------------------------
// Section renderers
// ---------------------------------------------------------------------------

function renderRuleList(): string {
    const visible = filteredRules();

    if (!state.rules.length) {
        return `<p class="ts-syn__empty">${esc(t('noSynonymRules', 'No synonym rules yet.'))}</p>`;
    }
    if (!visible.length) {
        return `<p class="ts-syn__empty">${esc(t('noFilteredSynonymRules', 'No synonym rules match your filter.'))}</p>`;
    }

    return `
        <div class="ts-syn__table-body">
            ${visible.map((rule) => `
                <button type="button" class="ts-syn__rule-row ${rule.id === state.selectedId ? 'is-active' : ''}" data-action="select" data-id="${rule.id}">
                    <span class="ts-syn__rule-terms">${esc(rule.terms.join(', '))}</span>
                    <span class="ts-syn__sync ${statusClass(rule)}">
                        <span class="dashicons ${rule.sync_status === 'synced' ? 'dashicons-yes-alt' : rule.sync_status === 'error' ? 'dashicons-warning' : 'dashicons-clock'}" aria-hidden="true"></span>
                        ${statusText(rule)}
                    </span>
                </button>
            `).join('')}
        </div>
    `;
}

function renderTermChips(): string {
    if (!state.draft.terms.length) {
        return '';
    }

    return `
        <ul class="ts-syn__chips">
            ${state.draft.terms.map((term, index) => `
                <li class="ts-syn__chip">
                    <span>${esc(term)}</span>
                    <button type="button" class="ts-syn__chip-remove" data-action="remove-term" data-index="${index}" title="${esc(t('removeTerm', 'Remove word'))}" aria-label="${esc(t('removeTerm', 'Remove word'))}">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </li>
            `).join('')}
        </ul>
    `;
}

function renderEditor(): string {
    const title           = state.draft.id ? state.draft.terms.join(', ') || t('newSynonymRule', 'New synonym rule') : t('newSynonymRule', 'New synonym rule');
    const saveButtonLabel = state.saveState === 'saving' ? t('saving', 'Saving...') : state.saveState === 'saved' ? t('saved', 'Saved') : t('saveChanges', 'Save changes');

    return `
        <section class="ts-syn__editor" aria-label="Synonym rule editor">
            <div class="ts-syn__editor-inner">
                <div class="ts-syn__editor-actions">
                    <button type="button" class="button button-primary ts-syn__save ${state.saveState === 'saved' ? 'is-saved' : ''}" data-action="save" ${state.saveState === 'saving' ? 'disabled' : ''}>
                        <span>${esc(saveButtonLabel)}</span>
                    </button>
                    ${state.draft.id ? `<button type="button" class="button-link-delete ts-syn__delete-rule" data-action="delete">${esc(t('deleteSynonymRule', 'Delete synonym rule'))}</button>` : ''}
                </div>

                <div class="ts-syn__title-row">
                    <h2>${esc(title)}</h2>
                </div>

                <div class="ts-syn__group">
                    <div class="ts-syn__field">
                        <span>${esc(t('terms', 'Words'))}</span>
                        <small class="ts-syn__field-help">${esc(t('termsHelp', 'All words in this group are treated as interchangeable when searching.'))}</small>
                        ${renderTermChips()}
                        ${state.editorError ? `<span class="ts-syn__field-error">${esc(state.editorError)}</span>` : ''}
                        <div class="ts-syn__add-synonym">
                            <input type="text" id="ts-syn-term-draft" value="${esc(state.termDraftValue)}" placeholder="${esc(t('addTermPlaceholder', 'Add a word and press Enter'))}" autocomplete="off">
                            <button type="button" class="button ts-syn__add-synonym-button" data-action="add-term" aria-label="${esc(t('addTerm', 'Add word'))}">+</button>
                        </div>
                    </div>
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

    if (!window.tsSynonyms) {
        app.innerHTML = `<div class="notice notice-error"><p>${esc(t('missingConfig', 'Missing synonyms configuration.'))}</p></div>`;
        return;
    }

    if (state.isLoading) {
        app.innerHTML = `
            <div class="ts-syn__loading" role="status" aria-live="polite">
                <span class="ts-syn__loading-spinner" aria-hidden="true"></span>
                <span>${esc(t('loadingSynonyms', 'Loading synonyms...'))}</span>
            </div>
        `;
        return;
    }

    const noticeClass = state.noticeType === 'error' ? 'is-error' : state.noticeType === 'success' ? 'is-success' : 'is-info';
    const noticeRole  = state.noticeType === 'error' ? 'alert' : 'status';

    app.innerHTML = `
        <div class="ts-syn__page">
            <header class="ts-syn__page-header">
                <h1>${esc(t('synonyms', 'Synonyms'))}</h1>
                <div class="ts-syn__toolbar">
                    <button type="button" class="button button-primary ts-syn__add" data-action="new" ${state.isSyncing ? 'disabled' : ''}>${esc(t('addSynonymRule', 'Add synonym rule'))}</button>
                    <button type="button" class="button ts-syn__sync-button ${state.isSyncing ? 'is-syncing' : ''}" data-action="sync" ${state.isSyncing ? 'disabled' : ''}>
                        <span>${esc(state.isSyncing ? t('syncing', 'Syncing...') : t('syncToTypesense', 'Sync to Typesense'))}</span>
                        <span class="${state.isSyncing ? 'ts-syn__sync-spinner' : 'dashicons dashicons-update'}" aria-hidden="true"></span>
                    </button>
                </div>
            </header>
            ${state.notice ? `<div class="ts-syn__toast ${noticeClass}" role="${noticeRole}" aria-live="${state.noticeType === 'error' ? 'assertive' : 'polite'}"><p>${esc(state.notice)}</p></div>` : ''}
            <div class="ts-syn__layout ${state.isSyncing ? 'is-dimmed' : ''}" ${state.isSyncing ? 'aria-busy="true"' : ''}>
                <aside class="ts-syn__sidebar">
                    <div class="ts-syn__rule-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="ts-syn-rule-search" value="${esc(state.ruleFilter)}" placeholder="${esc(t('searchSynonymRules', 'Search synonym rules...'))}" autocomplete="off">
                    </div>
                    <div class="ts-syn__rules">${renderRuleList()}</div>
                    <div class="ts-syn__intro">
                        <h3>${esc(t('instructionsHeading', 'Instructions'))}</h3>
                        <p>
                            ${esc(t('introText', 'Add words that should be treated as the same when people search. Every word in a group is interchangeable with every other word in that group.'))}
                            ${esc(t('introExample', 'Example: grouping "lekplatser, playground, amusement park" together means a search for any one of them also finds results for the others.'))}
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
