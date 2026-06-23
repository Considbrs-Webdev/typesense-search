/**
 * Typesense Search — Admin Settings JS
 */

document.addEventListener('DOMContentLoaded', () => {

    // Strings passed from PHP via wp_localize_script('typesense-search-admin', 'tsAdminI18n', ...)
    const i18n = window.tsAdminI18n ?? {};

    // ── Password visibility toggles ──────────────────────────────────────────
    document.querySelectorAll('.ts-field__toggle-visibility').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const input    = document.getElementById(targetId);
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const eyeIcon    = btn.querySelector('.ts-icon-eye');
            const eyeOffIcon = btn.querySelector('.ts-icon-eye-off');

            if (eyeIcon)    eyeIcon.style.display    = isHidden ? 'none' : '';
            if (eyeOffIcon) eyeOffIcon.style.display = isHidden ? ''     : 'none';
        });
    });

    // ── Shared AJAX helper ────────────────────────────────────────────────────
    async function ajaxPost(params) {
        const body = new URLSearchParams(params);
        const response = await fetch(tsSettings.ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        });
        return response.json();
    }

    function setButtonLoading(btn, loading) {
        btn.disabled = loading;
        btn.classList.toggle('is-loading', loading);
    }

    function showResult(area, data) {
        const msgEl = area.querySelector('.ts-connection-result__message');
        if (msgEl) msgEl.textContent = data?.data?.message ?? (data.success ? (i18n.ok ?? 'OK') : (i18n.unknownError ?? 'Unknown error.'));
        area.className = 'ts-connection-result ' + (data.success ? 'is-success' : 'is-error');
        area.hidden = false;
    }

    if (typeof tsSettings === 'undefined') return;

    // ── Test connection ───────────────────────────────────────────────────────
    const testBtn         = document.getElementById('ts-test-connection');
    const resultArea      = document.getElementById('ts-connection-result');
    const createColWrap   = document.getElementById('ts-create-collection-field');

    if (testBtn && resultArea) {
        testBtn.addEventListener('click', async () => {
            const remote         = document.getElementById('ts-remote')?.value?.trim()     ?? '';
            const adminKey       = document.getElementById('ts-admin-key')?.value?.trim()  ?? '';
            const collectionName = document.getElementById('ts-index-name')?.value?.trim() ?? '';

            setButtonLoading(testBtn, true);
            resultArea.hidden = true;
            if (createColWrap) createColWrap.hidden = true;

            let data;
            try {
                data = await ajaxPost({
                    action:          tsSettings.action,
                    nonce:           tsSettings.nonce,
                    remote,
                    admin_key:       adminKey,
                    collection_name: collectionName,
                });
            } catch (err) {
                data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
            } finally {
                setButtonLoading(testBtn, false);
            }

            showResult(resultArea, data);

            // Show "Create collection" only when connected but collection is missing
            if (createColWrap) {
                createColWrap.hidden = !(data.success && data.data?.collectionExists === false && collectionName);
            }
        });
    }

    // ── Create collection ─────────────────────────────────────────────────────
    const createColBtn    = document.getElementById('ts-create-collection');
    const createColResult = document.getElementById('ts-create-collection-result');

    if (createColBtn && createColResult) {
        createColBtn.addEventListener('click', async () => {
            const remote         = document.getElementById('ts-remote')?.value?.trim()     ?? '';
            const adminKey       = document.getElementById('ts-admin-key')?.value?.trim()  ?? '';
            const collectionName = document.getElementById('ts-index-name')?.value?.trim() ?? '';

            setButtonLoading(createColBtn, true);
            createColResult.hidden = true;

            let data;
            try {
                data = await ajaxPost({
                    action:          tsSettings.actionCreateCol,
                    nonce:           tsSettings.nonceCreateCol,
                    remote,
                    admin_key:       adminKey,
                    collection_name: collectionName,
                });
            } catch (err) {
                data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
            } finally {
                setButtonLoading(createColBtn, false);
            }

            showResult(createColResult, data);

            // On success, hide the create collection field (collection now exists)
            if (data.success && createColWrap) {
                createColWrap.hidden = true;
            }
        });
    }

    // ── Generate search key ───────────────────────────────────────────────────
    const genKeyBtn     = document.getElementById('ts-generate-search-key');
    const genKeyResult  = document.getElementById('ts-gen-key-result');
    const genKeyWrap    = document.getElementById('ts-gen-key-wrap');
    const searchKeyInput = document.getElementById('ts-search-key');

    // Show/hide generate button based on whether the search key field has a value
    function syncGenKeyVisibility() {
        if (!genKeyWrap) return;
        genKeyWrap.hidden = !!searchKeyInput?.value?.trim();
    }
    syncGenKeyVisibility();
    searchKeyInput?.addEventListener('input', syncGenKeyVisibility);

    if (genKeyBtn && genKeyResult) {
        genKeyBtn.addEventListener('click', async () => {
            const remote         = document.getElementById('ts-remote')?.value?.trim()     ?? '';
            const adminKey       = document.getElementById('ts-admin-key')?.value?.trim()  ?? '';
            const collectionName = document.getElementById('ts-index-name')?.value?.trim() ?? '';

            setButtonLoading(genKeyBtn, true);
            genKeyResult.hidden = true;

            let data;
            try {
                data = await ajaxPost({
                    action:          tsSettings.actionGenKey,
                    nonce:           tsSettings.nonceGenKey,
                    remote,
                    admin_key:       adminKey,
                    collection_name: collectionName,
                });
            } catch (err) {
                data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
            } finally {
                setButtonLoading(genKeyBtn, false);
            }

            showResult(genKeyResult, data);

            // Populate the search key input on success
            if (data.success && data.data?.key) {
                if (searchKeyInput) {
                    searchKeyInput.value = data.data.key;
                    searchKeyInput.type  = 'text'; // reveal so user can see it was set
                    const eyeBtn = searchKeyInput.closest('.ts-field__input-wrap')?.querySelector('.ts-field__toggle-visibility');
                    if (eyeBtn) {
                        eyeBtn.querySelector('.ts-icon-eye').style.display    = 'none';
                        eyeBtn.querySelector('.ts-icon-eye-off').style.display = '';
                    }
                    syncGenKeyVisibility(); // field now has value → hide button
                }
            }
        });
    }

    // ── Unsaved-changes guard on content tab ─────────────────────────────────
    const contentForm = document.querySelector('#ts-tab-content form');
    if (contentForm) {
        let isDirty = false;

        contentForm.addEventListener('change', () => { isDirty = true; });
        contentForm.addEventListener('submit', () => { isDirty = false; });

        window.addEventListener('beforeunload', (e) => {
            if (!isDirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    }

    // ── Statistics tab ────────────────────────────────────────────────────────

    const statsPanel       = document.getElementById('ts-tab-statistics');
    const statsRefreshBtn  = document.getElementById('ts-stats-refresh');

    // Palette for pie slices — cycles if more post types than colours
    const PIE_COLORS = [
        '#2271b1', '#00a32a', '#d63638', '#f0b849', '#8e44ad',
        '#16a085', '#e67e22', '#2980b9', '#27ae60', '#c0392b',
    ];

    /**
     * Draw a pure-SVG donut chart into the given container element.
     * @param {HTMLElement} container
     * @param {Array<{label:string, count:number, color:string}>} segments
     * @param {number} total
     */
    function drawDonutChart(container, segments, total) {
        const size   = 180;
        const cx     = size / 2;
        const cy     = size / 2;
        const rOuter = 70;
        const rInner = 42;

        let paths = '';

        if (total === 0) {
            // Empty state: grey ring
            paths = `<path d="M ${cx} ${cy - rOuter} A ${rOuter} ${rOuter} 0 1 1 ${cx - 0.001} ${cy - rOuter} Z" fill="#e0e0e0"/>`;
        } else {
            let startAngle = -Math.PI / 2;

            segments.forEach((seg) => {
                const fraction  = seg.count / total;
                const angle     = fraction * 2 * Math.PI;
                const endAngle  = startAngle + angle;
                const largeArc  = angle > Math.PI ? 1 : 0;

                const x1  = cx + rOuter * Math.cos(startAngle);
                const y1  = cy + rOuter * Math.sin(startAngle);
                const x2  = cx + rOuter * Math.cos(endAngle);
                const y2  = cy + rOuter * Math.sin(endAngle);
                const ix1 = cx + rInner * Math.cos(endAngle);
                const iy1 = cy + rInner * Math.sin(endAngle);
                const ix2 = cx + rInner * Math.cos(startAngle);
                const iy2 = cy + rInner * Math.sin(startAngle);

                const d = [
                    `M ${x1.toFixed(3)} ${y1.toFixed(3)}`,
                    `A ${rOuter} ${rOuter} 0 ${largeArc} 1 ${x2.toFixed(3)} ${y2.toFixed(3)}`,
                    `L ${ix1.toFixed(3)} ${iy1.toFixed(3)}`,
                    `A ${rInner} ${rInner} 0 ${largeArc} 0 ${ix2.toFixed(3)} ${iy2.toFixed(3)}`,
                    'Z',
                ].join(' ');

                paths += `<path d="${d}" fill="${seg.color}" stroke="#fff" stroke-width="2"/>`;
                startAngle = endAngle;
            });
        }

        container.innerHTML = `
            <svg viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${escHtml(i18n.pieChartLabel ?? 'Pie chart showing document distribution')}">
                ${paths}
                <circle cx="${cx}" cy="${cy}" r="${rInner}" fill="#fff"/>
                <text x="${cx}" y="${cy - 8}" text-anchor="middle" font-size="22" font-weight="700" fill="#1d2327" font-family="sans-serif">${total.toLocaleString()}</text>
                <text x="${cx}" y="${cy + 12}" text-anchor="middle" font-size="10" fill="#646970" font-family="sans-serif">${escHtml(i18n.documentsLabel ?? 'documents')}</text>
            </svg>`;
    }

    /**
     * Render the per-type breakdown list.
     */
    function renderBreakdownList(listEl, facets, total) {
        listEl.innerHTML = '';

        facets.forEach((facet, i) => {
            const color   = PIE_COLORS[i % PIE_COLORS.length];
            const pct     = total > 0 ? ((facet.count / total) * 100).toFixed(1) : '0.0';

            const li = document.createElement('li');
            li.className = 'ts-stats-row';
            li.dataset.postType = facet.slug;

            li.innerHTML = `
                <span class="ts-stats-row__swatch" style="background:${color}" aria-hidden="true"></span>
                <span class="ts-stats-row__label">${escHtml(facet.label)}<span class="ts-stats-row__slug">${escHtml(facet.slug)}</span>${facet.external ? `<span class="ts-stats-row__badge ts-stats-row__badge--external">${escHtml(i18n.externalBadge ?? 'External')}</span>` : ''}</span>
                <span class="ts-stats-row__count">${facet.count.toLocaleString()}</span>
                <span class="ts-stats-row__pct">${pct}%</span>
                <button type="button" class="button button-small ts-stats-row__clear" data-post-type="${escAttr(facet.slug)}" data-label="${escAttr(facet.label)}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    ${escHtml(i18n.clearBtn ?? 'Clear')}
                </button>
                <button type="button" class="button button-small ts-stats-row__reindex" data-post-type="${escAttr(facet.slug)}" data-label="${escAttr(facet.label)}"${facet.external ? ` disabled title="${escAttr(i18n.reindexExternalTitle ?? 'Managed by an external strategy — use WP-CLI to reindex.')}"` : ''}>
                    <svg class="ts-stats-row__reindex-spinner" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    <svg class="ts-stats-row__reindex-icon" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M552.2 64C538.9 64 528.2 74.7 528.2 88L528.2 166.1L501.1 139C401.1 39 239 39 139.1 139C39.2 239 39.1 401.1 139.1 501C239.1 600.9 401.2 601 501.1 501C516 486.1 528.7 469.8 539.2 452.5C546.1 441.2 542.4 426.4 531.1 419.5C519.8 412.6 505 416.3 498.1 427.6C489.6 441.6 479.3 454.9 467.1 467C385.9 548.2 254.2 548.2 172.9 467C91.6 385.8 91.7 254.1 172.9 172.8C254.1 91.5 385.8 91.6 467.1 172.8L494.2 199.9L416 199.9C402.7 199.9 392 210.6 392 223.9C392 237.2 402.7 247.9 416 247.9L552.1 247.9C565.4 247.9 576.1 237.2 576.1 223.9L576.1 87.9C576.1 74.6 565.4 63.9 552.1 63.9z"/></svg>
                    ${escHtml(i18n.reindexBtn ?? 'Reindex')}
                </button>
                <span class="ts-stats-row__feedback" hidden></span>`;

            listEl.appendChild(li);
        });

        // Bind clear buttons
        listEl.querySelectorAll('.ts-stats-row__clear').forEach((btn) => {
            btn.addEventListener('click', () => handleClearPostType(btn));
        });

        // Bind reindex buttons
        listEl.querySelectorAll('.ts-stats-row__reindex').forEach((btn) => {
            btn.addEventListener('click', () => handleReindexPostType(btn));
        });
    }

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /**
     * Handle a "Clear" button click for a specific post type.
     */
    async function handleClearPostType(btn) {
        const postType = btn.dataset.postType;
        const label    = btn.dataset.label || postType;
        const row      = btn.closest('.ts-stats-row');
        const feedback = row?.querySelector('.ts-stats-row__feedback');

        if (!confirm((i18n.confirmClearType ?? 'Remove all "%s" documents from the Typesense index? This cannot be undone.').replace('%s', label))) {
            return;
        }

        btn.disabled = true;
        if (feedback) { feedback.hidden = true; }

        let data;
        try {
            data = await ajaxPost({
                action:    tsSettings.actionClearType,
                nonce:     tsSettings.nonceClearType,
                post_type: postType,
            });
        } catch (err) {
            data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
        }

        if (data.success) {
            // Reload the stats to reflect the change
            await loadStats();
        } else {
            btn.disabled = false;
            if (feedback) {
                feedback.textContent = data?.data?.message ?? (i18n.error ?? 'Error.');
                feedback.className   = 'ts-stats-row__feedback is-error';
                feedback.hidden      = false;
            }
        }
    }

    /**
     * Show a notice at the top of the statistics card.
     * @param {string}  message
     * @param {'success'|'error'} type
     */
    function showStatsNotice(message, type) {
        const noticeEl  = document.getElementById('ts-stats-notice');
        const messageEl = document.getElementById('ts-stats-notice-message');
        if (!noticeEl || !messageEl) return;

        messageEl.textContent = message;
        noticeEl.classList.remove('ts-stats-notice--success', 'ts-stats-notice--error');
        noticeEl.classList.add(type === 'success' ? 'ts-stats-notice--success' : 'ts-stats-notice--error');
        noticeEl.hidden = false;
    }

    function hideStatsNotice() {
        const noticeEl = document.getElementById('ts-stats-notice');
        if (noticeEl) noticeEl.hidden = true;
    }

    // Dismiss button
    document.getElementById('ts-stats-notice-dismiss')?.addEventListener('click', hideStatsNotice);

    /**
     * Handle a "Reindex" button click for a specific post type.
     */
    async function handleReindexPostType(btn) {
        const postType = btn.dataset.postType;
        const label    = btn.dataset.label || postType;
        const row      = btn.closest('.ts-stats-row');
        const feedback = row?.querySelector('.ts-stats-row__feedback');

        if (!confirm((i18n.confirmReindexType ?? 'Re-index all "%s" documents? Existing entries will be re-processed and overwritten.').replace('%s', label))) {
            return;
        }

        setButtonLoading(btn, true);
        hideStatsNotice();
        if (feedback) { feedback.hidden = true; }

        // Also disable the clear button on the same row while reindexing
        const clearBtn = row?.querySelector('.ts-stats-row__clear');
        if (clearBtn) clearBtn.disabled = true;

        let data;
        try {
            data = await ajaxPost({
                action:    tsSettings.actionReindexType,
                nonce:     tsSettings.nonceReindexType,
                post_type: postType,
            });
        } catch (err) {
            data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
        } finally {
            setButtonLoading(btn, false);
            if (clearBtn) clearBtn.disabled = false;
        }

        const message = data?.data?.message ?? (data.success ? (i18n.ok ?? 'OK') : (i18n.error ?? 'Error.'));
        showStatsNotice(message, data.success ? 'success' : 'error');

        if (data.success) {
            await loadStats();
        }
    }

    /**
     * Fetch stats from the server and render them.
     */
    async function loadStats() {
        if (!statsPanel) return;

        const loadingEl  = document.getElementById('ts-stats-loading');
        const errorEl    = document.getElementById('ts-stats-error');
        const errorMsgEl = document.getElementById('ts-stats-error-message');
        const contentEl  = document.getElementById('ts-stats-content');
        const totalEl    = document.getElementById('ts-stats-total');
        const typesEl    = document.getElementById('ts-stats-types');
        const colEl      = document.getElementById('ts-stats-collection');
        const pieEl      = document.getElementById('ts-pie-chart');
        const listEl     = document.getElementById('ts-stats-list');

        // Show loading
        if (loadingEl)  loadingEl.hidden  = false;
        if (errorEl)    errorEl.hidden    = true;
        if (contentEl)  contentEl.hidden  = true;
        if (statsRefreshBtn) setButtonLoading(statsRefreshBtn, true);

        let data;
        try {
            data = await ajaxPost({
                action: tsSettings.actionGetStats,
                nonce:  tsSettings.nonceGetStats,
            });
        } catch (err) {
            data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
        } finally {
            if (loadingEl)       loadingEl.hidden = true;
            if (statsRefreshBtn) setButtonLoading(statsRefreshBtn, false);
        }

        if (!data.success) {
            if (errorEl)    errorEl.hidden  = false;
            if (errorMsgEl) errorMsgEl.textContent = data?.data?.message ?? (i18n.unknownError ?? 'Unknown error.');
            return;
        }

        const { total, facets, collectionName } = data.data;

        // Build colour-enriched facets
        const coloredFacets = (facets || []).map((f, i) => ({
            ...f,
            color: PIE_COLORS[i % PIE_COLORS.length],
        }));

        // Populate KPIs
        if (totalEl)  totalEl.textContent  = (total || 0).toLocaleString();
        if (typesEl)  typesEl.textContent  = coloredFacets.length;
        if (colEl)    colEl.textContent    = collectionName || '—';

        // Draw chart
        if (pieEl) drawDonutChart(pieEl, coloredFacets, total || 0);

        // Draw list
        if (listEl) renderBreakdownList(listEl, coloredFacets, total || 0);

        if (contentEl) contentEl.hidden = false;
    }

    // Auto-load stats when on the statistics tab
    if (statsPanel) {
        loadStats();
    }

    // Refresh button
    if (statsRefreshBtn) {
        statsRefreshBtn.addEventListener('click', loadStats);
    }

    // ── Logging tab ───────────────────────────────────────────────────────────

    const loggingPanel = document.getElementById('ts-tab-logging');

    if (loggingPanel) {
        const clearLogBtn = document.getElementById('ts-log-clear');

        clearLogBtn?.addEventListener('click', async () => {
            if (!confirm(i18n.confirmClearLog ?? 'Clear the indexing log?')) {
                return;
            }

            setButtonLoading(clearLogBtn, true);

            let data;
            try {
                data = await ajaxPost({
                    action: tsSettings.actionClearLog,
                    nonce:  tsSettings.nonceClearLog,
                });
            } catch (err) {
                data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
            } finally {
                setButtonLoading(clearLogBtn, false);
            }

            if (data.success) {
                window.location.reload();
                return;
            }

            alert(data?.data?.message ?? (i18n.unknownError ?? 'Unknown error.'));
        });
    }

    // ── Facetting tab ─────────────────────────────────────────────────────────

    const facettingPanel = document.getElementById('ts-tab-facetting');

    if (facettingPanel) {
        const facetList    = document.getElementById('ts-facet-list');
        const addFacetBtn  = document.getElementById('ts-add-facet');
        const facetNotice  = document.getElementById('ts-facet-notice');
        const facetEmpty   = document.getElementById('ts-facet-empty');

        let cachedFacetFields = null;  // null = not yet fetched, [] = fetched (may be empty)
        let facetRowCounter   = facetList ? facetList.querySelectorAll('.ts-facet-row').length : 0;

        function showFacetNotice(message, isError = false) {
            if (!facetNotice) return;
            const msgEl = facetNotice.querySelector('.ts-facet-notice__message');
            if (msgEl) msgEl.textContent = message;
            facetNotice.className = 'ts-facet-notice ' + (isError ? 'is-error' : 'is-info');
            facetNotice.hidden = false;
        }

        function hideFacetNotice() {
            if (facetNotice) facetNotice.hidden = true;
        }

        function updateEmptyState() {
            if (!facetEmpty || !facetList) return;
            facetEmpty.hidden = facetList.querySelectorAll('.ts-facet-row').length > 0;
        }

        /**
         * Populate a <select> element with the cached facet fields.
         * Restores the previously selected value via data-saved-value.
         */
        function populateSelect(select, fields) {
            const savedValue = select.dataset.savedValue || '';
            select.innerHTML = '';

            if (fields.length === 0) {
                const opt = document.createElement('option');
                opt.value    = '';
                opt.textContent = i18n.noFacetableFields ?? '— No facetable fields found —';
                opt.disabled = true;
                opt.selected = true;
                select.appendChild(opt);
                return;
            }

            fields.forEach((field) => {
                const opt = document.createElement('option');
                opt.value       = field.name;
                opt.textContent = field.name;
                if (field.name === savedValue) opt.selected = true;
                select.appendChild(opt);
            });

            // Ensure something is selected
            if (!select.value && fields.length > 0) {
                select.value = fields[0].name;
            }
            // Update disabled options across all selects to avoid duplicates
            updateDisabledOptions();
        }

        /**
         * Fetch facetable fields from the server.
         * Returns the fields array on success, null on failure.
         */
        async function fetchFacetFields() {
            if (cachedFacetFields !== null) return cachedFacetFields;

            let data;
            try {
                data = await ajaxPost({
                    action: tsSettings.actionGetFacetFields,
                    nonce:  tsSettings.nonceGetFacetFields,
                });
            } catch (err) {
                data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
            }

            if (!data.success) {
                showFacetNotice(data?.data?.message ?? (i18n.couldNotLoadFields ?? 'Could not load facetable fields.'), true);
                return null;
            }

            cachedFacetFields = data.data.fields || [];
            hideFacetNotice();
            return cachedFacetFields;
        }

        /**
         * Enable all existing select dropdowns in the facet list.
         */
        async function hydrateExistingRows() {
            const selects = facetList ? facetList.querySelectorAll('.ts-facet-row__select') : [];
            if (selects.length === 0) return;

            // Show spinners on all rows while loading
            facetList.querySelectorAll('.ts-facet-row__spinner').forEach((s) => { s.style.display = 'block'; });

            const fields = await fetchFacetFields();

            // Hide all spinners
            facetList.querySelectorAll('.ts-facet-row__spinner').forEach((s) => { s.style.display = ''; });

            if (!fields) return;  // error already shown

            selects.forEach((select) => {
                populateSelect(select, fields);
                select.disabled = false;
                select.addEventListener('change', updateDisabledOptions);
            });

            // Enable/display_as selects and set saved values
            facetList.querySelectorAll('.ts-facet-row__display').forEach((d) => {
                const saved = d.dataset.savedDisplay || 'dropdown';
                d.value = saved;
                d.disabled = false;
            });

            // After all selects are hydrated, ensure duplicate options are disabled
            updateDisabledOptions();
        }

        /**
         * Build and append a new facet row to the list.
         */
        function appendFacetRow(fields) {
            const index = facetRowCounter++;

            const row = document.createElement('div');
            row.className    = 'ts-facet-row';
            row.dataset.index = index;
            // Determine already chosen fields so new row doesn't pick a duplicate
            const existingSelects = Array.from(facetList.querySelectorAll('.ts-facet-row__select'));
            const chosen = existingSelects.map((s) => s.value).filter(Boolean);

            // Pick the first available field that's not already chosen
            const initialField = (fields.find((f) => !chosen.includes(f.name)) || fields[0]).name;

            const optionHtml = fields.map((f) => {
                const sel = f.name === initialField ? ' selected' : '';
                return `<option value="${escAttr(f.name)}"${sel}>${escHtml(f.name)}</option>`;
            }).join('');
            const optName    = `typesense_search_facets[${index}]`;

            row.innerHTML = `
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">${escHtml(i18n.facetFieldLabel ?? 'Field')}</label>
                    <div class="ts-facet-row__select-wrap">
                        <select name="${escAttr(optName)}[field]" class="ts-facet-row__select">
                            ${optionHtml}
                        </select>
                    </div>
                </div>
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">${escHtml(i18n.facetLabelLabel ?? 'Label')}</label>
                    <input
                        type="text"
                        name="${escAttr(optName)}[label]"
                        value=""
                        placeholder="${escAttr(i18n.facetLabelPlaceholder ?? 'e.g. Category')}"
                        class="regular-text ts-facet-row__input"
                    />
                </div>
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">${escHtml(i18n.facetPlaceholderLabel ?? 'Placeholder')}</label>
                    <input
                        type="text"
                        name="${escAttr(optName)}[placeholder]"
                        value=""
                        placeholder="${escAttr(i18n.facetPlaceholderPh ?? 'e.g. All categories')}"
                        class="regular-text ts-facet-row__input"
                    />
                </div>
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">${escHtml(i18n.facetDisplayAsLabel ?? 'Display as')}</label>
                    <select name="${escAttr(optName)}[display_as]" class="ts-facet-row__display">
                        <option value="dropdown" selected>${escHtml(i18n.facetOptDropdown ?? 'Dropdown')}</option>
                        <option value="button_group">${escHtml(i18n.facetOptButtonGroup ?? 'Button group')}</option>
                    </select>
                </div>
                <button type="button" class="button ts-facet-row__remove" title="${escAttr(i18n.removeFacet ?? 'Remove facet')}" aria-label="${escAttr(i18n.removeFacet ?? 'Remove facet')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>`;

            row.querySelector('.ts-facet-row__remove').addEventListener('click', () => {
                row.remove();
                updateEmptyState();
            });

            facetList.appendChild(row);
            // wire up newly added select
            const sel = row.querySelector('.ts-facet-row__select');
            if (sel) {
                sel.addEventListener('change', updateDisabledOptions);
            }
            // Recompute disabled options so the newly appended row can't duplicate
            updateDisabledOptions();
            updateEmptyState();
        }

        /**
         * Disable options that are already selected in other rows so the same
         * facet field cannot be chosen twice.
         */
        function updateDisabledOptions() {
            if (!facetList) return;
            const selects = Array.from(facetList.querySelectorAll('.ts-facet-row__select'));
            const chosen = selects.map((s) => s.value).filter(Boolean);

            selects.forEach((s) => {
                const current = s.value;
                Array.from(s.options).forEach((opt) => {
                    // never disable the option that is currently selected for this select
                    if (!opt.value) return (opt.disabled = false);
                    opt.disabled = (opt.value !== current) && chosen.includes(opt.value);
                });
            });
        }

        // Bind remove buttons on existing PHP-rendered rows
        if (facetList) {
            facetList.querySelectorAll('.ts-facet-row__remove').forEach((btn) => {
                btn.addEventListener('click', () => {
                    btn.closest('.ts-facet-row').remove();
                    updateEmptyState();
                });
            });
        }

        // Add facet button
        if (addFacetBtn) {
            addFacetBtn.addEventListener('click', async () => {
                setButtonLoading(addFacetBtn, true);

                const fields = await fetchFacetFields();

                setButtonLoading(addFacetBtn, false);

                if (!fields) return;  // error already shown

                if (fields.length === 0) {
                    showFacetNotice(i18n.noFieldsInSchema ?? 'No facetable fields found in the collection schema. Make sure the collection exists and has fields with facet: true.', true);
                    return;
                }

                // If all available fields are already chosen, show notice and don't add
                const existingSelects = Array.from(facetList.querySelectorAll('.ts-facet-row__select'));
                const chosen = existingSelects.map((s) => s.value).filter(Boolean);
                if (chosen.length >= fields.length) {
                    showFacetNotice(i18n.allFieldsAdded ?? 'All facetable fields have already been added as facets.', true);
                    return;
                }

                appendFacetRow(fields);
            });
        }

        // Auto-hydrate existing rows on page load
        hydrateExistingRows();
        // Ensure options are updated even if no explicit change is made by the user
        // (hydrateExistingRows will call updateDisabledOptions when complete)
    }

    // ── Quick search tab ──────────────────────────────────────────────────────

    const quickSearchPanel = document.getElementById('ts-tab-quick-search');

    if (quickSearchPanel) {
        const enabledToggle    = document.getElementById('ts-quick-search-enabled');
        const selectorsCard    = document.getElementById('ts-quick-search-selectors-card');
        const hitsField        = document.getElementById('ts-quick-search-hits-field');
        const selectorList     = document.getElementById('ts-qs-selector-list');
        const addSelectorBtn   = document.getElementById('ts-qs-add-selector');
        const selectorEmpty    = document.getElementById('ts-qs-selector-empty');

        let qsSelectorCounter = selectorList ? selectorList.querySelectorAll('.ts-qs-selector-row').length : 0;

        /**
         * Show/hide the selectors card based on the toggle state.
         */
        function syncSelectorsCardVisibility() {
            if (!selectorsCard) return;
            selectorsCard.hidden = !enabledToggle?.checked;
            if (hitsField) hitsField.hidden = !enabledToggle?.checked;
        }

        /**
         * Update the empty-state notice based on whether any rows exist.
         */
        function updateQsEmptyState() {
            if (!selectorEmpty || !selectorList) return;
            selectorEmpty.hidden = selectorList.querySelectorAll('.ts-qs-selector-row').length > 0;
        }

        /**
         * Escape HTML for use inside attribute strings.
         */
        function escAttrQs(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        /**
         * Append a new blank CSS-selector row to the list.
         */
        function appendSelectorRow() {
            const index   = qsSelectorCounter++;
            const optName = 'typesense_quick_search_selectors';

            const row = document.createElement('div');
            row.className    = 'ts-qs-selector-row';
            row.dataset.index = String(index);
            row.innerHTML = `
                <div class="ts-qs-selector-row__field" style="flex:1">
                    <label class="ts-qs-selector-row__label">${escAttrQs(i18n.qsSelectorLabel ?? 'CSS selector')}</label>
                    <input
                        type="text"
                        name="${escAttrQs(optName)}[${index}][selector]"
                        value=""
                        placeholder="${escAttrQs(i18n.qsSelectorPlaceholder ?? 'e.g. .site-header input[type=search]')}"
                        class="regular-text ts-qs-selector-row__input"
                        spellcheck="false"
                    />
                </div>
                <div class="ts-qs-selector-row__field">
                    <label class="ts-qs-selector-row__label">${escAttrQs(i18n.qsPlacementLabel ?? 'Placement')}</label>
                    <select name="${escAttrQs(optName)}[${index}][sibling]" class="ts-qs-selector-row__sibling-select">
                        <option value="0">${escAttrQs(i18n.qsPlacementDefault ?? 'Default (body)')}</option>
                        <option value="1">${escAttrQs(i18n.qsPlacementSibling ?? 'Sibling')}</option>
                    </select>
                </div>
                <div class="ts-qs-selector-row__field">
                    <label class="ts-qs-selector-row__label" for="ts-qs-mobile-behavior-${index}">${escAttrQs(i18n.qsMobileBehaviorLabel ?? 'Mobile behavior')}</label>
                    <select id="ts-qs-mobile-behavior-${index}" name="${escAttrQs(optName)}[${index}][mobile_behavior]" class="ts-qs-selector-row__mobile-behavior-select">
                        <option value="regular">${escAttrQs(i18n.qsMobileBehaviorRegular ?? 'Regular behavior')}</option>
                        <option value="overlay">${escAttrQs(i18n.qsMobileBehaviorOverlay ?? 'Open in modal')}</option>
                    </select>
                </div>
                <button
                    type="button"
                    class="button ts-qs-selector-row__remove"
                    title="${escAttrQs(i18n.removeSelector ?? 'Remove selector')}"
                    aria-label="${escAttrQs(i18n.removeSelector ?? 'Remove selector')}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>`;

            row.querySelector('.ts-qs-selector-row__remove').addEventListener('click', () => {
                row.remove();
                updateQsEmptyState();
            });

            selectorList.appendChild(row);
            updateQsEmptyState();

            // Focus the newly added input for convenience
            row.querySelector('input')?.focus();
        }

        // Wire up toggle
        if (enabledToggle) {
            enabledToggle.addEventListener('change', syncSelectorsCardVisibility);
        }

        // Wire up remove buttons on PHP-rendered rows
        if (selectorList) {
            selectorList.querySelectorAll('.ts-qs-selector-row__remove').forEach((btn) => {
                btn.addEventListener('click', () => {
                    btn.closest('.ts-qs-selector-row').remove();
                    updateQsEmptyState();
                });
            });
        }

        // Wire up add button
        if (addSelectorBtn) {
            addSelectorBtn.addEventListener('click', appendSelectorRow);
        }

        // Initial sync (matches the PHP-rendered state)
        syncSelectorsCardVisibility();
        updateQsEmptyState();
    }

    // ── Status tab ────────────────────────────────────────────────────────────

    const statusPanel = document.getElementById('ts-tab-status');

    if (statusPanel) {

        const statusLoading    = document.getElementById('ts-status-loading');
        const statusResults    = document.getElementById('ts-status-results');
        const statusRefreshBtn = document.getElementById('ts-status-refresh');
        const fixWrap          = document.getElementById('ts-status-fix-wrap');
        const fixKeyBtn        = document.getElementById('ts-status-fix-key');
        const fixResult        = document.getElementById('ts-status-fix-result');
        const regenHint        = document.getElementById('ts-status-regen-hint');
        const createColWrap    = document.getElementById('ts-status-create-col-wrap');
        const createColBtn     = document.getElementById('ts-status-create-col');
        const createColResult  = document.getElementById('ts-status-create-col-result');

        /**
         * Icons used for ok / fail status items.
         */
        const ICON_OK   = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`;
        const ICON_FAIL = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;

        /**
         * Update a single status list item.
         * @param {HTMLElement} item
         * @param {boolean}     ok
         * @param {string}      message
         */
        function setStatusItem(item, ok, message) {
            if (!item) return;
            const iconEl    = item.querySelector('.ts-status-item__icon');
            const messageEl = item.querySelector('.ts-status-item__message');

            item.classList.toggle('is-ok',   ok);
            item.classList.toggle('is-fail', !ok);

            if (iconEl)    iconEl.innerHTML = ok ? ICON_OK : ICON_FAIL;
            if (messageEl) messageEl.textContent = message;
        }

        /**
         * Fetch status from the server and render all three check rows.
         */
        async function loadStatus() {
            if (statusLoading)   statusLoading.hidden   = false;
            if (statusResults)   statusResults.hidden   = true;
            if (fixWrap)         fixWrap.hidden          = true;
            if (regenHint)       regenHint.hidden        = true;
            if (fixResult)       fixResult.hidden        = true;
            if (createColWrap)   createColWrap.hidden    = true;
            if (createColResult) createColResult.hidden  = true;
            if (statusRefreshBtn) setButtonLoading(statusRefreshBtn, true);

            let data;
            try {
                data = await ajaxPost({
                    action: tsSettings.actionCheckStatus,
                    nonce:  tsSettings.nonceCheckStatus,
                });
            } catch (err) {
                data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
            } finally {
                if (statusLoading)    statusLoading.hidden    = true;
                if (statusRefreshBtn) setButtonLoading(statusRefreshBtn, false);
            }

            if (!data.success) {
                // Unexpected failure — show generic error on all items
                const msg = data?.data?.message ?? (i18n.unknownError ?? 'Unknown error.');
                setStatusItem(document.getElementById('ts-status-connection'), false, msg);
                setStatusItem(document.getElementById('ts-status-admin-key'),  false, '');
                setStatusItem(document.getElementById('ts-status-collection'),  false, '');
                setStatusItem(document.getElementById('ts-status-search-key'), false, '');
                if (statusResults) statusResults.hidden = false;
                return;
            }

            const { connection, adminKey, collection, searchKey, collectionCanFix, searchKeyCanFix } = data.data;

            setStatusItem(document.getElementById('ts-status-connection'), connection.ok,  connection.message);
            setStatusItem(document.getElementById('ts-status-admin-key'),  adminKey.ok,    adminKey.message);
            setStatusItem(document.getElementById('ts-status-collection'),  collection.ok,  collection.message);
            setStatusItem(document.getElementById('ts-status-search-key'), searchKey.ok,   searchKey.message);

            // Show remediation UI for a missing collection
            if (!collection.ok && collectionCanFix) {
                if (createColWrap) createColWrap.hidden = false;
            }

            // Show remediation UI for a failing search key
            if (!searchKey.ok && searchKey.message) {
                if (searchKeyCanFix) {
                    if (fixWrap) fixWrap.hidden = false;
                } else {
                    if (regenHint) regenHint.hidden = false;
                }
            }

            if (statusResults) statusResults.hidden = false;
        }

        // ── Create collection button (status tab) ─────────────────────────────

        if (createColBtn) {
            createColBtn.addEventListener('click', async () => {
                setButtonLoading(createColBtn, true);
                if (createColResult) createColResult.hidden = true;

                let data;
                try {
                    data = await ajaxPost({
                        action: tsSettings.actionStatusCreateCol,
                        nonce:  tsSettings.nonceStatusCreateCol,
                    });
                } catch (err) {
                    data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
                } finally {
                    setButtonLoading(createColBtn, false);
                }

                if (createColResult) {
                    createColResult.textContent = data?.data?.message ?? (data.success ? (i18n.ok ?? 'Done.') : (i18n.unknownError ?? 'Unknown error.'));
                    createColResult.className   = 'ts-status-fix__result ' + (data.success ? 'is-success' : 'is-error');
                    createColResult.hidden      = false;
                }

                if (data.success) {
                    setTimeout(loadStatus, 800);
                }
            });
        }

        // ── Fix / regenerate search key button ────────────────────────────────

        if (fixKeyBtn) {
            fixKeyBtn.addEventListener('click', async () => {
                setButtonLoading(fixKeyBtn, true);
                if (fixResult) fixResult.hidden = true;

                let data;
                try {
                    data = await ajaxPost({
                        action: tsSettings.actionFixSearchKey,
                        nonce:  tsSettings.nonceFixSearchKey,
                    });
                } catch (err) {
                    data = { success: false, data: { message: (i18n.requestFailed ?? 'Request failed: ') + err.message } };
                } finally {
                    setButtonLoading(fixKeyBtn, false);
                }

                if (fixResult) {
                    fixResult.textContent = data?.data?.message ?? (data.success ? (i18n.ok ?? 'Done.') : (i18n.unknownError ?? 'Unknown error.'));
                    fixResult.className   = 'ts-status-fix__result ' + (data.success ? 'is-success' : 'is-error');
                    fixResult.hidden      = false;
                }

                // Re-run all checks so the search-key row updates
                if (data.success) {
                    setTimeout(loadStatus, 800);
                }
            });
        }

        // Auto-load on tab open, bind refresh button
        loadStatus();

        if (statusRefreshBtn) {
            statusRefreshBtn.addEventListener('click', loadStatus);
        }
    }

});
