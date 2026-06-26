type PinnedPost = {
    id: number;
    title: string;
    type_name: string;
    edit_url: string;
};

type PinnedRule = {
    id: number | null;
    phrase: string;
    match_type: 'exact' | 'contains';
    post_ids: number[];
    posts: PinnedPost[];
    sync_status: string;
    sync_error: string;
    synced_at: string;
    updated_at: string;
    enabled?: boolean;
};

type Config = {
    restUrl: string;
    nonce: string;
    i18n?: Record<string, string>;
};

declare global {
    interface Window {
        tsPinnedResults?: Config;
    }
}

const config = window.tsPinnedResults;
const app = document.getElementById('ts-pinned-results-app');

let rules: PinnedRule[] = [];
let selectedId: number | null = null;
let draft: PinnedRule = emptyRule();
let isDirty = false;
let isLoading = true;
let notice = '';
let noticeType: 'success' | 'error' | 'info' = 'info';
let ruleFilter = '';
let postSearchValue = '';
let postResults: PinnedPost[] = [];
let postSearchLoading = false;
let postSearchTouched = false;
let editorError = '';
let dragIndex: number | null = null;
let searchTimer: number | undefined;
let saveState: 'idle' | 'saving' | 'saved' = 'idle';
let saveStateTimer: number | undefined;
let isSyncing = false;
let noticeTimer: number | undefined;

function emptyRule(): PinnedRule {
    return {
        id: null,
        phrase: '',
        match_type: 'exact',
        post_ids: [],
        posts: [],
        sync_status: 'draft',
        sync_error: '',
        synced_at: '',
        updated_at: '',
        enabled: true,
    };
}

function cloneRule(rule: PinnedRule): PinnedRule {
    return {
        ...JSON.parse(JSON.stringify(rule)),
        enabled: rule.enabled ?? true,
    } as PinnedRule;
}

function t(key: string, fallback: string): string {
    return config?.i18n?.[key] ?? fallback;
}

async function request<T>(path = '', options: RequestInit = {}): Promise<T> {
    if (!config) throw new Error(t('missingConfig', 'Missing pinned results configuration.'));

    const response = await fetch(config.restUrl + path, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce,
            ...(options.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(data?.message ?? 'Request failed.');
    }

    return data as T;
}

function esc(value: unknown): string {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setNotice(message: string, type: 'success' | 'error' | 'info' = 'info'): void {
    window.clearTimeout(noticeTimer);
    notice = message;
    noticeType = type;

    if (message && type !== 'error') {
        noticeTimer = window.setTimeout(() => {
            notice = '';
            render();
        }, 4200);
    }
}

function resetPostSearch(): void {
    postSearchValue = '';
    postResults = [];
    postSearchLoading = false;
    postSearchTouched = false;
    window.clearTimeout(searchTimer);
}

function clearEditorError(): void {
    editorError = '';
}

function setSaveState(state: 'idle' | 'saving' | 'saved'): void {
    window.clearTimeout(saveStateTimer);
    saveState = state;

    if (state === 'saved') {
        saveStateTimer = window.setTimeout(() => {
            saveState = 'idle';
            render();
        }, 1600);
    }
}

function selectRule(id: number | null): void {
    const next = id === null ? null : rules.find((rule) => rule.id === id);
    selectedId = id;
    draft = next ? cloneRule(next) : emptyRule();
    resetPostSearch();
    clearEditorError();
    isDirty = false;
    render();
}

function syncSelectedFromDraft(saved: PinnedRule): void {
    const normalized = cloneRule(saved);
    const index = rules.findIndex((rule) => rule.id === normalized.id);

    if (index >= 0) {
        rules[index] = normalized;
    } else {
        rules.push(normalized);
        rules.sort((a, b) => a.phrase.localeCompare(b.phrase));
    }

    selectedId = normalized.id;
    draft = cloneRule(normalized);
    isDirty = false;
}

function removePost(index: number): void {
    draft.posts.splice(index, 1);
    draft.post_ids = draft.posts.map((post) => post.id);
    postResults = postResults.filter((post) => !draft.posts.some((item) => item.id === post.id));
    clearEditorError();
    isDirty = true;
    render();
}

function addPost(post: PinnedPost): void {
    if (draft.posts.some((item) => item.id === post.id)) return;

    draft.posts.push(post);
    draft.post_ids = draft.posts.map((item) => item.id);
    postResults = postResults.filter((item) => item.id !== post.id);
    clearEditorError();
    isDirty = true;
    render('ts-pr-post-search');
}

function movePost(from: number, to: number): void {
    if (from === to || from < 0 || to < 0 || from >= draft.posts.length || to >= draft.posts.length) return;

    const [item] = draft.posts.splice(from, 1);
    draft.posts.splice(to, 0, item);
    draft.post_ids = draft.posts.map((post) => post.id);
    isDirty = true;
    render();
}

async function loadRules(): Promise<void> {
    isLoading = true;
    render();

    try {
        const data = await request<{ rules: PinnedRule[] }>();
        rules = (data.rules ?? []).map(cloneRule);
        const first = rules[0] ?? null;
        selectedId = first?.id ?? null;
        draft = first ? cloneRule(first) : emptyRule();
        setNotice('', 'info');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('couldNotLoadPinnedResults', 'Could not load pinned results.'), 'error');
    } finally {
        isLoading = false;
        render();
    }
}

async function saveDraft(): Promise<void> {
    if (draft.phrase.trim() === '') {
        setSaveState('idle');
        editorError = t('emptyPhraseError', 'Add a search phrase before saving.');
        render('ts-pr-phrase');
        return;
    }

    if (!draft.posts.length) {
        setSaveState('idle');
        editorError = t('emptyPinnedResultsError', 'Add at least one pinned result before saving.');
        render();
        return;
    }

    if (saveState === 'saving') {
        return;
    }

    setSaveState('saving');
    render();

    try {
        const data = await request<{ rule: PinnedRule }>('', {
            method: 'POST',
            body: JSON.stringify({
                id: draft.id,
                phrase: draft.phrase,
                match_type: draft.match_type,
                enabled: draft.enabled !== false,
                post_ids: draft.posts.map((post) => post.id),
            }),
        });
        syncSelectedFromDraft(data.rule);
        clearEditorError();
        setSaveState('saved');
        setNotice(t('savedNotice', 'Pinned search saved. Sync to Typesense when you are ready.'), 'success');
    } catch (error) {
        setSaveState('idle');
        editorError = error instanceof Error ? error.message : t('saveError', 'Could not save pinned search.');
    }
    render();
}

async function deleteSelected(): Promise<void> {
    if (!draft.id || !window.confirm(t('confirmDeletePinnedSearch', 'Delete this pinned search?'))) return;

    try {
        const data = await request<{ rules: PinnedRule[] }>(`/${draft.id}`, { method: 'DELETE' });
        rules = (data.rules ?? []).map(cloneRule);
        const first = rules[0] ?? null;
        selectedId = first?.id ?? null;
        draft = first ? cloneRule(first) : emptyRule();
        clearEditorError();
        isDirty = false;
        resetPostSearch();
        setNotice(t('deletedNotice', 'Pinned search deleted. Sync to Typesense to apply the change.'), 'success');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('deleteError', 'Could not delete pinned search.'), 'error');
    }
    render();
}

async function syncRules(): Promise<void> {
    if (isSyncing) {
        return;
    }

    isSyncing = true;
    render();

    try {
        const data = await request<{ ok: boolean; message: string; rules: PinnedRule[] }>('/sync', { method: 'POST' });
        rules = (data.rules ?? rules).map(cloneRule);
        const current = draft.id ? rules.find((rule) => rule.id === draft.id) : null;
        draft = current ? cloneRule(current) : draft;
        setNotice(data.message || t('syncSuccess', 'Pinned searches synced.'), data.ok ? 'success' : 'error');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('syncError', 'Could not sync pinned searches.'), 'error');
    } finally {
        isSyncing = false;
    }
    render();
}

async function searchPosts(search: string): Promise<void> {
    postSearchTouched = true;
    postSearchValue = search;

    if (search.trim().length < 2) {
        postResults = [];
        postSearchLoading = false;
        render('ts-pr-post-search');
        return;
    }

    postSearchLoading = true;
    render('ts-pr-post-search');

    try {
        const data = await request<{ posts: PinnedPost[] }>(`/posts?search=${encodeURIComponent(search)}`);
        if (search !== postSearchValue) return;
        postResults = (data.posts ?? []).filter((post) => !draft.posts.some((item) => item.id === post.id));
    } catch (error) {
        postResults = [];
        setNotice(error instanceof Error ? error.message : t('postSearchError', 'Could not search posts.'), 'error');
    } finally {
        postSearchLoading = false;
        render('ts-pr-post-search');
    }
}

function filteredRules(): PinnedRule[] {
    const normalized = ruleFilter.trim().toLowerCase();
    if (!normalized) return rules;

    return rules.filter((rule) => rule.phrase.toLowerCase().includes(normalized));
}

function statusClass(rule: PinnedRule): string {
    if (rule.enabled === false) return 'is-disabled';
    if (rule.sync_status === 'error') return 'is-error';
    if (rule.sync_status === 'synced') return 'is-synced';
    return 'is-pending';
}

function statusText(rule: PinnedRule): string {
    if (rule.enabled === false) return t('disabledStatus', 'Disabled');
    if (!rule.id) return t('newStatus', 'New');
    if (rule.sync_status === 'synced') return t('syncedStatus', 'Synced');
    if (rule.sync_status === 'error') return t('errorStatus', 'Error');
    return t('pendingStatus', 'Pending');
}

function matchTypeLabel(matchType: PinnedRule['match_type']): string {
    return matchType === 'contains' ? t('contains', 'Contains') : t('exact', 'Exact');
}

function renderRuleList(): string {
    const visibleRules = filteredRules();

    if (!rules.length) {
        return `<p class="ts-pr__empty">${esc(t('noPinnedSearches', 'No pinned searches yet.'))}</p>`;
    }

    if (!visibleRules.length) {
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
            ${visibleRules.map((rule) => `
                <button type="button" class="ts-pr__rule-row ${rule.id === selectedId ? 'is-active' : ''}" data-action="select" data-id="${rule.id}">
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
        <div class="ts-pr__count">${visibleRules.length} ${visibleRules.length === 1 ? esc(t('itemSingular', 'item')) : esc(t('itemPlural', 'items'))}</div>
    `;
}

function renderPinnedPosts(): string {
    if (!draft.posts.length) {
        return `
            <p class="ts-pr__empty ts-pr__empty--inline">${esc(t('emptyPinnedResultsHelp', 'Search for posts below and add the results you want to pin.'))}</p>
            ${editorError && draft.phrase.trim() !== '' ? `<p class="ts-pr__field-error">${esc(editorError)}</p>` : ''}
        `;
    }

    return draft.posts.map((post, index) => `
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
    const shouldShowEmpty = postSearchTouched && postSearchValue.trim().length >= 2 && !postSearchLoading && postResults.length === 0;
    const shouldShowDropdown = postResults.length > 0 || postSearchLoading || shouldShowEmpty;

    return `
        <div class="ts-pr__autocomplete">
            <span class="dashicons dashicons-search ts-pr__search-icon" aria-hidden="true"></span>
            <input type="search" id="ts-pr-post-search" value="${esc(postSearchValue)}" placeholder="${esc(t('searchPostsToAdd', 'Search posts to add...'))}" autocomplete="off">
            ${postSearchLoading ? '<span class="spinner is-active ts-pr__spinner" aria-hidden="true"></span>' : ''}
            ${shouldShowDropdown ? `
                <div class="ts-pr__autocomplete-menu">
                    ${postSearchLoading ? `<div class="ts-pr__autocomplete-state">${esc(t('searching', 'Searching...'))}</div>` : ''}
                    ${shouldShowEmpty ? `<div class="ts-pr__autocomplete-state">${esc(t('noResultsFound', 'No results found.'))}</div>` : ''}
                    ${postResults.map((post) => `
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
    const title = draft.id ? draft.phrase || t('pinnedSearch', 'Pinned search') : t('newPinnedSearch', 'New pinned search');
    const saveButtonLabel = saveState === 'saving' ? t('saving', 'Saving...') : saveState === 'saved' ? t('saved', 'Saved') : t('saveChanges', 'Save changes');

    return `
        <section class="ts-pr__editor" aria-label="Pinned search editor">
            <div class="ts-pr__editor-scroll">
                <div class="ts-pr__editor-inner">
                    <div class="ts-pr__editor-actions">
                        <button type="button" class="button button-primary ts-pr__save ${saveState === 'saved' ? 'is-saved' : ''}" data-action="save" ${saveState === 'saving' ? 'disabled' : ''}>
                            <span>${esc(saveButtonLabel)}</span>
                            ${saveState === 'saving' ? '<span class="ts-pr__save-spinner" aria-hidden="true"></span>' : ''}
                        </button>
                        ${draft.id ? `<button type="button" class="button-link-delete ts-pr__delete-rule" data-action="delete">${esc(t('deletePinnedSearch', 'Delete pinned search'))}</button>` : ''}
                    </div>

                    <h2>${esc(title)}</h2>

                    <label class="ts-pr__field">
                        <span>${esc(t('searchPhrase', 'Search phrase'))}</span>
                        <input type="text" id="ts-pr-phrase" value="${esc(draft.phrase)}" autocomplete="off">
                        ${editorError && draft.phrase.trim() === '' ? `<span class="ts-pr__field-error">${esc(editorError)}</span>` : ''}
                    </label>

                    <div class="ts-pr__field">
                        <span>${esc(t('matchType', 'Match type'))}</span>
                        <div class="ts-pr__segments" role="group" aria-label="Match type">
                            <button type="button" class="ts-pr__segment ${draft.match_type === 'exact' ? 'is-active' : ''}" data-action="set-match-type" data-match-type="exact">${esc(t('exact', 'Exact'))}</button>
                            <button type="button" class="ts-pr__segment ${draft.match_type === 'contains' ? 'is-active' : ''}" data-action="set-match-type" data-match-type="contains">${esc(t('contains', 'Contains'))}</button>
                        </div>
                    </div>

                    <div class="ts-pr__enabled-row">
                        <button type="button" class="ts-pr__switch ${draft.enabled !== false ? 'is-on' : ''}" data-action="toggle-enabled" aria-pressed="${draft.enabled !== false ? 'true' : 'false'}">
                            <span></span>
                        </button>
                        <span>${esc(t('enabled', 'Enabled'))}</span>
                    </div>

                    ${draft.sync_error ? `<div class="notice notice-error inline"><p>${esc(draft.sync_error)}</p></div>` : ''}

                    <div class="ts-pr__section-title">${esc(t('pinnedResults', 'Pinned results'))}</div>
                    <ol class="ts-pr__pins">${renderPinnedPosts()}</ol>

                    <div class="ts-pr__add-result">
                        ${renderAutocomplete()}
                    </div>
                </div>
            </div>

        </section>
    `;
}

function render(focusId?: string): void {
    if (!app) return;

    if (!config) {
        app.innerHTML = `<div class="notice notice-error"><p>${esc(t('missingConfig', 'Missing pinned results configuration.'))}</p></div>`;
        return;
    }

    if (isLoading) {
        app.innerHTML = `
            <div class="ts-pr__loading" role="status" aria-live="polite">
                <span class="ts-pr__loading-spinner" aria-hidden="true"></span>
                <span>${esc(t('loadingPinnedSearches', 'Loading pinned searches...'))}</span>
            </div>
        `;
        return;
    }

    const noticeClass = noticeType === 'error' ? 'is-error' : noticeType === 'success' ? 'is-success' : 'is-info';
    const noticeRole = noticeType === 'error' ? 'alert' : 'status';

    app.innerHTML = `
        <div class="ts-pr__page">
            <header class="ts-pr__page-header">
                <h1>${esc(t('pinnedSearchResults', 'Pinned search results'))}</h1>
                <div class="ts-pr__toolbar">
                    <button type="button" class="button button-primary ts-pr__add" data-action="new" ${isSyncing ? 'disabled' : ''}>${esc(t('addPinnedSearch', 'Add pinned search'))}</button>
                    <button type="button" class="button ts-pr__sync-button ${isSyncing ? 'is-syncing' : ''}" data-action="sync" ${isSyncing ? 'disabled' : ''}>
                        <span>${esc(isSyncing ? t('syncing', 'Syncing...') : t('syncToTypesense', 'Sync to Typesense'))}</span>
                        <span class="${isSyncing ? 'ts-pr__sync-spinner' : 'dashicons dashicons-update'}" aria-hidden="true"></span>
                    </button>
                </div>
            </header>
            ${notice ? `<div class="ts-pr__toast ${noticeClass}" role="${noticeRole}" aria-live="${noticeType === 'error' ? 'assertive' : 'polite'}"><p>${esc(notice)}</p></div>` : ''}
            <div class="ts-pr__layout ${isSyncing ? 'is-dimmed' : ''}" ${isSyncing ? 'aria-busy="true"' : ''}>
                <aside class="ts-pr__sidebar">
                    <div class="ts-pr__rule-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="ts-pr-rule-search" value="${esc(ruleFilter)}" placeholder="${esc(t('searchPinnedSearches', 'Search pinned searches...'))}" autocomplete="off">
                    </div>
                    <div class="ts-pr__rules">${renderRuleList()}</div>
                </aside>
                ${renderEditor()}
            </div>
        </div>
    `;

    if (focusId) {
        const focused = document.getElementById(focusId);
        if (focused instanceof HTMLInputElement) {
            focused.focus();
            const end = focused.value.length;
            focused.setSelectionRange(end, end);
        }
    }
}

app?.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    const actionTarget = target.closest<HTMLElement>('[data-action]');
    if (!actionTarget) return;

    const action = actionTarget.dataset.action;
    if (isSyncing && action !== 'sync') return;

    if (action === 'new') selectRule(null);
    if (action === 'select') selectRule(Number(actionTarget.dataset.id));
    if (action === 'save') void saveDraft();
    if (action === 'sync') void syncRules();
    if (action === 'delete') void deleteSelected();
    if (action === 'remove-post') removePost(Number(actionTarget.dataset.index));
    if (action === 'set-match-type') {
        draft.match_type = actionTarget.dataset.matchType === 'contains' ? 'contains' : 'exact';
        isDirty = true;
        render();
    }
    if (action === 'toggle-enabled') {
        draft.enabled = !(draft.enabled !== false);
        isDirty = true;
        render();
    }
    if (action === 'add-post') {
        const post = postResults.find((item) => item.id === Number(actionTarget.dataset.postId));
        if (post) addPost(post);
    }
});

app?.addEventListener('input', (event) => {
    if (isSyncing) return;

    const target = event.target as HTMLInputElement;

    if (target.id === 'ts-pr-phrase') {
        draft.phrase = target.value;
        clearEditorError();
        isDirty = true;
    }

    if (target.id === 'ts-pr-rule-search') {
        ruleFilter = target.value;
        render('ts-pr-rule-search');
    }

    if (target.id === 'ts-pr-post-search') {
        postSearchValue = target.value;
        postSearchTouched = true;
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => void searchPosts(target.value), 250);
    }
});

app?.addEventListener('dragstart', (event) => {
    if (isSyncing) return;

    const item = (event.target as HTMLElement).closest<HTMLElement>('.ts-pr__pin');
    if (!item) return;
    dragIndex = Number(item.dataset.index);
    event.dataTransfer?.setData('text/plain', String(dragIndex));
});

app?.addEventListener('dragover', (event) => {
    if (isSyncing) return;

    if ((event.target as HTMLElement).closest('.ts-pr__pin')) {
        event.preventDefault();
    }
});

app?.addEventListener('drop', (event) => {
    if (isSyncing) return;

    const item = (event.target as HTMLElement).closest<HTMLElement>('.ts-pr__pin');
    if (!item || dragIndex === null) return;
    event.preventDefault();
    movePost(dragIndex, Number(item.dataset.index));
    dragIndex = null;
});

window.addEventListener('beforeunload', (event) => {
    if (!isDirty) return;
    event.preventDefault();
    event.returnValue = '';
});

void loadRules();
