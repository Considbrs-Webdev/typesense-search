export type ExternalPage = {
    id: number | null;
    title: string;
    content: string;
    url: string;
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
        tsExternalPages?: Config;
    }
}
