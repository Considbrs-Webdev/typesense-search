import type { PinnedPost, PinnedRule } from './types';
import { state, cloneRule, emptyRule, setNotice, setSaveState, resetPostSearch, clearEditorError, getSearchTimer, setSearchTimer } from './state';
import { render } from './render';

async function request<T>(path = '', options: RequestInit = {}): Promise<T> {
    const config = window.tsPinnedResults;
    if (!config) throw new Error('Missing pinned results configuration.');

    const response = await fetch(config.restUrl + path, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce':   config.nonce,
            ...(options.headers ?? {}),
        },
        credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data?.message ?? 'Request failed.');
    return data as T;
}

export async function loadRules(): Promise<void> {
    state.isLoading = true;
    render();

    try {
        const data = await request<{ rules: PinnedRule[] }>();
        state.rules      = (data.rules ?? []).map(cloneRule);
        state.selectedId = null;
        state.draft      = emptyRule();
        setNotice('', 'info', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not load pinned results.', 'error', render);
    } finally {
        state.isLoading = false;
        render();
    }
}

export async function saveDraft(): Promise<void> {
    if (state.draft.phrase.trim() === '') {
        setSaveState('idle', render);
        state.editorError = 'Add a search phrase before saving.';
        render('ts-pr-phrase');
        return;
    }

    if (!state.draft.posts.length) {
        setSaveState('idle', render);
        state.editorError = 'Add at least one pinned result before saving.';
        render();
        return;
    }

    if (state.saveState === 'saving') return;

    setSaveState('saving', render);
    render();

    try {
        const data = await request<{ rule: PinnedRule }>('', {
            method: 'POST',
            body:   JSON.stringify({
                id:         state.draft.id,
                phrase:     state.draft.phrase,
                match_type: state.draft.match_type,
                enabled:    state.draft.enabled !== false,
                post_ids:   state.draft.posts.map((p) => p.id),
            }),
        });

        const saved      = cloneRule(data.rule);
        const index      = state.rules.findIndex((r) => r.id === saved.id);
        if (index >= 0) {
            state.rules[index] = saved;
        } else {
            state.rules.push(saved);
            state.rules.sort((a, b) => a.phrase.localeCompare(b.phrase));
        }
        state.selectedId = saved.id;
        state.draft      = cloneRule(saved);
        state.isDirty    = false;

        clearEditorError();
        setSaveState('saved', render);
        setNotice('Pinned search saved. Sync to Typesense when you are ready.', 'success', render);
    } catch (error) {
        setSaveState('idle', render);
        state.editorError = error instanceof Error ? error.message : 'Could not save pinned search.';
    }
    render();
}

export async function deleteSelected(): Promise<void> {
    if (!state.draft.id || !window.confirm('Delete this pinned search?')) return;

    try {
        const data       = await request<{ rules: PinnedRule[] }>(`/${state.draft.id}`, { method: 'DELETE' });
        state.rules      = (data.rules ?? []).map(cloneRule);
        const first      = state.rules[0] ?? null;
        state.selectedId = first?.id ?? null;
        state.draft      = first ? cloneRule(first) : emptyRule();
        state.isDirty    = false;
        clearEditorError();
        resetPostSearch();
        setNotice('Pinned search deleted. Sync to Typesense to apply the change.', 'success', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not delete pinned search.', 'error', render);
    }
    render();
}

export async function syncRules(): Promise<void> {
    if (state.isSyncing) return;

    state.isSyncing = true;
    render();

    try {
        const data  = await request<{ ok: boolean; message: string; rules: PinnedRule[] }>('/sync', { method: 'POST' });
        state.rules = (data.rules ?? state.rules).map(cloneRule);
        const current = state.draft.id ? state.rules.find((r) => r.id === state.draft.id) : null;
        state.draft   = current ? cloneRule(current) : state.draft;
        setNotice(data.message || 'Pinned searches synced.', data.ok ? 'success' : 'error', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : 'Could not sync pinned searches.', 'error', render);
    } finally {
        state.isSyncing = false;
    }
    render();
}

export async function searchPosts(search: string): Promise<void> {
    state.postSearchTouched = true;
    state.postSearchValue   = search;

    if (search.trim().length < 2) {
        state.postResults       = [];
        state.postSearchLoading = false;
        render('ts-pr-post-search');
        return;
    }

    state.postSearchLoading = true;
    render('ts-pr-post-search');

    try {
        const data        = await request<{ posts: PinnedPost[] }>(`/posts?search=${encodeURIComponent(search)}`);
        if (search !== state.postSearchValue) return;
        state.postResults = (data.posts ?? []).filter((p) => !state.draft.posts.some((item) => item.id === p.id));
    } catch (error) {
        state.postResults = [];
        setNotice(error instanceof Error ? error.message : 'Could not search posts.', 'error', render);
    } finally {
        state.postSearchLoading = false;
        render('ts-pr-post-search');
    }
}

export function selectRule(id: number | null): void {
    const next       = id === null ? null : state.rules.find((r) => r.id === id);
    state.selectedId = id;
    state.draft      = next ? cloneRule(next) : emptyRule();
    resetPostSearch();
    clearEditorError();
    state.isDirty = false;
    render();
}

export function removePost(index: number): void {
    state.draft.posts.splice(index, 1);
    state.draft.post_ids = state.draft.posts.map((p) => p.id);
    state.postResults    = state.postResults.filter((p) => !state.draft.posts.some((item) => item.id === p.id));
    clearEditorError();
    state.isDirty = true;
    render();
}

export function addPost(post: PinnedPost): void {
    if (state.draft.posts.some((item) => item.id === post.id)) return;
    state.draft.posts.push(post);
    state.draft.post_ids = state.draft.posts.map((p) => p.id);
    state.postResults    = state.postResults.filter((p) => p.id !== post.id);
    clearEditorError();
    state.isDirty = true;
    render('ts-pr-post-search');
}

export function movePost(from: number, to: number): void {
    if (from === to || from < 0 || to < 0 || from >= state.draft.posts.length || to >= state.draft.posts.length) return;
    const [item] = state.draft.posts.splice(from, 1);
    state.draft.posts.splice(to, 0, item);
    state.draft.post_ids = state.draft.posts.map((p) => p.id);
    state.isDirty = true;
    render();
}

export function schedulePostSearch(value: string): void {
    state.postSearchValue   = value;
    state.postSearchTouched = true;
    clearTimeout(getSearchTimer());
    setSearchTimer(setTimeout(() => void searchPosts(value), 250));
}
