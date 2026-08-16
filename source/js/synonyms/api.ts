import type { SynonymRule } from './types';
import { state, cloneRule, emptyRule, setNotice, setSaveState, clearEditorError, t } from './state';
import { render } from './render';

async function request<T>(path = '', options: RequestInit = {}): Promise<T> {
    const config = window.tsSynonyms;
    if (!config) throw new Error(t('missingConfig', 'Missing synonyms configuration.'));

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
        const data = await request<{ rules: SynonymRule[] }>();
        state.rules      = (data.rules ?? []).map(cloneRule);
        state.selectedId = null;
        state.draft      = emptyRule();
        setNotice('', 'info', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('couldNotLoadSynonyms', 'Could not load synonyms.'), 'error', render);
    } finally {
        state.isLoading = false;
        render();
    }
}

export async function saveDraft(): Promise<void> {
    if (state.draft.terms.length < 2) {
        setSaveState('idle', render);
        state.editorError = t('tooFewTermsError', 'Add at least two words that should be treated as synonyms.');
        render('ts-syn-term-draft');
        return;
    }

    if (state.saveState === 'saving') return;

    setSaveState('saving', render);
    render();

    try {
        const data = await request<{ rule: SynonymRule }>('', {
            method: 'POST',
            body:   JSON.stringify({
                id:    state.draft.id,
                terms: state.draft.terms,
            }),
        });

        const saved = cloneRule(data.rule);
        const index = state.rules.findIndex((r) => r.id === saved.id);
        if (index >= 0) {
            state.rules[index] = saved;
        } else {
            state.rules.push(saved);
            state.rules.sort((a, b) => (a.terms[0] ?? '').localeCompare(b.terms[0] ?? ''));
        }
        state.selectedId = saved.id;
        state.draft      = cloneRule(saved);
        state.isDirty    = false;

        clearEditorError();
        setSaveState('saved', render);
        setNotice(t('savedNotice', 'Synonym rule saved. Sync to Typesense when you are ready.'), 'success', render);
    } catch (error) {
        setSaveState('idle', render);
        state.editorError = error instanceof Error ? error.message : t('saveError', 'Could not save synonym rule.');
    }
    render();
}

export async function deleteSelected(): Promise<void> {
    if (!state.draft.id || !window.confirm(t('confirmDeleteSynonymRule', 'Delete this synonym rule?'))) return;

    try {
        const data       = await request<{ rules: SynonymRule[] }>(`/${state.draft.id}`, { method: 'DELETE' });
        state.rules      = (data.rules ?? []).map(cloneRule);
        const first      = state.rules[0] ?? null;
        state.selectedId = first?.id ?? null;
        state.draft      = first ? cloneRule(first) : emptyRule();
        state.isDirty    = false;
        clearEditorError();
        setNotice(t('deletedNotice', 'Synonym rule deleted. Sync to Typesense to apply the change.'), 'success', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('deleteError', 'Could not delete synonym rule.'), 'error', render);
    }
    render();
}

export async function syncRules(): Promise<void> {
    if (state.isSyncing) return;

    state.isSyncing = true;
    render();

    try {
        const data  = await request<{ ok: boolean; message: string; rules: SynonymRule[] }>('/sync', { method: 'POST' });
        state.rules = (data.rules ?? state.rules).map(cloneRule);
        const current = state.draft.id ? state.rules.find((r) => r.id === state.draft.id) : null;
        state.draft   = current ? cloneRule(current) : state.draft;
        setNotice(data.message || t('syncSuccess', 'Synonyms synced.'), data.ok ? 'success' : 'error', render);
    } catch (error) {
        setNotice(error instanceof Error ? error.message : t('syncError', 'Could not sync synonyms.'), 'error', render);
    } finally {
        state.isSyncing = false;
    }
    render();
}

export function selectRule(id: number | null): void {
    const next        = id === null ? null : state.rules.find((r) => r.id === id);
    state.selectedId  = id;
    state.draft       = next ? cloneRule(next) : emptyRule();
    state.termDraftValue = '';
    clearEditorError();
    state.isDirty = false;
    render();
}

export function addTerm(term: string): void {
    const clean = term.trim();
    if (clean === '' || state.draft.terms.includes(clean)) return;
    state.draft.terms.push(clean);
    clearEditorError();
    state.isDirty = true;
    state.termDraftValue = '';
    render('ts-syn-term-draft');
}

export function removeTerm(index: number): void {
    state.draft.terms.splice(index, 1);
    clearEditorError();
    state.isDirty = true;
    render();
}
