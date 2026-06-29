export type PinnedPost = {
    id: number;
    title: string;
    type_name: string;
    edit_url: string;
};

export type PinnedRule = {
    id: number | null;
    phrase: string;
    match_type: 'exact' | 'contains';
    post_ids: number[];
    posts: PinnedPost[];
    sync_status: string;
    sync_error: string;
    synced_at: string;
    updated_at: string;
    enabled?: boolean;
};

export type Config = {
    restUrl: string;
    nonce: string;
    i18n?: Record<string, string>;
};

declare global {
    interface Window {
        tsPinnedResults?: Config;
    }
}
