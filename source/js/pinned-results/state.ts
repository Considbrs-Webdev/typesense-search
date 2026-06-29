import type { PinnedRule, PinnedPost } from './types';

export type SaveState  = 'idle' | 'saving' | 'saved';
export type NoticeType = 'success' | 'error' | 'info';

export const state = {
    rules:              [] as PinnedRule[],
    selectedId:         null as number | null,
    draft:              emptyRule(),
    isDirty:            false,
    isLoading:          true,
    notice:             '',
    noticeType:         'info' as NoticeType,
    ruleFilter:         '',
    postSearchValue:    '',
    postResults:        [] as PinnedPost[],
    postSearchLoading:  false,
    postSearchTouched:  false,
    editorError:        '',
    saveState:          'idle' as SaveState,
    isSyncing:          false,
};

let saveStateTimer: ReturnType<typeof setTimeout> | undefined;
let noticeTimer:    ReturnType<typeof setTimeout> | undefined;
let searchTimer:    ReturnType<typeof setTimeout> | undefined;

export function emptyRule(): PinnedRule {
    return {
        id:          null,
        phrase:      '',
        match_type:  'exact',
        post_ids:    [],
        posts:       [],
        sync_status: 'draft',
        sync_error:  '',
        synced_at:   '',
        updated_at:  '',
        enabled:     true,
    };
}

export function cloneRule(rule: PinnedRule): PinnedRule {
    return { ...JSON.parse(JSON.stringify(rule)), enabled: rule.enabled ?? true } as PinnedRule;
}

export function t(key: string, fallback: string): string {
    return window.tsPinnedResults?.i18n?.[key] ?? fallback;
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

export function resetPostSearch(): void {
    clearTimeout(searchTimer);
    state.postSearchValue   = '';
    state.postResults       = [];
    state.postSearchLoading = false;
    state.postSearchTouched = false;
}

export function clearEditorError(): void {
    state.editorError = '';
}

export function getSearchTimer(): ReturnType<typeof setTimeout> | undefined {
    return searchTimer;
}

export function setSearchTimer(timer: ReturnType<typeof setTimeout> | undefined): void {
    searchTimer = timer;
}
