import type { SynonymRule } from './types';

export type SaveState  = 'idle' | 'saving' | 'saved';
export type NoticeType = 'success' | 'error' | 'info';

export const state = {
    rules:         [] as SynonymRule[],
    selectedId:     null as number | null,
    draft:          emptyRule(),
    isDirty:        false,
    isLoading:      true,
    notice:         '',
    noticeType:     'info' as NoticeType,
    ruleFilter:     '',
    termDraftValue: '',
    editorError:    '',
    saveState:      'idle' as SaveState,
    isSyncing:      false,
};

let saveStateTimer: ReturnType<typeof setTimeout> | undefined;
let noticeTimer:    ReturnType<typeof setTimeout> | undefined;

export function emptyRule(): SynonymRule {
    return {
        id:          null,
        terms:       [],
        sync_status: 'draft',
        sync_error:  '',
        synced_at:   '',
        updated_at:  '',
    };
}

export function cloneRule(rule: SynonymRule): SynonymRule {
    return JSON.parse(JSON.stringify(rule)) as SynonymRule;
}

export function t(key: string, fallback: string): string {
    return window.tsSynonyms?.i18n?.[key] ?? fallback;
}

export function setNotice(message: string, type: NoticeType, render: () => void): void {
    clearTimeout(noticeTimer);
    state.notice     = message;
    state.noticeType = type;

    if (message && type !== 'error') {
        noticeTimer = setTimeout(() => {
            state.notice = '';
            render();
        }, 4200);
    }
}

export function setSaveState(value: SaveState, render: () => void): void {
    clearTimeout(saveStateTimer);
    state.saveState = value;

    if (value === 'saved') {
        saveStateTimer = setTimeout(() => {
            state.saveState = 'idle';
            render();
        }, 1600);
    }
}

export function clearEditorError(): void {
    state.editorError = '';
}
