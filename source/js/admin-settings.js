/**
 * Typesense Search — Admin Settings JS
 */

document.addEventListener('DOMContentLoaded', () => {

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
        if (msgEl) msgEl.textContent = data?.data?.message ?? (data.success ? 'OK' : 'Unknown error.');
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
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
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
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
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
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
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
            <svg viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Pie chart showing document distribution">
                ${paths}
                <circle cx="${cx}" cy="${cy}" r="${rInner}" fill="#fff"/>
                <text x="${cx}" y="${cy - 8}" text-anchor="middle" font-size="22" font-weight="700" fill="#1d2327" font-family="sans-serif">${total.toLocaleString()}</text>
                <text x="${cx}" y="${cy + 12}" text-anchor="middle" font-size="10" fill="#646970" font-family="sans-serif">documents</text>
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
                <span class="ts-stats-row__label">${escHtml(facet.label)}<span class="ts-stats-row__slug">${escHtml(facet.slug)}</span></span>
                <span class="ts-stats-row__count">${facet.count.toLocaleString()}</span>
                <span class="ts-stats-row__pct">${pct}%</span>
                <button type="button" class="button button-small ts-stats-row__clear" data-post-type="${escAttr(facet.slug)}" data-label="${escAttr(facet.label)}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Clear
                </button>
                <span class="ts-stats-row__feedback" hidden></span>`;

            listEl.appendChild(li);
        });

        // Bind clear buttons
        listEl.querySelectorAll('.ts-stats-row__clear').forEach((btn) => {
            btn.addEventListener('click', () => handleClearPostType(btn));
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

        if (!confirm(`Remove all "${label}" documents from the Typesense index? This cannot be undone.`)) {
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
            data = { success: false, data: { message: 'Request failed: ' + err.message } };
        }

        if (data.success) {
            // Reload the stats to reflect the change
            await loadStats();
        } else {
            btn.disabled = false;
            if (feedback) {
                feedback.textContent = data?.data?.message ?? 'Error.';
                feedback.className   = 'ts-stats-row__feedback is-error';
                feedback.hidden      = false;
            }
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
            data = { success: false, data: { message: 'Request failed: ' + err.message } };
        } finally {
            if (loadingEl)       loadingEl.hidden = true;
            if (statsRefreshBtn) setButtonLoading(statsRefreshBtn, false);
        }

        if (!data.success) {
            if (errorEl)    errorEl.hidden  = false;
            if (errorMsgEl) errorMsgEl.textContent = data?.data?.message ?? 'Unknown error.';
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
                opt.textContent = '— No facetable fields found —';
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
                data = { success: false, data: { message: 'Request failed: ' + err.message } };
            }

            if (!data.success) {
                showFacetNotice(data?.data?.message ?? 'Could not load facetable fields.', true);
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
            });
        }

        /**
         * Build and append a new facet row to the list.
         */
        function appendFacetRow(fields) {
            const index = facetRowCounter++;

            const row = document.createElement('div');
            row.className    = 'ts-facet-row';
            row.dataset.index = index;

            const optionHtml = fields.map((f) => `<option value="${escAttr(f.name)}">${escHtml(f.name)}</option>`).join('');
            const optName    = `typesense_search_facets[${index}]`;

            row.innerHTML = `
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">Field</label>
                    <div class="ts-facet-row__select-wrap">
                        <select name="${escAttr(optName)}[field]" class="ts-facet-row__select">
                            ${optionHtml}
                        </select>
                    </div>
                </div>
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">Label</label>
                    <input
                        type="text"
                        name="${escAttr(optName)}[label]"
                        value=""
                        placeholder="e.g. Category"
                        class="regular-text ts-facet-row__input"
                    />
                </div>
                <div class="ts-facet-row__field">
                    <label class="ts-facet-row__label">Placeholder</label>
                    <input
                        type="text"
                        name="${escAttr(optName)}[placeholder]"
                        value=""
                        placeholder="e.g. All categories"
                        class="regular-text ts-facet-row__input"
                    />
                </div>
                <button type="button" class="button ts-facet-row__remove" aria-label="Remove facet">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Remove
                </button>`;

            row.querySelector('.ts-facet-row__remove').addEventListener('click', () => {
                row.remove();
                updateEmptyState();
            });

            facetList.appendChild(row);
            updateEmptyState();
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
                    showFacetNotice('No facetable fields found in the collection schema. Make sure the collection exists and has fields with facet: true.', true);
                    return;
                }

                appendFacetRow(fields);
            });
        }

        // Auto-hydrate existing rows on page load
        hydrateExistingRows();
    }

});
