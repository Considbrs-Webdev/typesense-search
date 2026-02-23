import { createViteConfig } from "vite-config-factory";

const entries = {
    'css/typesense-search': './source/sass/typesense-search.scss',
    'js/typesense-search':  './source/js/typesense-search.ts',
    'css/admin-settings':   './source/sass/admin-settings.scss',
    'js/admin-settings':    './source/js/admin-settings.js',
};

export default createViteConfig(entries, {
    outDir: "assets/dist",
    manifestFile: "manifest.json",
});
