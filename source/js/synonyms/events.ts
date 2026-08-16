import { state } from './state';
import { loadRules, saveDraft, deleteSelected, syncRules, selectRule, addTerm, removeTerm } from './api';
import { render } from './render';

const app = document.getElementById('ts-synonyms-app');

export function registerEvents(): void {
    if (!app) return;

    // ── Click delegation ──────────────────────────────────────────────────────

    app.addEventListener('click', (event) => {
        const target       = event.target as HTMLElement;
        const actionTarget = target.closest<HTMLElement>('[data-action]');
        if (!actionTarget) return;

        const action = actionTarget.dataset.action;
        if (state.isSyncing && action !== 'sync') return;

        if (action === 'new')         selectRule(null);
        if (action === 'select')      selectRule(Number(actionTarget.dataset.id));
        if (action === 'save')        void saveDraft();
        if (action === 'sync')        void syncRules();
        if (action === 'delete')      void deleteSelected();
        if (action === 'remove-term') removeTerm(Number(actionTarget.dataset.index));
        if (action === 'add-term')    addTerm(state.termDraftValue);
    });

    // ── Input delegation ──────────────────────────────────────────────────────

    app.addEventListener('input', (event) => {
        if (state.isSyncing) return;

        const target = event.target as HTMLInputElement;

        if (target.id === 'ts-syn-rule-search') {
            state.ruleFilter = target.value;
            render('ts-syn-rule-search');
        }

        if (target.id === 'ts-syn-term-draft') {
            state.termDraftValue = target.value;
        }
    });

    // ── Keydown: Enter or comma commits the term draft as a chip ───────────────

    app.addEventListener('keydown', (event) => {
        const target = event.target as HTMLElement;
        if (target.id !== 'ts-syn-term-draft') return;

        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTerm(state.termDraftValue);
        }
    });

    // ── Unsaved-changes guard ─────────────────────────────────────────────────

    window.addEventListener('beforeunload', (event) => {
        if (!state.isDirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

export { loadRules };
