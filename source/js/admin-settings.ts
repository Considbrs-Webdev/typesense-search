import { init as initToggles }          from './admin-settings/toggles';
import { init as initConnection }        from './admin-settings/connection';
import { init as initStatistics }        from './admin-settings/statistics';
import { init as initStatus }            from './admin-settings/status';
import { init as initFacets }            from './admin-settings/facets';
import { init as initQuickSearch }       from './admin-settings/quick-search-settings';

document.addEventListener('DOMContentLoaded', () => {
    if (typeof (window as Record<string, unknown>).tsSettings === 'undefined') {
        initToggles();
        return;
    }

    initToggles();
    initConnection();
    initStatistics();
    initStatus();
    initFacets();
    initQuickSearch();
});
