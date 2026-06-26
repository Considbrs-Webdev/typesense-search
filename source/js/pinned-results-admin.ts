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
};

type Config = {
    restUrl: string;
    nonce: string;
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
let postResults: PinnedPost[] = [];
let dragIndex: number | null = null;
let searchTimer: number | undefined;

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
    };
}

function cloneRule(rule: PinnedRule): PinnedRule {
    return JSON.parse(JSON.stringify(rule)) as PinnedRule;
}

async function request<T>(path = '', options: RequestInit = {}): Promise<T> {
    if (!config) throw new Error('Missing pinned results configuration.');

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
    notice = message;
    noticeType = type;
}

function selectRule(id: number | null): void {
    const next = id === null ? null : rules.find((rule) => rule.id === id);
    selectedId = id;
    draft = next ? cloneRule(next) : emptyRule();
    postResults = [];
    isDirty = false;
    render();
}

function syncSelectedFromDraft(saved: PinnedRule): void {
    const index = rules.findIndex((rule) => rule.id === saved.id);
    if (index >= 0) {
        rules[index] = saved;
    } else {
        rules.push(saved);
    rules.sort((a, b) => a.phrase.localeCompare(b.phrase));
    }
    selectedId = saved.id;
    draft = cloneRule(saved);
    isDirty = false;
}

function removePost(index: number): void {
    draft.posts.splice(index, 1);
    draft.post_ids = draft.posts.map((post) => post.id);
    isDirty = true;
    render();
}

function addPost(post: PinnedPost): void {
    if (draft.posts.some((item) => item.id === post.id)) return;
    draft.posts.push(post);
    draft.post_ids = draft.posts.map((item) => item.id);
    isDirty = true;
    render();
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
        rules = data.rules ?? [];
        const first = rules[0] ?? null;
        selectedId = first?.id ?? null;
        draft = first ? cloneRule(first) : emptyRule();
        setNotice('', 'info');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not load pinned results.', 'error');
    } finally {
        isLoading = false;
        render();
    }
}

async function saveDraft(): Promise<void> {
    try {
        const data = await request<{ rule: PinnedRule }>('', {
            method: 'POST',
            body: JSON.stringify({
                id: draft.id,
                phrase: draft.phrase,
                match_type: draft.match_type,
                post_ids: draft.posts.map((post) => post.id),
            }),
        });
        syncSelectedFromDraft(data.rule);
        setNotice('Pinned result saved. Sync to Typesense when you are ready.', 'success');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not save pinned result.', 'error');
    }
    render();
}

async function deleteSelected(): Promise<void> {
    if (!draft.id || !window.confirm('Delete this pinned result rule?')) return;

    try {
        const data = await request<{ rules: PinnedRule[] }>(`/${draft.id}`, { method: 'DELETE' });
        rules = data.rules ?? [];
        const first = rules[0] ?? null;
        selectedId = first?.id ?? null;
        draft = first ? cloneRule(first) : emptyRule();
        isDirty = false;
        setNotice('Pinned result deleted. Sync to Typesense to apply the change.', 'success');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not delete pinned result.', 'error');
    }
    render();
}

async function syncRules(): Promise<void> {
    try {
        const data = await request<{ ok: boolean; message: string; rules: PinnedRule[] }>('/sync', { method: 'POST' });
        rules = data.rules ?? rules;
        const current = draft.id ? rules.find((rule) => rule.id === draft.id) : null;
        draft = current ? cloneRule(current) : draft;
        setNotice(data.message || 'Pinned results synced.', data.ok ? 'success' : 'error');
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not sync pinned results.', 'error');
    }
    render();
}

async function searchPosts(search: string): Promise<void> {
    if (search.trim().length < 2) {
        postResults = [];
        render();
        return;
    }

    try {
        const data = await request<{ posts: PinnedPost[] }>(`/posts?search=${encodeURIComponent(search)}`);
        postResults = (data.posts ?? []).filter((post) => !draft.posts.some((item) => item.id === post.id));
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not search posts.', 'error');
    }
    render();
}

function renderRuleList(): string {
    if (!rules.length) {
        return '<p class="ts-pr__empty">No pinned result rules yet.</p>';
    }

    return rules.map((rule) => `
        <button type="button" class="ts-pr__term ${rule.id === selectedId ? 'is-active' : ''}" data-action="select" data-id="${rule.id}">
            <span class="ts-pr__term-name">${esc(rule.phrase)}</span>
            <span class="ts-pr__term-meta">${rule.posts.length} pinned</span>
        </button>
    `).join('');
}

function renderPinnedPosts(): string {
    if (!draft.posts.length) {
        return '<p class="ts-pr__empty">Search for posts below and add the results you want to pin.</p>';
    }

    return draft.posts.map((post, index) => `
        <li class="ts-pr__pin" draggable="true" data-index="${index}">
            <button type="button" class="ts-pr__drag" title="Drag to reorder" aria-label="Drag to reorder">::</button>
            <span class="ts-pr__pin-position">${index + 1}</span>
            <span class="ts-pr__pin-text">
                <strong>${esc(post.title)}</strong>
                <small>${esc(post.type_name)}</small>
            </span>
            ${post.edit_url ? `<a class="ts-pr__icon-link" href="${esc(post.edit_url)}" target="_blank" rel="noreferrer" title="Edit post" aria-label="Edit post"><span class="dashicons dashicons-edit-page" aria-hidden="true"></span></a>` : ''}
            <button type="button" class="button-link-delete ts-pr__remove" data-action="remove-post" data-index="${index}">Remove</button>
        </li>
    `).join('');
}

function renderPostResults(): string {
    if (!postResults.length) return '';

    return `
        <div class="ts-pr__results">
            ${postResults.map((post) => `
                <button type="button" class="ts-pr__result" data-action="add-post" data-post-id="${post.id}">
                    <span>
                        <strong>${esc(post.title)}</strong>
                        <small>${esc(post.type_name)}</small>
                    </span>
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                </button>
            `).join('')}
        </div>
    `;
}

function renderEditor(): string {
    const statusClass = draft.sync_status === 'error' ? 'is-error' : draft.sync_status === 'synced' ? 'is-synced' : 'is-pending';
    const statusText = draft.id ? draft.sync_status : 'new';

    return `
        <section class="ts-pr__editor" aria-label="Pinned result editor">
            <div class="ts-pr__editor-bar">
                <div>
                    <h2>${draft.id ? esc(draft.phrase || 'Pinned result') : 'New pinned result'}</h2>
                    <span class="ts-pr__status ${statusClass}">${esc(statusText)}</span>
                </div>
                <div class="ts-pr__actions">
                    ${draft.id ? '<button type="button" class="button" data-action="delete">Delete</button>' : ''}
                    <button type="button" class="button button-primary" data-action="save">${isDirty ? 'Save changes' : 'Save'}</button>
                    <button type="button" class="button" data-action="sync">Sync to Typesense</button>
                </div>
            </div>

            <div class="ts-pr__fields">
                <label class="ts-pr__field">
                    <span>Search phrase</span>
                    <input type="text" id="ts-pr-phrase" value="${esc(draft.phrase)}" autocomplete="off">
                </label>
                <label class="ts-pr__field">
                    <span>Match type</span>
                    <select id="ts-pr-match-type">
                        <option value="exact" ${draft.match_type === 'exact' ? 'selected' : ''}>Exact phrase</option>
                        <option value="contains" ${draft.match_type === 'contains' ? 'selected' : ''}>Contains phrase</option>
                    </select>
                </label>
            </div>

            ${draft.sync_error ? `<div class="notice notice-error inline"><p>${esc(draft.sync_error)}</p></div>` : ''}

            <div class="ts-pr__section-head">
                <h3>Pinned results</h3>
            </div>
            <ol class="ts-pr__pins">${renderPinnedPosts()}</ol>

            <div class="ts-pr__post-search">
                <label class="ts-pr__field">
                    <span>Add result</span>
                    <input type="search" id="ts-pr-post-search" placeholder="Search published content" autocomplete="off">
                </label>
                ${renderPostResults()}
            </div>
        </section>
    `;
}

function render(): void {
    if (!app) return;

    if (!config) {
        app.innerHTML = '<div class="notice notice-error"><p>Missing pinned results configuration.</p></div>';
        return;
    }

    if (isLoading) {
        app.innerHTML = '<div class="ts-pr__loading">Loading pinned results...</div>';
        return;
    }

    app.innerHTML = `
        ${notice ? `<div class="notice notice-${noticeType === 'error' ? 'error' : noticeType === 'success' ? 'success' : 'info'} inline"><p>${esc(notice)}</p></div>` : ''}
        <div class="ts-pr__layout">
            <aside class="ts-pr__sidebar">
                <div class="ts-pr__sidebar-head">
                    <h2>Search phrases</h2>
                    <button type="button" class="button" data-action="new">Add</button>
                </div>
                <div class="ts-pr__terms">${renderRuleList()}</div>
            </aside>
            ${renderEditor()}
        </div>
    `;
}

app?.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    const actionTarget = target.closest<HTMLElement>('[data-action]');
    if (!actionTarget) return;

    const action = actionTarget.dataset.action;
    if (action === 'new') selectRule(null);
    if (action === 'select') selectRule(Number(actionTarget.dataset.id));
    if (action === 'save') void saveDraft();
    if (action === 'sync') void syncRules();
    if (action === 'delete') void deleteSelected();
    if (action === 'remove-post') removePost(Number(actionTarget.dataset.index));
    if (action === 'add-post') {
        const post = postResults.find((item) => item.id === Number(actionTarget.dataset.postId));
        if (post) addPost(post);
    }
});

app?.addEventListener('input', (event) => {
    const target = event.target as HTMLInputElement;
    if (target.id === 'ts-pr-phrase') {
        draft.phrase = target.value;
        isDirty = true;
    }
    if (target.id === 'ts-pr-post-search') {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => void searchPosts(target.value), 250);
    }
});

app?.addEventListener('change', (event) => {
    const target = event.target as HTMLSelectElement;
    if (target.id === 'ts-pr-match-type') {
        draft.match_type = target.value === 'contains' ? 'contains' : 'exact';
        isDirty = true;
        render();
    }
});

app?.addEventListener('dragstart', (event) => {
    const item = (event.target as HTMLElement).closest<HTMLElement>('.ts-pr__pin');
    if (!item) return;
    dragIndex = Number(item.dataset.index);
    event.dataTransfer?.setData('text/plain', String(dragIndex));
});

app?.addEventListener('dragover', (event) => {
    if ((event.target as HTMLElement).closest('.ts-pr__pin')) {
        event.preventDefault();
    }
});

app?.addEventListener('drop', (event) => {
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
