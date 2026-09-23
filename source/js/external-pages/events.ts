import { state } from './state';
import { loadPages, saveDraft, deleteSelected, syncPages, selectPage, updateDraftField } from './api';
import { render } from './render';

const app = document.getElementById('ts-external-pages-app');

const FIELD_IDS: Record<string, 'title' | 'content' | 'url'> = {
    'ts-ext-title':   'title',
    'ts-ext-url':     'url',
    'ts-ext-content': 'content',
};

export function registerEvents(): void {
    if (!app) return;

    // ── Click delegation ──────────────────────────────────────────────────────

    app.addEventListener('click', (event) => {
        const target       = event.target as HTMLElement;
        const actionTarget = target.closest<HTMLElement>('[data-action]');
        if (!actionTarget) return;

        const action = actionTarget.dataset.action;
        if (state.isSyncing && action !== 'sync') return;

        if (action === 'new')    selectPage(null);
        if (action === 'select') selectPage(Number(actionTarget.dataset.id));
        if (action === 'save')   void saveDraft();
        if (action === 'sync')   void syncPages();
        if (action === 'delete') void deleteSelected();
    });

    // ── Input delegation ──────────────────────────────────────────────────────

    app.addEventListener('input', (event) => {
        if (state.isSyncing) return;

        const target = event.target as HTMLInputElement | HTMLTextAreaElement;

        if (target.id === 'ts-ext-page-search') {
            state.pageFilter = target.value;
            render('ts-ext-page-search');
            return;
        }

        const field = FIELD_IDS[target.id];
        if (field) updateDraftField(field, target.value);
    });

    // ── Unsaved-changes guard ─────────────────────────────────────────────────

    window.addEventListener('beforeunload', (event) => {
        if (!state.isDirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

export { loadPages };
