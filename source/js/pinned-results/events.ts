import { state, clearEditorError } from './state';
import { loadRules, saveDraft, deleteSelected, syncRules, selectRule, removePost, addPost, movePost, schedulePostSearch } from './api';
import { render } from './render';
import { createSortableList } from '../admin/sortable-list';

const app = document.getElementById('ts-pinned-results-app');

export function registerEvents(): void {
    if (!app) return;

    // ── Click delegation ──────────────────────────────────────────────────────

    app.addEventListener('click', (event) => {
        const target       = event.target as HTMLElement;
        const actionTarget = target.closest<HTMLElement>('[data-action]');
        if (!actionTarget) return;

        const action = actionTarget.dataset.action;
        if (state.isSyncing && action !== 'sync') return;

        if (action === 'new')    selectRule(null);
        if (action === 'select') selectRule(Number(actionTarget.dataset.id));
        if (action === 'save')   void saveDraft();
        if (action === 'sync')   void syncRules();
        if (action === 'delete') void deleteSelected();
        if (action === 'remove-post') removePost(Number(actionTarget.dataset.index));

        if (action === 'set-match-type') {
            state.draft.match_type = actionTarget.dataset.matchType === 'contains' ? 'contains' : 'exact';
            state.isDirty = true;
            render();
        }

        if (action === 'toggle-enabled') {
            state.draft.enabled = !(state.draft.enabled !== false);
            state.isDirty = true;
            render();
        }

        if (action === 'add-post') {
            const post = state.postResults.find((p) => p.id === Number(actionTarget.dataset.postId));
            if (post) addPost(post);
        }
    });

    // ── Input delegation ──────────────────────────────────────────────────────

    app.addEventListener('input', (event) => {
        if (state.isSyncing) return;

        const target = event.target as HTMLInputElement;

        if (target.id === 'ts-pr-phrase') {
            state.draft.phrase = target.value;
            state.isDirty      = true;
            clearEditorError();
        }

        if (target.id === 'ts-pr-rule-search') {
            state.ruleFilter = target.value;
            render('ts-pr-rule-search');
        }

        if (target.id === 'ts-pr-post-search') {
            schedulePostSearch(target.value);
        }
    });

    // ── Drag-and-drop sortable list ───────────────────────────────────────────

    createSortableList(app, '.ts-pr__pin', movePost);

    // ── Unsaved-changes guard ─────────────────────────────────────────────────

    window.addEventListener('beforeunload', (event) => {
        if (!state.isDirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

export { loadRules };
