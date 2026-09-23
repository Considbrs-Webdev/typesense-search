import type { ExternalPage } from './types';

export type SaveState  = 'idle' | 'saving' | 'saved';
export type NoticeType = 'success' | 'error' | 'info';

export const state = {
    pages:         [] as ExternalPage[],
    selectedId:     null as number | null,
    draft:          emptyPage(),
    isDirty:        false,
    isLoading:      true,
    notice:         '',
    noticeType:     'info' as NoticeType,
    pageFilter:     '',
    editorError:    '',
    saveState:      'idle' as SaveState,
    isSyncing:      false,
};

let saveStateTimer: ReturnType<typeof setTimeout> | undefined;
let noticeTimer:    ReturnType<typeof setTimeout> | undefined;

export function emptyPage(): ExternalPage {
    return {
        id:          null,
        title:       '',
        content:     '',
        url:         '',
        sync_status: 'draft',
        sync_error:  '',
        synced_at:   '',
        updated_at:  '',
    };
}

export function clonePage(page: ExternalPage): ExternalPage {
    return JSON.parse(JSON.stringify(page)) as ExternalPage;
}

export function t(key: string, fallback: string): string {
    return window.tsExternalPages?.i18n?.[key] ?? fallback;
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
