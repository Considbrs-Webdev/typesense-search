export type SynonymRule = {
    id: number | null;
    terms: string[];
    sync_status: string;
    sync_error: string;
    synced_at: string;
    updated_at: string;
};

export type Config = {
    restUrl: string;
    nonce: string;
    i18n?: Record<string, string>;
};

declare global {
    interface Window {
        tsSynonyms?: Config;
    }
}
