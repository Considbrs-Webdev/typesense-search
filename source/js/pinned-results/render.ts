import { state, t } from './state';
import type { PinnedRule } from './types';
import { esc } from '../admin/dom';

const app = document.getElementById('ts-pinned-results-app');

// ---------------------------------------------------------------------------
// Status helpers
// ---------------------------------------------------------------------------

function statusClass(rule: PinnedRule): string {
    if (rule.enabled === false)       return 'is-disabled';
    if (rule.sync_status === 'error') return 'is-error';
    if (rule.sync_status === 'synced') return 'is-synced';
    return 'is-pending';
}

function statusText(rule: PinnedRule): string {
    if (rule.enabled === false)        return t('disabledStatus', 'Disabled');
    if (!rule.id)                      return t('newStatus', 'New');
    if (rule.sync_status === 'synced') return t('syncedStatus', 'Synced');
    if (rule.sync_status === 'error')  return t('errorStatus', 'Error');
    return t('pendingStatus', 'Pending');
}

function matchTypeLabel(matchType: PinnedRule['match_type']): string {
    return matchType === 'contains' ? t('contains', 'Contains') : t('exact', 'Exact');
}

function filteredRules(): PinnedRule[] {
    const normalized = state.ruleFilter.trim().toLowerCase();
    if (!normalized) return state.rules;
    return state.rules.filter((r) => r.phrase.toLowerCase().includes(normalized));
}

// ---------------------------------------------------------------------------
// Section renderers
// ---------------------------------------------------------------------------

function renderRuleList(): string {
    const visible = filteredRules();

    if (!state.rules.length) {
        return `<p class="ts-pr__empty">${esc(t('noPinnedSearches', 'No pinned searches yet.'))}</p>`;
    }
    if (!visible.length) {
        return `<p class="ts-pr__empty">${esc(t('noFilteredPinnedSearches', 'No pinned searches match your filter.'))}</p>`;
    }

    return `
        <div class="ts-pr__table-head">
            <span>${esc(t('searchPhrase', 'Search phrase'))}</span>
            <span>${esc(t('matchType', 'Match type'))}</span>
            <span>${esc(t('pinnedResults', 'Pinned results'))}</span>
            <span>${esc(t('syncStatus', 'Sync status'))}</span>
            <span aria-hidden="true"></span>
        </div>
        <div class="ts-pr__table-body">
            ${visible.map((rule) => `
                <button type="button" class="ts-pr__rule-row ${rule.id === state.selectedId ? 'is-active' : ''}" data-action="select" data-id="${rule.id}">
                    <span class="ts-pr__rule-phrase">${esc(rule.phrase)}</span>
                    <span>${matchTypeLabel(rule.match_type)}</span>
                    <span>${rule.posts.length}</span>
                    <span class="ts-pr__sync ${statusClass(rule)}">
                        <span class="dashicons ${rule.enabled === false ? 'dashicons-hidden' : rule.sync_status === 'synced' ? 'dashicons-yes-alt' : rule.sync_status === 'error' ? 'dashicons-warning' : 'dashicons-clock'}" aria-hidden="true"></span>
                        ${statusText(rule)}
                    </span>
                    <span class="dashicons dashicons-arrow-right-alt2 ts-pr__chevron" aria-hidden="true"></span>
                </button>
            `).join('')}
        </div>
        <div class="ts-pr__count">${visible.length} ${visible.length === 1 ? esc(t('itemSingular', 'item')) : esc(t('itemPlural', 'items'))}</div>
    `;
}

function renderPinnedPosts(): string {
    if (!state.draft.posts.length) {
        return `
            <p class="ts-pr__empty ts-pr__empty--inline">${esc(t('emptyPinnedResultsHelp', 'Search for posts below and add the results you want to pin.'))}</p>
            ${state.editorError && state.draft.phrase.trim() !== '' ? `<p class="ts-pr__field-error">${esc(state.editorError)}</p>` : ''}
        `;
    }

    return state.draft.posts.map((post, index) => `
        <li class="ts-pr__pin" draggable="true" data-index="${index}">
            <button type="button" class="ts-pr__drag" title="${esc(t('dragToReorder', 'Drag to reorder'))}" aria-label="${esc(t('dragToReorder', 'Drag to reorder'))}">
                <span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span>
            </button>
            <span class="ts-pr__pin-position">${index + 1}</span>
            <span class="ts-pr__pin-title">${esc(post.title)}</span>
            <button type="button" class="ts-pr__icon-button" data-action="remove-post" data-index="${index}" title="${esc(t('removeResult', 'Remove result'))}" aria-label="${esc(t('removeResult', 'Remove result'))}">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
            </button>
        </li>
    `).join('');
}

function renderAutocomplete(): string {
    const shouldShowEmpty    = state.postSearchTouched && state.postSearchValue.trim().length >= 2 && !state.postSearchLoading && state.postResults.length === 0;
    const shouldShowDropdown = state.postResults.length > 0 || state.postSearchLoading || shouldShowEmpty;

    return `
        <div class="ts-pr__autocomplete">
            <span class="dashicons dashicons-search ts-pr__search-icon" aria-hidden="true"></span>
            <input type="search" id="ts-pr-post-search" value="${esc(state.postSearchValue)}" placeholder="${esc(t('searchPostsToAdd', 'Search posts to add...'))}" autocomplete="off">
            ${state.postSearchLoading ? '<span class="spinner is-active ts-pr__spinner" aria-hidden="true"></span>' : ''}
            ${shouldShowDropdown ? `
                <div class="ts-pr__autocomplete-menu">
                    ${state.postSearchLoading ? `<div class="ts-pr__autocomplete-state">${esc(t('searching', 'Searching...'))}</div>` : ''}
                    ${shouldShowEmpty ? `<div class="ts-pr__autocomplete-state">${esc(t('noResultsFound', 'No results found.'))}</div>` : ''}
                    ${state.postResults.map((post) => `
                        <button type="button" class="ts-pr__autocomplete-item" data-action="add-post" data-post-id="${post.id}">
                            <span>
                                <strong>${esc(post.title)}</strong>
                                <small>${esc(post.type_name)}</small>
                            </span>
                            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        </button>
                    `).join('')}
                </div>
            ` : ''}
        </div>
    `;
}

function renderEditor(): string {
    const title           = state.draft.id ? state.draft.phrase || t('pinnedSearch', 'Pinned search') : t('newPinnedSearch', 'New pinned search');
    const saveButtonLabel = state.saveState === 'saving' ? t('saving', 'Saving...') : state.saveState === 'saved' ? t('saved', 'Saved') : state.draft.id ? t('saveChanges', 'Save changes') : t('saveNewSearch', 'Save new search');

    return `
        <section class="ts-pr__editor" aria-label="Pinned search editor">
            <div class="ts-pr__editor-scroll">
                <div class="ts-pr__editor-inner">
                    <div class="ts-pr__editor-actions">
                        <button type="button" class="button button-primary ts-pr__save ${state.saveState === 'saved' ? 'is-saved' : ''}" data-action="save" ${state.saveState === 'saving' ? 'disabled' : ''}>
                            <span>${esc(saveButtonLabel)}</span>
                            ${state.saveState === 'saving' ? '<span class="ts-pr__save-spinner" aria-hidden="true"></span>' : ''}
                        </button>
                        ${state.draft.id ? `<button type="button" class="button-link-delete ts-pr__delete-rule" data-action="delete">${esc(t('deletePinnedSearch', 'Delete pinned search'))}</button>` : ''}
                    </div>

                    <div class="ts-pr__title-row">
                        <h2>${esc(title)}</h2>
                        <div class="ts-pr__enabled-row">
                            <button type="button" class="ts-pr__switch ${state.draft.enabled !== false ? 'is-on' : ''}" data-action="toggle-enabled" aria-pressed="${state.draft.enabled !== false ? 'true' : 'false'}">
                                <span></span>
                            </button>
                            <span>${esc(t('enabled', 'Enabled'))}</span>
                        </div>
                    </div>

                    <div class="ts-pr__group">
                        <label class="ts-pr__field">
                            <span>${esc(t('searchPhrase', 'Search phrase'))}</span>
                            <input type="text" id="ts-pr-phrase" value="${esc(state.draft.phrase)}" autocomplete="off">
                            ${state.editorError && state.draft.phrase.trim() === '' ? `<span class="ts-pr__field-error">${esc(state.editorError)}</span>` : ''}
                        </label>

                        <div class="ts-pr__field">
                            <span>${esc(t('matchType', 'Match type'))}</span>
                            <div class="ts-pr__segments" role="group" aria-label="Match type">
                                <button type="button" class="ts-pr__segment ${state.draft.match_type === 'exact' ? 'is-active' : ''}" data-action="set-match-type" data-match-type="exact">${esc(t('exact', 'Exact'))}</button>
                                <button type="button" class="ts-pr__segment ${state.draft.match_type === 'contains' ? 'is-active' : ''}" data-action="set-match-type" data-match-type="contains">${esc(t('contains', 'Contains'))}</button>
                            </div>
                        </div>
                    </div>

                    ${state.draft.sync_error ? `<div class="notice notice-error inline"><p>${esc(state.draft.sync_error)}</p></div>` : ''}

                    <div class="ts-pr__group">
                        <div class="ts-pr__section-title">${esc(t('pinnedResults', 'Pinned results'))}</div>
                        <ol class="ts-pr__pins">${renderPinnedPosts()}</ol>
                        <div class="ts-pr__add-result">
                            ${renderAutocomplete()}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    `;
}

// ---------------------------------------------------------------------------
// Root render
// ---------------------------------------------------------------------------

export function render(focusId?: string): void {
    if (!app) return;

    if (!window.tsPinnedResults) {
        app.innerHTML = `<div class="notice notice-error"><p>${esc(t('missingConfig', 'Missing pinned results configuration.'))}</p></div>`;
        return;
    }

    if (state.isLoading) {
        app.innerHTML = `
            <div class="ts-pr__loading" role="status" aria-live="polite">
                <span class="ts-pr__loading-spinner" aria-hidden="true"></span>
                <span>${esc(t('loadingPinnedSearches', 'Loading pinned searches...'))}</span>
            </div>
        `;
        return;
    }

    const noticeClass = state.noticeType === 'error' ? 'is-error' : state.noticeType === 'success' ? 'is-success' : 'is-info';
    const noticeRole  = state.noticeType === 'error' ? 'alert' : 'status';

    app.innerHTML = `
        <div class="ts-pr__page">
            <header class="ts-pr__page-header">
                <h1>${esc(t('pinnedSearchResults', 'Pinned search results'))}</h1>
                <div class="ts-pr__toolbar">
                    <button type="button" class="button button-primary ts-pr__add" data-action="new" ${state.isSyncing ? 'disabled' : ''}>${esc(t('addPinnedSearch', 'Add pinned search'))}</button>
                    <button type="button" class="button ts-pr__sync-button ${state.isSyncing ? 'is-syncing' : ''}" data-action="sync" ${state.isSyncing ? 'disabled' : ''}>
                        <span>${esc(state.isSyncing ? t('syncing', 'Syncing...') : t('syncToTypesense', 'Sync to Typesense'))}</span>
                        <span class="${state.isSyncing ? 'ts-pr__sync-spinner' : 'dashicons dashicons-update'}" aria-hidden="true"></span>
                    </button>
                </div>
            </header>
            ${state.notice ? `<div class="ts-pr__toast ${noticeClass}" role="${noticeRole}" aria-live="${state.noticeType === 'error' ? 'assertive' : 'polite'}"><p>${esc(state.notice)}</p></div>` : ''}
            <div class="ts-pr__layout ${state.isSyncing ? 'is-dimmed' : ''}" ${state.isSyncing ? 'aria-busy="true"' : ''}>
                <aside class="ts-pr__sidebar">
                    <div class="ts-pr__rule-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="ts-pr-rule-search" value="${esc(state.ruleFilter)}" placeholder="${esc(t('searchPinnedSearches', 'Search pinned searches...'))}" autocomplete="off">
                    </div>
                    <div class="ts-pr__rules">${renderRuleList()}</div>
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
