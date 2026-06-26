import { createViteConfig } from "vite-config-factory";
import { mergeConfig } from "vite";

const entries = {
    'css/typesense-search':  './source/sass/typesense-search.scss',
    'js/typesense-search':   './source/js/typesense-search.ts',
    'css/admin-settings':    './source/sass/admin-settings.scss',
    'js/admin-settings':     './source/js/admin-settings.js',
    'css/pinned-results-admin': './source/sass/pinned-results-admin.scss',
    'js/pinned-results-admin':  './source/js/pinned-results-admin.ts',
    'css/quick-search':      './source/sass/quick-search.scss',
    'js/quick-search':       './source/js/quick-search.ts',
};

const config = createViteConfig(entries, {
    outDir: "assets/dist",
    manifestFile: "manifest.json",
});

export default (env) => mergeConfig(config(env), {
    base: "./",
});
