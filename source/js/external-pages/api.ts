import type { ExternalPage } from './types';
import { state, clonePage, emptyPage, setNotice, setSaveState, clearEditorError, t } from './state';
import { render } from './render';

async function request<T>(path = '', options: RequestInit = {}): Promise<T> {
    const config = window.tsExternalPages;
    if (!config) throw new Error(t('missingConfig', 'Missing external pages configuration.'));

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

export async function loadPages(): Promise<void> {
    state.isLoading = true;
    render();

    try {
        const data = await request<{ pages: ExternalPage[] }>();
        state.pages      = (data.pages ?? []).map(clonePage);
        state.selectedId = null;
        state.draft      = emptyPage();
        setNotice('', 'info', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('couldNotLoadPages', 'Could not load external pages.'), 'error', render);
    } finally {
        state.isLoading = false;
        render();
    }
}

export async function saveDraft(): Promise<void> {
    if (state.draft.title.trim() === '') {
        setSaveState('idle', render);
        state.editorError = t('missingTitleError', 'Enter a title for the external page.');
        render('ts-ext-title');
        return;
    }

    if (!/^https?:\/\/\S+/i.test(state.draft.url.trim())) {
        setSaveState('idle', render);
        state.editorError = t('invalidUrlError', 'Enter a valid URL starting with http:// or https://.');
        render('ts-ext-url');
        return;
    }

    if (state.saveState === 'saving') return;

    setSaveState('saving', render);
    render();

    try {
        const data = await request<{ page: ExternalPage }>('', {
            method: 'POST',
            body:   JSON.stringify({
                id:      state.draft.id,
                title:   state.draft.title,
                content: state.draft.content,
                url:     state.draft.url,
            }),
        });

        const saved = clonePage(data.page);
        const index = state.pages.findIndex((p) => p.id === saved.id);
        if (index >= 0) {
            state.pages[index] = saved;
        } else {
            state.pages.push(saved);
            state.pages.sort((a, b) => a.title.localeCompare(b.title));
        }
        state.selectedId = saved.id;
        state.draft      = clonePage(saved);
        state.isDirty    = false;

        clearEditorError();
        setSaveState('saved', render);
        setNotice(t('savedNotice', 'External page saved. Sync to Typesense when you are ready.'), 'success', render);
    } catch (error) {
        setSaveState('idle', render);
        state.editorError = error instanceof Error ? error.message : t('saveError', 'Could not save external page.');
    }
    render();
}

export async function deleteSelected(): Promise<void> {
    if (!state.draft.id || !window.confirm(t('confirmDeletePage', 'Delete this external page?'))) return;

    try {
        const data       = await request<{ pages: ExternalPage[] }>(`/${state.draft.id}`, { method: 'DELETE' });
        state.pages      = (data.pages ?? []).map(clonePage);
        const first      = state.pages[0] ?? null;
        state.selectedId = first?.id ?? null;
        state.draft      = first ? clonePage(first) : emptyPage();
        state.isDirty    = false;
        clearEditorError();
        setNotice(t('deletedNotice', 'External page deleted. Sync to Typesense to apply the change.'), 'success', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('deleteError', 'Could not delete external page.'), 'error', render);
    }
    render();
}

export async function syncPages(): Promise<void> {
    if (state.isSyncing) return;

    state.isSyncing = true;
    render();

    try {
        const data  = await request<{ ok: boolean; message: string; pages: ExternalPage[] }>('/sync', { method: 'POST' });
        state.pages = (data.pages ?? state.pages).map(clonePage);
        const current = state.draft.id ? state.pages.find((p) => p.id === state.draft.id) : null;
        state.draft   = current ? clonePage(current) : state.draft;
        setNotice(data.message || t('syncSuccess', 'External pages synced.'), data.ok ? 'success' : 'error', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('syncError', 'Could not sync external pages.'), 'error', render);
    } finally {
        state.isSyncing = false;
    }
    render();
}

export function selectPage(id: number | null): void {
    const next       = id === null ? null : state.pages.find((p) => p.id === id);
    state.selectedId = id;
    state.draft      = next ? clonePage(next) : emptyPage();
    clearEditorError();
    state.isDirty = false;
    render();
}

export function updateDraftField(field: 'title' | 'content' | 'url', value: string): void {
    state.draft[field] = value;
    clearEditorError();
    state.isDirty = true;
}
